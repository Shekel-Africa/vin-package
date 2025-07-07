<?php

namespace Shekel\VinPackage\Tests\DataSources;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\DataSources\LocalVinDataSource;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;

class LocalVinDataSourceTest extends TestCase
{
    private LocalVinDataSource $dataSource;
    private VinCacheInterface $cache;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(VinCacheInterface::class);
        $this->dataSource = new LocalVinDataSource($this->cache);
    }

    public function testGetName()
    {
        $this->assertEquals('local', $this->dataSource->getName());
    }

    public function testGetPriority()
    {
        $this->assertEquals(1, $this->dataSource->getPriority());
    }

    public function testIsAlwaysEnabled()
    {
        $this->assertTrue($this->dataSource->isEnabled());

        // Local source should always remain enabled
        $this->dataSource->setEnabled(false);
        $this->assertTrue($this->dataSource->isEnabled());
    }

    public function testCanHandleAnyVin()
    {
        $this->assertTrue($this->dataSource->canHandle('5TDYK3DC8DS290235'));
        $this->assertTrue($this->dataSource->canHandle('1HGBH41JXMN109186'));
        $this->assertTrue($this->dataSource->canHandle('INVALID_VIN_FORMAT'));
        $this->assertTrue($this->dataSource->canHandle(''));
    }

    public function testDecodeSuccess()
    {
        $vin = '5TDYK3DC8DS290235';
        $result = $this->dataSource->decode($vin);

        $this->assertInstanceOf(VinDataSourceResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('local', $result->getSource());
        $this->assertNotEmpty($result->getData());
        $this->assertNull($result->getErrorMessage());
    }

    public function testDecodeWithCache()
    {
        $vin = '5TDYK3DC8DS290235';
        $cacheKey = 'local_vin_' . md5($vin);

        $cachedData = [
            'make' => 'Toyota',
            'manufacturer' => 'Toyota Motor Corporation',
            'country' => 'United States'
        ];

        $this->cache->expects($this->once())
            ->method('has')
            ->with($cacheKey)
            ->willReturn(true);

        $this->cache->expects($this->once())
            ->method('get')
            ->with($cacheKey)
            ->willReturn($cachedData);

        $result = $this->dataSource->decode($vin);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals($cachedData, $result->getData());
    }

    public function testDecodeWithoutCache()
    {
        $vin = '5TDYK3DC8DS290235';
        $cacheKey = 'local_vin_' . md5($vin);

        $this->cache->expects($this->once())
            ->method('has')
            ->with($cacheKey)
            ->willReturn(false);

        $this->cache->expects($this->once())
            ->method('set')
            ->with($cacheKey, $this->anything(), $this->anything())
            ->willReturn(true);

        $result = $this->dataSource->decode($vin);

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getData());
    }

    public function testGetSourceType()
    {
        $this->assertEquals('local', $this->dataSource->getSourceType());
    }

    public function testNeverFails()
    {
        // Test various edge cases - local source should never fail
        $testVins = [
            '5TDYK3DC8DS290235',
            '1HGBH41JXMN109186',
            'INVALID_VIN',
            '',
            '12345',
            'ABCDEFGHIJKLMNOPQ'
        ];

        foreach ($testVins as $vin) {
            $result = $this->dataSource->decode($vin);
            $this->assertTrue($result->isSuccess(), "Local source failed for VIN: {$vin}");
        }
    }

    public function testExtractWMI()
    {
        $vin = '5TDYK3DC8DS290235';
        $result = $this->dataSource->decode($vin);

        $data = $result->getData();
        $this->assertEquals('5TD', $data['additional_info']['WMI']);
    }

    public function testExtractVDS()
    {
        $vin = '5TDYK3DC8DS290235';
        $result = $this->dataSource->decode($vin);

        $data = $result->getData();
        $this->assertEquals('YK3DC8', $data['additional_info']['VDS']);
    }

    public function testExtractVIS()
    {
        $vin = '5TDYK3DC8DS290235';
        $result = $this->dataSource->decode($vin);

        $data = $result->getData();
        $this->assertEquals('DS290235', $data['additional_info']['VIS']);
    }

    public function testManufacturerLookup()
    {
        $vin = '5TDYK3DC8DS290235'; // Toyota
        $result = $this->dataSource->decode($vin);

        $data = $result->getData();
        $this->assertNotEmpty($data['manufacturer']);
        $this->assertStringContainsString('Toyota', $data['manufacturer']);
    }

    public function testCountryLookup()
    {
        $vin = '5TDYK3DC8DS290235'; // Starts with 5 (United States)
        $result = $this->dataSource->decode($vin);

        $data = $result->getData();
        $this->assertNotEmpty($data['country']);
        $this->assertEquals('United States', $data['country']);
    }

    public function testModelYear()
    {
        $vin = '5TDYK3DC8DS290235'; // 10th character is D (2013)
        $result = $this->dataSource->decode($vin);

        $data = $result->getData();
        $this->assertEquals('2013', $data['year']);
    }

    public function testUnknownManufacturer()
    {
        $vin = 'XXXYK3DC8DS290235'; // Unknown WMI
        $result = $this->dataSource->decode($vin);

        $data = $result->getData();
        $this->assertEquals('Unknown', $data['manufacturer']);
    }

    public function testInvalidVinHandling()
    {
        $vin = '12345'; // Too short
        $result = $this->dataSource->decode($vin);

        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertEquals('Unknown', $data['manufacturer']);
        $this->assertEquals('United States', $data['country']); // '1' maps to United States after padding
    }

    public function testMetadata()
    {
        $vin = '5TDYK3DC8DS290235';
        $result = $this->dataSource->decode($vin);

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('decoded_by', $metadata);
        $this->assertEquals('local_decoder', $metadata['decoded_by']);
        $this->assertArrayHasKey('decoding_date', $metadata);
        $this->assertArrayHasKey('execution_time', $metadata);
    }

    public function testSetCacheTTL()
    {
        $newTTL = 3600; // 1 hour
        $this->dataSource->setCacheTTL($newTTL);

        $vin = '5TDYK3DC8DS290235';

        $this->cache->expects($this->once())
            ->method('set')
            ->with($this->anything(), $this->anything(), $newTTL)
            ->willReturn(true);

        $this->dataSource->decode($vin);
    }

    public function testAddCustomManufacturer()
    {
        $customWMI = 'XYZ';
        $customManufacturer = 'Custom Motors';

        $this->dataSource->addManufacturerCode($customWMI, $customManufacturer);

        $vin = 'XYZYK3DC8DS290235';
        $result = $this->dataSource->decode($vin);

        $data = $result->getData();
        $this->assertEquals($customManufacturer, $data['manufacturer']);
    }

    public function testNoCache()
    {
        $dataSourceWithoutCache = new LocalVinDataSource();
        $vin = '5TDYK3DC8DS290235';

        $result = $dataSourceWithoutCache->decode($vin);

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getData());
    }

    public function testPerformance()
    {
        $vin = '5TDYK3DC8DS290235';

        $startTime = microtime(true);
        $result = $this->dataSource->decode($vin);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertTrue($result->isSuccess());
        $this->assertLessThan(0.1, $executionTime, 'Local decoding should be fast');
    }
}
