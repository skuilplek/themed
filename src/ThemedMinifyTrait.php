<?php declare(strict_types=1);

namespace Skuilplek\Themed;

trait ThemedMinifyTrait {

    private function minifyCss(string $css): string
    {
        $originalSize = strlen($css);

        if (intval($this->getThemedConfig("debug")) > 0) {
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
            $this->log("NOTICE: CSS minified: {$originalSize} bytes -> {$minifiedSize} bytes (" . round(($minifiedSize / $originalSize) * 100) . "% of original)");
        }
        return $css;
    }

    private function minifyJs(string $js): string
    {
        if (intval($this->getThemedConfig("debug")) > 0) {
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
}
