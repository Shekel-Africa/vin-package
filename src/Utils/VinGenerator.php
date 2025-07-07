<?php

namespace Shekel\VinPackage\Utils;

use Shekel\VinPackage\Validators\VinValidator;

/**
 * Utility class for generating valid VINs for testing purposes
 */
class VinGenerator
{
    /**
     * Common World Manufacturer Identifiers (WMIs)
     */
    private const COMMON_WMIS = [
        'US' => ['1FA', '1G1', '1HD', '1J4', '2FA', '3FA', '5YJ'],  // United States
        'EU' => ['WVW', 'WAU', 'WBA', 'WDD', 'VF1', 'ZFA', 'VSK'],  // Europe
        'JP' => ['JHM', 'JN1', 'JT1', 'JF1', 'JM1', 'JS1', 'JYA'],  // Japan
        'KR' => ['KMH', 'KNA', 'KND', 'KPT'],                        // South Korea
        'CN' => ['LFV', 'LBV', 'LDC', 'LGW', 'LVS'],                 // China
    ];

    /**
     * Valid year characters
     */
    private const YEAR_CHARS = [
        '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N'
    ];

    /**
     * Valid characters for VIN (excluding I, O, Q)
     */
    private const VALID_CHARS = '0123456789ABCDEFGHJKLMNPRSTUVWXYZ';

    /**
     * VIN validator for verification
     */
    private VinValidator $validator;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->validator = new VinValidator();
    }

    /**
     * Generate a random valid VIN
     *
     * @param string $region Region code (US, EU, JP, KR, CN)
     * @return string A valid VIN
     */
    public function generateVin(string $region = 'US'): string
    {
        // Default to US if region is not recognized
        $region = in_array($region, ['US', 'EU', 'JP', 'KR', 'CN']) ? $region : 'US';

        // Try to generate a valid VIN
        for ($attempts = 0; $attempts < 100; $attempts++) {
            $vin = $this->createRandomVin($region);

            if ($this->validator->validate($vin)) {
                return $vin;
            }
        }

        // If we couldn't generate a valid VIN after multiple attempts,
        // return a known valid VIN for the requested region
        return $this->getKnownValidVin($region);
    }

    /**
     * Generate a list of valid VINs
     *
     * @param int $count Number of VINs to generate
     * @param string $region Region code
     * @return array Array of valid VINs
     */
    public function generateMultipleVins(int $count, string $region = 'US'): array
    {
        $vins = [];

        for ($i = 0; $i < $count; $i++) {
            $vins[] = $this->generateVin($region);
        }

        return $vins;
    }

    /**
     * Create a random VIN
     *
     * @param string $region Region code
     * @return string A potentially valid VIN
     */
    private function createRandomVin(string $region): string
    {
        // Get a random WMI from the specified region
        $wmis = self::COMMON_WMIS[$region];
        $wmi = $wmis[array_rand($wmis)];

        // Generate random Vehicle Descriptor Section (positions 4-8)
        $vds = '';
        for ($i = 0; $i < 5; $i++) {
            $vds .= $this->getRandomVinChar();
        }

        // Position 9 is check digit, will be replaced later

        // Position 10 is the year
        $yearChar = self::YEAR_CHARS[array_rand(self::YEAR_CHARS)];

        // Position 11 is typically the plant code
        // Use a valid character from the VIN character set to avoid 'I', 'O', 'Q'
        $plantCode = $this->getRandomVinChar();

        // Positions 12-17 are the sequential number
        $sequentialNumber = '';
        for ($i = 0; $i < 6; $i++) {
            $sequentialNumber .= random_int(0, 9);
        }

        // Combine parts (with X as a placeholder for check digit)
        $vin = $wmi . $vds . 'X' . $yearChar . $plantCode . $sequentialNumber;

        // Calculate and set the correct check digit
        return $this->setCheckDigit($vin);
    }

    /**
     * Replace the check digit in a VIN with the correct value
     *
     * @param string $vin VIN with placeholder check digit
     * @return string VIN with correct check digit
     */
    private function setCheckDigit(string $vin): string
    {
        // Character values for check digit calculation
        $transliterationValues = [
            'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8,
            'J' => 1, 'K' => 2, 'L' => 3, 'M' => 4, 'N' => 5, 'P' => 7, 'R' => 9,
            'S' => 2, 'T' => 3, 'U' => 4, 'V' => 5, 'W' => 6, 'X' => 7, 'Y' => 8, 'Z' => 9,
            '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '0' => 0
        ];

        // Weights for each position in the VIN
        $weights = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;

        // Calculate weighted sum (ignoring position 8 which is the check digit)
        for ($i = 0; $i < 17; $i++) {
            if ($i != 8) { // Skip the check digit position
                $char = $vin[$i];

                // Make sure we have a valid character (shouldn't have I, O, Q)
                if (!isset($transliterationValues[$char]) && !is_numeric($char)) {
                    // Replace invalid characters with similar valid ones
                    if ($char == 'I') {
                        $char = '1';
                    } elseif ($char == 'O') {
                        $char = '0';
                    } elseif ($char == 'Q') {
                        $char = '9';
                    }
                }

                $value = is_numeric($char) ? (int)$char : $transliterationValues[$char];
                $sum += $value * $weights[$i];
            }
        }

        // Calculate check digit
        $checkDigit = $sum % 11;
        $checkDigitChar = ($checkDigit == 10) ? 'X' : (string)$checkDigit;

        // Replace the check digit in the VIN
        return substr($vin, 0, 8) . $checkDigitChar . substr($vin, 9);
    }

    /**
     * Get a random valid character for a VIN
     *
     * @return string A single valid VIN character
     */
    private function getRandomVinChar(): string
    {
        return self::VALID_CHARS[random_int(0, strlen(self::VALID_CHARS) - 1)];
    }

    /**
     * Get a known valid VIN for a region (fallback)
     *
     * @param string $region Region code
     * @return string A known valid VIN
     */
    private function getKnownValidVin(string $region): string
    {
        $knownVins = [
            'US' => '1HGCM82633A004352', // Honda Accord
            'EU' => 'WVWZZZ3BZWE689725', // Volkswagen Golf
            'JP' => 'JN1BZ4BH4DM180724', // Nissan 370Z
            'KR' => 'KMHDN45D02U438032', // Hyundai Elantra
            'CN' => 'LFV2A21K573115012', // Chery
        ];

        return $knownVins[$region];
    }
}
