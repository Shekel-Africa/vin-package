<?php

namespace Shekel\VinPackage\Tests\Services;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Services\VinDataSourceChain;
use Shekel\VinPackage\Contracts\VinDataSourceInterface;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;
use Shekel\VinPackage\ValueObjects\VinDataSourceChainResult;

class VinDataSourceChainTest extends TestCase
{
    private VinDataSourceChain $chain;

    protected function setUp(): void
    {
        $this->chain = new VinDataSourceChain();
    }

    public function testAddSource()
    {
        $source = $this->createMockSource('test_source', 1, true);

        $result = $this->chain->addSource($source);

        $this->assertInstanceOf(VinDataSourceChain::class, $result);
        $this->assertCount(1, $this->chain->getSources());
        $this->assertEquals('test_source', $this->chain->getSources()[0]->getName());
    }

    public function testRemoveSource()
    {
        $source1 = $this->createMockSource('source1', 1, true);
        $source2 = $this->createMockSource('source2', 2, true);

        $this->chain->addSource($source1);
        $this->chain->addSource($source2);

        $this->assertCount(2, $this->chain->getSources());

        $result = $this->chain->removeSource('source1');

        $this->assertInstanceOf(VinDataSourceChain::class, $result);
        $this->assertCount(1, $this->chain->getSources());
        $this->assertEquals('source2', $this->chain->getSources()[0]->getName());
    }

    public function testRemoveNonExistentSource()
    {
        $source = $this->createMockSource('existing_source', 1, true);
        $this->chain->addSource($source);

        $result = $this->chain->removeSource('nonexistent_source');

        $this->assertInstanceOf(VinDataSourceChain::class, $result);
        $this->assertCount(1, $this->chain->getSources());
    }

    public function testEnableSource()
    {
        $source = $this->createMockSource('test_source', 1, false);
        $this->chain->addSource($source);

        $result = $this->chain->enableSource('test_source');

        $this->assertInstanceOf(VinDataSourceChain::class, $result);
        $this->assertTrue($source->isEnabled());
    }

    public function testDisableSource()
    {
        $source = $this->createMockSource('test_source', 1, true);
        $this->chain->addSource($source);

        $result = $this->chain->disableSource('test_source');

        $this->assertInstanceOf(VinDataSourceChain::class, $result);
        $this->assertFalse($source->isEnabled());
    }

    public function testGetEnabledSources()
    {
        $enabledSource = $this->createMockSource('enabled', 1, true);
        $disabledSource = $this->createMockSource('disabled', 2, false);

        $this->chain->addSource($enabledSource);
        $this->chain->addSource($disabledSource);

        $enabledSources = $this->chain->getEnabledSources();

        $this->assertCount(1, $enabledSources);
        $this->assertEquals('enabled', $enabledSources[0]->getName());
    }

    public function testExecuteChainFailFast()
    {
        $successSource = $this->createMockSource('success_source', 1, true, true);
        $failSource = $this->createMockSource('fail_source', 2, true, false);

        $this->chain->addSource($successSource);
        $this->chain->addSource($failSource);

        $result = $this->chain->executeChain('5TDYK3DC8DS290235', 'fail_fast');

        $this->assertInstanceOf(VinDataSourceChainResult::class, $result);
        $this->assertTrue($result->hasSuccessfulResults());
        $this->assertCount(1, $result->getSuccessfulResults());
        $this->assertEquals('success_source', $result->getSuccessfulResults()[0]->getSource());
    }

    public function testExecuteChainCollectAll()
    {
        $successSource = $this->createMockSource('success_source', 1, true, true);
        $failSource = $this->createMockSource('fail_source', 2, true, false);

        $this->chain->addSource($successSource);
        $this->chain->addSource($failSource);

        $result = $this->chain->executeChain('5TDYK3DC8DS290235', 'collect_all');

        $this->assertInstanceOf(VinDataSourceChainResult::class, $result);
        $this->assertTrue($result->hasSuccessfulResults());
        $this->assertCount(1, $result->getSuccessfulResults());
        $this->assertCount(1, $result->getFailedResults());
        $this->assertEquals('success_source', $result->getSuccessfulResults()[0]->getSource());
        $this->assertEquals('fail_source', $result->getFailedResults()[0]->getSource());
    }

    public function testReorderSources()
    {
        $source1 = $this->createMockSource('source1', 1, true);
        $source2 = $this->createMockSource('source2', 2, true);
        $source3 = $this->createMockSource('source3', 3, true);

        $this->chain->addSource($source1);
        $this->chain->addSource($source2);
        $this->chain->addSource($source3);

        $result = $this->chain->reorderSources(['source3', 'source1', 'source2']);

        $this->assertInstanceOf(VinDataSourceChain::class, $result);

        $sources = $this->chain->getSources();
        $this->assertEquals('source3', $sources[0]->getName());
        $this->assertEquals('source1', $sources[1]->getName());
        $this->assertEquals('source2', $sources[2]->getName());
    }

    public function testPriorityOrdering()
    {
        $lowPriority = $this->createMockSource('low', 3, true);
        $highPriority = $this->createMockSource('high', 1, true);
        $mediumPriority = $this->createMockSource('medium', 2, true);

        $this->chain->addSource($lowPriority);
        $this->chain->addSource($highPriority);
        $this->chain->addSource($mediumPriority);

        $this->chain->sortByPriority();

        $sources = $this->chain->getSources();
        $this->assertEquals('high', $sources[0]->getName());
        $this->assertEquals('medium', $sources[1]->getName());
        $this->assertEquals('low', $sources[2]->getName());
    }

    public function testEmptyChain()
    {
        $result = $this->chain->executeChain('5TDYK3DC8DS290235');

        $this->assertInstanceOf(VinDataSourceChainResult::class, $result);
        $this->assertFalse($result->hasSuccessfulResults());
        $this->assertCount(0, $result->getSuccessfulResults());
        $this->assertCount(0, $result->getFailedResults());
    }

    public function testDisabledSources()
    {
        $disabledSource = $this->createMockSource('disabled', 1, false, true);
        $this->chain->addSource($disabledSource);

        $result = $this->chain->executeChain('5TDYK3DC8DS290235');

        $this->assertInstanceOf(VinDataSourceChainResult::class, $result);
        $this->assertFalse($result->hasSuccessfulResults());
        $this->assertCount(0, $result->getSuccessfulResults());
    }

    public function testGetSourceByName()
    {
        $source = $this->createMockSource('test_source', 1, true);
        $this->chain->addSource($source);

        $found = $this->chain->getSourceByName('test_source');
        $notFound = $this->chain->getSourceByName('nonexistent');

        $this->assertSame($source, $found);
        $this->assertNull($notFound);
    }

    public function testHasSource()
    {
        $source = $this->createMockSource('test_source', 1, true);
        $this->chain->addSource($source);

        $this->assertTrue($this->chain->hasSource('test_source'));
        $this->assertFalse($this->chain->hasSource('nonexistent'));
    }

    public function testFluentInterface()
    {
        $source1 = $this->createMockSource('source1', 1, true);
        $source2 = $this->createMockSource('source2', 2, true);

        $result = $this->chain
            ->addSource($source1)
            ->addSource($source2)
            ->enableSource('source1')
            ->disableSource('source2');

        $this->assertInstanceOf(VinDataSourceChain::class, $result);
        $this->assertCount(2, $this->chain->getSources());
        $this->assertTrue($source1->isEnabled());
        $this->assertFalse($source2->isEnabled());
    }

    private function createMockSource(
        string $name,
        int $priority,
        bool $enabled,
        bool $shouldSucceed = true
    ): VinDataSourceInterface {
        $enabledState = $enabled;
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
                new VinDataSourceResult(true, ['make' => 'Test'], $name)
            );
        } else {
            $mock->method('decode')->willReturn(
                new VinDataSourceResult(false, [], $name, 'Test error')
            );
        }

        return $mock;
    }
}
