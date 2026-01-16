<?php

namespace Shekel\VinPackage\Validators;

/**
 * Validator for Japanese Domestic Market (JDM) chassis numbers
 *
 * Japanese chassis numbers (frame numbers) have a different format than
 * international 17-character VINs. They typically consist of:
 * - A model code (2-5 alphanumeric characters)
 * - A hyphen separator
 * - A serial number (6-7 digits)
 *
 * Example: JZA80-1004956 (Toyota Supra)
 */
class JapaneseChassisValidator
{
    /**
     * Valid characters for Japanese chassis numbers
     * Note: Unlike VINs, Japanese chassis numbers allow I, O, Q
     *
     * @var string
     */
    private const VALID_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-';

    /**
     * Minimum total length of chassis number
     *
     * @var int
     */
    private const MIN_LENGTH = 9;

    /**
     * Maximum total length of chassis number
     * Includes model code (2-6), hyphen (1), and serial (6-7)
     * Max = 6 + 1 + 7 = 14
     *
     * @var int
     */
    private const MAX_LENGTH = 14;

    /**
     * Minimum length for model code (before hyphen)
     *
     * @var int
     */
    private const MIN_MODEL_CODE_LENGTH = 2;

    /**
     * Maximum length for model code (before hyphen)
     * Some manufacturers use up to 6 characters (e.g., BCNR33 for Nissan Skyline GT-R R33)
     *
     * @var int
     */
    private const MAX_MODEL_CODE_LENGTH = 6;

    /**
     * Minimum length for serial number (after hyphen)
     *
     * @var int
     */
    private const MIN_SERIAL_LENGTH = 6;

    /**
     * Maximum length for serial number (after hyphen)
     *
     * @var int
     */
    private const MAX_SERIAL_LENGTH = 7;

    /**
     * Pattern for validating Japanese chassis numbers
     * Format: MODEL_CODE-SERIAL_NUMBER
     *
     * @var string
     */
    private const PATTERN = '/^([A-Z0-9]{2,6})-([0-9]{6,7})$/';

    /**
     * Validate a Japanese chassis number
     *
     * @param string $chassisNumber
     * @param bool $returnErrors Whether to return error messages instead of boolean
     * @return bool|string True if valid, or error message if $returnErrors is true
     */
    public function validate(string $chassisNumber, bool $returnErrors = false)
    {
        // Normalize input
        $chassisNumber = strtoupper(trim($chassisNumber));

        // Check for valid characters
        if (!$this->hasValidCharacters($chassisNumber)) {
            return $returnErrors ?
                "Invalid chassis number characters: contains invalid characters" :
                false;
        }

        // Check total length
        $length = strlen($chassisNumber);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return $returnErrors ?
                "Invalid chassis number length: must be between " . self::MIN_LENGTH .
                " and " . self::MAX_LENGTH . " characters (found {$length})" :
                false;
        }

        // Check for hyphen separator
        if (strpos($chassisNumber, '-') === false) {
            return $returnErrors ?
                "Invalid chassis number format: missing hyphen separator" :
                false;
        }

        // Check for multiple hyphens
        if (substr_count($chassisNumber, '-') > 1) {
            return $returnErrors ?
                "Invalid chassis number format: multiple hyphens found" :
                false;
        }

        // Split by hyphen and validate parts
        $parts = explode('-', $chassisNumber);
        $modelCode = $parts[0];
        $serialNumber = $parts[1];

        // Validate model code length
        $modelCodeLength = strlen($modelCode);
        if ($modelCodeLength < self::MIN_MODEL_CODE_LENGTH || $modelCodeLength > self::MAX_MODEL_CODE_LENGTH) {
            return $returnErrors ?
                "Invalid model code length: must be between " . self::MIN_MODEL_CODE_LENGTH .
                " and " . self::MAX_MODEL_CODE_LENGTH . " characters (found {$modelCodeLength})" :
                false;
        }

        // Validate model code is alphanumeric
        if (!preg_match('/^[A-Z0-9]+$/', $modelCode)) {
            return $returnErrors ?
                "Invalid model code: must contain only letters and numbers" :
                false;
        }

        // Validate serial number length
        $serialLength = strlen($serialNumber);
        if ($serialLength < self::MIN_SERIAL_LENGTH || $serialLength > self::MAX_SERIAL_LENGTH) {
            return $returnErrors ?
                "Invalid serial number length: must be between " . self::MIN_SERIAL_LENGTH .
                " and " . self::MAX_SERIAL_LENGTH . " digits (found {$serialLength})" :
                false;
        }

        // Validate serial number is numeric
        if (!ctype_digit($serialNumber)) {
            return $returnErrors ?
                "Invalid serial number: must contain only digits" :
                false;
        }

        // Final validation using regex pattern
        if (!preg_match(self::PATTERN, $chassisNumber)) {
            return $returnErrors ?
                "Invalid chassis number format" :
                false;
        }

        return $returnErrors ? true : true;
    }

    /**
     * Check if chassis number contains only valid characters
     *
     * @param string $chassisNumber
     * @return bool
     */
    private function hasValidCharacters(string $chassisNumber): bool
    {
        for ($i = 0; $i < strlen($chassisNumber); $i++) {
            if (strpos(self::VALID_CHARS, $chassisNumber[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a string looks like a Japanese chassis number
     * Used for auto-detection between VIN and chassis number
     *
     * @param string $identifier
     * @return bool
     */
    public static function looksLikeJapaneseChassisNumber(string $identifier): bool
    {
        $identifier = strtoupper(trim($identifier));

        // Quick checks before full validation:
        // 1. Must contain a hyphen
        if (strpos($identifier, '-') === false) {
            return false;
        }

        // 2. Length should be 9-14 characters
        $length = strlen($identifier);
        if ($length < 9 || $length > 14) {
            return false;
        }

        // 3. Should match the general pattern
        if (!preg_match('/^[A-Z0-9]{2,6}-[0-9]{6,7}$/', $identifier)) {
            return false;
        }

        return true;
    }

    /**
     * Parse a chassis number into its components
     *
     * @param string $chassisNumber
     * @return array|null Returns array with 'model_code' and 'serial_number', or null if invalid
     */
    public function parse(string $chassisNumber): ?array
    {
        $chassisNumber = strtoupper(trim($chassisNumber));

        if (!$this->validate($chassisNumber)) {
            return null;
        }

        $parts = explode('-', $chassisNumber);

        return [
            'model_code' => $parts[0],
            'serial_number' => $parts[1],
        ];
    }
}
