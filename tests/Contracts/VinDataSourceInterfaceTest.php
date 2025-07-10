<?php

namespace Shekel\VinPackage\Tests\Contracts;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Contracts\VinDataSourceInterface;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;

class VinDataSourceInterfaceTest extends TestCase
{
    private VinDataSourceInterface $dataSource;

    protected function setUp(): void
    {
        // Create a mock implementation for testing
        $this->dataSource = new class implements VinDataSourceInterface {
            private bool $enabled = true;
            private string $name = 'test_source';
            private int $priority = 1;
            private string $sourceType = 'test';

            public function getName(): string
            {
                return $this->name;
            }

            public function getPriority(): int
            {
                return $this->priority;
            }

            public function isEnabled(): bool
            {
                return $this->enabled;
            }

            public function setEnabled(bool $enabled): void
            {
                $this->enabled = $enabled;
            }

            public function canHandle(string $vin): bool
            {
                return strlen($vin) === 17;
            }

            public function decode(string $vin): VinDataSourceResult
            {
                if (!$this->canHandle($vin)) {
                    return new VinDataSourceResult(
                        false,
                        [],
                        $this->name,
                        'Invalid VIN format'
                    );
                }

                return new VinDataSourceResult(
                    true,
                    ['make' => 'Test Make', 'vin' => $vin],
                    $this->name
                );
            }

            public function getSourceType(): string
            {
                return $this->sourceType;
            }

            public function setPriority(int $priority): void
            {
                $this->priority = $priority;
            }

            public function setName(string $name): void
            {
                $this->name = $name;
            }
        };
    }

    public function testGetName()
    {
        $this->assertEquals('test_source', $this->dataSource->getName());

        $this->dataSource->setName('custom_name');
        $this->assertEquals('custom_name', $this->dataSource->getName());
    }

    public function testGetPriority()
    {
        $this->assertEquals(1, $this->dataSource->getPriority());

        $this->dataSource->setPriority(5);
        $this->assertEquals(5, $this->dataSource->getPriority());
    }

    public function testIsEnabled()
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

    public function testCanHandle()
    {
        // Valid 17-character VIN
        $this->assertTrue($this->dataSource->canHandle('5TDYK3DC8DS290235'));

        // Invalid VIN - too short
        $this->assertFalse($this->dataSource->canHandle('12345'));

        // Invalid VIN - too long
        $this->assertFalse($this->dataSource->canHandle('5TDYK3DC8DS2902351'));

        // Invalid VIN - empty
        $this->assertFalse($this->dataSource->canHandle(''));
    }

    public function testDecodeSuccess()
    {
        $vin = '5TDYK3DC8DS290235';
        $result = $this->dataSource->decode($vin);

        $this->assertInstanceOf(VinDataSourceResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('test_source', $result->getSource());
        $this->assertEquals('Test Make', $result->getDataValue('make'));
        $this->assertEquals($vin, $result->getDataValue('vin'));
        $this->assertNull($result->getErrorMessage());
    }

    public function testDecodeFailure()
    {
        $invalidVin = '12345';
        $result = $this->dataSource->decode($invalidVin);

        $this->assertInstanceOf(VinDataSourceResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('test_source', $result->getSource());
        $this->assertEquals([], $result->getData());
        $this->assertEquals('Invalid VIN format', $result->getErrorMessage());
    }

    public function testGetSourceType()
    {
        $this->assertEquals('test', $this->dataSource->getSourceType());
    }

    public function testInterfaceContract()
    {
        // Ensure the interface defines all required methods
        $reflectionClass = new \ReflectionClass(VinDataSourceInterface::class);
        $methods = $reflectionClass->getMethods();

        $expectedMethods = [
            'getName',
            'getPriority',
            'isEnabled',
            'setEnabled',
            'canHandle',
            'decode',
            'getSourceType'
        ];

        $actualMethods = array_map(fn($method) => $method->getName(), $methods);

        foreach ($expectedMethods as $expectedMethod) {
            $this->assertContains(
                $expectedMethod,
                $actualMethods,
                "Interface must define method: {$expectedMethod}"
            );
        }
    }

    public function testDecodeReturnsCorrectType()
    {
        $result = $this->dataSource->decode('5TDYK3DC8DS290235');
        $this->assertInstanceOf(VinDataSourceResult::class, $result);
    }

    public function testCanHandleReturnsBoolean()
    {
        $result = $this->dataSource->canHandle('5TDYK3DC8DS290235');
        $this->assertIsBool($result);
    }

    public function testGetNameReturnsString()
    {
        $result = $this->dataSource->getName();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testGetPriorityReturnsInteger()
    {
        $result = $this->dataSource->getPriority();
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testGetSourceTypeReturnsString()
    {
        $result = $this->dataSource->getSourceType();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
