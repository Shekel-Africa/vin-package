<?php

namespace Shekel\VinPackage\Tests;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\JapaneseChassisNumber;
use Shekel\VinPackage\VehicleIdentifierFactory;
use Shekel\VinPackage\Vin;
use Shekel\VinPackage\Contracts\VehicleIdentifierInterface;

class JapaneseChassisIntegrationTest extends TestCase
{
    /**
     * Test full decode flow with JapaneseChassisNumber class
     */
    public function testFullDecodeFlow()
    {
        $chassis = new JapaneseChassisNumber('JZA80-1004956');

        $this->assertTrue($chassis->isValid());
        $this->assertEquals('japanese_chassis_number', $chassis->getIdentifierType());
        $this->assertEquals('JZA80-1004956', $chassis->getIdentifier());
        $this->assertEquals('JZA80-1004956', $chassis->getChassisNumber());

        $info = $chassis->getVehicleInfo();

        $this->assertEquals('Toyota', $info->getMake());
        $this->assertEquals('Supra', $info->getModel());
        $this->assertEquals('Japan', $info->getCountry());
        $this->assertTrue($info->isJapaneseVehicle());
        $this->assertEquals('japanese_chassis_number', $info->getIdentifierType());

        // Check chassis number structure
        $structure = $info->getChassisNumberStructure();
        $this->assertIsArray($structure);
        $this->assertEquals('JZA80', $structure['model_code']);
        $this->assertEquals('1004956', $structure['serial_number']);
    }

    /**
     * Test VehicleIdentifierFactory auto-detection for Japanese chassis
     */
    public function testFactoryAutoDetectsJapaneseChassis()
    {
        $vehicle = VehicleIdentifierFactory::create('JZA80-1004956');

        $this->assertInstanceOf(JapaneseChassisNumber::class, $vehicle);
        $this->assertInstanceOf(VehicleIdentifierInterface::class, $vehicle);
        $this->assertEquals('japanese_chassis_number', $vehicle->getIdentifierType());
    }

    /**
     * Test VehicleIdentifierFactory auto-detection for international VIN
     */
    public function testFactoryAutoDetectsVin()
    {
        $vehicle = VehicleIdentifierFactory::create('1HGCM82633A004352');

        $this->assertInstanceOf(Vin::class, $vehicle);
        $this->assertInstanceOf(VehicleIdentifierInterface::class, $vehicle);
        $this->assertEquals('vin', $vehicle->getIdentifierType());
    }

    /**
     * Test factory detectType method
     */
    public function testFactoryDetectType()
    {
        $this->assertEquals('japanese_chassis_number', VehicleIdentifierFactory::detectType('JZA80-1004956'));
        $this->assertEquals('japanese_chassis_number', VehicleIdentifierFactory::detectType('BNR32-305366'));
        $this->assertEquals('vin', VehicleIdentifierFactory::detectType('1HGCM82633A004352'));
        $this->assertEquals('vin', VehicleIdentifierFactory::detectType('WVWZZZ3BZWE689725'));
    }

    /**
     * Test factory analyzeIdentifier method
     */
    public function testFactoryAnalyzeIdentifier()
    {
        $analysis = VehicleIdentifierFactory::analyzeIdentifier('JZA80-1004956');

        $this->assertEquals('japanese_chassis_number', $analysis['type']);
        $this->assertGreaterThan(0, $analysis['confidence']);
        $this->assertIsArray($analysis['reasons']);
    }

    /**
     * Test factory explicit creation methods
     */
    public function testFactoryExplicitCreation()
    {
        $vin = VehicleIdentifierFactory::createVin('1HGCM82633A004352');
        $this->assertInstanceOf(Vin::class, $vin);

        $chassis = VehicleIdentifierFactory::createJapaneseChassis('JZA80-1004956');
        $this->assertInstanceOf(JapaneseChassisNumber::class, $chassis);
    }

    /**
     * Test convenience methods on JapaneseChassisNumber
     */
    public function testConvenienceMethods()
    {
        $chassis = new JapaneseChassisNumber('JZA80-1004956');

        $this->assertEquals('JZA80', $chassis->getModelCode());
        $this->assertEquals('1004956', $chassis->getSerialNumber());
        $this->assertEquals('Toyota', $chassis->getMake());
        $this->assertEquals('Supra', $chassis->getModel());
        $this->assertEquals('Toyota Motor Corporation', $chassis->getManufacturer());
        $this->assertStringContainsString('2JZ', $chassis->getEngine());
        $this->assertTrue($chassis->isKnownModelCode());
    }

    /**
     * Test unknown model code returns partial info
     */
    public function testUnknownModelCodeReturnsPartialInfo()
    {
        $chassis = new JapaneseChassisNumber('ZZZ99-123456');

        $this->assertTrue($chassis->isValid());
        $this->assertFalse($chassis->isKnownModelCode());

        $info = $chassis->getVehicleInfo();
        $this->assertNull($info->getModel());
        $this->assertEquals('Japan', $info->getCountry());
    }

    /**
     * Test invalid chassis number throws exception
     */
    public function testInvalidChassisNumberThrowsException()
    {
        $this->expectException(\Exception::class);

        $chassis = new JapaneseChassisNumber('invalid');
        $chassis->getVehicleInfo();
    }

    /**
     * Test validation error message
     */
    public function testValidationErrorMessage()
    {
        $chassis = new JapaneseChassisNumber('invalid');

        $this->assertFalse($chassis->isValid());
        $error = $chassis->getValidationError();
        $this->assertIsString($error);
    }

    /**
     * Test VehicleInfo methods for Japanese vehicles
     */
    public function testVehicleInfoJapaneseMethods()
    {
        $chassis = new JapaneseChassisNumber('JZA80-1004956');
        $info = $chassis->getVehicleInfo();

        // Test Japanese-specific methods
        $this->assertTrue($info->isJapaneseVehicle());
        $this->assertEquals('japanese_chassis_number', $info->getIdentifierType());
        $this->assertNotNull($info->getChassisNumberStructure());
        $this->assertNull($info->getVinStructure()); // VIN structure should be null

        // Test production years
        $productionYears = $info->getProductionYears();
        $this->assertNotNull($productionYears);
        $this->assertStringContainsString('-', $productionYears);
    }

    /**
     * Test VehicleInfo methods for regular VINs
     */
    public function testVehicleInfoVinMethods()
    {
        // Use a local-only decode to avoid API calls
        $vin = new Vin('5TFEV54198X063203');

        // VehicleInfo from VIN should have different properties
        if ($vin->isValid()) {
            // Note: We can't test getVehicleInfo() easily without mocking the API
            // Just verify the interface
            $this->assertEquals('vin', $vin->getIdentifierType());
        }
    }

    /**
     * Test multiple manufacturers
     */
    public function testMultipleManufacturers()
    {
        $testCases = [
            'JZA80-1004956' => ['Toyota', 'Supra'],
            'BNR32-305366' => ['Nissan', 'Skyline GT-R'],
            'DC2-1234567' => ['Honda', 'Integra Type R'],
            'GDB-123456' => ['Subaru', 'Impreza WRX STI'],
            'FD3S-123456' => ['Mazda', 'RX-7'],
            'CT9A-1234567' => ['Mitsubishi', 'Lancer Evolution VII/VIII/IX'],
        ];

        foreach ($testCases as $chassisNumber => $expected) {
            $chassis = new JapaneseChassisNumber($chassisNumber);
            $this->assertTrue($chassis->isValid(), "Chassis $chassisNumber should be valid");
            $this->assertEquals($expected[0], $chassis->getMake(), "Make for $chassisNumber");
            $this->assertEquals($expected[1], $chassis->getModel(), "Model for $chassisNumber");
        }
    }

    /**
     * Test getSupportedManufacturers
     */
    public function testGetSupportedManufacturers()
    {
        $chassis = new JapaneseChassisNumber('JZA80-1004956');
        $manufacturers = $chassis->getSupportedManufacturers();

        $this->assertIsArray($manufacturers);
        $this->assertGreaterThan(5, count($manufacturers));
    }
}
