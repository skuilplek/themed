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
    /**
     * Optional override for the base template path.
     * If set, this path is used instead of Themed::getThemePath().
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
    protected function __construct(string $component, array $config = [])
    {
        $this->component = $component;
        $this->id = uniqid();

        // Determine base path: use override if provided
        $themePath = self::$baseTemplatePath ?? Themed::getThemePath();
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
        Themed::loadScripts($component);

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
     * Internal log method. Uses custom logger if set, otherwise falls back to Themed::log().
     */
    protected static function log(string $message): void
    {
        if (self::$loggerCallback !== null) {
            call_user_func(self::$loggerCallback, $message);
        } else {
            Themed::log($message);
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
            $this->componentData['svg'] = Themed::getSvgContent($this->componentData['name']);
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
            Themed::headerScripts($css);
        }

        foreach ($this->javascript as $js) {
            Themed::footerScripts($js);
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
}
