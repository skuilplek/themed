<?php declare(strict_types=1);

namespace Skuilplek;

use Exception;

class Themed
{
    public const SESSION_KEY = "sk_themed";

    private static array $svgCache = [];
    public static function loadScripts($component = ""): void
    {
        $themePath = self::getThemePath();

        if (empty($component)) {
            //Load all css files
            if (file_exists($themePath . "css/")) {
                $files = glob($themePath . "css/*.css");
                foreach ($files as $file) {
                    $script = "";
                    if (intval(getenv("THEMED_DEBUG")) > 0) {
                        $script .= "<!-- " . $file . " -->\n";
                    }
                    $cssContent = file_get_contents($file);
                    $cssContent = self::processStylesWithFonts($cssContent, dirname($file));
                    $script .= "<style>\n" . self::minifyCss($cssContent) . "\n</style>";
                    self::headerScripts($script);
                }
            }

            //Load all js files
            if (file_exists($themePath . "js/")) {
                $files = glob($themePath . "js/*.js");
                foreach ($files as $file) {
                    $script = "";
                    if (intval(getenv("THEMED_DEBUG")) > 0) {
                        $script .= "<!-- " . $file . " -->\n";
                    }
                    $script .= "<script>\n" . self::minifyJs(file_get_contents($file)) . "\n</script>";
                    self::headerScripts($script);
                }
            }

            //Load all footer/js files into the footer
            if (file_exists($themePath . "js/footer/")) {
                $files = glob($themePath . "js/footer/*.js");
                foreach ($files as $file) {
                    $script = "";
                    if (intval(getenv("THEMED_DEBUG")) > 0) {
                        $script .= "<!-- " . $file . " -->\n";
                    }
                    $script .= "<script>\n" . self::minifyJs(file_get_contents($file)) . "\n</script>";
                    self::footerScripts($script);
                }
            }
        } else {
            //Load the component css and js files
            $componentCssFile = $themePath . "components/" . $component . ".css";
            if (file_exists($componentCssFile)) {
                $script = file_get_contents($componentCssFile);
                $script = "<style>\n" . self::minifyCss($script) . "\n</style>";
                self::headerScripts($script);
            }
            $componentJsFile = $themePath . "components/" . $component . ".js";
            if (file_exists($componentJsFile)) {
                $script = file_get_contents($componentJsFile);
                $script = "<script>\n" . self::minifyJs($script) . "\n</script>";
                self::footerScripts($script);
            }
        }
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

    public static function headerScripts($script = "", $type = "auto", $attributes = []): ?string
    {
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

    public static function log(string $message)
    {
        if (intval(getenv("THEMED_DEBUG")) < 1) {
            return;
        }
        $logFilePath = getenv('THEMED_DEBUG_LOG') ?? "";
        if(empty($logFilePath)) {
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


    public static function getSvgContent(string $name): ?string
    {
        if (isset(self::$svgCache[$name])) {
            return self::$svgCache[$name];
        }

        $themePath = self::getThemePath();
        self::log("Theme path: {$themePath}");

        $svgPath = $themePath . 'icons/' . $name . '.svg';
        self::log("Looking for SVG at: {$svgPath}");

        if (!file_exists($svgPath)) {
            // Try without the icons/ prefix
            $svgPath = $themePath . $name . '.svg';
            self::log("Not found, trying: {$svgPath}");
        }
        
        if (!file_exists($svgPath)) {
            self::log("Icon not found: {$svgPath}");
            return null;
        }

        $svg = file_get_contents($svgPath);
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