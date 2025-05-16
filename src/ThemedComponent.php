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
     * Protected constructor to enforce usage of static make("content/card") method.
     */
    public function __construct(string $component, array $config = [])
    {
        $this->component = $component;
        $this->id = uniqid();

        // Determine base path: use override if provided
        $themePath = self::$baseTemplatePath ?? self::getThemePath();
        if (self::$twig === null) {
            $loader = new FilesystemLoader([
                $themePath,
                $themePath . 'components/'
            ]);

            //Check if we are in debug mode
            $this->debugging = (int) (getenv('THEMED_DEBUG') ?? 0) > 0;
            $this->debuggingLevel = (int) (getenv('THEMED_DEBUG_LEVEL') ?? 0);
            // Initialize Twig with HTML autoescaping by default for security
            self::$twig = new Environment($loader, [
                'cache' => $this->debugging ? false : '/tmp/twig_cache', // Use cache directory when debugging is disabled
                'debug' => $this->debugging,
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
        $this->templatePath = $themePath;

        self::log("Loading scripts for component: {$component}, id: {$this->id}");
        self::loadScripts($component);

        // Parse parameter definitions from the Twig docblock
        $this->parseParametersFromDocblock($themePath . 'components/' . $component . '.twig');
    }

    /**
     * Set a custom logger callback for ThemedComponent.
     * @param callable(string): void $callback
     */
    public static function setLoggerCallback(callable $callback): void
    {
        self::$loggerCallback = $callback;
    }
    /**
     * Set a custom Twig Environment to use (skips default bootstrap).
     */
    public static function setTwigEnvironment(Environment $twig): void
    {
        self::$twig = $twig;
    }

    /**
     * Get the current Twig Environment, or null if not initialized.
     */
    public static function getTwigEnvironment(): ?Environment
    {
        return self::$twig;
    }

    /**
     * Override the base template path. Next instantiation will bootstrap Twig with this path.
     * @param string $path Absolute path to template directory, trailing slash optional.
     */
    public static function setBasePath(string $path): void
    {
        self::$baseTemplatePath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        // Reset Twig to force re-initialization with new loader
        self::$twig = null;
    }

    /**
     * Internal log method. Uses custom logger if set, otherwise falls back to self::log().
     */
    protected static function log(string $message): void
    {
        if (self::$loggerCallback !== null) {
            call_user_func(self::$loggerCallback, $message);
        } else {
            self::internalLog($message);
        }
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
    private function parseParametersFromDocblock(string $componentFile)
    {
        // Parameter-block caching: reuse previously parsed definitions
        if (isset(self::$parameterCache[$componentFile])) {
            $this->parameters = self::$parameterCache[$componentFile];
            return $this;
        }
        $parameters = [];
        if (file_exists($componentFile)) {
            $componentHtml = file_get_contents($componentFile);
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
            self::log("parameters: {$this->component} : " . json_encode($parameters));
        }
        return $this;
    }

    /**
     * Static factory method to create a new component group.
     * Change to take arg group/component name and then add .twig to render
     */
    public static function make(string $component, array $config = []): self
    {
        return new static($component, $config);
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
                self::log("Content: {$this->component} : " . json_encode($args));
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
        return $this;
    }

    /**
     * Add custom CSS.
     */
    protected function addCss(string $css): self
    {
        $this->css[md5($css)] = $css;
        return $this;
    }

    /**
     * Sets inline JavaScript scripts.
     */
    protected function addJavaScript(string $script): self
    {
        $this->javascript[md5($script)] = $script;
        return $this;
    }

    /**
     * Adds an HTML attribute to the component.
     */
    protected function addAttribute(string $name, string $value): self
    {
        $this->attributes[$name] = $value;
        return $this;
    }

    protected function canSee(bool $canSee): self
    {
        $this->canSee = $canSee;
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
        if (!$this->canSee) {
            return '';
        }

        if (empty($this->component)) {
            if($this->debugging) {
                throw new Exception('Component group and name must be set before rendering');
            } else {
                self::log('Component group and name must be set before rendering');
                return "<!-- ERROR: Component group and name must be set before rendering -->";
            }
        }

        $this->preprocessContent();

        if (self::$twig === null) {
            if($this->debugging) {
                throw new Exception('Twig environment not initialized. Call ThemedComponent::setBasePath() first.');
            } else {
                self::log('Twig environment not initialized. Call ThemedComponent::setBasePath() first.');
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
                self::log(sprintf('Template "%s" not found. Searched in: %s', $templateFile, implode(', ', $paths)));
                return "<!-- ERROR: Template \"{$templateFile}\" not found. -->";
            }
        }
        return self::$twig->render($templateFile, $context);
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
                        self::log("Failed to read file: {$resolvedFile}");
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
                    self::log("Failed to read component file: {$resolvedPath}");
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
    }
    private static function processStylesWithFonts(string $css, string $basePath): string
    {
        // Find all font URLs in the CSS
        if (preg_match_all('/url\(["\']?([^"\'\)]+)["\']?\)/i', $css, $matches)) {
            foreach ($matches[1] as $index => $fontPath) {
                // Remove query string if present
                $fontPath = explode('?', $fontPath)[0];

                // Convert relative path to absolute
                $fullFontPath = $basePath . '/' . ltrim($fontPath, './');

                if (file_exists($fullFontPath)) {
                    // Get font file content and encode as base64
                    $fontContent = base64_encode(file_get_contents($fullFontPath));

                    // Get mime type based on extension
                    $ext = pathinfo($fullFontPath, PATHINFO_EXTENSION);
                    $mimeType = match ($ext) {
                        'woff2' => 'font/woff2',
                        'woff' => 'font/woff',
                        'ttf' => 'font/ttf',
                        'eot' => 'application/vnd.ms-fontobject',
                        'svg' => 'image/svg+xml',
                        default => 'application/octet-stream'
                    };

                    // Replace URL with base64 data
                    $dataUrl = "data:{$mimeType};base64,{$fontContent}";
                    $css = str_replace($matches[0][$index], "url('{$dataUrl}')", $css);
                    self::log("Embedded font file: {$fullFontPath}");
                } else {
                    self::log("Missing font file: {$fullFontPath}");
                }
            }
        }
        return $css;
    }

    public static function getThemePath(): string
    {
        $themePath = getenv('THEMED_TEMPLATE_PATH') ?? '';
        if (empty($themePath) || !file_exists($themePath)) {
            $themePath = '';
        }
        if (empty($themePath)) {
            //One folder back
            $themePath = dirname(dirname(__FILE__)) . "/";

            //This is the default theme
            $themePath .= "template/bs5/";
        }
        if (!file_exists($themePath)) {
            self::log("Unable to find theme in: {$themePath}");
        }
        return $themePath;
    }

    /**
     * Ensure session is started
     */
    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    public static function headerScripts($script = "", $type = "auto", $attributes = []): ?string
    {
        self::ensureSessionStarted();
        
        // If a custom script callback is set, delegate all non-empty scripts to it
        if ($script !== '' && is_callable(self::$scriptCallback)) {
            $ext = $type === 'auto' ? pathinfo($script, PATHINFO_EXTENSION) : $type;
            call_user_func(self::$scriptCallback, $script, $ext, 'header');
            return null;
        }
        
        if (!isset($_SESSION[self::SESSION_KEY]['header_scripts'])) {
            $_SESSION[self::SESSION_KEY]['header_scripts'] = [];
        }
        if (empty($script)) {
            self::loadScripts();
            if ((int) (getenv('THEMED_DEBUG_LEVEL') ?? 0) > 2) {
                self::log("Header Scripts: " . json_encode($_SESSION[self::SESSION_KEY]['header_scripts']));
            }
            $scripts = implode("\n", $_SESSION[self::SESSION_KEY]['header_scripts']);
            $_SESSION[self::SESSION_KEY]['header_scripts'] = [];
            return $scripts;
        }

        if ((int) (getenv("THEMED_DEBUG_LEVEL") ?? 0) > 0) {
            self::log("Adding Header Script: " . $script);
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
                self::log("Footer Scripts: " . json_encode($_SESSION[self::SESSION_KEY]['footer_scripts']));
            }
            $scripts = implode("\n", $_SESSION[self::SESSION_KEY]['footer_scripts']);
            $_SESSION[self::SESSION_KEY]['footer_scripts'] = [];
            return $scripts;
        }

        if ((int) (getenv("THEMED_DEBUG_LEVEL") ?? 0) > 0) {
            self::log("Adding Footer Script: " . $script);
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

    public static function internalLog(string $message)
    {
        if (intval(getenv("THEMED_DEBUG")) < 1) {
            return;
        }
        $logFilePath = getenv('THEMED_DEBUG_LOG') ?? "";
        if (empty($logFilePath)) {
            $logFilePath = "/tmp/themed.log";
        }
        if (!file_exists($logFilePath)) {
            try {
                touch($logFilePath);
            } catch (Exception $e) {
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
    public static function getSvgContent(string $name): ?string
    {
        // Validate input
        if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $name)) {
            throw new \InvalidArgumentException('Invalid SVG name. Only alphanumeric characters, hyphens, underscores and forward slashes are allowed.');
        }

        // Check cache first
        if (isset(self::$svgCache[$name])) {
            return self::$svgCache[$name];
        }

        $themePath = rtrim(self::getThemePath(), '/');
        self::log("Theme path: {$themePath}");

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
                $svgPath = $resolvedPath;
                self::log("Found SVG at: {$svgPath}");
                break;
            }
        }

        if ($svgPath === null || !file_exists($svgPath)) {
            self::log("SVG not found: {$name}");
            return null;
        }

        $svg = @file_get_contents($svgPath);
        if ($svg === false) {
            self::log("Failed to read SVG file: {$svgPath}");
            return null;
        }

        // Remove XML declaration and optimize SVG
        $svg = preg_replace('/<\?xml.*?\?>/', '', $svg);
        $svg = preg_replace('/<!--.*?-->/', '', $svg);
        $svg = preg_replace('/>\s+</', '><', $svg);

        // Cache the result
        self::$svgCache[$name] = trim($svg);
        return self::$svgCache[$name];
    }

    private static function minifyCss(string $css): string
    {
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

        return $css;
    }
}
