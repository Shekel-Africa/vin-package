<?php

namespace Shekel\VinPackage\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Utils\UnitConverter;

class UnitConverterTest extends TestCase
{
    public function testInchesToCm()
    {
        $this->assertEquals(50.8, UnitConverter::inchesToCm(20));
        $this->assertEquals(25.4, UnitConverter::inchesToCm(10));
        $this->assertEquals(2.5, UnitConverter::inchesToCm(1, 1));
    }

    public function testCmToInches()
    {
        $this->assertEquals(7.9, UnitConverter::cmToInches(20));
        $this->assertEquals(3.9, UnitConverter::cmToInches(10));
        $this->assertEquals(0.4, UnitConverter::cmToInches(1, 1));
    }

    public function testFeetToMeters()
    {
        $this->assertEquals(0.61, UnitConverter::feetToMeters(2));
        $this->assertEquals(3.05, UnitConverter::feetToMeters(10));
    }

    public function testMetersToFeet()
    {
        $this->assertEquals(6.6, UnitConverter::metersToFeet(2));
        $this->assertEquals(32.8, UnitConverter::metersToFeet(10));
    }

    public function testLbsToKg()
    {
        $this->assertEquals(45.4, UnitConverter::lbsToKg(100));
        $this->assertEquals(22.7, UnitConverter::lbsToKg(50));
    }

    public function testKgToLbs()
    {
        $this->assertEquals(220.5, UnitConverter::kgToLbs(100));
        $this->assertEquals(110.2, UnitConverter::kgToLbs(50));
    }

    public function testMpgToL100km()
    {
        $this->assertEquals(9.4, UnitConverter::mpgToL100km(25));
        $this->assertEquals(13.1, UnitConverter::mpgToL100km(18));
        $this->assertEquals(0.0, UnitConverter::mpgToL100km(0));
    }

    public function testL100kmToMpg()
    {
        $this->assertEquals(25.0, UnitConverter::l100kmToMpg(9.4));
        $this->assertEquals(18.0, UnitConverter::l100kmToMpg(13.1));
        $this->assertEquals(0.0, UnitConverter::l100kmToMpg(0));
    }

    public function testParseDimensionString()
    {
        $result = UnitConverter::parseDimensionString('200.20 in');
        $this->assertEquals(200.20, $result['value']);
        $this->assertEquals('in', $result['unit']);
        $this->assertEquals('200.20 in', $result['original']);

        $result = UnitConverter::parseDimensionString('18 miles/gallon');
        $this->assertEquals(18.0, $result['value']);
        $this->assertEquals('miles/gallon', $result['unit']);

        $result = UnitConverter::parseDimensionString('N/A');
        $this->assertNull($result['value']);
        $this->assertNull($result['unit']);
        $this->assertEquals('N/A', $result['original']);

        $result = UnitConverter::parseDimensionString('');
        $this->assertNull($result['value']);
        $this->assertNull($result['unit']);
    }

    public function testConvert()
    {
        // Inches to cm
        $this->assertEquals(50.8, UnitConverter::convert(20, 'in', 'cm'));
        $this->assertEquals(508.0, UnitConverter::convert(20, 'inches', 'mm', 0));

        // Feet to meters
        $this->assertEquals(0.6, UnitConverter::convert(2, 'ft', 'm'));

        // Pounds to kg
        $this->assertEquals(45.4, UnitConverter::convert(100, 'lbs', 'kg'));

        // MPG to L/100km
        $this->assertEquals(9.4, UnitConverter::convert(25, 'mpg', 'l/100km'));

        // Same unit
        $this->assertEquals(25.0, UnitConverter::convert(25, 'in', 'in'));

        // Unsupported conversion
        $this->assertNull(UnitConverter::convert(25, 'in', 'kg'));
    }

    public function testGetCommonUnits()
    {
        $imperial = UnitConverter::getCommonUnits('imperial');
        $this->assertArrayHasKey('length', $imperial);
        $this->assertContains('in', $imperial['length']);
        $this->assertContains('ft', $imperial['length']);

        $metric = UnitConverter::getCommonUnits('metric');
        $this->assertArrayHasKey('length', $metric);
        $this->assertContains('cm', $metric['length']);
        $this->assertContains('m', $metric['length']);
    }

    public function testFormatValue()
    {
        $this->assertEquals('200.2 in', UnitConverter::formatValue(200.2, 'in'));
        $this->assertEquals('200 in', UnitConverter::formatValue(200.2, 'in', 0));
        $this->assertEquals('200.20 in', UnitConverter::formatValue(200.2, 'in', 2));
    }
}
