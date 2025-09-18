<?php declare(strict_types=1);

namespace Skuilplek\Themed\Tests;

use PHPUnit\Framework\TestCase;
use Skuilplek\Themed\ThemedComponent;

final class ThemedComponentIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        // Tests are handled by THEMED_DEBUG=0 in phpunit.xml.dist
    }

    public function testThemedComponentRendersCorrectly(): void
    {
        // Test basic button component
        $button = ThemedComponent::make('buttons/button')
            ->text('Test Button')
            ->variant('primary');
        
        $output = $button->render();
        $this->assertIsString($output);
        $this->assertStringContainsString('Test Button', $output);
        $this->assertStringContainsString('btn', $output);
        $this->assertStringContainsString('btn-primary', $output);
    }

    public function testThemedComponentWithMultipleInstances(): void
    {
        // Test that multiple instances work correctly with object pooling
        $components = [];
        
        for ($i = 0; $i < 10; $i++) {
            $components[] = ThemedComponent::make('buttons/button')
                ->text("Button $i")
                ->variant('secondary');
        }
        
        foreach ($components as $i => $component) {
            $output = $component->render();
            $this->assertStringContainsString("Button $i", $output);
            $this->assertStringContainsString('btn-secondary', $output);
        }
    }

    public function testThemedComponentWithDifferentTypes(): void
    {
        // Test different component types to ensure pooling works correctly
        $button = ThemedComponent::make('buttons/button')
            ->text('Button');
        $alert = ThemedComponent::make('feedback/alert')
            ->message('Alert message');
        $icon = ThemedComponent::make('icons/icon')
            ->name('star');
        
        $buttonOutput = $button->render();
        $alertOutput = $alert->render();
        $iconOutput = $icon->render();
        
        $this->assertStringContainsString('Button', $buttonOutput);
        $this->assertStringContainsString('Alert message', $alertOutput);
        $this->assertStringContainsString('star', $iconOutput);
        
        // Ensure they're different outputs
        $this->assertNotEquals($buttonOutput, $alertOutput);
        $this->assertNotEquals($buttonOutput, $iconOutput);
        $this->assertNotEquals($alertOutput, $iconOutput);
    }

    public function testThemedComponentPoolClearing(): void
    {
        // Create some components
        $component1 = ThemedComponent::make('buttons/button')
            ->text('Test 1');
        $component2 = ThemedComponent::make('buttons/button')
            ->text('Test 2');
        
        // Render them
        $output1 = $component1->render();
        $output2 = $component2->render();
        
        $this->assertStringContainsString('Test 1', $output1);
        $this->assertStringContainsString('Test 2', $output2);
        
        // Clear the pool
        ThemedComponent::clearPool();
        
        // Create new components after clearing
        $component3 = ThemedComponent::make('buttons/button')
            ->text('Test 3');
        $output3 = $component3->render();
        
        $this->assertStringContainsString('Test 3', $output3);
    }
}
