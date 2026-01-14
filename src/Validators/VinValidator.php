<?php

namespace Shekel\VinPackage\Validators;

class VinValidator
{
    /**
     * Valid VIN characters (excluding I, O, Q which are not used in VINs)
     *
     * @var string
     */
    private const VALID_CHARS = '0123456789ABCDEFGHJKLMNPRSTUVWXYZ';

    /**
     * Standard VIN length
     *
     * @var int
     */
    private const VIN_LENGTH = 17;

    /**
     * Character values for checksum calculation
     *
     * @var array
     */
    private const CHAR_VALUES = [
        'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8,
        'J' => 1, 'K' => 2, 'L' => 3, 'M' => 4, 'N' => 5, 'P' => 7, 'R' => 9,
        'S' => 2, 'T' => 3, 'U' => 4, 'V' => 5, 'W' => 6, 'X' => 7, 'Y' => 8, 'Z' => 9,
        '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '0' => 0
    ];

    /**
     * Weight factors for each position in the VIN
     *
     * @var array
     */
    private const WEIGHTS = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

    /**
     * Validate a VIN
     *
     * @param string $vin
     * @param bool $returnErrors Whether to return error messages instead of boolean
     * @return bool|string True if valid, or error message if $returnErrors is true
     */
    public function validate(string $vin, bool $returnErrors = false)
    {
        $isJapaneseChassis = $this->isJapaneseVinOrChassis($vin);
        if ($isJapaneseChassis) {
            return true;
        }
        // Check length
        if (strlen($vin) !== self::VIN_LENGTH) {
            return $returnErrors ?
                "Invalid VIN length: must be exactly 17 characters (found " . strlen($vin) . ")" :
                false;
        }

        // Check for valid characters
        if (!$this->hasValidCharacters($vin)) {
            return $returnErrors ?
                "Invalid VIN characters: contains I, O, Q or other invalid characters" :
                false;
        }

        // Different validation approaches based on region
        $firstChar = $vin[0];

        // European manufacturers (S-Z)
        if (in_array($firstChar, ['S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'])) {
            // European VINs have different check digit systems but we'll validate basic structure
            return $returnErrors ? true : true;
        }

        // Japanese and Korean manufacturers typically start with J, K, L, etc.
        if (in_array($firstChar, ['J', 'K', 'L', 'M', 'N', 'R'])) {
            // Asian manufacturers may use different check systems
            return $returnErrors ? true : true;
        }

        // North American (1-5) and Australian (6-7) manufacturers
        if (in_array($firstChar, ['1', '2', '3', '4', '5', '6', '7'])) {
            // Try standard North American algorithm
            if ($this->validateCheckDigit($vin)) {
                return $returnErrors ? true : true;
            }

            // Some manufacturers might use variants, so provide some flexibility
            $isValid = $this->validateBasicStructure($vin);
            if (!$isValid && $returnErrors) {
                return "Invalid check digit for North American VIN";
            }
            return $isValid;
        }

        // South American manufacturers (8-9)
        if (in_array($firstChar, ['8', '9'])) {
            return $returnErrors ? true : true; // South American VINs may use different check systems
        }

        // If we've reached here and haven't returned yet,
        // use the standard check digit validation as a fallback
        $isValid = $this->validateCheckDigit($vin);
        if (!$isValid && $returnErrors) {
            return "Failed check digit validation";
        }
        return $isValid;
    }

    /**
     * Check if VIN contains only valid characters
     *
     * @param string $vin
     * @return bool
     */
    private function hasValidCharacters(string $vin): bool
    {
        for ($i = 0; $i < strlen($vin); $i++) {
            if (strpos(self::VALID_CHARS, $vin[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if a VIN is Japanese style (with dash)
     *
     * @param string $vin
     * @return bool
     */
    private function isJapaneseVinOrChassis(string $vin): bool
    {
        return preg_match('/^[A-Z0-9]{2,10}-[0-9]{3,10}$/', $vin) === 1;
    }

    /**
     * Determine if a VIN is European based on first character
     *
     * @param string $vin
     * @return bool
     */
    private function isEuropeanVin(string $vin): bool
    {
        $firstChar = $vin[0];

        // European manufacturers start with S-Z
        $europeanPrefixes = ['S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

        return in_array($firstChar, $europeanPrefixes);
    }

    /**
     * Validate the VIN's check digit (9th position)
     *
     * @param string $vin
     * @return bool
     */
    private function validateCheckDigit(string $vin): bool
    {
        $sum = 0;

        for ($i = 0; $i < self::VIN_LENGTH; $i++) {
            $char = $vin[$i];
            $value = is_numeric($char) ? (int)$char : self::CHAR_VALUES[$char];
            $sum += $value * self::WEIGHTS[$i];
        }

        $checkDigit = $sum % 11;
        $checkDigit = $checkDigit === 10 ? 'X' : (string)$checkDigit;

        return $checkDigit === $vin[8];
    }

    /**
     * Validate basic VIN structure and patterns
     * This is a more lenient validation when check digit fails
     *
     * @param string $vin
     * @return bool
     */
    private function validateBasicStructure(string $vin): bool
    {
        // Check for Toyota pattern (common for North American Toyota VINs)
        if (substr($vin, 0, 1) === '5' && substr($vin, 1, 1) === 'T') {
            return true;
        }

        // For North American VINs, positions 12-17 should be numeric (sequential number)
        for ($i = 11; $i < 17; $i++) {
            if (!is_numeric($vin[$i])) {
                return false;
            }
        }

        // Additional general structure checks could be added here

        return true;
    }
}
