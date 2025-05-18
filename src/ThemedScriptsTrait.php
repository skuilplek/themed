<?php declare(strict_types=1);

namespace Skuilplek\Themed;

trait ThemedScriptsTrait {

    /**
     * Optional custom script callback. Signature: function(string $script, string $type, string $location): void
     * @var callable|null
     */
    protected $scriptCallback = null;

    /**
     * Load scripts and styles for components with security checks
     * 
     * @param string $component Component name (without extension)
     * @throws \InvalidArgumentException If the component name contains invalid characters
     */
    public function loadScripts(string $component = ""): void
    {
        $themePath = rtrim($this->getThemedConfig('template_path'), '/');
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
                        $this->log("ERROR: Failed to read file: {$resolvedFile}");
                        continue;
                    }

                    $script = "";
                    if (intval(getenv("THEMED_DEBUG") ?: '0') > 0) {
                        $script .= "<!-- " . basename($resolvedFile) . " -->\n";
                    }

                    if (str_ends_with($resolvedFile, '.css')) {
                        $content = $this->processStylesWithFonts($content, dirname($resolvedFile));
                        $script .= "<style>\n" . $this->minifyCss($content) . "\n</style>";
                        $this->headerScripts($script);
                    } elseif (str_ends_with($resolvedFile, '.js')) {
                        $script .= "<script>\n" . $this->minifyJs($content) . "\n</script>";
                        $isFooter = str_contains($resolvedFile, '/footer/');
                        $isFooter ? $this->footerScripts($script) : $this->headerScripts($script);
                    }
                }
            }
        } else {
            // Validate component name
            if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $component)) {
                throw new \InvalidArgumentException('Invalid component name. Only alphanumeric characters, hyphens, underscores and forward slashes are allowed.');
            }

            // Define component files to load
            //TODO: Also implement a way to specify via the filename that we want the script to load in the footer of the page
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
                    $this->log("ERROR: Failed to read component file: {$resolvedPath}");
                    continue;
                }

                $script = "";
                if ($this->getThemedConfig('debug') > 0) {
                    $script .= "<!-- " . basename($resolvedPath) . " -->\n";
                }

                if ($type === 'css') {
                    $content = $this->processStylesWithFonts($content, dirname($resolvedPath));
                    $script .= "<style>\n" . $this->minifyCss($content) . "\n</style>";
                    $this->headerScripts($script);
                } else {
                    $script .= "<script>\n" . $this->minifyJs($content) . "\n</script>";
                    $this->footerScripts($script);
                }
            }
        }
    }

    /**
     * Set a custom script callback. Next calls to headerScripts/footerScripts with non-empty scripts
     * will invoke this callback instead of the default session-based storage.
     * @param callable(string, string, string): void $callback
     */
    public function setScriptCallback(callable $callback): void
    {
        $this->scriptCallback = $callback;
        $this->log("NOTICE: Custom script callback registered");
    }

    /**
     * Process CSS and embed font files as base64 data URIs
     * 
     * @param string $css CSS content to process
     * @param string $basePath Base path for resolving relative font URLs
     * @return string Processed CSS with embedded fonts
     */
    private function processStylesWithFonts(string $css, string $basePath): string
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
                        $this->log("NOTICE: Skipping remote font: {$fontPath}");
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

                    $this->log("NOTICE: Successfully embedded font: {$fullFontPath}");

                } catch (\Exception $e) {
                    $this->log("ERROR: Error processing font URL '{$fontPath}': " . $e->getMessage());
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
            $this->log("ERROR: Error in processStylesWithFonts: " . $e->getMessage());
            // Continue with unprocessed CSS on error
        }

        return $css;
    }

    public function headerScripts($script = "", $type = "auto", $attributes = []): ?string
    {
        $this->ensureSessionStarted();
        $this->log("NOTICE: Processing header script request");

        // If a custom script callback is set, delegate all non-empty scripts to it
        if ($script !== '' && is_callable($this->scriptCallback)) {
            $ext = $type === 'auto' ? pathinfo($script, PATHINFO_EXTENSION) : $type;
            call_user_func($this->scriptCallback, $script, $ext, 'header');
            $this->log("NOTICE: Header script processed by custom callback");
            return null;
        }

        if (empty($script)) {
            $this->loadScripts();
            if ((int) (getenv('THEMED_DEBUG_LEVEL') ?? 0) > 2) {
                $this->log("NOTICE: Header Scripts: " . json_encode($this->getSessionKey('header_scripts')));
            }
            $headerScripts = $this->getSessionKey('header_scripts') ?? [];
            $scripts = implode("\n", $headerScripts);
            $this->setSessionKey('header_scripts', []);
            $this->log("NOTICE: Returning header scripts");
            return $scripts;
        }

        if ((int) (getenv("THEMED_DEBUG_LEVEL") ?? 0) > 0) {
            $this->log("NOTICE: Adding Header Script: " . $script);
        }

        //Get script extension
        if ($type == "auto") {
            $scriptExtension = pathinfo($script, PATHINFO_EXTENSION);
            $this->log("NOTICE: Auto-detected script extension: " . $scriptExtension);
        } else {
            $scriptExtension = $type;
            $this->log("NOTICE: Using specified script type: " . $scriptExtension);
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

        $currentScripts = $this->getSessionKey('header_scripts');
        $currentScripts[md5($script)] = $script;
        $this->setSessionKey('header_scripts', $currentScripts);

        return null;
    }

    public function footerScripts($script = "", $type = "auto", $attributes = []): ?string
    {
        // If a custom script callback is set, delegate all non-empty scripts to it
        if ($script !== '' && is_callable($this->scriptCallback)) {
            $ext = $type === 'auto' ? pathinfo($script, PATHINFO_EXTENSION) : $type;
            call_user_func($this->scriptCallback, $script, $ext, 'footer');
            return null;
        }

        if (empty($script)) {
            $this->loadScripts();
            if ((int) (getenv('THEMED_DEBUG_LEVEL') ?? 0) > 2) {
                $this->log("NOTICE: Footer Scripts: " . json_encode($this->getSessionKey('footer_scripts')));
            }

            $footerScripts = $this->getSessionKey('footer_scripts') ?? [];
            $scripts = implode("\n", $footerScripts);
            $this->setSessionKey('footer_scripts', []);
            return $scripts;
        }

        if ((int) (getenv("THEMED_DEBUG_LEVEL") ?? 0) > 0) {
            $this->log("NOTICE: Adding Footer Script: " . $script);
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
        $this->setSessionKey(['footer_scripts',md5($script)], $script);

        return null;
    }
}
