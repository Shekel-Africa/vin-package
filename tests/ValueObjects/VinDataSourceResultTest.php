<?php

namespace Shekel\VinPackage\Tests\ValueObjects;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;

class VinDataSourceResultTest extends TestCase
{
    public function testSuccessfulResult()
    {
        $data = [
            'make' => 'Toyota',
            'model' => 'Sienna',
            'year' => '2013'
        ];

        $result = new VinDataSourceResult(
            true,
            $data,
            'nhtsa_api',
            null,
            ['response_time' => 1.2]
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals($data, $result->getData());
        $this->assertEquals('nhtsa_api', $result->getSource());
        $this->assertNull($result->getErrorMessage());
        $this->assertEquals(['response_time' => 1.2], $result->getMetadata());
    }

    public function testFailedResult()
    {
        $result = new VinDataSourceResult(
            false,
            [],
            'clearvin',
            'Connection timeout',
            ['timeout' => true]
        );

        $this->assertFalse($result->isSuccess());
        $this->assertEquals([], $result->getData());
        $this->assertEquals('clearvin', $result->getSource());
        $this->assertEquals('Connection timeout', $result->getErrorMessage());
        $this->assertEquals(['timeout' => true], $result->getMetadata());
    }

    public function testWithMetadata()
    {
        $metadata = [
            'execution_time' => 0.5,
            'cache_hit' => true,
            'api_version' => 'v1.0'
        ];

        $result = new VinDataSourceResult(
            true,
            ['make' => 'Honda'],
            'local',
            null,
            $metadata
        );

        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertEquals(0.5, $result->getMetadata()['execution_time']);
    }

    public function testGetters()
    {
        $result = new VinDataSourceResult(
            true,
            ['test' => 'data'],
            'test_source'
        );

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['test' => 'data'], $result->getData());
        $this->assertEquals('test_source', $result->getSource());
        $this->assertNull($result->getErrorMessage());
        $this->assertEquals([], $result->getMetadata());
    }

    public function testToArray()
    {
        $data = ['make' => 'Ford', 'model' => 'F-150'];
        $metadata = ['source_type' => 'api'];

        $result = new VinDataSourceResult(
            true,
            $data,
            'nhtsa_api',
            null,
            $metadata
        );

        $expected = [
            'success' => true,
            'data' => $data,
            'source' => 'nhtsa_api',
            'error_message' => null,
            'metadata' => $metadata
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    public function testToArrayWithError()
    {
        $result = new VinDataSourceResult(
            false,
            [],
            'clearvin',
            'Service unavailable'
        );

        $expected = [
            'success' => false,
            'data' => [],
            'source' => 'clearvin',
            'error_message' => 'Service unavailable',
            'metadata' => []
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    public function testHasData()
    {
        $resultWithData = new VinDataSourceResult(
            true,
            ['make' => 'Toyota'],
            'local'
        );

        $resultWithoutData = new VinDataSourceResult(
            true,
            [],
            'local'
        );

        $this->assertTrue($resultWithData->hasData());
        $this->assertFalse($resultWithoutData->hasData());
    }

    public function testGetDataValue()
    {
        $data = [
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => '2020'
        ];

        $result = new VinDataSourceResult(true, $data, 'test');

        $this->assertEquals('Toyota', $result->getDataValue('make'));
        $this->assertEquals('Camry', $result->getDataValue('model'));
        $this->assertNull($result->getDataValue('nonexistent'));
        $this->assertEquals('default', $result->getDataValue('nonexistent', 'default'));
    }

    public function testGetMetadataValue()
    {
        $metadata = [
            'execution_time' => 1.5,
            'cache_hit' => false
        ];

        $result = new VinDataSourceResult(true, [], 'test', null, $metadata);

        $this->assertEquals(1.5, $result->getMetadataValue('execution_time'));
        $this->assertFalse($result->getMetadataValue('cache_hit'));
        $this->assertNull($result->getMetadataValue('nonexistent'));
        $this->assertEquals('default', $result->getMetadataValue('nonexistent', 'default'));
    }
}
