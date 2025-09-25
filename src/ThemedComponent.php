<?php declare(strict_types=1);

namespace Skuilplek\Themed;

use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * ThemedComponent
 *
 * A simple way to create and render HTML components using PHP.
 *
 */
class ThemedComponent
{
    protected string $id = '';
    protected array $attributes = [];
    protected array $classes = [];
    protected array $css = [];
    protected array $javascript = [];
    protected array $componentData = [];
    protected string $component = '';
    protected string $templatePath; //The full path where the template folders are

    public const SESSION_KEY = "sk_themed";

    private static array $svgCache = [];
    /**
     * Optional custom script callback. Signature: function(string $script, string $type, string $location): void
     * @var callable|null
     */
    protected static $scriptCallback = null;

    /**
     * Optional override for the base template path.
     * If set, this path is used instead of self::getThemePath().
     * @var string|null
     */
    protected static ?string $baseTemplatePath = null;
    protected static ?Environment $twig = null;

    /**
     * Optional custom logger callback. Signature: function(string $message): void
     * @var callable|null
     */
    protected static $loggerCallback = null;

    /**
     * Cache for parsed parameter definitions, keyed by template file path.
     * @var array<string, array<string, string>>
     */
    protected static array $parameterCache = [];

    /**
     * Component pool for object reuse to reduce memory allocation overhead
     * @var array<string, ThemedComponent[]>
     */
    private static array $componentPool = [];

    /**
     * Maximum pool size per component type to prevent memory leaks
     */
    private const MAX_POOL_SIZE = 50;

    /**
     * Flag to track if static initialization has been completed
     */
    private static bool $staticInitialized = false;

    protected bool $debugging = false;
    /**
     * Level 0 - Only log the component name
     * Level 1 - Log the component name and the content
     * Level 2 - Log the component name, content and parameters
     * Level 3 - Log the component name, content, parameters and internal method calls
     * @var int
     */
    protected int $debuggingLevel = 0;
    protected array $parameters = [];

    protected bool $canSee = true;

    /**
     * Initialize static resources once for all components
     */
    private static function initializeStatic(): void
    {
        if (self::$staticInitialized) {
            return;
        }

        // Determine base path: use override if provided
        $themePath = self::$baseTemplatePath ?? self::getThemePath();
        
        if (self::$twig === null) {
            $loader = new FilesystemLoader([
                $themePath,
                $themePath . 'components/'
            ]);

            $debugging = (int) (getenv('THEMED_DEBUG') ?? 0) > 0;
            
            // Initialize Twig with HTML autoescaping by default for security
            self::$twig = new Environment($loader, [
                'cache' => $debugging ? false : '/tmp/twig_cache', // Use cache directory when debugging is disabled
                'debug' => $debugging,
                'autoescape' => 'html', // Escape all variables by default; use |raw for trusted HTML
            ]);
            
            self::$twig->addFilter(new \Twig\TwigFilter('regex_replace', function ($string, $pattern, $replacement) {
                return preg_replace($pattern, $replacement, $string);
            }));

            // Add component function to Twig
            self::$twig->addFunction(new \Twig\TwigFunction('component', function(string $name, array $content = []) {
                return ThemedComponent::make($name)
                    ->content($content)
                    ->render();
            }, ['is_safe' => ['html']]));
        }

        self::$staticInitialized = true;
        self::log("NOTICE: Static initialization completed");
    }

    /**
     * Lightweight constructor for object reuse
     */
    public function __construct(string $component = '', array $config = [])
    {
        $this->reset($component);
    }

    /**
     * Reset component state for reuse
     */
    private function reset(string $component): self
    {
        $this->component = $component;
        $this->id = uniqid();
        $this->attributes = [];
        $this->classes = [];
        $this->css = [];
        $this->javascript = [];
        $this->componentData = [];
        $this->canSee = true;
        
        // Set debugging flags from environment
        $this->debugging = (int) (getenv('THEMED_DEBUG') ?? 0) > 0;
        $this->debuggingLevel = (int) (getenv('THEMED_DEBUG_LEVEL') ?? 0);
        
        // Set template path
        $this->templatePath = self::$baseTemplatePath ?? self::getThemePath();

        return $this;
    }

    /**
     * Set a custom logger callback for ThemedComponent.
     * @param callable(string): void $callback
     */
    public static function setLoggerCallback(callable $callback): void
    {
        self::$loggerCallback = $callback;
        error_log("Custom logger callback registered"); // Use error_log here to avoid recursion
    }
    /**
     * Set a custom Twig Environment to use (skips default bootstrap).
     */
    public static function setTwigEnvironment(Environment $twig): void
    {
        self::$twig = $twig;
        self::log("NOTICE: Custom Twig environment set");
    }

    /**
     * Get the current Twig Environment, or null if not initialized.
     */
    public static function getTwigEnvironment(): ?Environment
    {
        $twig = self::$twig;
        if ($twig === null) {
            self::log("NOTICE: Twig environment not initialized");
        }
        return $twig;
    }

    /**
     * Override the base template path. Next instantiation will bootstrap Twig with this path.
     * @param string $path Absolute path to template directory, trailing slash optional.
     */
    public static function setBasePath(string $path): void
    {
        self::$baseTemplatePath = rtrim($path, '/');
        self::log("NOTICE: Base template path set to: {$path}");
        // Reset Twig to force re-initialization with new loader
        self::$twig = null;
    }

    /**
     * Log a message with auto-detected debug levels
     * 
     * @param string $message Message to log. The level is auto-detected from the message prefix:
     *                       'ERROR:' = level 0 (error)
     *                       'WARN:' = level 1 (warning)
     *                       'NOTICE:' = level 2 (notice)
     *                       No prefix = level 3 (info)
     */
    protected static function log(string $message): void
    {
        // Fast path: skip all logging overhead if debugging is disabled and no custom logger
        static $debugEnabled = null;
        if ($debugEnabled === null) {
            $debugEnabled = (bool) (getenv('THEMED_DEBUG') ?: false);
        }
        
        if (!$debugEnabled && self::$loggerCallback === null) {
            return;
        }

        // If a custom logger is set, use it regardless of debug level
        if (self::$loggerCallback !== null) {
            call_user_func(self::$loggerCallback, $message);
            return;
        }

        self::internalLog($message);
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Parse all parameters from the first Twig docblock and store them in the parameters array.
     * Only the first {# ... #} block at the top of the template is considered.
     *
     * @param string $componentFile Path to the .twig template file
     * @return $this
     */
    /**
     * Parse parameters from Twig docblock
     * 
     * @param string $componentFile Path to the component file
     * @return $this
     * @throws \RuntimeException If the file cannot be read
     */
    private function parseParametersFromDocblock(string $componentFile)
    {
        // Parameter-block caching: reuse previously parsed definitions
        if (isset(self::$parameterCache[$componentFile])) {
            $this->parameters = self::$parameterCache[$componentFile];
            return $this;
        }
        
        $parameters = [];
        
        try {
            if (!file_exists($componentFile)) {
                throw new \RuntimeException("Component file does not exist: {$componentFile}");
            }
            
            $componentHtml = @file_get_contents($componentFile);
            if ($componentHtml === false) {
                throw new \RuntimeException("Failed to read component file: {$componentFile}");
            }
            
            // Step 1: Extract the comment block
            // Match only the first Twig docblock at the top of the file
            if (preg_match('/\A\s*\{#([\s\S]*?)#\}/', $componentHtml, $commentMatch)) {
                $commentBlock = $commentMatch[1];

                // Step 3: Match lines like "- param: type - description"
                if (preg_match_all('/-\s*([a-zA-Z0-9_]+):\s*[^\n-]*?-?\s*([^\n]*)/', $commentBlock, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $param = trim($match[1]);
                        $description = trim($match[2]);
                        $parameters[$param] = $description;
                    }
                }
            }
        } catch (\Exception $e) {
            self::log("ERROR: Error parsing docblock: " . $e->getMessage());
            if ($this->debugging) {
                throw $e;
            }
        }
        $methods = [
            'id' => 'string - The id of the element. If no id is supplied, a random one will be generated (optional)',
            'canSee' => 'bool - Whether the component should be visible or not (optional)',
            'addAttribute' => 'Add an attribute to the element. This is a string like \'data-foo="bar"\' or multiple attributes in a single string like \'data-foo="bar" data-bar="baz"\' (optional)',
            'addJavaScript' => htmlentities('string - Add a script to the element. This is a string like \'<script>console.log(\'Hello World!\')</script>\' (optional)'),
            'addCss' => htmlentities('string - Add css styles to the element. This should be \'<style> custom-class {...} </style>\' (optional)')
        ];

        $parameters = array_merge($parameters, $methods);
        ksort($parameters);
        $remove_parameters = ['attributes'];
        foreach ($remove_parameters as $parameter) {
            unset($parameters[$parameter]);
        }
        if(isset($parameters['content'])) {
            $content = $parameters['content']. " (always set this first)";
            unset($parameters['content']);
            //Add the content key to the beginning of the array
            $parameters = ['content' => $content] + $parameters;
        }
        $this->parameters = $parameters;
        // Cache the parsed parameters for this template file
        self::$parameterCache[$componentFile] = $parameters;
        if ($this->debuggingLevel > 1) {
            self::log("NOTICE: parameters: {$this->component} : " . json_encode($parameters));
        }
        return $this;
    }

    /**
     * Static factory method using object pool for performance optimization.
     * Reuses existing objects when possible to reduce memory allocation overhead.
     */
    public static function make(string $component, array $config = []): self
    {
        // Initialize static resources once
        self::initializeStatic();

        // Try to get an object from the pool
        $instance = self::getFromPool($component);
        
        if ($instance === null) {
            // Create new instance if pool is empty
            $instance = new static();
        }
        
        // Reset and configure the instance
        $instance->reset($component);
        
        // Load scripts and parse parameters only if not cached
        self::ensureComponentInitialized($component, $instance);
        
        // Configure the instance with provided data
        foreach ($config as $key => $value) {
            $instance->componentData[$key] = $value;
        }
        
        return $instance;
    }

    /**
     * Get an instance from the component pool
     */
    private static function getFromPool(string $component): ?self
    {
        if (!isset(self::$componentPool[$component]) || empty(self::$componentPool[$component])) {
            return null;
        }
        
        return array_pop(self::$componentPool[$component]);
    }

    /**
     * Return an instance to the pool for reuse
     */
    public function returnToPool(): void
    {
        $component = $this->component;
        
        if (!isset(self::$componentPool[$component])) {
            self::$componentPool[$component] = [];
        }
        
        // Limit pool size to prevent memory leaks
        if (count(self::$componentPool[$component]) < self::MAX_POOL_SIZE) {
            self::$componentPool[$component][] = $this;
        }
    }

    /**
     * Clear the component pool to free memory
     */
    public static function clearPool(): void
    {
        self::$componentPool = [];
        self::log("NOTICE: Component pool cleared");
    }

    /**
     * Get pool statistics for monitoring
     */
    public static function getPoolStats(): array
    {
        $stats = [
            'total_pools' => count(self::$componentPool),
            'total_instances' => 0,
            'pools' => []
        ];
        
        foreach (self::$componentPool as $component => $pool) {
            $count = count($pool);
            $stats['pools'][$component] = $count;
            $stats['total_instances'] += $count;
        }
        
        return $stats;
    }

    /**
     * Ensure component-specific initialization is done (scripts, parameters)
     */
    private static function ensureComponentInitialized(string $component, self $instance): void
    {
        $themePath = self::$baseTemplatePath ?? self::getThemePath();
        $componentFile = $themePath . 'components/' . $component . '.twig';
        
        // Only load scripts if not already done for this component type
        static $scriptsLoaded = [];
        if (!isset($scriptsLoaded[$component])) {
            self::log("NOTICE: Loading scripts for component: {$component}");
            self::loadScripts($component);
            $scriptsLoaded[$component] = true;
        }
        
        // Parse parameters if not cached
        if (!isset(self::$parameterCache[$componentFile])) {
            $instance->parseParametersFromDocblock($componentFile);
        } else {
            $instance->parameters = self::$parameterCache[$componentFile];
        }
    }

    /**
     * Magic method for dynamic instance method calls.
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->call($method, $args);
    }

    /**
     * Handles dynamic method calls for internal prefixed methods.
     */
    private function call(string $method, array $args): mixed
    {
        if (!method_exists($this, $method)) {
            if ($this->debuggingLevel > 2) {
                self::log("NOTICE: Content: {$this->component} : " . json_encode($args));
            }
            if (is_array($args) && !empty($args[0])) {
                $args = reset($args);
            } else {
                $args = '';
            }
            $this->componentData[$method] = $args;
            return $this;
        } else {
            //We can pass all the data as an array to a component or we can pass just the component's content data. This handles that
            return $this->{$method}(...$args);
        }
    }

    /**
     * Sets the component ID.
     */
    protected function id(string $id): self
    {
        $this->id = $id;
        self::log("NOTICE: Component ID set to: {$id}");
        return $this;
    }

    /**
     * Add custom CSS.
     */
    protected function addCss(string $css): self
    {
        $this->css[] = $css;
        self::log("NOTICE: Added CSS to component: " . substr($css, 0, 100) . (strlen($css) > 100 ? '...' : ''));
        return $this;
    }

    /**
     * Sets inline JavaScript scripts.
     */
    protected function addJavaScript(string $script): self
    {
        $this->javascript[] = $script;
        self::log("NOTICE: Added JavaScript to component: " . substr($script, 0, 100) . (strlen($script) > 100 ? '...' : ''));
        return $this;
    }

    /**
     * Adds an HTML attribute to the component.
     */
    public function addAttribute(string $name, string $value): self
    {
        $this->attributes[$name] = $value;
        self::log("NOTICE: Added attribute {$name}='{$value}' to component {$this->component}");
        return $this;
    }

    public function canSee(bool $canSee): self
    {
        $this->canSee = $canSee;
        self::log("NOTICE: Component visibility set to: " . ($canSee ? 'visible' : 'hidden'));
        return $this;
    }

    /**
     * Renders the component using its Twig template.
     */
    protected function preprocessContent(): void
    {
        // Special handling for icon components
        if (strpos($this->component, 'icons/') === 0 && isset($this->componentData['name'])) {
            $this->componentData['svg'] = self::getSvgContent($this->componentData['name']);
        }
    }

    public function render(): string
    {
        try {
            if (!$this->canSee) {
                return '';
            }

            if (empty($this->component)) {
                if($this->debugging) {
                    throw new Exception('Component group and name must be set before rendering');
                } else {
                    self::log('ERROR: Component group and name must be set before rendering');
                    return "<!-- ERROR: Component group and name must be set before rendering -->";
                }
            }

            $this->preprocessContent();

            if (self::$twig === null) {
                if($this->debugging) {
                    throw new Exception('Twig environment not initialized. Call ThemedComponent::setBasePath() first.');
                } else {
                    self::log('ERROR: Twig environment not initialized. Call ThemedComponent::setBasePath() first.');
                    return "<!-- ERROR: Twig environment not initialized. -->";
                }
            }
            
            $templateFile = "{$this->component}.twig";

            foreach ($this->css as $css) {
                self::headerScripts($css);
            }

            foreach ($this->javascript as $js) {
                self::footerScripts($js);
            }

            if(empty($this->componentData['id'])) {
                $this->componentData['id'] = $this->id;
            }

            if(empty($this->componentData['classes'])) {
                $this->componentData['classes'] = implode(' ', $this->classes);
            }

            if(empty($this->componentData['attributes'])) {
                $this->componentData['attributes'] = $this->attributes;
            }

            $context = [
                'content' => $this->componentData,
            ];

            // Improvement: Template existence check before rendering
            $loader = self::$twig->getLoader();
            if (method_exists($loader, 'exists') && !$loader->exists($templateFile)) {
                $paths = [];
                if ($loader instanceof FilesystemLoader) {
                    $paths = $loader->getPaths();
                }
                if($this->debugging) {
                    throw new Exception(
                        sprintf(
                        'Template "%s" not found. Searched in: %s',
                        $templateFile,
                        implode(', ', $paths)
                    ));
                } else {
                    self::log(sprintf('ERROR: Template "%s" not found. Searched in: %s', $templateFile, implode(', ', $paths)));
                    return "<!-- ERROR: Template \"{$templateFile}\" not found. -->";
                }
            }
            
            $result = self::$twig->render($templateFile, $context);
            
            return $result;
        } finally {
            // Always return object to pool after rendering for reuse
            $this->returnToPool();
        }
    }

    /**
     * Load scripts and styles for components with security checks
     * 
     * @param string $component Component name (without extension)
     * @throws \InvalidArgumentException If the component name contains invalid characters
     */
    public static function loadScripts(string $component = ""): void
    {
        $themePath = rtrim(self::getThemePath(), '/');
        
        if (empty($component)) {
            // Define allowed directories relative to theme path
            $allowedDirs = [
                'css' => '/*.css',
                'js' => '/*.js',
                'js/footer' => '/*.js'
            ];
            
            foreach ($allowedDirs as $dir => $pattern) {
                $dirPath = $themePath . '/' . $dir;
                $resolvedDir = realpath($dirPath);
                
                // Skip if directory doesn't exist or is outside theme path
                if ($resolvedDir === false || strpos($resolvedDir, $themePath) !== 0) {
                    continue;
                }
                
                $files = glob($resolvedDir . $pattern);
                if ($files === false) {
                    continue;
                }
                
                foreach ($files as $file) {
                    // Double-check the resolved path is within theme directory
                    $resolvedFile = realpath($file);
                    if ($resolvedFile === false || strpos($resolvedFile, $themePath) !== 0) {
                        continue;
                    }
                    
                    $content = @file_get_contents($resolvedFile);
                    if ($content === false) {
                        self::log("ERROR: Failed to read file: {$resolvedFile}");
                        continue;
                    }
                    
                    $script = "";
                    if (intval(getenv("THEMED_DEBUG") ?: '0') > 0) {
                        $script .= "<!-- " . basename($resolvedFile) . " -->\n";
                    }
                    
                    if (str_ends_with($resolvedFile, '.css')) {
                        $content = self::processStylesWithFonts($content, dirname($resolvedFile));
                        $script .= "<style>\n" . self::minifyCss($content) . "\n</style>";
                        self::headerScripts($script);
                    } elseif (str_ends_with($resolvedFile, '.js')) {
                        $script .= "<script>\n" . self::minifyJs($content) . "\n</script>";
                        $isFooter = str_contains($resolvedFile, '/footer/');
                        $isFooter ? self::footerScripts($script) : self::headerScripts($script);
                    }
                }
            }
        } else {
            // Validate component name
            if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $component)) {
                throw new \InvalidArgumentException('Invalid component name. Only alphanumeric characters, hyphens, underscores and forward slashes are allowed.');
            }
            
            // Define component files to load
            $componentFiles = [
                'css' => "/components/{$component}.css",
                'js' => "/components/{$component}.js"
            ];
            
            foreach ($componentFiles as $type => $file) {
                $filePath = $themePath . $file;
                $resolvedPath = realpath($filePath);
                
                // Skip if file doesn't exist or is outside theme path
                if ($resolvedPath === false || strpos($resolvedPath, $themePath) !== 0) {
                    continue;
                }
                
                $content = @file_get_contents($resolvedPath);
                if ($content === false) {
                    self::log("ERROR: Failed to read component file: {$resolvedPath}");
                    continue;
                }
                
                $script = "";
                if (intval(getenv("THEMED_DEBUG") ?: '0') > 0) {
                    $script .= "<!-- " . basename($resolvedPath) . " -->\n";
                }
                
                if ($type === 'css') {
                    $content = self::processStylesWithFonts($content, dirname($resolvedPath));
                    $script .= "<style>\n" . self::minifyCss($content) . "\n</style>";
                    self::headerScripts($script);
                } else {
                    $script .= "<script>\n" . self::minifyJs($content) . "\n</script>";
                    self::footerScripts($script);
                }
            }
        }
    }
    /**
     * Set a custom script callback. Next calls to headerScripts/footerScripts with non-empty scripts
     * will invoke this callback instead of the default session-based storage.
     * @param callable(string, string, string): void $callback
     */
    public static function setScriptCallback(callable $callback): void
    {
        self::$scriptCallback = $callback;
        self::log("NOTICE: Custom script callback registered");
    }
    /**
     * Process CSS and embed font files as base64 data URIs
     * 
     * @param string $css CSS content to process
     * @param string $basePath Base path for resolving relative font URLs
     * @return string Processed CSS with embedded fonts
     */
    private static function processStylesWithFonts(string $css, string $basePath): string
    {
        try {
            // Normalize base path
            $basePath = rtrim($basePath, '/\\');
            
            // Find all font URLs in the CSS
            if (!preg_match_all('/url\([\"\']?([^\"\'\)]+)[\"\']?\)/i', $css, $matches)) {
                return $css;
            }
            
            $replacements = [];
            
            foreach ($matches[1] as $index => $fontPath) {
                try {
                    // Skip if already processed or invalid
                    if (str_starts_with($fontPath, 'data:') || trim($fontPath) === '') {
                        continue;
                    }
                    
                    // Remove query string and fragment if present
                    $fontPath = strtok($fontPath, '?#');
                    
                    // Handle absolute URLs (skip if not local)
                    if (preg_match('#^(https?:)?//#i', $fontPath)) {
                        self::log("NOTICE: Skipping remote font: {$fontPath}");
                        continue;
                    }
                    
                    // Convert relative path to absolute
                    $fontPath = ltrim($fontPath, './');
                    $fullFontPath = realpath($basePath . '/' . $fontPath);
                    
                    // Verify the resolved path is within the base directory
                    if ($fullFontPath === false || strpos($fullFontPath, $basePath) !== 0) {
                        throw new \RuntimeException("Font path resolution failed or points outside base directory: {$fontPath}");
                    }
                    
                    // Check file existence and readability
                    if (!is_readable($fullFontPath)) {
                        throw new \RuntimeException("Font file not readable: {$fullFontPath}");
                    }
                    
                    // Read file content
                    $fontContent = file_get_contents($fullFontPath);
                    if ($fontContent === false) {
                        throw new \RuntimeException("Failed to read font file: {$fullFontPath}");
                    }
                    
                    // Get mime type based on extension
                    $ext = strtolower(pathinfo($fullFontPath, PATHINFO_EXTENSION));
                    $mimeType = match ($ext) {
                        'woff2' => 'font/woff2',
                        'woff' => 'font/woff',
                        'ttf' => 'font/ttf',
                        'eot' => 'application/vnd.ms-fontobject',
                        'svg' => 'image/svg+xml',
                        default => 'application/octet-stream'
                    };
                    
                    // Encode and create data URL
                    $dataUrl = "data:{$mimeType};base64," . base64_encode($fontContent);
                    $replacements[$matches[0][$index]] = "url('{$dataUrl}')";
                    
                    self::log("NOTICE: Successfully embedded font: {$fullFontPath}");
                    
                } catch (\Exception $e) {
                    self::log("ERROR: Error processing font URL '{$fontPath}': " . $e->getMessage());
                    // Continue with next font if one fails
                    continue;
                }
            }
            
            // Apply all replacements at once
            if (!empty($replacements)) {
                $css = str_replace(
                    array_keys($replacements),
                    array_values($replacements),
                    $css
                );
            }
            
        } catch (\Exception $e) {
            self::log("ERROR: Error in processStylesWithFonts: " . $e->getMessage());
            // Continue with unprocessed CSS on error
        }
        
        return $css;
    }

    public static function getThemePath(): string
    {
        self::log("NOTICE: Getting theme path");
        $themePath = getenv('THEMED_TEMPLATE_PATH') ?: getenv('THEMED_PATH') ?: getenv('THEMED_THEME_PATH') ?: dirname(__DIR__) . '/template/bs5/';
        if (!file_exists($themePath)) {
            self::log("NOTICE: Default theme path not found, trying fallback");
            $themePath = dirname(__DIR__) . '/template/bs5/';
        }
        if (!file_exists($themePath)) {
            self::log("ERROR: Unable to find theme in: {$themePath}");
        }
        return $themePath;
    }

    /**
     * Ensure session is started
     */
    private static function ensureSessionStarted(): void
    {
        // Skip session handling during tests
        if (getenv('THEMED_DEBUG') === '0' || defined('PHPUNIT_COMPOSER_INSTALL')) {
            return;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            self::log("NOTICE: Starting session");
            session_start();
        } else {
            self::log("NOTICE: Session already started");
        }
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    public static function headerScripts($script = "", $type = "auto", $attributes = []): ?string
    {
        self::ensureSessionStarted();
        self::log("NOTICE: Processing header script request");
        
        // If a custom script callback is set, delegate all non-empty scripts to it
        if ($script !== '' && is_callable(self::$scriptCallback)) {
            $ext = $type === 'auto' ? pathinfo($script, PATHINFO_EXTENSION) : $type;
            call_user_func(self::$scriptCallback, $script, $ext, 'header');
            self::log("NOTICE: Header script processed by custom callback");
            return null;
        }
        
        if (!isset($_SESSION[self::SESSION_KEY]['header_scripts'])) {
            $_SESSION[self::SESSION_KEY]['header_scripts'] = [];
            self::log("NOTICE: Initialized header scripts array");
        }
        if (empty($script)) {
            self::loadScripts();
            if ((int) (getenv('THEMED_DEBUG_LEVEL') ?? 0) > 2) {
                self::log("NOTICE: Header Scripts: " . json_encode($_SESSION[self::SESSION_KEY]['header_scripts']));
            }
            $scripts = implode("\n", $_SESSION[self::SESSION_KEY]['header_scripts']);
            $_SESSION[self::SESSION_KEY]['header_scripts'] = [];
            self::log("NOTICE: Returning header scripts");
            return $scripts;
        }

        if ((int) (getenv("THEMED_DEBUG_LEVEL") ?? 0) > 0) {
            self::log("NOTICE: Adding Header Script: " . $script);
        }

        //Get script extension
        if ($type == "auto") {
            $scriptExtension = pathinfo($script, PATHINFO_EXTENSION);
            self::log("NOTICE: Auto-detected script extension: " . $scriptExtension);
        } else {
            $scriptExtension = $type;
            self::log("NOTICE: Using specified script type: " . $scriptExtension);
        }
        //If it is js, then add a script tag around it
        // Convert attributes array to string
        $attributeStr = '';
        if (!empty($attributes) && is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                $attributeStr .= " {$key}=\"{$value}\"";
            }
        }

        if ($scriptExtension === "js") {
            $script = '<script type="text/javascript" src="' . $script . '"' . $attributeStr . '></script>';
        } elseif ($scriptExtension === "css") {
            $script = '<link rel="stylesheet" href="' . $script . '"' . $attributeStr . '>';
        }
        //Prevent duplicate entries
        $_SESSION[self::SESSION_KEY]['header_scripts'][md5($script)] = $script;

        return null;
    }

    public static function footerScripts($script = "", $type = "auto", $attributes = []): ?string
    {
        self::ensureSessionStarted();
        
        // If a custom script callback is set, delegate all non-empty scripts to it
        if ($script !== '' && is_callable(self::$scriptCallback)) {
            $ext = $type === 'auto' ? pathinfo($script, PATHINFO_EXTENSION) : $type;
            call_user_func(self::$scriptCallback, $script, $ext, 'footer');
            return null;
        }
        
        if (!isset($_SESSION[self::SESSION_KEY]['footer_scripts'])) {
            $_SESSION[self::SESSION_KEY]['footer_scripts'] = [];
        }
        if (empty($script)) {
            self::loadScripts();
            if ((int) (getenv('THEMED_DEBUG_LEVEL') ?? 0) > 2) {
                self::log("NOTICE: Footer Scripts: " . json_encode($_SESSION[self::SESSION_KEY]['footer_scripts']));
            }
            $scripts = implode("\n", $_SESSION[self::SESSION_KEY]['footer_scripts']);
            $_SESSION[self::SESSION_KEY]['footer_scripts'] = [];
            return $scripts;
        }

        if ((int) (getenv("THEMED_DEBUG_LEVEL") ?? 0) > 0) {
            self::log("NOTICE: Adding Footer Script: " . $script);
        }

        //Get script extension
        if ($type == "auto") {
            $scriptExtension = pathinfo($script, PATHINFO_EXTENSION);
        } else {
            $scriptExtension = $type;
        }

        //If it is js, then add a script tag around it
        // Convert attributes array to string
        $attributeStr = '';
        if (!empty($attributes) && is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                $attributeStr .= " {$key}=\"{$value}\"";
            }
        }

        if ($scriptExtension === "js") {
            $script = '<script type="text/javascript" src="' . $script . '"' . $attributeStr . '></script>';
        } elseif ($scriptExtension === "css") {
            $script = '<link rel="stylesheet" href="' . $script . '"' . $attributeStr . '>';
        }
        //Prevent duplicate entries
        $_SESSION[self::SESSION_KEY]['footer_scripts'][md5($script)] = $script;

        return null;
    }

    /**
     * Internal logging implementation with auto-detected debug levels
     * 
     * @param string $message Message to log. The level is auto-detected from the message prefix:
     *                       'ERROR:' = level 0 (error)
     *                       'WARN:' = level 1 (warning)
     *                       'NOTICE:' = level 2 (notice)
     *                       No prefix = level 3 (info)
     */
    protected static function internalLog(string $message): void
    {
        $debug = (bool) (getenv('THEMED_DEBUG') ?: false);
        $debugLevel = (int) (getenv('THEMED_DEBUG_LEVEL') ?: '0');

        // Auto-detect level from message prefix
        $level = 3; // Default to INFO level
        if (preg_match('/^(ERROR|WARN|NOTICE):/', $message, $matches)) {
            $level = match($matches[1]) {
                'ERROR' => 0,
                'WARN' => 1,
                'NOTICE' => 2
            };
            // Remove the prefix from the message
            $message = trim(substr($message, strlen($matches[1]) + 1));
        }

        // Skip logging if debugging is disabled or message level is higher than debug level
        if (!$debug || $debugLevel < $level) {
            return;
        }

        // Add level prefix to message
        $prefix = match($level) {
            0 => '[ERROR] ',
            1 => '[WARN] ',
            2 => '[NOTICE] ',
            3 => '[INFO] ',
            default => '[LOG] '
        };

        $logFilePath = getenv('THEMED_DEBUG_LOG') ?: '/tmp/themed.log';
        
        $logMessage = date('Y-m-d H:i:s') . ' ' . $prefix . $message . "\n";

        // Ensure log directory exists
        $logDir = dirname($logFilePath);
        if (!is_dir($logDir)) {
            try {
                mkdir($logDir, 0755, true);
            } catch (\Exception $e) {
                error_log("Unable to create log directory: " . $e->getMessage());
                return;
            }
        }

        // Create log file if it doesn't exist
        if (!file_exists($logFilePath)) {
            try {
                touch($logFilePath);
                chmod($logFilePath, 0644);
            } catch (\Exception $e) {
                error_log("Unable to create log file: " . $e->getMessage());
                return;
            }
        }

        // Check file size and rotate if needed (1MB = 1048576 bytes)
        if (file_exists($logFilePath) && filesize($logFilePath) > 1048576) {
            // Find the next available backup number
            $backupNumber = 1;
            while (file_exists($logFilePath . '.' . $backupNumber)) {
                $backupNumber++;
                // Limit to 5 backup files to prevent unlimited growth
                if ($backupNumber > 5) {
                    unlink($logFilePath . '.1'); // Remove oldest backup
                    // Shift all files down by one number
                    for ($i = 1; $i < 5; $i++) {
                        if (file_exists($logFilePath . '.' . ($i + 1))) {
                            rename($logFilePath . '.' . ($i + 1), $logFilePath . '.' . $i);
                        }
                    }
                    $backupNumber = 5;
                    break;
                }
            }
            rename($logFilePath, $logFilePath . '.' . $backupNumber);
            try {
                touch($logFilePath);
            } catch (Exception $e) {
                error_log("Unable to create log file: " . $e->getMessage());
                return;
            }
        }

        $message = date("Y-m-d H:i:s") . " - " . $message;
        if (file_exists($logFilePath)) {
            file_put_contents($logFilePath, $message . "\n", FILE_APPEND);
        }
    }

    private static function minifyJs(string $js): string
    {
        if (intval(getenv("THEMED_DEBUG")) < 1) {
            return $js;
        }

        // Remove comments
        $js = preg_replace('/\/\*[\s\S]*?\*\/|\/\/.*$/m', '', $js);

        // Remove extra whitespace and newlines
        $js = preg_replace('/\s+/', ' ', $js);

        // Remove whitespace around operators and punctuation
        $js = preg_replace('/\s*([{}()[\];,:=+\-<>!&|?])\s*/', '$1', $js);

        // Remove whitespace before/after braces
        $js = preg_replace('/\s*([{}])\s*/', '$1', $js);

        // Remove remaining unnecessary spaces
        $js = trim(preg_replace('/ {2,}/', ' ', $js));

        return $js;
    }


    /**
     * Get SVG content by name with security checks against path traversal
     * 
     * @param string $name Name of the SVG file (without .svg extension)
     * @return string|null The SVG content or null if not found
     * @throws \InvalidArgumentException If the name contains invalid characters
     */
    /**
     * Get SVG content by name with security checks against path traversal
     * 
     * @param string $name Name of the SVG file (without .svg extension)
     * @return string|null The SVG content or null if not found or on error
     * @throws \InvalidArgumentException If the name contains invalid characters
     * @throws \RuntimeException If there's an error reading the file
     */
    public static function getSvgContent(string $name): ?string
    {
        try {
            // Validate input
            if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $name)) {
                throw new \InvalidArgumentException('Invalid SVG name. Only alphanumeric characters, hyphens, underscores and forward slashes are allowed.');
            }

            // Check cache first
            if (isset(self::$svgCache[$name])) {
                return self::$svgCache[$name];
            }

            $themePath = rtrim(self::getThemePath(), '/');
            self::log("NOTICE: Looking for SVG: {$name} in theme path: {$themePath}");

            // Define allowed paths
            $possiblePaths = [
                $themePath . '/icons/' . $name . '.svg',
                $themePath . '/' . $name . '.svg'
            ];

            $svgPath = null;
            
            // Check each possible path
            foreach ($possiblePaths as $path) {
                $resolvedPath = realpath($path);
                // Ensure the resolved path is within the theme directory
                if ($resolvedPath !== false && strpos($resolvedPath, $themePath) === 0) {
                    if (!is_readable($resolvedPath)) {
                        self::log("WARN: SVG file not readable: {$resolvedPath}");
                        continue;
                    }
                    $svgPath = $resolvedPath;
                    self::log("NOTICE: Found SVG at: {$svgPath}");
                    break;
                }
            }

            if ($svgPath === null) {
                self::log("WARN: SVG not found in any allowed location: {$name}");
                return null;
            }

            $svg = file_get_contents($svgPath);
            if ($svg === false) {
                throw new \RuntimeException("Failed to read SVG file: {$svgPath}");
            }

            // Basic SVG validation
            if (trim($svg) === '') {
                throw new \RuntimeException("SVG file is empty: {$svgPath}");
            }

            // Remove XML declaration and optimize SVG
            $svg = preg_replace('/<\?xml.*?\?>/', '', $svg);
            $svg = preg_replace('/<!--.*?-->/', '', $svg);
            $svg = preg_replace('/>\s+</', '><', $svg);

            $result = trim($svg);
            if (empty($result)) {
                throw new \RuntimeException("SVG processing resulted in empty content: {$svgPath}");
            }

            // Cache the result
            self::$svgCache[$name] = $result;
            return $result;
            
        } catch (\Exception $e) {
            $debug = intval(getenv("THEMED_DEBUG") ?: '0') > 0;
            self::log("ERROR: Error in getSvgContent({$name}): " . $e->getMessage());
            if ($debug) {
                throw $e;
            }
            return null;
        }
    }

    private static function minifyCss(string $css): string
    {
        $originalSize = strlen($css);

        if (intval(getenv("THEMED_DEBUG")) < 1) {
            return $css;
        }
        // Remove comments
        $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);

        // Remove extra whitespace and newlines
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove whitespace around brackets, colons, and semicolons
        $css = preg_replace('/\s*([{}:;])\s*/', '$1', $css);

        // Remove whitespace before/after braces
        $css = preg_replace('/\s*([{}])\s*/', '$1', $css);

        // Remove unnecessary semicolons
        $css = preg_replace('/;}/', '}', $css);

        // Remove remaining unnecessary spaces
        $css = trim(preg_replace('/ {2,}/', ' ', $css));

        $minifiedSize = strlen($css);
        if ($minifiedSize < $originalSize) {
            self::log("NOTICE: CSS minified: {$originalSize} bytes -> {$minifiedSize} bytes (" . round(($minifiedSize / $originalSize) * 100) . "% of original)");
        }
        return $css;
    }
}
