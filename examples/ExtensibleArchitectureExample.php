<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shekel\VinPackage\Builders\VinDecoderServiceBuilder;
use Shekel\VinPackage\Cache\ArrayVinCache;
use Shekel\VinPackage\Contracts\VinDataSourceInterface;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;

echo "VIN Package - Extensible Architecture Examples\n";
echo "==============================================\n\n";

// Example 1: Basic multi-source setup
echo "Example 1: Basic Multi-Source Configuration\n";
echo "-------------------------------------------\n";

$cache = new ArrayVinCache(3600);

$service = (new VinDecoderServiceBuilder())
    ->setCache($cache)
    ->addNhtsaSource()      // Primary API source
    ->addLocalSource()      // Local fallback
    ->addClearVinSource()   // Enhanced details source
    ->setExecutionStrategy('fail_fast')  // Stop on first success
    ->setMergeStrategy('priority')       // Merge by source priority
    ->build();

$vin = '1HGCM82633A004352'; // Honda Accord example
echo "Decoding VIN: {$vin}\n";

$result = $service->decode($vin);
echo "Make: " . $result->getMake() . "\n";
echo "Model: " . $result->getModel() . "\n";
echo "Year: " . $result->getYear() . "\n";
$decodedBy = $result->getAdditionalValue('cache_metadata')['decoded_by'] ??
             $result->getAdditionalValue('decoded_by', 'unknown');
echo "Decoded by: " . $decodedBy . "\n\n";

// Example 2: Custom execution and merge strategies
echo "Example 2: Collect All Sources Strategy\n";
echo "--------------------------------------\n";

$serviceCollectAll = (new VinDecoderServiceBuilder())
    ->setCache($cache)
    ->addNhtsaSource()
    ->addLocalSource()
    ->addClearVinSource()
    ->setExecutionStrategy('collect_all')    // Query all sources
    ->setMergeStrategy('best_effort')        // Best effort merge
    ->setConflictResolution('newest')        // Use newest data for conflicts
    ->build();

$result2 = $serviceCollectAll->decode($vin);
echo "Combined data from all sources:\n";
echo "Make: " . $result2->getMake() . "\n";
echo "Model: " . $result2->getModel() . "\n";
echo "Body Class: " . ($result2->getBodyStyle() ?: 'N/A') . "\n";
echo "Engine: " . ($result2->getEngine() ?: 'N/A') . "\n\n";

// Example 3: Field-specific source priorities
echo "Example 3: Custom Field Priorities\n";
echo "----------------------------------\n";

$servicePriorities = (new VinDecoderServiceBuilder())
    ->setCache($cache)
    ->addNhtsaSource()
    ->addLocalSource()
    ->addClearVinSource()
    ->setExecutionStrategy('collect_all')
    ->setMergeStrategy('priority')
    // Prefer ClearVin for detailed specs, NHTSA for official data
    ->setFieldPriority('engine', ['clearvin', 'nhtsa_api', 'local'])
    ->setFieldPriority('body_style', ['clearvin', 'nhtsa_api', 'local'])
    ->setFieldPriority('make', ['nhtsa_api', 'clearvin', 'local'])
    ->setFieldPriority('model', ['nhtsa_api', 'clearvin', 'local'])
    ->build();

$result3 = $servicePriorities->decode($vin);
echo "Data with custom field priorities:\n";
echo "Make: " . $result3->getMake() . " (prioritized from NHTSA)\n";
echo "Model: " . $result3->getModel() . " (prioritized from NHTSA)\n";
echo "Body Class: " . ($result3->getBodyStyle() ?: 'N/A') . " (prioritized from ClearVin)\n\n";

// Example 4: Disabling and enabling sources
echo "Example 4: Source Management\n";
echo "----------------------------\n";

$serviceManaged = (new VinDecoderServiceBuilder())
    ->setCache($cache)
    ->addNhtsaSource()
    ->addLocalSource()
    ->addClearVinSource()
    ->disableSource('clearvin')  // Disable ClearVin source
    ->setExecutionStrategy('collect_all')
    ->build();

$result4 = $serviceManaged->decode($vin);
echo "Decoding with ClearVin disabled:\n";
echo "Make: " . $result4->getMake() . "\n";
echo "Sources used: NHTSA API + Local only\n\n";

// Example 5: Custom data source implementation
echo "Example 5: Custom Data Source\n";
echo "-----------------------------\n";

// Create a mock custom data source
class MockCustomDataSource implements VinDataSourceInterface
{
    private bool $enabled = true;

    public function getName(): string
    {
        return 'mock_custom';
    }

    public function getPriority(): int
    {
        return 1; // Highest priority
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function decode(string $vin): VinDataSourceResult
    {
        // Mock implementation - always returns success with custom data
        $mockData = [
            'make' => 'CUSTOM MAKE',
            'model' => 'CUSTOM MODEL',
            'year' => '2023',
            'custom_field' => 'This data comes from custom source',
            'decoded_by' => 'mock_custom',
        ];

        return new VinDataSourceResult(
            true,
            $mockData,
            $this->getName(),
            null, // No error message
            [
                'execution_time' => 0.1,
                'attempts' => 1,
                'message' => 'Mock decode successful'
            ]
        );
    }


    public function canHandle(string $vin): bool
    {
        return true; // Mock can handle any VIN
    }

    public function getSourceType(): string
    {
        return 'mock';
    }
}

$serviceWithCustom = (new VinDecoderServiceBuilder())
    ->setCache($cache)
    ->addCustomSource(new MockCustomDataSource()) // Highest priority
    ->addNhtsaSource()
    ->addLocalSource()
    ->setExecutionStrategy('fail_fast') // Will use custom source first
    ->build();

$result5 = $serviceWithCustom->decode($vin);
echo "Using custom data source (highest priority):\n";
echo "Make: " . $result5->getMake() . "\n";
echo "Model: " . $result5->getModel() . "\n";
echo "Custom Field: " . ($result5->toArray()['custom_field'] ?? 'N/A') . "\n\n";

// Example 6: Performance comparison
echo "Example 6: Performance Analysis\n";
echo "------------------------------\n";

$testVins = [
    '1HGCM82633A004352', // Honda
    'WVWZZZ3BZWE689725', // Volkswagen
    '1FTFW1ET5DFC10312'  // Ford
];

echo "Comparing fail_fast vs collect_all strategies:\n\n";

foreach (['fail_fast', 'collect_all'] as $strategy) {
    echo "Strategy: {$strategy}\n";

    $perfService = (new VinDecoderServiceBuilder())
        ->setCache(new ArrayVinCache(3600)) // Fresh cache for each test
        ->addNhtsaSource()
        ->addLocalSource()
        ->setExecutionStrategy($strategy)
        ->build();

    $totalTime = 0;
    foreach ($testVins as $testVin) {
        $start = microtime(true);
        $perfService->decode($testVin);
        $end = microtime(true);
        $totalTime += ($end - $start);
    }

    echo "Total time for 3 VINs: " . round($totalTime * 1000, 2) . " ms\n";
    echo "Average per VIN: " . round(($totalTime / count($testVins)) * 1000, 2) . " ms\n\n";
}

// Example 7: Error handling and fallback
echo "Example 7: Error Handling with Fallback\n";
echo "---------------------------------------\n";

$reliableService = (new VinDecoderServiceBuilder())
    ->addNhtsaSource('https://invalid-url.example.com') // This will fail
    ->addLocalSource() // This will be the fallback
    ->setExecutionStrategy('fail_fast')
    ->build();

try {
    $result7 = $reliableService->decode($vin);
    echo "Fallback successful!\n";
    echo "Make: " . $result7->getMake() . "\n";
    $decodedBy7 = $result7->getAdditionalValue('cache_metadata')['decoded_by'] ??
              $result7->getAdditionalValue('decoded_by', 'unknown');
    echo "Decoded by: " . $decodedBy7 . "\n";
    echo "This shows graceful fallback from failed API to local decoding.\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

echo "Extensible Architecture Demo Complete!\n";
echo "=====================================\n";
echo "\nKey Features Demonstrated:\n";
echo "• Multiple data sources (NHTSA API, Local, ClearVin)\n";
echo "• Execution strategies (fail_fast, collect_all)\n";
echo "• Merge strategies (priority, best_effort)\n";
echo "• Custom field priorities\n";
echo "• Source management (enable/disable)\n";
echo "• Custom data source implementation\n";
echo "• Performance optimization\n";
echo "• Error handling and fallback\n";
echo "• Caching integration\n";
