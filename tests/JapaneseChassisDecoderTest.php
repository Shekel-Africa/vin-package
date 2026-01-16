<?php

namespace Shekel\VinPackage\Tests;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Decoders\JapaneseChassisDecoder;

class JapaneseChassisDecoderTest extends TestCase
{
    private JapaneseChassisDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new JapaneseChassisDecoder();
    }

    /**
     * Test decoding a known Toyota Supra chassis number
     */
    public function testDecodeToyotaSupra()
    {
        $result = $this->decoder->decode('JZA80-1004956');

        $this->assertEquals('Toyota', $result['make']);
        $this->assertEquals('Supra', $result['model']);
        $this->assertEquals('Toyota Motor Corporation', $result['manufacturer']);
        $this->assertEquals('Japan', $result['country']);
        $this->assertNotNull($result['engine']);
        $this->assertStringContainsString('2JZ', $result['engine']);

        // Check chassis structure
        $structure = $result['additional_info']['chassis_number_structure'];
        $this->assertEquals('JZA80', $structure['model_code']);
        $this->assertEquals('1004956', $structure['serial_number']);

        // Verify identifier type
        $this->assertEquals('japanese_chassis_number', $result['additional_info']['identifier_type']);
    }

    /**
     * Test decoding a known Nissan Skyline GT-R R32 chassis number
     */
    public function testDecodeNissanSkylineR32()
    {
        $result = $this->decoder->decode('BNR32-305366');

        $this->assertEquals('Nissan', $result['make']);
        $this->assertEquals('Skyline GT-R', $result['model']);
        $this->assertEquals('Nissan Motor Company', $result['manufacturer']);
        $this->assertEquals('Japan', $result['country']);

        $structure = $result['additional_info']['chassis_number_structure'];
        $this->assertEquals('BNR32', $structure['model_code']);
        $this->assertEquals('305366', $structure['serial_number']);
    }

    /**
     * Test decoding a known Honda Integra Type R chassis number
     */
    public function testDecodeHondaIntegra()
    {
        $result = $this->decoder->decode('DC2-1234567');

        $this->assertEquals('Honda', $result['make']);
        $this->assertEquals('Integra Type R', $result['model']);
        $this->assertEquals('Honda Motor Company', $result['manufacturer']);
    }

    /**
     * Test decoding a known Subaru WRX STI chassis number
     */
    public function testDecodeSubaruWRXSTI()
    {
        $result = $this->decoder->decode('GDB-123456');

        $this->assertEquals('Subaru', $result['make']);
        $this->assertEquals('Impreza WRX STI', $result['model']);
        $this->assertEquals('Subaru Corporation', $result['manufacturer']);
    }

    /**
     * Test decoding a known Mazda RX-7 chassis number
     */
    public function testDecodeMazdaRX7()
    {
        $result = $this->decoder->decode('FD3S-123456');

        $this->assertEquals('Mazda', $result['make']);
        $this->assertEquals('RX-7', $result['model']);
        $this->assertEquals('Mazda Motor Corporation', $result['manufacturer']);
        $this->assertStringContainsString('Rotary', $result['engine']);
    }

    /**
     * Test decoding a known Mitsubishi Lancer Evo chassis number
     */
    public function testDecodeMitsubishiEvo()
    {
        $result = $this->decoder->decode('CT9A-1234567');

        $this->assertEquals('Mitsubishi', $result['make']);
        $this->assertStringContainsString('Lancer Evolution', $result['model']);
        $this->assertEquals('Mitsubishi Motors Corporation', $result['manufacturer']);
    }

    /**
     * Test decoding an unknown model code returns partial info
     */
    public function testDecodeUnknownModelCode()
    {
        $result = $this->decoder->decode('ZZZ99-123456');

        // Should still return some structure info
        $this->assertArrayHasKey('additional_info', $result);
        $structure = $result['additional_info']['chassis_number_structure'];
        $this->assertEquals('ZZZ99', $structure['model_code']);
        $this->assertEquals('123456', $structure['serial_number']);

        // Should mark as partial info
        $this->assertTrue($result['additional_info']['local_decoder_info']['partial_info']);

        // Country should still be Japan (all JDM chassis numbers are from Japan)
        $this->assertEquals('Japan', $result['country']);
    }

    /**
     * Test decoding an invalid chassis number
     */
    public function testDecodeInvalidChassisNumber()
    {
        $result = $this->decoder->decode('invalid');

        $this->assertNull($result['make']);
        $this->assertNull($result['model']);
        $this->assertFalse($result['validation']['is_valid']);
        $this->assertNotNull($result['validation']['error_text']);
    }

    /**
     * Test year is always null for Japanese chassis numbers
     */
    public function testYearIsAlwaysNull()
    {
        $result = $this->decoder->decode('JZA80-1004956');
        $this->assertNull($result['year']);

        $result = $this->decoder->decode('BNR32-305366');
        $this->assertNull($result['year']);
    }

    /**
     * Test production years are available in additional info
     */
    public function testProductionYearsAvailable()
    {
        $result = $this->decoder->decode('JZA80-1004956');

        // Should have production years info
        $productionYears = $result['additional_info']['production_years'] ?? null;
        $this->assertNotNull($productionYears);
        $this->assertStringContainsString('1993', $productionYears);
        $this->assertStringContainsString('2002', $productionYears);
    }

    /**
     * Test decoder handles lowercase input
     */
    public function testDecoderHandlesLowercaseInput()
    {
        $result = $this->decoder->decode('jza80-1004956');

        $this->assertEquals('Toyota', $result['make']);
        $this->assertEquals('Supra', $result['model']);
    }

    /**
     * Test getting supported manufacturers
     */
    public function testGetSupportedManufacturers()
    {
        $manufacturers = $this->decoder->getSupportedManufacturers();

        $this->assertIsArray($manufacturers);
        $this->assertArrayHasKey('toyota', $manufacturers);
        $this->assertArrayHasKey('nissan', $manufacturers);
        $this->assertArrayHasKey('honda', $manufacturers);
        $this->assertArrayHasKey('subaru', $manufacturers);
        $this->assertArrayHasKey('mazda', $manufacturers);
        $this->assertArrayHasKey('mitsubishi', $manufacturers);
    }

    /**
     * Test getting model codes for a specific manufacturer
     */
    public function testGetModelCodesForManufacturer()
    {
        $toyotaCodes = $this->decoder->getModelCodesForManufacturer('toyota');

        $this->assertIsArray($toyotaCodes);
        $this->assertContains('JZA80', $toyotaCodes);
        $this->assertContains('AE86', $toyotaCodes);
    }

    /**
     * Test pattern inference for unknown model codes
     */
    public function testPatternInferenceForUnknownCodes()
    {
        // JZ prefix should be inferred as Toyota
        $result = $this->decoder->decode('JZ999-123456');
        $this->assertEquals('Toyota', $result['make']);

        // EC prefix should be inferred as Nissan
        $result = $this->decoder->decode('EC99-123456');
        $this->assertEquals('Nissan', $result['make']);
    }

    /**
     * Test fuel type inference
     */
    public function testFuelTypeInference()
    {
        // RX-7 (rotary) should be gasoline
        $result = $this->decoder->decode('FD3S-123456');
        $this->assertEquals('Gasoline', $result['fuel_type']);

        // Most JDM cars are gasoline
        $result = $this->decoder->decode('JZA80-1004956');
        $this->assertEquals('Gasoline', $result['fuel_type']);
    }

    /**
     * Test body style is returned when available
     */
    public function testBodyStyleReturned()
    {
        $result = $this->decoder->decode('JZA80-1004956');
        $this->assertEquals('Coupe', $result['body_style']);

        $result = $this->decoder->decode('GDB-123456');
        // GDB has multiple body styles, should return first one
        $this->assertNotNull($result['body_style']);
    }
}
