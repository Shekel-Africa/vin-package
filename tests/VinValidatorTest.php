<?php

namespace Shekel\VinPackage\Tests;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Validators\VinValidator;

class VinValidatorTest extends TestCase
{
    private VinValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new VinValidator();
    }

    /**
     * Test valid VIN validation
     */
    public function testValidVin()
    {
        // Known valid VINs
        $validVins = [
            '1HGCM82633A004352', // Honda
            'WVWZZZ3BZWE689725', // Volkswagen
            '5TFEV54198X063203', // Toyota
            'JH4NA21674T000853', // Acura
            'WAUZZZ8E56A123456', // Audi
        ];

        foreach ($validVins as $vin) {
            $this->assertTrue($this->validator->validate($vin), "VIN $vin should be valid");
        }
    }

    /**
     * Test invalid VIN validation
     */
    public function testInvalidVin()
    {
        // Invalid VINs
        $invalidVins = [
            '1HGCM82633A00435X', // Invalid check digit
            '1HGCM826I3A004352', // Contains 'I' which is not allowed
            '1HGCM82633A0043',   // Too short
            '1HGCM82633A0043522', // Too long
            'ABCDEFGHIJKLMNOPQ',  // Invalid format
        ];

        foreach ($invalidVins as $vin) {
            $this->assertFalse($this->validator->validate($vin), "VIN $vin should be invalid");
        }
    }

    /**
     * Test VIN with incorrect length
     */
    public function testInvalidLength()
    {
        $this->assertFalse($this->validator->validate('1HGCM82633A00'));
        $this->assertFalse($this->validator->validate('1HGCM82633A0043522222'));
    }

    /**
     * Test VIN with invalid characters
     */
    public function testInvalidCharacters()
    {
        $this->assertFalse($this->validator->validate('1HGCM826I3A004352')); // Contains 'I'
        $this->assertFalse($this->validator->validate('1HGCM826O3A004352')); // Contains 'O'
        $this->assertFalse($this->validator->validate('1HGCM826Q3A004352')); // Contains 'Q'
    }
}
