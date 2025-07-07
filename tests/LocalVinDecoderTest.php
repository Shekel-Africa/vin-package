<?php

namespace Shekel\VinPackage\Tests;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Decoders\LocalVinDecoder;

class LocalVinDecoderTest extends TestCase
{
    private LocalVinDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new LocalVinDecoder();
    }

    /**
     * Test basic local decoding functionality
     */
    public function testLocalDecoding()
    {
        // Test a North American VIN (Honda)
        $data = $this->decoder->decode('1HGCM82633A004352');

        $this->assertArrayHasKey('country', $data);
        $this->assertArrayHasKey('make', $data);
        $this->assertArrayHasKey('year', $data);
        $this->assertArrayHasKey('additional_info', $data);

        $this->assertEquals('United States', $data['country']);
        $this->assertEquals('Honda', $data['make']);

        // Check metadata
        $this->assertEquals('local_decoder', $data['additional_info']['decoded_by']);
    }

    /**
     * Test VIN decoding with different manufacturers
     */
    public function testDifferentManufacturers()
    {
        // Test a German VIN (Volkswagen)
        $data = $this->decoder->decode('WVWZZZ3BZWE689725');
        $this->assertEquals('Germany', $data['country']);
        $this->assertEquals('Volkswagen', $data['make']);

        // Test a Japanese VIN (Toyota)
        $data = $this->decoder->decode('JT2BG22K9Y0328616');
        $this->assertEquals('Japan', $data['country']);
        $this->assertEquals('Toyota', $data['make']);
    }

    /**
     * Test model year extraction
     */
    public function testYearDecoding()
    {
        // Test different year codes
        $vinBase = '1HGCM826XXA004352'; // Template VIN with X as year placeholder

        $testYears = [
            'A' => '2010',
            'B' => '2011',
            'J' => '2018',
            '1' => '2001',
            '9' => '2009'
        ];

        foreach ($testYears as $yearCode => $expectedYear) {
            $vin = substr_replace($vinBase, $yearCode, 9, 1); // Replace X with year code
            $data = $this->decoder->decode($vin);
            $this->assertEquals($expectedYear, $data['year'], "Failed year check for code $yearCode");
        }
    }

    /**
     * Test country identification
     */
    public function testCountryIdentification()
    {
        $vinBase = 'XHGCM82633A004352'; // Template VIN with X as country placeholder

        $testCountries = [
            '1' => 'United States',
            '2' => 'Canada',
            '3' => 'Mexico',
            'J' => 'Japan',
            'S' => 'United Kingdom',
            'W' => 'Germany'
        ];

        foreach ($testCountries as $countryCode => $expectedCountry) {
            $vin = substr_replace($vinBase, $countryCode, 0, 1); // Replace X with country code
            $data = $this->decoder->decode($vin);
            $this->assertEquals($expectedCountry, $data['country'], "Failed country check for code $countryCode");
        }
    }

    /**
     * Test manufacturer code caching functionality
     */
    public function testManufacturerCodeCaching()
    {
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

        // Create an instance of LocalVinDecoder with the mock cache
        $decoder = new LocalVinDecoder();
        $decoder->setCache($mockCache);

        // Test adding a new manufacturer code
        $wmi = 'ABC';
        $manufacturerName = 'Test Manufacturer';
        $decoder->addManufacturerCode($wmi, $manufacturerName);

        // Check if the manufacturer code was cached
        $this->assertTrue($mockCache->has('manufacturer_codes'), 'Manufacturer codes were not cached');
        $cachedCodes = $mockCache->get('manufacturer_codes');
        $this->assertIsArray($cachedCodes, 'Cached manufacturer codes is not an array');
        $this->assertArrayHasKey($wmi, $cachedCodes, 'Added manufacturer code was not found in cache');
        $this->assertEquals($manufacturerName, $cachedCodes[$wmi], 'Cached manufacturer name does not match');

        // Test that the runtime manufacturer codes are updated
        $allCodes = $decoder->getManufacturerCodes();
        $this->assertArrayHasKey($wmi, $allCodes, 'Added manufacturer code not found in runtime codes');
        $this->assertEquals($manufacturerName, $allCodes[$wmi], 'Runtime manufacturer name does not match');

        // Test decoding with the new manufacturer code
        $testVin = 'ABC' . 'DE12345678901234'; // Create a VIN starting with our test WMI
        $decodedData = $decoder->decode($testVin);
        $this->assertEquals(
            $manufacturerName,
            $decodedData['manufacturer'],
            'Decoded manufacturer does not match added code'
        );

        // Test loading manufacturer codes from cache on a new decoder instance
        $newDecoder = new LocalVinDecoder();
        $newDecoder->setCache($mockCache);

        // The new decoder should now have our cached manufacturer code
        $newAllCodes = $newDecoder->getManufacturerCodes();
        $this->assertArrayHasKey($wmi, $newAllCodes, 'Cached manufacturer code not loaded in new instance');
        $this->assertEquals(
            $manufacturerName,
            $newAllCodes[$wmi],
            'Loaded manufacturer name does not match'
        );

        // Test that the new decoder can use the cached manufacturer code for decoding
        $newDecodedData = $newDecoder->decode($testVin);
        $this->assertEquals(
            $manufacturerName,
            $newDecodedData['manufacturer'],
            'New decoder failed to use cached manufacturer code'
        );
    }

    /**
     * Test handling invalid WMI codes
     */
    public function testInvalidWmiHandling()
    {
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

        $decoder = new LocalVinDecoder();
        $decoder->setCache($mockCache);

        // Test with WMI that's too short
        $decoder->addManufacturerCode('AB', 'Invalid Short WMI');
        $this->assertFalse(
            $mockCache->has('manufacturer_codes'),
            'Invalid short WMI was incorrectly cached'
        );

        // Test with WMI that's too long - should only use first 3 characters
        $decoder->addManufacturerCode('ABCD', 'Long WMI Manufacturer');
        $this->assertTrue($mockCache->has('manufacturer_codes'), 'Valid WMI part was not cached');

        $cachedCodes = $mockCache->get('manufacturer_codes');
        $this->assertArrayHasKey('ABC', $cachedCodes, 'First 3 chars of long WMI not used');
        $this->assertEquals(
            'Long WMI Manufacturer',
            $cachedCodes['ABC'],
            'Manufacturer name for trimmed WMI incorrect'
        );

        // Test with empty WMI
        $decoder->addManufacturerCode('', 'Empty WMI');
        // Cache should still contain only our valid entry
        $cachedCodes = $mockCache->get('manufacturer_codes');
        $this->assertCount(1, $cachedCodes, 'Empty WMI was incorrectly cached');
    }

    /**
     * Test built-in vs cached manufacturer code precedence
     */
    public function testManufacturerCodePrecedence()
    {
        // Create a mock cache that will return a conflicting manufacturer code
        $mockCache = new class implements \Shekel\VinPackage\Contracts\VinCacheInterface {
            private $storage = [];

            public function __construct()
            {
                // Pre-populate with a Toyota WMI but different manufacturer name
                $this->storage['manufacturer_codes'] = [
                    '1HG' => 'Cached Honda Value', // Conflicts with built-in 'Honda'
                    'NEW' => 'New Manufacturer'    // Doesn't conflict
                ];
            }

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

        $decoder = new LocalVinDecoder();
        $decoder->setCache($mockCache);

        // Test decoding with the Honda WMI - should use built-in code, not cached
        $hondaVin = '1HGCM82633A004352';
        $decodedData = $decoder->decode($hondaVin);

        // The manufacturer should be the built-in "Honda", not "Cached Honda Value"
        $this->assertStringContainsString(
            'Honda',
            $decodedData['manufacturer'],
            'Built-in manufacturer not prioritized over cached one'
        );
        $this->assertStringNotContainsString(
            'Cached Honda Value',
            $decodedData['manufacturer'],
            'Cached manufacturer incorrectly used over built-in'
        );

        // Test decoding with the new WMI that only exists in cache
        $newVin = 'NEW' . 'DE12345678901234';
        $decodedData = $decoder->decode($newVin);

        // Should use the cached manufacturer
        $this->assertEquals(
            'New Manufacturer',
            $decodedData['manufacturer'],
            'Cached manufacturer not used for new WMI'
        );
    }
}
