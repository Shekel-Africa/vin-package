<?php

namespace Shekel\VinPackage\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Services\VinDecoderService;
use ReflectionClass;

class VinDecoderServiceTest extends TestCase
{
    /**
     * Test decoding a VIN with mock API response
     */
    public function testDecode()
    {
        // Mock API response
        $mockResponse = [
            'Count' => 1,
            'Message' => 'Results returned successfully',
            'Results' => [
                [
                    'Variable' => 'Make',
                    'Value' => 'HONDA'
                ],
                [
                    'Variable' => 'Model',
                    'Value' => 'CIVIC'
                ],
                [
                    'Variable' => 'Model Year',
                    'Value' => '2015'
                ],
                [
                    'Variable' => 'Trim',
                    'Value' => 'LX'
                ],
                [
                    'Variable' => 'Engine',
                    'Value' => '2.0L I4'
                ],
                [
                    'Variable' => 'Plant City',
                    'Value' => 'GREENSBURG'
                ],
                [
                    'Variable' => 'Body Class',
                    'Value' => 'Sedan'
                ]
            ]
        ];

        // Create mock handler
        $mock = new MockHandler([
            new Response(200, [], json_encode($mockResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Use reflection to set the client property on the service
        $decoderService = new VinDecoderService();
        $reflection = new ReflectionClass($decoderService);

        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($decoderService, $client);

        // Test the decoder
        $result = $decoderService->decode('1HGCM82633A004352');

        // Verify the result using the VehicleInfo getters
        $this->assertEquals('HONDA', $result->getMake());
        $this->assertEquals('CIVIC', $result->getModel());
        $this->assertEquals('2015', $result->getYear());
        $this->assertEquals('LX', $result->getTrim());
        $this->assertEquals('2.0L I4', $result->getEngine());
    }

    /**
     * Test model year decoder
     */
    public function testDecodeModelYear()
    {
        $decoderService = new VinDecoderService();

        // Test a few year codes
        $this->assertEquals('2010', $decoderService->decodeModelYear('A'));
        $this->assertEquals('2018', $decoderService->decodeModelYear('J'));
        $this->assertEquals('2023', $decoderService->decodeModelYear('P'));
        $this->assertEquals('2005', $decoderService->decodeModelYear('5'));

        // Test unknown code
        $this->assertEquals('Unknown', $decoderService->decodeModelYear('Q'));
    }

    /**
     * Test caching functionality of VIN decoder
     */
    public function testCaching()
    {
        // Create a test VIN
        $vin = '5TFBV54188X063485';  // Toyota Tundra

        // Create a mock cache implementation
        $mockCache = new class implements \Shekel\VinPackage\Contracts\VinCacheInterface {
            private $storage = [];

            public function get(string $key)
            {
                return $this->storage[$key] ?? null;
            }

            public function set(string $key, $value, ?int $ttl = null): bool
            {
                $this->storage[$key] = $value;
                return true;
            }

            public function delete(string $key): bool
            {
                if (isset($this->storage[$key])) {
                    unset($this->storage[$key]);
                    return true;
                }
                return false;
            }

            public function has(string $key): bool
            {
                return isset($this->storage[$key]);
            }

            public function getStorage(): array
            {
                return $this->storage;
            }
        };

        // Mock API response for Toyota Tundra
        $mockResponse = [
            'Count' => 1,
            'Message' => 'Results returned successfully',
            'Results' => [
                [
                    'Variable' => 'Make',
                    'Value' => 'TOYOTA'
                ],
                [
                    'Variable' => 'Model',
                    'Value' => 'TUNDRA'
                ],
                [
                    'Variable' => 'Model Year',
                    'Value' => '2018'
                ],
                [
                    'Variable' => 'Manufacturer Name',
                    'Value' => 'TOYOTA MOTOR MANUFACTURING, TEXAS, INC.'
                ],
                [
                    'Variable' => 'Error Code',
                    'Value' => '0'
                ],
                [
                    'Variable' => 'Error Text',
                    'Value' => 'No errors'
                ]
            ]
        ];

        // Create mock handler
        $mock = new MockHandler([
            new Response(200, [], json_encode($mockResponse)),
            // Add a second mock response for the second API call to verify we use cache
            new Response(500, [], 'Server Error - This should not be called if cache works')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Create a VinDecoderService with our mock cache
        $decoderService = new VinDecoderService(null, $mockCache);

        // Use reflection to set the client property on the service
        $reflection = new ReflectionClass($decoderService);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($decoderService, $client);

        // First decode - should hit API and store in cache
        $result1 = $decoderService->decode($vin);

        // Verify basic result
        $this->assertEquals('TOYOTA', $result1->getMake());
        $this->assertEquals('TUNDRA', $result1->getModel());

        // Check if data was stored in cache
        $cacheKey = 'vin_data_' . md5($vin);
        $this->assertTrue($mockCache->has($cacheKey), 'VIN data was not stored in cache');

        // Second decode - should use cached data and not hit API
        $result2 = $decoderService->decode($vin);

        // Verify the second result matches
        $this->assertEquals('TOYOTA', $result2->getMake());
        $this->assertEquals('TUNDRA', $result2->getModel());

        // Verify manufacturer code was cached
        // The WMI for this Toyota VIN is '5TF'
        $this->assertTrue($mockCache->has('manufacturer_codes'), 'Manufacturer codes were not cached');
        $manufacturerCodes = $mockCache->get('manufacturer_codes');

        // The mock response includes 'TOYOTA MOTOR MANUFACTURING, TEXAS, INC.' as the manufacturer
        // Check that the WMI-to-manufacturer mapping was stored
        $this->assertIsArray($manufacturerCodes, 'Cached manufacturer codes is not an array');
        $this->assertArrayHasKey('5TF', $manufacturerCodes, 'Toyota WMI was not learned and cached');
        $this->assertEquals('TOYOTA MOTOR MANUFACTURING, TEXAS, INC.', $manufacturerCodes['5TF']);

        // Test local decoding with the cached manufacturer code
        // Force local decoding by clearing VIN cache but keeping manufacturer codes
        $mockCache->delete($cacheKey);

        // Set local fallback to true to ensure we use local decoding
        $decoderService->setLocalFallback(true);

        // Decode locally
        $localResult = $decoderService->decodeLocally($vin);

        // The local decoder should use our cached manufacturer codes
        $this->assertStringContainsString(
            'TOYOTA',
            $localResult->getManufacturer(),
            'Local decoding did not use cached manufacturer code'
        );
    }

    /**
     * Test clearing of cache
     */
    public function testClearCache()
    {
        // Create a test VIN
        $vin = '1HGCM82633A004352';

        // Create a mock cache implementation
        $mockCache = new class implements \Shekel\VinPackage\Contracts\VinCacheInterface {
            private $storage = [];

            public function get(string $key)
            {
                return $this->storage[$key] ?? null;
            }

            public function set(string $key, $value, ?int $ttl = null): bool
            {
                $this->storage[$key] = $value;
                return true;
            }

            public function delete(string $key): bool
            {
                if (isset($this->storage[$key])) {
                    unset($this->storage[$key]);
                    return true;
                }
                return false;
            }

            public function has(string $key): bool
            {
                return isset($this->storage[$key]);
            }
        };

        // Pre-populate the cache with some data
        $cacheKey = 'vin_data_' . md5($vin);
        $mockCache->set($cacheKey, [
            'make' => 'HONDA',
            'model' => 'CIVIC',
            'additional_info' => ['decoded_by' => 'test']
        ]);

        // Create the service with our mock cache
        $decoderService = new VinDecoderService(null, $mockCache);

        // Verify cache has the data
        $this->assertTrue($mockCache->has($cacheKey), 'Test data not stored in cache');

        // Clear the cache
        $result = $decoderService->clearCacheForVin($vin);

        // Verify result and cache state
        $this->assertTrue($result, 'clearCacheForVin returned false');
        $this->assertFalse($mockCache->has($cacheKey), 'VIN data still exists in cache after clearing');
    }
}
