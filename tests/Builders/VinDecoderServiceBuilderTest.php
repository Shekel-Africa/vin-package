<?php

namespace Shekel\VinPackage\Tests\Builders;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Builders\VinDecoderServiceBuilder;
use Shekel\VinPackage\Services\VinDecoderService;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\Contracts\VinDataSourceInterface;
use Shekel\VinPackage\DataSources\LocalVinDataSource;
use Shekel\VinPackage\DataSources\NhtsaApiDataSource;
use Shekel\VinPackage\DataSources\ClearVinDataSource;

class VinDecoderServiceBuilderTest extends TestCase
{
    private VinDecoderServiceBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new VinDecoderServiceBuilder();
    }

    public function testDefaultBuilder()
    {
        $service = $this->builder->build();

        $this->assertInstanceOf(VinDecoderService::class, $service);

        // Should have default sources (local, nhtsa)
        $sources = $service->getDataSources();
        $this->assertCount(2, $sources);

        $sourceNames = array_map(fn($source) => $source->getName(), $sources);
        $this->assertContains('local', $sourceNames);
        $this->assertContains('nhtsa_api', $sourceNames);
    }

    public function testAddLocalSource()
    {
        $result = $this->builder->addLocalSource();

        $this->assertInstanceOf(VinDecoderServiceBuilder::class, $result);

        $service = $this->builder->build();
        $sources = $service->getDataSources();

        $localSources = array_filter($sources, fn($source) => $source->getName() === 'local');
        $this->assertCount(1, $localSources);
    }

    public function testAddNhtsaSource()
    {
        $apiUrl = 'https://custom-nhtsa.api.com/';
        $timeout = 30;

        $result = $this->builder->addNhtsaSource($apiUrl, $timeout);

        $this->assertInstanceOf(VinDecoderServiceBuilder::class, $result);

        $service = $this->builder->build();
        $sources = $service->getDataSources();

        $nhtsaSources = array_filter($sources, fn($source) => $source->getName() === 'nhtsa_api');
        $this->assertCount(1, $nhtsaSources);

        $nhtsaSource = reset($nhtsaSources);
        $this->assertEquals($apiUrl, $nhtsaSource->getApiBaseUrl());
        $this->assertEquals($timeout, $nhtsaSource->getTimeout());
    }

    public function testAddClearVinSource()
    {
        $timeout = 20;

        $result = $this->builder->addClearVinSource($timeout);

        $this->assertInstanceOf(VinDecoderServiceBuilder::class, $result);

        $service = $this->builder->build();
        $sources = $service->getDataSources();

        $clearVinSources = array_filter($sources, fn($source) => $source->getName() === 'clearvin');
        $this->assertCount(1, $clearVinSources);

        $clearVinSource = reset($clearVinSources);
        $this->assertEquals($timeout, $clearVinSource->getTimeout());
    }

    public function testAddCustomSource()
    {
        $customSource = $this->createMock(VinDataSourceInterface::class);
        $customSource->method('getName')->willReturn('custom_source');
        $customSource->method('getPriority')->willReturn(10);

        $result = $this->builder->addCustomSource($customSource);

        $this->assertInstanceOf(VinDecoderServiceBuilder::class, $result);

        $service = $this->builder->build();
        $sources = $service->getDataSources();

        $customSources = array_filter($sources, fn($source) => $source->getName() === 'custom_source');
        $this->assertCount(1, $customSources);
    }

    public function testSetExecutionStrategy()
    {
        $result = $this->builder->setExecutionStrategy('collect_all');

        $this->assertInstanceOf(VinDecoderServiceBuilder::class, $result);

        $service = $this->builder->build();
        $this->assertEquals('collect_all', $service->getExecutionStrategy());
    }

    public function testSetMergeStrategy()
    {
        $result = $this->builder->setMergeStrategy('best_effort');

        $this->assertInstanceOf(VinDecoderServiceBuilder::class, $result);

        $service = $this->builder->build();
        $this->assertEquals('best_effort', $service->getMergeStrategy());
    }

    public function testSetCache()
    {
        $cache = $this->createMock(VinCacheInterface::class);

        $result = $this->builder->setCache($cache);

        $this->assertInstanceOf(VinDecoderServiceBuilder::class, $result);

        $service = $this->builder->build();
        $this->assertSame($cache, $service->getCache());
    }

    public function testFluentInterface()
    {
        $cache = $this->createMock(VinCacheInterface::class);

        $service = $this->builder
            ->addLocalSource()
            ->addNhtsaSource('https://api.example.com/', 25)
            ->addClearVinSource(15)
            ->setExecutionStrategy('fail_fast')
            ->setMergeStrategy('priority')
            ->setCache($cache)
            ->build();

        $this->assertInstanceOf(VinDecoderService::class, $service);
        $this->assertEquals('fail_fast', $service->getExecutionStrategy());
        $this->assertEquals('priority', $service->getMergeStrategy());
        $this->assertSame($cache, $service->getCache());

        $sources = $service->getDataSources();
        $this->assertCount(3, $sources);

        $sourceNames = array_map(fn($source) => $source->getName(), $sources);
        $this->assertContains('local', $sourceNames);
        $this->assertContains('nhtsa_api', $sourceNames);
        $this->assertContains('clearvin', $sourceNames);
    }

    public function testBuildMultipleTimes()
    {
        $this->builder->addLocalSource();

        $service1 = $this->builder->build();
        $service2 = $this->builder->build();

        $this->assertInstanceOf(VinDecoderService::class, $service1);
        $this->assertInstanceOf(VinDecoderService::class, $service2);
        $this->assertNotSame($service1, $service2); // Should create new instances
    }

    public function testSourceOrdering()
    {
        $service = $this->builder
            ->addClearVinSource() // Priority 3
            ->addLocalSource()    // Priority 1
            ->addNhtsaSource()    // Priority 2
            ->build();

        $sources = $service->getDataSources();

        // Should be ordered by priority (1, 2, 3)
        $this->assertEquals('local', $sources[0]->getName());
        $this->assertEquals('nhtsa_api', $sources[1]->getName());
        $this->assertEquals('clearvin', $sources[2]->getName());
    }

    public function testCustomSourceOrdering()
    {
        $customSource = $this->createMock(VinDataSourceInterface::class);
        $customSource->method('getName')->willReturn('custom');
        $customSource->method('getPriority')->willReturn(0); // Highest priority

        $service = $this->builder
            ->addLocalSource()     // Priority 1
            ->addCustomSource($customSource) // Priority 0
            ->addNhtsaSource()     // Priority 2
            ->build();

        $sources = $service->getDataSources();

        // Custom source should be first
        $this->assertEquals('custom', $sources[0]->getName());
        $this->assertEquals('local', $sources[1]->getName());
        $this->assertEquals('nhtsa_api', $sources[2]->getName());
    }

    public function testSetCacheTTL()
    {
        $ttl = 7200; // 2 hours

        $result = $this->builder->setCacheTTL($ttl);

        $this->assertInstanceOf(VinDecoderServiceBuilder::class, $result);

        $service = $this->builder->build();
        $this->assertEquals($ttl, $service->getCacheTTL());
    }

    public function testEnableSourceByName()
    {
        $service = $this->builder
            ->addLocalSource()
            ->addNhtsaSource()
            ->disableSource('nhtsa_api')
            ->enableSource('nhtsa_api')
            ->build();

        $sources = $service->getDataSources();
        $nhtsaSource = array_filter($sources, fn($source) => $source->getName() === 'nhtsa_api');
        $nhtsaSource = reset($nhtsaSource);

        $this->assertTrue($nhtsaSource->isEnabled());
    }

    public function testDisableSourceByName()
    {
        $service = $this->builder
            ->addLocalSource()
            ->addNhtsaSource()
            ->disableSource('nhtsa_api')
            ->build();

        $sources = $service->getDataSources();
        $nhtsaSource = array_filter($sources, fn($source) => $source->getName() === 'nhtsa_api');
        $nhtsaSource = reset($nhtsaSource);

        $this->assertFalse($nhtsaSource->isEnabled());
    }

    public function testRemoveSourceByName()
    {
        $service = $this->builder
            ->addLocalSource()
            ->addNhtsaSource()
            ->addClearVinSource()
            ->removeSource('nhtsa_api')
            ->build();

        $sources = $service->getDataSources();
        $sourceNames = array_map(fn($source) => $source->getName(), $sources);

        $this->assertCount(2, $sources);
        $this->assertContains('local', $sourceNames);
        $this->assertContains('clearvin', $sourceNames);
        $this->assertNotContains('nhtsa_api', $sourceNames);
    }

    public function testSetFieldPriority()
    {
        $result = $this->builder->setFieldPriority('trim', ['clearvin', 'nhtsa_api', 'local']);

        $this->assertInstanceOf(VinDecoderServiceBuilder::class, $result);

        $service = $this->builder->build();
        $fieldPriorities = $service->getFieldPriorities();

        $this->assertEquals(['clearvin', 'nhtsa_api', 'local'], $fieldPriorities['trim']);
    }

    public function testSetConflictResolution()
    {
        $result = $this->builder->setConflictResolution('newest');

        $this->assertInstanceOf(VinDecoderServiceBuilder::class, $result);

        $service = $this->builder->build();
        $this->assertEquals('newest', $service->getConflictResolution());
    }

    public function testValidateConfiguration()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid execution strategy');

        $this->builder
            ->setExecutionStrategy('invalid_strategy')
            ->build();
    }

    public function testValidateMergeStrategy()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid merge strategy');

        $this->builder
            ->setMergeStrategy('invalid_merge')
            ->build();
    }

    public function testMinimalConfiguration()
    {
        $service = $this->builder
            ->addLocalSource()
            ->build();

        $this->assertInstanceOf(VinDecoderService::class, $service);

        $sources = $service->getDataSources();
        $this->assertCount(1, $sources);
        $this->assertEquals('local', $sources[0]->getName());
    }

    public function testMaximalConfiguration()
    {
        $cache = $this->createMock(VinCacheInterface::class);
        $customSource = $this->createMock(VinDataSourceInterface::class);
        $customSource->method('getName')->willReturn('custom');
        $customSource->method('getPriority')->willReturn(5);

        $service = $this->builder
            ->addLocalSource()
            ->addNhtsaSource('https://custom.api.com/', 30)
            ->addClearVinSource(20)
            ->addCustomSource($customSource)
            ->setExecutionStrategy('collect_all')
            ->setMergeStrategy('complete')
            ->setCache($cache)
            ->setCacheTTL(3600)
            ->setFieldPriority('trim', ['clearvin', 'custom'])
            ->setConflictResolution('newest')
            ->build();

        $this->assertInstanceOf(VinDecoderService::class, $service);
        $this->assertCount(4, $service->getDataSources());
        $this->assertEquals('collect_all', $service->getExecutionStrategy());
        $this->assertEquals('complete', $service->getMergeStrategy());
        $this->assertSame($cache, $service->getCache());
        $this->assertEquals(3600, $service->getCacheTTL());
        $this->assertEquals('newest', $service->getConflictResolution());
    }
}
