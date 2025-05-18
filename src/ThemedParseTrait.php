<?php declare(strict_types=1);

namespace Skuilplek\Themed;

trait ThemedParseTrait {

    /**
     * Cache for parsed parameter definitions, keyed by template file path.
     * @var array<string, array<string, string>>
     */
    protected array $parameterCache = [];
    protected array $parameters = [];

    private function parseParametersFromDocblock(string $componentFile)
    {
        // Parameter-block caching: reuse previously parsed definitions
        if (isset($this->parameterCache[$componentFile])) {
            $this->parameters = $this->parameterCache[$componentFile];
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
            if ($this->themedConfig['debug']) {
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
        if (isset($parameters['content'])) {
            $content = $parameters['content'] . " (always set this first)";
            unset($parameters['content']);
            //Add the content key to the beginning of the array
            $parameters = ['content' => $content] + $parameters;
        }
        $this->parameters = $parameters;
        // Cache the parsed parameters for this template file
        $this->parameterCache[$componentFile] = $parameters;
        if ($this->themedConfig['debug_level'] > 1) {
            self::log("NOTICE: parameters: {$this->component} : " . json_encode($parameters));
        }
        return $this;
    }

    public function getParameters()
    {
        return $this->parameters;
    }
}