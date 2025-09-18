<?php
/**
 * Performance Test Example for ThemedComponent
 * 
 * This script demonstrates the performance improvements achieved through
 * the object pool pattern and optimized initialization.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Skuilplek\Themed\ThemedComponent;

// Set up environment for testing
putenv('THEMED_DEBUG=0'); // Disable debug logging for performance
putenv('THEMED_TEMPLATE_PATH=' . __DIR__ . '/../template/bs5/');

/**
 * Test rendering performance with thousands of components
 */
function testComponentPerformance(int $componentCount = 1000): array
{
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    
    // Render many components
    $results = [];
    for ($i = 0; $i < $componentCount; $i++) {
        $component = ThemedComponent::make('buttons/button')
            ->text("Button $i")
            ->href("#button-$i")
            ->render();
        
        $results[] = $component;
        
        // Log progress every 100 components
        if (($i + 1) % 100 === 0) {
            echo "Rendered " . ($i + 1) . " components...\n";
        }
    }
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);
    
    return [
        'component_count' => $componentCount,
        'execution_time' => $endTime - $startTime,
        'memory_used' => $endMemory - $startMemory,
        'memory_peak' => memory_get_peak_usage(true),
        'avg_time_per_component' => ($endTime - $startTime) / $componentCount,
        'pool_stats' => ThemedComponent::getPoolStats()
    ];
}

/**
 * Display performance results
 */
function displayResults(array $results): void
{
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "PERFORMANCE TEST RESULTS\n";
    echo str_repeat("=", 60) . "\n";
    
    printf("Components rendered: %d\n", $results['component_count']);
    printf("Total execution time: %.4f seconds\n", $results['execution_time']);
    printf("Average time per component: %.6f seconds\n", $results['avg_time_per_component']);
    printf("Memory used: %.2f MB\n", $results['memory_used'] / 1024 / 1024);
    printf("Peak memory usage: %.2f MB\n", $results['memory_peak'] / 1024 / 1024);
    
    echo "\nObject Pool Statistics:\n";
    printf("Total pools: %d\n", $results['pool_stats']['total_pools']);
    printf("Total pooled instances: %d\n", $results['pool_stats']['total_instances']);
    
    if (!empty($results['pool_stats']['pools'])) {
        echo "Pool breakdown:\n";
        foreach ($results['pool_stats']['pools'] as $component => $count) {
            printf("  %s: %d instances\n", $component, $count);
        }
    }
    
    echo str_repeat("=", 60) . "\n";
}

/**
 * Test different component types
 */
function testMixedComponents(int $componentCount = 500): array
{
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    
    $componentTypes = [
        'buttons/button',
        'buttons/dropdown', 
        'buttons/toggle',
        'form/input'
    ];
    
    $results = [];
    for ($i = 0; $i < $componentCount; $i++) {
        $type = $componentTypes[$i % count($componentTypes)];
        
        $component = ThemedComponent::make($type)
            ->text("Component $i")
            ->id("comp-$i")
            ->render();
        
        $results[] = $component;
    }
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);
    
    return [
        'component_count' => $componentCount,
        'execution_time' => $endTime - $startTime,
        'memory_used' => $endMemory - $startMemory,
        'memory_peak' => memory_get_peak_usage(true),
        'avg_time_per_component' => ($endTime - $startTime) / $componentCount,
        'pool_stats' => ThemedComponent::getPoolStats()
    ];
}

// Run the tests
echo "Starting ThemedComponent Performance Tests...\n\n";

echo "Test 1: Single component type (5000 buttons)\n";
$results1 = testComponentPerformance(5000);
displayResults($results1);

echo "\nClearing component pool...\n";
ThemedComponent::clearPool();

echo "\nTest 2: Mixed component types (2000 components)\n";
$results2 = testMixedComponents(2000);
displayResults($results2);

echo "\nPerformance testing completed!\n";
echo "\nKey Benefits of the Optimized Implementation:\n";
echo "- Object pooling reduces memory allocation overhead\n";
echo "- Static initialization prevents repeated Twig setup\n";
echo "- Cached parameter parsing avoids redundant file I/O\n";
echo "- Optimized script loading prevents duplicate operations\n";
echo "- Fast-path logging reduces overhead in production\n";
