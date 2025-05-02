<?php declare(strict_types=1);

namespace Skuilplek\Themed;

use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Class ThemedComponent
 *
 * A simple way to create and render HTML components using PHP.
 *
 * @package Skuilplek\Themed\ThemedComponent
 */
class ThemedComponent
{
    protected string $id = '';
    protected array $attributes = [];
    protected array $classes = [];
    protected array $css = [];
    protected array $javascript = [];
    protected array $content = [];
    protected string $component = '';
    protected string $templatePath; //The full path where the template folders are
    protected static string $baseTemplatePath;
    protected static ?Environment $twig = null;

    protected bool $debugging;
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

        $themePath = Themed::getThemePath();
        if (self::$twig === null) {
            $loader = new FilesystemLoader([
                $themePath,
                $themePath . 'components/'
            ]);

            //Check if we are in debug mode
            $this->debugging = (int) (getenv('THEMED_DEBUG') ?? 0) > 0;
            $this->debuggingLevel = (int) (getenv('THEMED_DEBUG_LEVEL') ?? 0);
            self::$twig = new Environment($loader, [
                'cache' => $this->debugging ? false : '/tmp/twig_cache', // Use cache directory when debugging is disabled
                'debug' => $this->debugging,
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

        Themed::log("Loading scripts for component: {$component}, id: {$this->id}");
        Themed::loadScripts($component);

        $this->extractParameters($themePath . 'components/' . $component . '.twig');
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Extract all parameters from the component html and store them in the parameters array
     * @param string $component
     */
    private function extractParameters(string $componentFile)
    {
        $parameters = [];
        if (file_exists($componentFile)) {
            $componentHtml = file_get_contents($componentFile);
            // Step 1: Extract the comment block
            if (preg_match('/{#([\s\S]*?)#}/', $componentHtml, $commentMatch)) {
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
            'addClass' => 'Add a single "classname" to the element or multiple classes as a string like "class1 class2 class3" (optional)',
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
        if ($this->debuggingLevel > 1) {
            Themed::log("parameters: {$this->component} : " . json_encode($parameters));
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
                Themed::log("Content: {$this->component} : " . json_encode($args));
            }
            if (is_array($args) && !empty($args[0])) {
                $args = reset($args);
            } else {
                $args = '';
            }
            if (is_array($args)) {
                foreach ($args as $property => $value) {
                    $this->content[$property] = $value;
                }
            } else {
                $this->content[$method] = $args;
            }
            return $this;
        } else {
            //We can pass all the data as an array to a component or we can pass just the component's content data. This handles that
            if($method == 'content') {
                if(count($args) == 1) {
                    if(is_string($args[0])) {
                        $args[0] = ['content' => $args[0]];
                    }
                }
            }
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
     * Adds a CSS class to the component.
     */
    protected function addClass(string $class): self
    {
        $this->classes[] = $class;
        $this->classes = array_unique($this->classes);
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

    /**
     * Sets the component's content.
     */
    protected function content(null|bool|string|array $content): self
    {
        if(!is_array($content)) {
            $content = ['content' => $content];
        }
        $this->content = $content;
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
        if (strpos($this->component, 'icons/') === 0 && isset($this->content['name'])) {
            $this->content['svg'] = Themed::getSvgContent($this->content['name']);
        }
    }

    public function render(): string
    {
        if (!$this->canSee) {
            return '';
        }

        if (empty($this->component)) {
            throw new Exception('Component group and name must be set before rendering');
        }

        $this->preprocessContent();

        if (self::$twig === null) {
            throw new Exception('Twig environment not initialized. Call ThemedComponent::setBasePath() first.');
        }
        
        $templateFile = "{$this->component}.twig";

        foreach ($this->css as $css) {
            Themed::headerScripts($css);
        }

        foreach ($this->javascript as $js) {
            Themed::footerScripts($js);
        }
        if(empty($this->content['id'])) {
            $this->content['id'] = $this->id;
        }
        if(empty($this->content['classes'])) {
            $this->content['classes'] = implode(' ', $this->classes);
        }
        if(empty($this->content['attributes'])) {
            $this->content['attributes'] = $this->attributes;
        }
        $context = [
            'content' => $this->content,
        ];

        return self::$twig->render($templateFile, $context);
    }
}
