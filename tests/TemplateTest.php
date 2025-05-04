<?php declare(strict_types=1);

namespace Skuilplek\Themed\Tests;

final class TemplateTest extends BaseTwigTestCase
{

    /**
     * Data provider for template paths and minimal context.
     */
    public static function templateProvider(): array
    {
        return [
            ['components/buttons/button.twig', ['content' => ['text' => 'Test Button']]],
            ['components/feedback/alert.twig', ['content' => ['message' => 'Test Alert']]],
            ['components/feedback/toast.twig', ['content' => ['title' => 'Test Toast', 'content' => 'Toast body']]],
            ['components/icons/icon.twig', ['content' => ['name' => 'star']]],
            ['components/layout/grid.twig', ['content' => ['columns' => [[ 'content' => '<p>A</p>' ], [ 'content' => '<p>B</p>' ]]]]],
            ['components/layout/page.twig', ['content' => ['title' => 'Page Title', 'description' => 'Desc', 'content' => '<p>Body</p>']]],
            ['components/layout/section.twig', ['content' => ['title' => 'Section Title', 'content' => 'Section body']]],
            ['components/navigation/navbar.twig', ['content' => ['brand' => ['text' => 'Brand', 'url' => '/'], 'items' => []]]],
            ['components/overlays/modal.twig', ['content' => ['id' => 'modal1', 'title' => 'Modal Title', 'content' => 'Modal body']]],
        ];
    }

    /**
     * @dataProvider templateProvider
     */
    public function testTemplateRenders(string $templatePath, array $context): void
    {
        $output = $this->twig->render($templatePath, $context);
        $this->assertIsString($output);
        $this->assertNotEmpty(trim($output));

        // Assert that all passed content values are rendered in the output
        if (isset($context['content']) && is_array($context['content'])) {
            foreach ($context['content'] as $value) {
                if (is_string($value) && $value !== '') {
                    $this->assertStringContainsString($value, $output);
                }
            }
        }
    }
}
