<?php

namespace Shekel\VinPackage\Tests\ValueObjects;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\ValueObjects\DimensionalValue;

class DimensionalValueTest extends TestCase
{
    public function testFromString()
    {
        $dimension = DimensionalValue::fromString('200.20 in');
        
        $this->assertEquals(200.20, $dimension->getValue());
        $this->assertEquals('in', $dimension->getUnit());
        $this->assertEquals('200.20 in', $dimension->getOriginalString());
        $this->assertTrue($dimension->isValid());
    }

    public function testFromStringInvalid()
    {
        $dimension = DimensionalValue::fromString('N/A');
        
        $this->assertNull($dimension->getValue());
        $this->assertNull($dimension->getUnit());
        $this->assertEquals('N/A', $dimension->getOriginalString());
        $this->assertFalse($dimension->isValid());
    }

    public function testCreate()
    {
        $dimension = DimensionalValue::create(200.20, 'in');
        
        $this->assertEquals(200.20, $dimension->getValue());
        $this->assertEquals('in', $dimension->getUnit());
        $this->assertTrue($dimension->isValid());
    }

    public function testConvertTo()
    {
        $dimension = DimensionalValue::create(20, 'in');
        $converted = $dimension->convertTo('cm');
        
        $this->assertNotNull($converted);
        $this->assertEquals(50.8, $converted->getValue());
        $this->assertEquals('cm', $converted->getUnit());
    }

    public function testConvertToUnsupported()
    {
        $dimension = DimensionalValue::create(20, 'in');
        $converted = $dimension->convertTo('kg');
        
        $this->assertNull($converted);
    }

    public function testToImperial()
    {
        // Already imperial
        $dimension = DimensionalValue::create(20, 'in');
        $imperial = $dimension->toImperial();
        
        $this->assertEquals($dimension, $imperial);

        // Metric to imperial
        $dimension = DimensionalValue::create(50.8, 'cm');
        $imperial = $dimension->toImperial();
        
        $this->assertNotNull($imperial);
        $this->assertEquals(20.0, $imperial->getValue());
        $this->assertEquals('in', $imperial->getUnit());
    }

    public function testToMetric()
    {
        // Already metric
        $dimension = DimensionalValue::create(50.8, 'cm');
        $metric = $dimension->toMetric();
        
        $this->assertEquals($dimension, $metric);

        // Imperial to metric
        $dimension = DimensionalValue::create(20, 'in');
        $metric = $dimension->toMetric();
        
        $this->assertNotNull($metric);
        $this->assertEquals(50.8, $metric->getValue());
        $this->assertEquals('cm', $metric->getUnit());
    }

    public function testFormat()
    {
        $dimension = DimensionalValue::create(200.2, 'in');
        
        $this->assertEquals('200.2 in', $dimension->format());
        $this->assertEquals('200 in', $dimension->format(0));
        $this->assertEquals('200.20 in', $dimension->format(2));
    }

    public function testToString()
    {
        $dimension = DimensionalValue::create(200.2, 'in');
        $this->assertEquals('200.2 in', (string) $dimension);
    }

    public function testToArray()
    {
        $dimension = DimensionalValue::create(200.2, 'in');
        $array = $dimension->toArray();
        
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('unit', $array);
        $this->assertArrayHasKey('original', $array);
        $this->assertArrayHasKey('formatted', $array);
        
        $this->assertEquals(200.2, $array['value']);
        $this->assertEquals('in', $array['unit']);
        $this->assertEquals('200.2 in', $array['formatted']);
    }

    public function testEquals()
    {
        $dim1 = DimensionalValue::create(20, 'in');
        $dim2 = DimensionalValue::create(20, 'in');
        $dim3 = DimensionalValue::create(50.8, 'cm');
        $dim4 = DimensionalValue::create(21, 'in');
        
        $this->assertTrue($dim1->equals($dim2));
        $this->assertTrue($dim1->equals($dim3, 0.1)); // Different units but equivalent
        $this->assertFalse($dim1->equals($dim4));
    }

    public function testFromArray()
    {
        $dimensions = [
            'length' => '200.20 in',
            'width' => '78.10 in',
            'height' => '70.70 in'
        ];
        
        $result = DimensionalValue::fromArray($dimensions);
        
        $this->assertCount(3, $result);
        $this->assertInstanceOf(DimensionalValue::class, $result['length']);
        $this->assertEquals(200.20, $result['length']->getValue());
        $this->assertEquals('in', $result['length']->getUnit());
    }

    public function testCollectionToArrayOriginal()
    {
        $dimensions = [
            'length' => DimensionalValue::create(200.2, 'in'),
            'width' => DimensionalValue::create(78.1, 'in')
        ];
        
        $result = DimensionalValue::collectionToArray($dimensions, 'original');
        
        $this->assertEquals('200.2 in', $result['length']);
        $this->assertEquals('78.1 in', $result['width']);
    }

    public function testCollectionToArrayMetric()
    {
        $dimensions = [
            'length' => DimensionalValue::create(20, 'in'),
            'width' => DimensionalValue::create(10, 'in')
        ];
        
        $result = DimensionalValue::collectionToArray($dimensions, 'metric');
        
        $this->assertEquals('50.8 cm', $result['length']);
        $this->assertEquals('25.4 cm', $result['width']);
    }

    public function testCollectionToArrayBoth()
    {
        $dimensions = [
            'length' => DimensionalValue::create(20, 'in')
        ];
        
        $result = DimensionalValue::collectionToArray($dimensions, 'both');
        
        $this->assertArrayHasKey('length', $result);
        $this->assertArrayHasKey('original', $result['length']);
        $this->assertArrayHasKey('imperial', $result['length']);
        $this->assertArrayHasKey('metric', $result['length']);
        
        $this->assertEquals('20.0 in', $result['length']['original']);
        $this->assertEquals('20.0 in', $result['length']['imperial']);
        $this->assertEquals('50.8 cm', $result['length']['metric']);
    }

    public function testMileageConversion()
    {
        $mpg = DimensionalValue::create(25, 'mpg');
        $metric = $mpg->toMetric();
        
        $this->assertNotNull($metric);
        $this->assertEquals(9.4, $metric->getValue());
        $this->assertEquals('l/100km', $metric->getUnit());

        // Test reverse conversion
        $imperial = $metric->toImperial();
        $this->assertNotNull($imperial);
        $this->assertEquals(25.0, $imperial->getValue());
        $this->assertEquals('mpg', $imperial->getUnit());
    }
}