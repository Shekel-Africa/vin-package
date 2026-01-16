<?php

namespace Shekel\VinPackage\Tests;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Validators\JapaneseChassisValidator;

class JapaneseChassisValidatorTest extends TestCase
{
    private JapaneseChassisValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new JapaneseChassisValidator();
    }

    /**
     * Test valid Japanese chassis number validation
     */
    public function testValidChassisNumbers()
    {
        $validNumbers = [
            'JZA80-1004956',   // Toyota Supra
            'BNR32-305366',    // Nissan Skyline GT-R R32
            'DC2-1234567',     // Honda Integra Type R
            'GDB-123456',      // Subaru WRX STI
            'FD3S-123456',     // Mazda RX-7
            'SV30-0169266',    // Toyota Camry
            'AE86-1234567',    // Toyota Corolla Levin
            'S13-123456',      // Nissan Silvia
            'EK9-1234567',     // Honda Civic Type R
            'CT9A-1234567',    // Mitsubishi Lancer Evo
            'NA1-1234567',     // Honda NSX
            'BCNR33-123456',   // Nissan Skyline GT-R R33
        ];

        foreach ($validNumbers as $chassisNumber) {
            $this->assertTrue(
                $this->validator->validate($chassisNumber),
                "Chassis number $chassisNumber should be valid"
            );
        }
    }

    /**
     * Test lowercase input is normalized
     */
    public function testLowercaseInputIsNormalized()
    {
        $this->assertTrue($this->validator->validate('jza80-1004956'));
        $this->assertTrue($this->validator->validate('Bnr32-305366'));
    }

    /**
     * Test invalid chassis number - missing serial number
     */
    public function testInvalidMissingSerial()
    {
        $this->assertFalse($this->validator->validate('JZA80'));
        $this->assertFalse($this->validator->validate('JZA80-'));
    }

    /**
     * Test invalid chassis number - serial too short
     */
    public function testInvalidSerialTooShort()
    {
        $this->assertFalse($this->validator->validate('JZA80-123'));
        $this->assertFalse($this->validator->validate('JZA80-12345'));
    }

    /**
     * Test invalid chassis number - serial too long
     */
    public function testInvalidSerialTooLong()
    {
        $this->assertFalse($this->validator->validate('JZA80-12345678'));
    }

    /**
     * Test invalid chassis number - model code too short
     */
    public function testInvalidModelCodeTooShort()
    {
        $this->assertFalse($this->validator->validate('J-1234567'));
    }

    /**
     * Test invalid chassis number - model code too long
     */
    public function testInvalidModelCodeTooLong()
    {
        // 7 character model code is too long (max is 6)
        $this->assertFalse($this->validator->validate('JZABCDE-1234567'));
    }

    /**
     * Test invalid chassis number - missing hyphen
     */
    public function testInvalidMissingHyphen()
    {
        $this->assertFalse($this->validator->validate('JZA801004956'));
    }

    /**
     * Test invalid chassis number - multiple hyphens
     */
    public function testInvalidMultipleHyphens()
    {
        $this->assertFalse($this->validator->validate('JZA80-100-4956'));
    }

    /**
     * Test invalid chassis number - wrong separator
     */
    public function testInvalidWrongSeparator()
    {
        $this->assertFalse($this->validator->validate('JZA80_1004956'));
        $this->assertFalse($this->validator->validate('JZA80/1004956'));
    }

    /**
     * Test invalid chassis number - non-numeric serial
     */
    public function testInvalidNonNumericSerial()
    {
        $this->assertFalse($this->validator->validate('JZA80-ABCDEFG'));
        $this->assertFalse($this->validator->validate('JZA80-123ABC4'));
    }

    /**
     * Test invalid chassis number - invalid characters in model code
     */
    public function testInvalidCharactersInModelCode()
    {
        $this->assertFalse($this->validator->validate('JZ@80-1234567'));
        $this->assertFalse($this->validator->validate('JZ#80-1234567'));
    }

    /**
     * Test validation with error messages
     */
    public function testValidationWithErrorMessages()
    {
        // Valid - should return true
        $result = $this->validator->validate('JZA80-1004956', true);
        $this->assertTrue($result);

        // Invalid length - should return error message
        $result = $this->validator->validate('JZA80-123', true);
        $this->assertIsString($result);
        $this->assertStringContainsString('serial number', strtolower($result));

        // Missing hyphen - should return error message
        $result = $this->validator->validate('JZA801004956', true);
        $this->assertIsString($result);
        $this->assertStringContainsString('hyphen', strtolower($result));
    }

    /**
     * Test parsing valid chassis number
     */
    public function testParseValidChassisNumber()
    {
        $parsed = $this->validator->parse('JZA80-1004956');

        $this->assertIsArray($parsed);
        $this->assertArrayHasKey('model_code', $parsed);
        $this->assertArrayHasKey('serial_number', $parsed);
        $this->assertEquals('JZA80', $parsed['model_code']);
        $this->assertEquals('1004956', $parsed['serial_number']);
    }

    /**
     * Test parsing invalid chassis number returns null
     */
    public function testParseInvalidChassisNumberReturnsNull()
    {
        $this->assertNull($this->validator->parse('invalid'));
        $this->assertNull($this->validator->parse('JZA80'));
        $this->assertNull($this->validator->parse('JZA80-123'));
    }

    /**
     * Test static looksLikeJapaneseChassisNumber method
     */
    public function testLooksLikeJapaneseChassisNumber()
    {
        // Should look like Japanese chassis numbers
        $this->assertTrue(JapaneseChassisValidator::looksLikeJapaneseChassisNumber('JZA80-1004956'));
        $this->assertTrue(JapaneseChassisValidator::looksLikeJapaneseChassisNumber('BNR32-305366'));

        // Should NOT look like Japanese chassis numbers
        $this->assertFalse(JapaneseChassisValidator::looksLikeJapaneseChassisNumber('1HGCM82633A004352'));
        $this->assertFalse(JapaneseChassisValidator::looksLikeJapaneseChassisNumber('WVWZZZ3BZWE689725'));
        $this->assertFalse(JapaneseChassisValidator::looksLikeJapaneseChassisNumber('JZA801004956')); // No hyphen
    }

    /**
     * Test edge case - minimum length valid chassis number
     */
    public function testMinimumLengthChassisNumber()
    {
        // 9 chars: 2 char model + hyphen + 6 digit serial
        $this->assertTrue($this->validator->validate('AB-123456'));
    }

    /**
     * Test edge case - maximum length valid chassis number
     */
    public function testMaximumLengthChassisNumber()
    {
        // 14 chars: 6 char model + hyphen + 7 digit serial
        $this->assertTrue($this->validator->validate('ABCDEF-1234567'));

        // This would be 15 chars (7 char model) - invalid
        $this->assertFalse($this->validator->validate('ABCDEFG-1234567'));
    }

    /**
     * Test serial numbers with leading zeros are valid
     */
    public function testSerialWithLeadingZeros()
    {
        $this->assertTrue($this->validator->validate('JZA80-0000001'));
        $this->assertTrue($this->validator->validate('JZA80-0123456'));
    }
}
