<?php

namespace Shekel\VinPackage\Tests\DataSources;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\DataSources\NhtsaApiDataSource;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;

class NhtsaApiDataSourceTest extends TestCase
{
    private NhtsaApiDataSource $dataSource;
    private VinCacheInterface $cache;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(VinCacheInterface::class);
        $this->mockHandler = new MockHandler();

        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $this->dataSource = new NhtsaApiDataSource($httpClient, $this->cache);
    }

    public function testGetName()
    {
        $this->assertEquals('nhtsa_api', $this->dataSource->getName());
    }

    public function testGetPriority()
    {
        $this->assertEquals(2, $this->dataSource->getPriority());
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

    public function testSuccessfulApiCall()
    {
        $vin = '5TDYK3DC8DS290235';
        $mockResponse = [
            'Results' => [
                ['Variable' => 'Make', 'Value' => 'Toyota'],
                ['Variable' => 'Model', 'Value' => 'Sienna'],
                ['Variable' => 'Model Year', 'Value' => '2013'],
                ['Variable' => 'Trim', 'Value' => 'XLE FWD 8-Passenger V6'],
                ['Variable' => 'Error Code', 'Value' => '0'],
                ['Variable' => 'Error Text', 'Value' => '']
            ]
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($mockResponse))
        );

        $result = $this->dataSource->decode($vin);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('nhtsa_api', $result->getSource());

        $data = $result->getData();
        $this->assertEquals('Toyota', $data['make']);
        $this->assertEquals('Sienna', $data['model']);
        $this->assertEquals('2013', $data['year']);
        $this->assertEquals('XLE FWD 8-Passenger V6', $data['trim']);
        $this->assertTrue($data['validation']['is_valid']);
    }

    public function testFailedApiCall()
    {
        $vin = '5TDYK3DC8DS290235';

        // Add multiple responses for retry attempts
        $this->mockHandler->append(
            new Response(500, [], 'Internal Server Error'),
            new Response(500, [], 'Internal Server Error'),
            new Response(500, [], 'Internal Server Error')
        );

        $result = $this->dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('nhtsa_api', $result->getSource());
        $this->assertStringContainsString('Max retries exceeded', $result->getErrorMessage());
    }

    public function testTimeoutHandling()
    {
        $vin = '5TDYK3DC8DS290235';

        // Add multiple responses for retry attempts
        $this->mockHandler->append(
            new ConnectException(
                'Connection timeout',
                $this->createMock(RequestInterface::class)
            ),
            new ConnectException(
                'Connection timeout',
                $this->createMock(RequestInterface::class)
            ),
            new ConnectException(
                'Connection timeout',
                $this->createMock(RequestInterface::class)
            )
        );

        $result = $this->dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('nhtsa_api', $result->getSource());
        $this->assertStringContainsString('Connection timeout', $result->getErrorMessage());
    }

    public function testInvalidResponse()
    {
        $vin = '5TDYK3DC8DS290235';

        // Add multiple responses for retry attempts
        $this->mockHandler->append(
            new Response(200, [], 'Invalid JSON response'),
            new Response(200, [], 'Invalid JSON response'),
            new Response(200, [], 'Invalid JSON response')
        );

        $result = $this->dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('nhtsa_api', $result->getSource());
        $this->assertStringContainsString('Invalid response format', $result->getErrorMessage());
    }

    public function testCaching()
    {
        $vin = '5TDYK3DC8DS290235';
        $cacheKey = 'nhtsa_api_' . md5($vin);

        $cachedData = [
            'make' => 'Toyota',
            'model' => 'Sienna',
            'validation' => ['is_valid' => true]
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

    public function testGetSourceType()
    {
        $this->assertEquals('api', $this->dataSource->getSourceType());
    }

    public function testCustomApiUrl()
    {
        $customUrl = 'https://custom-api.example.com/decode/';
        $dataSource = new NhtsaApiDataSource(null, null, $customUrl);

        $this->assertEquals($customUrl, $dataSource->getApiBaseUrl());
    }

    public function testCustomTimeout()
    {
        $timeout = 30;
        $dataSource = new NhtsaApiDataSource(null, null, null, $timeout);

        $this->assertEquals($timeout, $dataSource->getTimeout());
    }

    public function testValidationErrorHandling()
    {
        $vin = '5TDYK3DC8DS290235';
        $mockResponse = [
            'Results' => [
                ['Variable' => 'Make', 'Value' => ''],
                ['Variable' => 'Model', 'Value' => ''],
                ['Variable' => 'Error Code', 'Value' => '5'],
                ['Variable' => 'Error Text', 'Value' => 'Invalid VIN']
            ]
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($mockResponse))
        );

        $result = $this->dataSource->decode($vin);

        $this->assertTrue($result->isSuccess()); // API call succeeded
        $data = $result->getData();
        $this->assertFalse($data['validation']['is_valid']); // But VIN is invalid
        $this->assertEquals('5', $data['validation']['error_code']);
        $this->assertEquals('Invalid VIN', $data['validation']['error_text']);
    }

    public function testRetryLogic()
    {
        $vin = '5TDYK3DC8DS290235';
        $dataSource = new NhtsaApiDataSource(null, null, null, 15, 3); // 3 max attempts

        // First two attempts fail, third succeeds
        $this->mockHandler->append(
            new Response(500, [], 'Server Error'),
            new Response(503, [], 'Service Unavailable'),
            new Response(200, [], json_encode([
                'Results' => [
                    ['Variable' => 'Make', 'Value' => 'TOYOTA'],
                    ['Variable' => 'Error Code', 'Value' => '0']
                ]
            ]))
        );

        $result = $dataSource->decode($vin);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('TOYOTA', $result->getDataValue('make'));
    }

    public function testMaxRetriesExceeded()
    {
        $vin = '5TDYK3DC8DS290235';

        // Create a new handler for this test with limited retries
        $mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);
        $dataSource = new NhtsaApiDataSource($httpClient, $this->cache, null, 15, 2); // 2 max attempts

        // All attempts fail (maxRetries = 2 means 2 total attempts)
        $mockHandler->append(
            new Response(500, [], 'Server Error'),
            new Response(500, [], 'Server Error')
        );

        $result = $dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Max retries exceeded', $result->getErrorMessage());
    }

    public function testEmptyResults()
    {
        $vin = '5TDYK3DC8DS290235';
        $mockResponse = ['Results' => []];

        $this->mockHandler->append(
            new Response(200, [], json_encode($mockResponse))
        );

        $result = $this->dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No results', $result->getErrorMessage());
    }

    public function testMetadata()
    {
        $vin = '5TDYK3DC8DS290235';
        $mockResponse = [
            'Results' => [
                ['Variable' => 'Make', 'Value' => 'Toyota'],
                ['Variable' => 'Error Code', 'Value' => '0']
            ]
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($mockResponse))
        );

        $result = $this->dataSource->decode($vin);

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('decoded_by', $metadata);
        $this->assertEquals('nhtsa_api', $metadata['decoded_by']);
        $this->assertArrayHasKey('api_call_success', $metadata);
        $this->assertTrue($metadata['api_call_success']);
        $this->assertArrayHasKey('response_time', $metadata);
        $this->assertArrayHasKey('api_url', $metadata);
    }

    public function testDisabledSource()
    {
        $this->dataSource->setEnabled(false);
        $vin = '5TDYK3DC8DS290235';

        $result = $this->dataSource->decode($vin);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('disabled', $result->getErrorMessage());
    }

    public function testClearCache()
    {
        $vin = '5TDYK3DC8DS290235';
        $cacheKey = 'nhtsa_api_' . md5($vin);

        $this->cache->expects($this->once())
            ->method('delete')
            ->with($cacheKey)
            ->willReturn(true);

        $result = $this->dataSource->clearCache($vin);
        $this->assertTrue($result);
    }

    public function testRateLimiting()
    {
        $dataSource = new NhtsaApiDataSource(null, null, null, 15, 3, 1.0); // 1 second rate limit

        $vin = '5TDYK3DC8DS290235';
        $mockResponse = [
            'Results' => [
                ['Variable' => 'Make', 'Value' => 'Toyota'],
                ['Variable' => 'Error Code', 'Value' => '0']
            ]
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($mockResponse)),
            new Response(200, [], json_encode($mockResponse))
        );

        $startTime = microtime(true);
        $dataSource->decode($vin);
        $dataSource->decode($vin); // Second call should be rate limited
        $endTime = microtime(true);

        $this->assertGreaterThanOrEqual(1.0, $endTime - $startTime);
    }
}
