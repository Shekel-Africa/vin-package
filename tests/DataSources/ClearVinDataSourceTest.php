<?php

namespace Shekel\VinPackage\Tests\DataSources;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\DataSources\ClearVinDataSource;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\RequestInterface;

class ClearVinDataSourceTest extends TestCase
{
    private ClearVinDataSource $dataSource;
    private VinCacheInterface $cache;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(VinCacheInterface::class);
        $this->mockHandler = new MockHandler();

        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $this->dataSource = new ClearVinDataSource($httpClient, $this->cache);
    }

    public function testGetName()
    {
        $this->assertEquals('clearvin', $this->dataSource->getName());
    }

    public function testGetPriority()
    {
        $this->assertEquals(3, $this->dataSource->getPriority());
    }

    public function testIsEnabledByDefault()
    {
        $this->assertTrue($this->dataSource->isEnabled());
    }

    public function testSetEnabled()
    {
        $this->dataSource->setEnabled(false);
        $this->assertFalse($this->dataSource->isEnabled());

        $this->dataSource->setEnabled(true);
        $this->assertTrue($this->dataSource->isEnabled());
    }

    public function testCanHandleValidVin()
    {
        $this->assertTrue($this->dataSource->canHandle('5TDYK3DC8DS290235'));
        $this->assertTrue($this->dataSource->canHandle('1HGBH41JXMN109186'));
    }

    public function testCannotHandleInvalidVin()
    {
        $this->assertFalse($this->dataSource->canHandle(''));
        $this->assertFalse($this->dataSource->canHandle('12345'));
        $this->assertFalse($this->dataSource->canHandle('5TDYK3DC8DS2902351')); // Too long
    }

    public function testMarkdownParsing()
    {
        $vin = '5TDYK3DC8DS290235';
        $mockMarkdown = $this->getSampleMarkdownResponse();

        $this->mockHandler->append(
            new Response(200, [], $mockMarkdown)
        );

        $result = $this->dataSource->decode($vin);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('clearvin', $result->getSource());

        $data = $result->getData();
        $this->assertEquals('Toyota', $data['make']);
        $this->assertEquals('Sienna', $data['model']);
        $this->assertEquals('2013', $data['year']);
        $this->assertEquals('XLE FWD 8-Passenger V6', $data['trim']);
        $this->assertEquals('3.5L V6 EFI DOHC 24V', $data['engine']);
        $this->assertEquals('SPORTS VAN', $data['body_style']);
        $this->assertEquals('UNITED STATES', $data['country']);
    }

    public function testSuccessfulScraping()
    {
        $vin = '5TDYK3DC8DS290235';
        $mockMarkdown = $this->getSampleMarkdownResponse();

        $this->mockHandler->append(
            new Response(200, [], $mockMarkdown)
        );

        $result = $this->dataSource->decode($vin);

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getData());
        $this->assertNull($result->getErrorMessage());
    }

    public function testFailedScraping()
    {
        $vin = '5TDYK3DC8DS290235';

        $this->mockHandler->append(
            new Response(404, [], 'Not Found')
        );

        $result = $this->dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('clearvin', $result->getSource());
        $this->assertStringContainsString('HTTP error', $result->getErrorMessage());
    }

    public function testTimeoutHandling()
    {
        $vin = '5TDYK3DC8DS290235';

        $this->mockHandler->append(
            new ConnectException(
                'Connection timeout',
                $this->createMock(RequestInterface::class)
            )
        );

        $result = $this->dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('clearvin', $result->getSource());
        $this->assertStringContainsString('Connection timeout', $result->getErrorMessage());
    }

    public function testInvalidMarkdown()
    {
        $vin = '5TDYK3DC8DS290235';
        $invalidMarkdown = 'This is not valid vehicle data markdown';

        $this->mockHandler->append(
            new Response(200, [], $invalidMarkdown)
        );

        $result = $this->dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('clearvin', $result->getSource());
        $this->assertStringContainsString('No vehicle data found', $result->getErrorMessage());
    }

    public function testDataExtraction()
    {
        $vin = '5TDYK3DC8DS290235';
        $mockMarkdown = $this->getSampleMarkdownResponse();

        $this->mockHandler->append(
            new Response(200, [], $mockMarkdown)
        );

        $result = $this->dataSource->decode($vin);
        $data = $result->getData();

        // Test dimensional data extraction
        $this->assertEquals('200.20 in', $data['dimensions']['length']);
        $this->assertEquals('78.10 in', $data['dimensions']['width']);
        $this->assertEquals('70.70 in', $data['dimensions']['height']);
        $this->assertEquals('119.30 in', $data['dimensions']['wheelbase']);

        // Test seating data
        $this->assertEquals(8, $data['seating']['standardSeating']);

        // Test pricing data
        $this->assertEquals('$33,360 USD', $data['pricing']['msrp']);
        $this->assertEquals('$30,691 USD', $data['pricing']['dealerInvoice']);

        // Test mileage data
        $this->assertEquals('18 miles/gallon', $data['mileage']['city']);
        $this->assertEquals('25 miles/gallon', $data['mileage']['highway']);
    }

    public function testFieldMapping()
    {
        $vin = '5TDYK3DC8DS290235';
        $mockMarkdown = $this->getSampleMarkdownResponse();

        $this->mockHandler->append(
            new Response(200, [], $mockMarkdown)
        );

        $result = $this->dataSource->decode($vin);
        $data = $result->getData();

        // Test field mapping to standard format
        $this->assertArrayHasKey('make', $data);
        $this->assertArrayHasKey('model', $data);
        $this->assertArrayHasKey('year', $data);
        $this->assertArrayHasKey('trim', $data);
        $this->assertArrayHasKey('engine', $data);
        $this->assertArrayHasKey('body_style', $data);
        $this->assertArrayHasKey('country', $data);
        $this->assertArrayHasKey('dimensions', $data);
        $this->assertArrayHasKey('seating', $data);
        $this->assertArrayHasKey('pricing', $data);
        $this->assertArrayHasKey('mileage', $data);
    }

    public function testGetSourceType()
    {
        $this->assertEquals('web', $this->dataSource->getSourceType());
    }

    public function testCaching()
    {
        $vin = '5TDYK3DC8DS290235';
        $cacheKey = 'clearvin_' . md5($vin);

        $cachedData = [
            'make' => 'Toyota',
            'model' => 'Sienna',
            'trim' => 'XLE Premium'
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

    public function testDisabledSource()
    {
        $this->dataSource->setEnabled(false);
        $vin = '5TDYK3DC8DS290235';

        $result = $this->dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('disabled', $result->getErrorMessage());
    }

    public function testCustomTimeout()
    {
        $timeout = 30;
        $dataSource = new ClearVinDataSource(null, null, $timeout);

        $this->assertEquals($timeout, $dataSource->getTimeout());
    }

    public function testEmptyResponse()
    {
        $vin = '5TDYK3DC8DS290235';

        $this->mockHandler->append(
            new Response(200, [], '')
        );

        $result = $this->dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Empty response', $result->getErrorMessage());
    }

    public function testPartialData()
    {
        $vin = '5TDYK3DC8DS290235';
        $partialMarkdown = '
# Vehicle Information

**VIN:** 5TDYK3DC8DS290235
**Year:** 2013
**Make:** Toyota
**Model:** Sienna
        ';

        $this->mockHandler->append(
            new Response(200, [], $partialMarkdown)
        );

        $result = $this->dataSource->decode($vin);

        $this->assertTrue($result->isSuccess());
        $data = $result->getData();

        $this->assertEquals('2013', $data['year']);
        $this->assertEquals('Toyota', $data['make']);
        $this->assertEquals('Sienna', $data['model']);

        // Fields not present should be null or empty
        $this->assertEmpty($data['trim'] ?? '');
        $this->assertEmpty($data['dimensions'] ?? []);
    }

    public function testMetadata()
    {
        $vin = '5TDYK3DC8DS290235';
        $mockMarkdown = $this->getSampleMarkdownResponse();

        $this->mockHandler->append(
            new Response(200, [], $mockMarkdown)
        );

        $result = $this->dataSource->decode($vin);

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('decoded_by', $metadata);
        $this->assertEquals('clearvin', $metadata['decoded_by']);
        $this->assertArrayHasKey('api_call_success', $metadata);
        $this->assertTrue($metadata['api_call_success']);
        $this->assertArrayHasKey('response_time', $metadata);
        $this->assertArrayHasKey('url', $metadata);
        $this->assertArrayHasKey('markdown_length', $metadata);
    }

    public function testClearCache()
    {
        $vin = '5TDYK3DC8DS290235';
        $cacheKey = 'clearvin_' . md5($vin);

        $this->cache->expects($this->once())
            ->method('delete')
            ->with($cacheKey)
            ->willReturn(true);

        $result = $this->dataSource->clearCache($vin);
        $this->assertTrue($result);
    }

    private function getSampleMarkdownResponse(): string
    {
        return '
# Vehicle Information

**VIN:** 5TDYK3DC8DS290235
**Year:** 2013
**Make:** Toyota
**Model:** Sienna
**Trim:** XLE FWD 8-Passenger V6
**Origin:** UNITED STATES
**Style:** SPORTS VAN
**Age:** 12 year(s)

## Mechanical
**Engine:** 3.5L V6 EFI DOHC 24V
**Wheel Drive:** FWD
**City Mileage:** 18 miles/gallon
**Highway Mileage:** 25 miles/gallon

## Dimensions
**Length:** 200.20 in
**Width:** 78.10 in
**Height:** 70.70 in
**Wheelbase:** 119.30 in

## Seating
**Standard Seating:** 8
**Passenger Volume:** N/A

## Pricing
**MSRP:** $33,360 USD
**Dealer Invoice:** $30,691 USD
        ';
    }
}
