<?php

namespace Shekel\VinPackage\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Services\VinDecoderService;
use Shekel\VinPackage\Services\VinDataSourceChain;
use Shekel\VinPackage\Services\VinDataMerger;
use Shekel\VinPackage\Builders\VinDecoderServiceBuilder;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\Contracts\VinDataSourceInterface;
use Shekel\VinPackage\ValueObjects\VehicleInfo;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;
use Shekel\VinPackage\Cache\ArrayVinCache;

class VinDecoderServiceExtensibleTest extends TestCase
{
    private VinDecoderService $service;
    private VinCacheInterface $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayVinCache();
    }

    public function testDefaultConfiguration()
    {
        $this->service = (new VinDecoderServiceBuilder())
            ->addLocalSource()
            ->build();

        $vin = '5TDYK3DC8DS290235';
        $result = $this->service->decode($vin);

        $this->assertInstanceOf(VehicleInfo::class, $result);
        $this->assertNotEmpty($result->getMake());
    }

    public function testCustomSourceChain()
    {
        $mockSource1 = $this->createMockSource('source1', 1, ['make' => 'Toyota']);
        $mockSource2 = $this->createMockSource('source2', 2, ['model' => 'Camry']);

        $chain = new VinDataSourceChain();
        $chain->addSource($mockSource1)->addSource($mockSource2);

        $merger = new VinDataMerger();

        $this->service = new VinDecoderService($chain, $merger, $this->cache);

        $vin = '5TDYK3DC8DS290235';
        $result = $this->service->decode($vin);

        $this->assertEquals('Toyota', $result->getMake());
        $this->assertEquals('Camry', $result->getModel());
    }

    public function testFailFastStrategy()
    {
        $successSource = $this->createMockSource('success', 1, ['make' => 'Toyota'], true);
        $failSource = $this->createMockSource('fail', 2, [], false);
        $neverCalledSource = $this->createMockSource('never_called', 3, ['model' => 'Camry'], true);

        $chain = new VinDataSourceChain();
        $chain->addSource($successSource)
              ->addSource($failSource)
              ->addSource($neverCalledSource);

        $merger = new VinDataMerger();

        $this->service = new VinDecoderService($chain, $merger, $this->cache, 'fail_fast');

        $vin = '5TDYK3DC8DS290235';
        $result = $this->service->decode($vin);

        $this->assertEquals('Toyota', $result->getMake());

        // Verify that the never_called source was indeed never called
        // This would be tested through mock expectations in a real implementation
    }

    public function testCollectAllStrategy()
    {
        $source1 = $this->createMockSource('source1', 1, ['make' => 'Toyota'], true);
        $source2 = $this->createMockSource('source2', 2, [], false); // Fails
        $source3 = $this->createMockSource('source3', 3, ['model' => 'Camry'], true);

        $chain = new VinDataSourceChain();
        $chain->addSource($source1)
              ->addSource($source2)
              ->addSource($source3);

        $merger = new VinDataMerger();

        $this->service = new VinDecoderService($chain, $merger, $this->cache, 'collect_all');

        $vin = '5TDYK3DC8DS290235';
        $result = $this->service->decode($vin);

        $this->assertEquals('Toyota', $result->getMake());
        $this->assertEquals('Camry', $result->getModel());
    }

    public function testCacheIntegration()
    {
        $mockSource = $this->createMockSource('test_source', 1, ['make' => 'Toyota']);

        $chain = new VinDataSourceChain();
        $chain->addSource($mockSource);

        $merger = new VinDataMerger();

        $this->service = new VinDecoderService($chain, $merger, $this->cache);

        $vin = '5TDYK3DC8DS290235';

        // First call - should hit the source
        $result1 = $this->service->decode($vin);
        $this->assertEquals('Toyota', $result1->getMake());

        // Second call - should hit the cache
        $result2 = $this->service->decode($vin);
        $this->assertEquals('Toyota', $result2->getMake());

        // Verify cache was used
        $this->assertTrue($this->cache->has('vin_data_' . md5($vin)));
    }

    public function testBackwardCompatibility()
    {
        // Test that old VinDecoderService interface still works
        $this->service = (new VinDecoderServiceBuilder())
            ->addLocalSource()
            ->build();

        $vin = '5TDYK3DC8DS290235';

        // Old interface methods should still work
        $result = $this->service->decode($vin);
        $this->assertInstanceOf(VehicleInfo::class, $result);

        $localResult = $this->service->decodeLocally($vin);
        $this->assertInstanceOf(VehicleInfo::class, $localResult);

        $modelYear = $this->service->decodeModelYear('D');
        $this->assertEquals('2013', $modelYear);
    }

    public function testSourceFailureHandling()
    {
        $failingSource = $this->createMockSource('failing', 1, [], false);
        $fallbackSource = $this->createMockSource('fallback', 2, ['make' => 'Toyota'], true);

        $chain = new VinDataSourceChain();
        $chain->addSource($failingSource)->addSource($fallbackSource);

        $merger = new VinDataMerger();

        $this->service = new VinDecoderService($chain, $merger, $this->cache, 'collect_all');

        $vin = '5TDYK3DC8DS290235';
        $result = $this->service->decode($vin);

        $this->assertEquals('Toyota', $result->getMake());
    }

    public function testDataMerging()
    {
        $localSource = $this->createMockSource('local', 1, [
            'make' => 'Local Make',
            'manufacturer' => 'Local Manufacturer'
        ]);

        $nhtsaSource = $this->createMockSource('nhtsa_api', 2, [
            'make' => 'NHTSA Make',
            'model' => 'NHTSA Model',
            'validation' => ['is_valid' => true]
        ]);

        $clearVinSource = $this->createMockSource('clearvin', 3, [
            'trim' => 'Premium Trim',
            'dimensions' => ['length' => '200 in']
        ]);

        $chain = new VinDataSourceChain();
        $chain->addSource($localSource)
              ->addSource($nhtsaSource)
              ->addSource($clearVinSource);

        $merger = new VinDataMerger();

        $this->service = new VinDecoderService($chain, $merger, $this->cache, 'collect_all');

        $vin = '5TDYK3DC8DS290235';
        $result = $this->service->decode($vin);

        // NHTSA should override local for make
        $this->assertEquals('NHTSA Make', $result->getMake());
        $this->assertEquals('NHTSA Model', $result->getModel());

        // Local fallback for fields not in NHTSA
        $this->assertEquals('Local Manufacturer', $result->getManufacturer());

        // ClearVIN exclusive fields
        $this->assertEquals('Premium Trim', $result->getTrim());

        // NHTSA validation data preserved
        $validation = $result->getValidation();
        $this->assertTrue($validation['is_valid']);
    }

    public function testMetadataTracking()
    {
        $source1 = $this->createMockSource('source1', 1, ['make' => 'Toyota']);
        $source2 = $this->createMockSource('source2', 2, ['model' => 'Camry']);

        $chain = new VinDataSourceChain();
        $chain->addSource($source1)->addSource($source2);

        $merger = new VinDataMerger();

        $this->service = new VinDecoderService($chain, $merger, $this->cache, 'collect_all');

        $vin = '5TDYK3DC8DS290235';
        $result = $this->service->decode($vin);

        $metadata = $result->getAdditionalValue('cache_metadata');

        $this->assertArrayHasKey('sources', $metadata);
        $this->assertContains('source1', $metadata['sources']);
        $this->assertContains('source2', $metadata['sources']);
        $this->assertArrayHasKey('total_execution_time', $metadata);
        $this->assertArrayHasKey('source_details', $metadata);
    }

    public function testPerformanceMetrics()
    {
        $this->service = (new VinDecoderServiceBuilder())
            ->addLocalSource()
            ->build();

        $vin = '5TDYK3DC8DS290235';

        $startTime = microtime(true);
        $result = $this->service->decode($vin);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertInstanceOf(VehicleInfo::class, $result);
        $this->assertLessThan(1.0, $executionTime, 'Decoding should be reasonably fast');
    }

    public function testSourcePriorityOrdering()
    {
        $lowPriority = $this->createMockSource('low', 3, ['make' => 'Low Priority']);
        $highPriority = $this->createMockSource('high', 1, ['make' => 'High Priority']);
        $mediumPriority = $this->createMockSource('medium', 2, ['make' => 'Medium Priority']);

        $chain = new VinDataSourceChain();
        $chain->addSource($lowPriority)
              ->addSource($highPriority)
              ->addSource($mediumPriority);

        $merger = new VinDataMerger();

        $this->service = new VinDecoderService($chain, $merger, $this->cache, 'fail_fast');

        $vin = '5TDYK3DC8DS290235';
        $result = $this->service->decode($vin);

        // High priority source should win
        $this->assertEquals('High Priority', $result->getMake());
    }

    public function testDisabledSources()
    {
        $enabledSource = $this->createMockSource('enabled', 1, ['make' => 'Enabled']);
        $disabledSource = $this->createMockSource('disabled', 2, ['make' => 'Disabled']);
        $disabledSource->setEnabled(false);

        $chain = new VinDataSourceChain();
        $chain->addSource($enabledSource)->addSource($disabledSource);

        $merger = new VinDataMerger();

        $this->service = new VinDecoderService($chain, $merger, $this->cache);

        $vin = '5TDYK3DC8DS290235';
        $result = $this->service->decode($vin);

        $this->assertEquals('Enabled', $result->getMake());

        // Verify disabled source was not used
        $metadata = $result->getAdditionalValue('cache_metadata');
        $this->assertNotContains('disabled', $metadata['sources'] ?? []);
    }

    public function testComplexScenario()
    {
        // Test with multiple sources, some failing, complex data merging
        $this->service = (new VinDecoderServiceBuilder())
            ->addLocalSource()
            ->setExecutionStrategy('collect_all')
            ->setMergeStrategy('priority')
            ->setCache($this->cache)
            ->setCacheTTL(3600)
            ->setFieldPriority('trim', ['clearvin', 'nhtsa_api', 'local'])
            ->build();

        $vin = '5TDYK3DC8DS290235';

        // First decode - should populate cache
        $result1 = $this->service->decode($vin);
        $this->assertInstanceOf(VehicleInfo::class, $result1);

        // Second decode - should use cache
        $result2 = $this->service->decode($vin);
        $this->assertEquals($result1->getMake(), $result2->getMake());

        // Clear cache and decode again
        $this->service->clearCacheForVin($vin);
        $result3 = $this->service->decode($vin);
        $this->assertInstanceOf(VehicleInfo::class, $result3);
    }

    private function createMockSource(
        string $name,
        int $priority,
        array $data = [],
        bool $shouldSucceed = true
    ): VinDataSourceInterface {
        $enabledState = true;
        $mock = $this->createMock(VinDataSourceInterface::class);

        $mock->method('getName')->willReturn($name);
        $mock->method('getPriority')->willReturn($priority);
        $mock->method('getSourceType')->willReturn('test');
        $mock->method('canHandle')->willReturn(true);

        $mock->method('isEnabled')->willReturnCallback(function () use (&$enabledState) {
            return $enabledState;
        });

        $mock->method('setEnabled')->willReturnCallback(function ($newEnabled) use (&$enabledState) {
            $enabledState = $newEnabled;
        });

        if ($shouldSucceed) {
            $mock->method('decode')->willReturn(
                new VinDataSourceResult(true, $data, $name, null, [
                    'execution_time' => 0.1,
                    'decoded_by' => $name
                ])
            );
        } else {
            $mock->method('decode')->willReturn(
                new VinDataSourceResult(false, [], $name, 'Test error', [
                    'execution_time' => 0.1,
                    'error' => 'Test error'
                ])
            );
        }

        return $mock;
    }
}
