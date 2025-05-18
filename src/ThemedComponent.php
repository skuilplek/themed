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
    use ThemedSessionTrait;
    use ThemedConfigTrait;
    use ThemedParseTrait;
    use ThemedLogTrait;
    use ThemedScriptsTrait;
    use ThemedMinifyTrait;

    protected string $id = '';
    protected array $attributes = [];
    protected array $classes = [];
    protected array $css = [];
    protected array $javascript = [];
    protected array $componentData = [];
    protected string $component = '';

    private array $svgCache = [];

    /**
     * Optional override for the base template path. (e.g. /var/www/html/theme/fancytheme/)
     * @var string|null
     */
    protected ?Environment $twig = null;

    protected bool $canSee = true;

    /**
     * Protected constructor to enforce usage of static make("content/card") method.
     */
    public function __construct(string $component = '')
    {
        $this->component = $component;
        $this->id = uniqid();
        $this->setThemedConfig();
        //Load twig
        if (!empty($component) && $this->twig === null) {
            $loader = new FilesystemLoader([
                $this->getThemedConfig('template_path'),
                $this->getThemedConfig('template_path') . 'components/'
            ]);

            //Check if we are in debug mode
            // Initialize Twig with HTML autoescaping by default for security
            $this->twig = new Environment($loader, [
                'cache' => $this->getThemedConfig('debug') ? false : '/tmp/twig_cache', // Use cache directory when debugging is disabled
                'debug' => $this->getThemedConfig('debug'),
                'autoescape' => 'html', // Escape all variables by default; use |raw for trusted HTML
            ]);

            $this->getTwigEnvironment()->addFilter(new \Twig\TwigFilter('regex_replace', function ($string, $pattern, $replacement) {
                return preg_replace($pattern, $replacement, $string);
            }));

            // Add component function to Twig
            $this->getTwigEnvironment()->addFunction(new \Twig\TwigFunction('component', function (string $name, array $content = []) {
                return ThemedComponent::make($name)
                    ->content($content)
                    ->render();
            }, ['is_safe' => ['html']]));
        }
        if(!empty($component)) {
            $this->log("NOTICE: Loading scripts for component: {$component}, id: {$this->id}");
            $this->loadScripts($component);

            // Parse parameter definitions from the Twig docblock
            $this->parseParametersFromDocblock($this->getThemedConfig('template_path') . 'components/' . $component . '.twig');
        }
    }

    /**
     * Set a custom Twig Environment to use (skips default bootstrap).
     */
    public function setTwigEnvironment(Environment $twig): void
    {
        $this->twig = $twig;
        $this->log("NOTICE: Custom Twig environment set");
    }

    /**
     * Get the current Twig Environment, or null if not initialized.
     */
    public function getTwigEnvironment(): ?Environment
    {
        $twig = $this->twig;
        if ($twig === null) {
            $this->log("NOTICE: Twig environment not initialized");
        }
        return $twig;
    }

    /**
     * Static factory method to create a new component group.
     * Change to take arg group/component name and then add .twig to render
     */
    public static function make(string $component): self
    {
        return new static($component);
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
            if ($this->themedConfig['debug_level'] > 2) {
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
        if (!$this->canSee) {
            return '';
        }

        if (empty($this->component)) {
            if ($this->getThemedConfig('debug')) {
                throw new Exception('Component group and name must be set before rendering');
            } else {
                $this->log('ERROR: Component group and name must be set before rendering');
                return "<!-- ERROR: Component group and name must be set before rendering -->";
            }
        }

        $this->preprocessContent();

        if ($this->twig === null) {
            if ($this->getThemedConfig('debug')) {
                throw new Exception('Twig environment not initialized. Call ThemedComponent::setBasePath() first.');
            } else {
                $this->log('ERROR: Twig environment not initialized. Call ThemedComponent::setBasePath() first.');
                return "<!-- ERROR: Twig environment not initialized. -->";
            }
        }

        $templateFile = "{$this->component}.twig";

        foreach ($this->css as $css) {
            if(!empty($css))
                $this->headerScripts($css);
        }

        foreach ($this->javascript as $js) {
            if(!empty($js))
                $this->footerScripts($js);
        }

        if (empty($this->componentData['id'])) {
            $this->componentData['id'] = $this->id;
        }

        if (empty($this->componentData['classes'])) {
            $this->componentData['classes'] = implode(' ', $this->classes);
        }

        if (empty($this->componentData['attributes'])) {
            $this->componentData['attributes'] = $this->attributes;
        }

        $context = [
            'content' => $this->componentData,
        ];

        // Improvement: Template existence check before rendering
        $loader = $this->getTwigEnvironment()->getLoader();
        if (method_exists($loader, 'exists') && !$loader->exists($templateFile)) {
            $paths = [];
            if ($loader instanceof FilesystemLoader) {
                $paths = $loader->getPaths();
            }
            if ($this->getThemedConfig('debug')) {
                throw new Exception(
                    sprintf(
                        'Template "%s" not found. Searched in: %s',
                        $templateFile,
                        implode(', ', $paths)
                    )
                );
            } else {
                $this->log(sprintf('ERROR: Template "%s" not found. Searched in: %s', $templateFile, implode(', ', $paths)));
                return "<!-- ERROR: Template \"{$templateFile}\" not found. -->";
            }
        }
        return $this->getTwigEnvironment()->render($templateFile, $context);
    }

    public function getSvgContent(string $name): ?string
    {
        try {
            // Validate input
            if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $name)) {
                throw new \InvalidArgumentException('Invalid SVG name. Only alphanumeric characters, hyphens, underscores and forward slashes are allowed.');
            }

            // Check cache first
            if (isset($this->svgCache[$name])) {
                return $this->svgCache[$name];
            }

            $themePath = rtrim($this->getThemedConfig('template_path'), '/');
            $this->log("NOTICE: Looking for SVG: {$name} in theme path: {$themePath}");

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
                        $this->log("WARN: SVG file not readable: {$resolvedPath}");
                        continue;
                    }
                    $svgPath = $resolvedPath;
                    $this->log("NOTICE: Found SVG at: {$svgPath}");
                    break;
                }
            }

            if ($svgPath === null) {
                $this->log("WARN: SVG not found in any allowed location: {$name}");
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
            $this->svgCache[$name] = $result;
            return $result;

        } catch (\Exception $e) {
            $debug = intval($this->getThemedConfig("debug")) > 0;
            $this->log("ERROR: Error in getSvgContent({$name}): " . $e->getMessage());
            if ($debug) {
                throw $e;
            }
            return null;
        }
    }

}
