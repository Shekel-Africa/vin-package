<?php

namespace Shekel\VinPackage\Tests\ValueObjects;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\ValueObjects\VehicleInfo;

class VehicleInfoDimensionalTest extends TestCase
{
    private function createSampleVehicleData(): array
    {
        return [
            'make' => 'Toyota',
            'model' => 'Sienna',
            'year' => '2013',
            'trim' => 'XLE',
            'dimensions' => [
                'length' => '200.20 in',
                'width' => '78.10 in', 
                'height' => '70.70 in',
                'wheelbase' => '119.30 in'
            ],
            'mileage' => [
                'city' => '18 miles/gallon',
                'highway' => '25 miles/gallon'
            ],
            'additional_info' => [
                'vin_structure' => [
                    'WMI' => '5TD',
                    'VDS' => 'YK3DC8',
                    'VIS' => 'DS290235'
                ]
            ],
            'validation' => [
                'error_code' => null,
                'error_text' => null,
                'is_valid' => true
            ]
        ];
    }

    public function testGetDimensionsOriginal()
    {
        $vehicleInfo = VehicleInfo::fromArray($this->createSampleVehicleData());
        $dimensions = $vehicleInfo->getDimensions('original');
        
        $this->assertIsArray($dimensions);
        $this->assertEquals('200.20 in', $dimensions['length']);
        $this->assertEquals('78.10 in', $dimensions['width']);
        $this->assertEquals('70.70 in', $dimensions['height']);
        $this->assertEquals('119.30 in', $dimensions['wheelbase']);
    }

    public function testGetDimensionsDefaultOriginal()
    {
        $vehicleInfo = VehicleInfo::fromArray($this->createSampleVehicleData());
        $dimensions = $vehicleInfo->getDimensions(); // Default should be 'original'
        
        $this->assertIsArray($dimensions);
        $this->assertEquals('200.20 in', $dimensions['length']);
        $this->assertEquals('78.10 in', $dimensions['width']);
    }

    public function testGetDimensionsMetric()
    {
        $vehicleInfo = VehicleInfo::fromArray($this->createSampleVehicleData());
        $dimensions = $vehicleInfo->getDimensions('metric');
        
        $this->assertIsArray($dimensions);
        $this->assertEquals('508.5 cm', $dimensions['length']);
        $this->assertEquals('198.4 cm', $dimensions['width']);
        $this->assertEquals('179.6 cm', $dimensions['height']);
        $this->assertEquals('303.0 cm', $dimensions['wheelbase']);
    }

    public function testGetDimensionsImperial()
    {
        $vehicleInfo = VehicleInfo::fromArray($this->createSampleVehicleData());
        $dimensions = $vehicleInfo->getDimensions('imperial');
        
        $this->assertIsArray($dimensions);
        // Should remain the same since already in imperial units
        $this->assertEquals('200.2 in', $dimensions['length']);
        $this->assertEquals('78.1 in', $dimensions['width']);
        $this->assertEquals('70.7 in', $dimensions['height']);
        $this->assertEquals('119.3 in', $dimensions['wheelbase']);
    }

    public function testGetDimensionsBoth()
    {
        $vehicleInfo = VehicleInfo::fromArray($this->createSampleVehicleData());
        $dimensions = $vehicleInfo->getDimensions('both');
        
        $this->assertIsArray($dimensions);
        $this->assertArrayHasKey('length', $dimensions);
        $this->assertArrayHasKey('original', $dimensions['length']);
        $this->assertArrayHasKey('imperial', $dimensions['length']);
        $this->assertArrayHasKey('metric', $dimensions['length']);
        
        $this->assertEquals('200.20 in', $dimensions['length']['original']);
        $this->assertEquals('200.2 in', $dimensions['length']['imperial']);
        $this->assertEquals('508.5 cm', $dimensions['length']['metric']);
    }

    public function testGetMileageOriginal()
    {
        $vehicleInfo = VehicleInfo::fromArray($this->createSampleVehicleData());
        $mileage = $vehicleInfo->getMileage('original');
        
        $this->assertIsArray($mileage);
        $this->assertEquals('18 miles/gallon', $mileage['city']);
        $this->assertEquals('25 miles/gallon', $mileage['highway']);
    }

    public function testGetMileageMetric()
    {
        $vehicleInfo = VehicleInfo::fromArray($this->createSampleVehicleData());
        $mileage = $vehicleInfo->getMileage('metric');
        
        $this->assertIsArray($mileage);
        $this->assertEquals('13.1 l/100km', $mileage['city']);
        $this->assertEquals('9.4 l/100km', $mileage['highway']);
    }

    public function testGetMileageBoth()
    {
        $vehicleInfo = VehicleInfo::fromArray($this->createSampleVehicleData());
        $mileage = $vehicleInfo->getMileage('both');
        
        $this->assertIsArray($mileage);
        $this->assertArrayHasKey('city', $mileage);
        $this->assertArrayHasKey('original', $mileage['city']);
        $this->assertArrayHasKey('imperial', $mileage['city']);
        $this->assertArrayHasKey('metric', $mileage['city']);
        
        $this->assertEquals('18 miles/gallon', $mileage['city']['original']);
        $this->assertEquals('18.0 miles/gallon', $mileage['city']['imperial']);
        $this->assertEquals('13.1 l/100km', $mileage['city']['metric']);
    }

    public function testGetDimensionsNullData()
    {
        $data = $this->createSampleVehicleData();
        unset($data['dimensions']);
        
        $vehicleInfo = VehicleInfo::fromArray($data);
        $dimensions = $vehicleInfo->getDimensions('metric');
        
        $this->assertNull($dimensions);
    }

    public function testGetDimensionsEmptyData()
    {
        $data = $this->createSampleVehicleData();
        $data['dimensions'] = [];
        
        $vehicleInfo = VehicleInfo::fromArray($data);
        $dimensions = $vehicleInfo->getDimensions('metric');
        
        $this->assertIsArray($dimensions);
        $this->assertEmpty($dimensions);
    }

    public function testGetDimensionsWithPrecision()
    {
        $vehicleInfo = VehicleInfo::fromArray($this->createSampleVehicleData());
        $dimensions = $vehicleInfo->getDimensions('metric', 2);
        
        $this->assertIsArray($dimensions);
        $this->assertEquals('508.51 cm', $dimensions['length']);
        $this->assertEquals('198.37 cm', $dimensions['width']);
    }

    public function testBackwardCompatibilityToArray()
    {
        $vehicleInfo = VehicleInfo::fromArray($this->createSampleVehicleData());
        $array = $vehicleInfo->toArray();
        
        // Ensure backward compatibility - dimensions should be in original format
        $this->assertArrayHasKey('dimensions', $array);
        $this->assertEquals('200.20 in', $array['dimensions']['length']);
        $this->assertEquals('78.10 in', $array['dimensions']['width']);
        
        // Ensure mileage is also in original format
        $this->assertArrayHasKey('mileage', $array);
        $this->assertEquals('18 miles/gallon', $array['mileage']['city']);
        $this->assertEquals('25 miles/gallon', $array['mileage']['highway']);
    }
}