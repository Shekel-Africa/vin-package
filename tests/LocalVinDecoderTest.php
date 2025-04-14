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
}