<?php declare(strict_types=1);

namespace Skuilplek\Themed\Tests;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Twig\TwigFilter;

abstract class BaseTwigTestCase extends TestCase
{
    protected Environment $twig;

    protected function setUp(): void
    {
        $templateRoot = __DIR__ . '/../template/bs5';
        $loader = new FilesystemLoader([
            $templateRoot,
            $templateRoot . '/components',
        ]);
        $this->twig = new Environment($loader, [
            'cache' => false,
            'autoescape' => false,
        ]);
        $this->twig->addFunction(new TwigFunction('component', function (string $name, array $content = []) {
            return $this->twig->render($name . '.twig', ['content' => $content]);
        }, ['is_safe' => ['html']]));
        $this->twig->addFilter(new TwigFilter('regex_replace', function ($subject, $pattern, $replacement) {
            return preg_replace($pattern, $replacement, $subject);
        }, ['is_safe' => ['html']]));
    }
}