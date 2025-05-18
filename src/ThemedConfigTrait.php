<?php declare(strict_types=1);

namespace Skuilplek\Themed;

trait ThemedConfigTrait {

    /**
     * Level 0 - Only log the component name
     * Level 1 - Log the component name and the content
     * Level 2 - Log the component name, content and parameters
     * Level 3 - Log the component name, content, parameters and internal method calls
     * @var int
     */
    protected array $themedConfig = [
        'template_path' => '', //Overrides the default template directory (e.g. /var/www/html/theme/fancytheme/).
        'debug' => true, //Enables debug mode (1 for on, 0 for off).
        'debug_level' => 0, //Sets debug verbosity (0-3).
        'debug_log' => '/tmp/themed.log', //Specifies full path to log file for debug output.
    ];

    /**
     * Get the theme path.
     * @return string The theme path.
     */
    public function getThemedConfig($key = 'null'): mixed
    {
        $this->themedConfig = $this->getSessionKey('themedConfig') ?? $this->themedConfig;
        return $this->themedConfig[$key] ?? null;
    }

    protected function setThemedConfig()
    {
        // Determine base path: use override if provided
        if (empty($this->themedConfig['template_path']) || !file_exists($this->themedConfig['template_path'] . 'components/')) {
            $themePath = getenv('THEMED_TEMPLATE_PATH') ?? dirname(__DIR__) . '/template/bs5/';

            if (!file_exists($themePath . 'components/')) {
                $this->log("ERROR: Unable to find components/ in the theme folder: {$themePath}");
            }
            $this->themedConfig['template_path'] = $themePath;
        }
        $this->themedConfig['debug'] = (bool) (getenv('THEMED_DEBUG') ?? 0) > 0;
        $this->themedConfig['debug_level'] = (int) (getenv('THEMED_DEBUG_LEVEL') ?? 0);
        $this->twig = null;

        $this->setSessionKey('themedConfig',$this->themedConfig);
    }
}