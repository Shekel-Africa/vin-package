<?php

namespace Shekel\VinPackage;

use Shekel\VinPackage\Contracts\VehicleIdentifierInterface;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\Validators\JapaneseChassisValidator;

/**
 * Factory for creating vehicle identifier objects
 *
 * Provides auto-detection to determine whether an identifier is an international VIN
 * or a Japanese chassis number, and returns the appropriate handler.
 *
 * Example usage:
 * ```php
 * // Auto-detect and create appropriate handler
 * $vehicle = VehicleIdentifierFactory::create('JZA80-1004956');  // Returns JapaneseChassisNumber
 * $vehicle = VehicleIdentifierFactory::create('1HGBH41JXMN109186'); // Returns Vin
 *
 * // Check the type
 * echo $vehicle->getIdentifierType(); // 'japanese_chassis_number' or 'vin'
 *
 * // Use the common interface
 * if ($vehicle->isValid()) {
 *     $info = $vehicle->getVehicleInfo();
 * }
 * ```
 */
class VehicleIdentifierFactory
{
    /**
     * Create a vehicle identifier object based on auto-detection
     *
     * @param string $identifier The VIN or chassis number to process
     * @param VinCacheInterface|null $cache Cache implementation for VIN decoding (optional)
     * @param int|null $cacheTtl Cache TTL in seconds for VIN decoding (optional)
     * @param bool $useLocalFallback Whether to use local decoder as fallback for VIN (optional)
     * @return VehicleIdentifierInterface
     */
    public static function create(
        string $identifier,
        ?VinCacheInterface $cache = null,
        ?int $cacheTtl = null,
        bool $useLocalFallback = true
    ): VehicleIdentifierInterface {
        $identifier = strtoupper(trim($identifier));

        if (self::isJapaneseChassisNumber($identifier)) {
            return new JapaneseChassisNumber($identifier);
        }

        return new Vin($identifier, null, $cache, $cacheTtl, $useLocalFallback);
    }

    /**
     * Create a VIN object explicitly (bypass auto-detection)
     *
     * @param string $vin The VIN string
     * @param VinCacheInterface|null $cache Cache implementation (optional)
     * @param int|null $cacheTtl Cache TTL in seconds (optional)
     * @param bool $useLocalFallback Whether to use local decoder as fallback (optional)
     * @return Vin
     */
    public static function createVin(
        string $vin,
        ?VinCacheInterface $cache = null,
        ?int $cacheTtl = null,
        bool $useLocalFallback = true
    ): Vin {
        return new Vin($vin, null, $cache, $cacheTtl, $useLocalFallback);
    }

    /**
     * Create a JapaneseChassisNumber object explicitly (bypass auto-detection)
     *
     * @param string $chassisNumber The chassis number string
     * @return JapaneseChassisNumber
     */
    public static function createJapaneseChassis(string $chassisNumber): JapaneseChassisNumber
    {
        return new JapaneseChassisNumber($chassisNumber);
    }

    /**
     * Detect the type of vehicle identifier
     *
     * @param string $identifier The identifier to check
     * @return string One of: 'japanese_chassis_number', 'vin', 'unknown'
     */
    public static function detectType(string $identifier): string
    {
        $identifier = strtoupper(trim($identifier));

        if (self::isJapaneseChassisNumber($identifier)) {
            return 'japanese_chassis_number';
        }

        if (self::isVin($identifier)) {
            return 'vin';
        }

        return 'unknown';
    }

    /**
     * Check if the identifier looks like a Japanese chassis number
     *
     * @param string $identifier
     * @return bool
     */
    public static function isJapaneseChassisNumber(string $identifier): bool
    {
        return JapaneseChassisValidator::looksLikeJapaneseChassisNumber($identifier);
    }

    /**
     * Check if the identifier looks like an international VIN
     *
     * @param string $identifier
     * @return bool
     */
    public static function isVin(string $identifier): bool
    {
        $identifier = strtoupper(trim($identifier));

        // VINs are exactly 17 characters
        if (strlen($identifier) !== 17) {
            return false;
        }

        // VINs should not contain hyphens
        if (strpos($identifier, '-') !== false) {
            return false;
        }

        // VINs should only contain valid characters (no I, O, Q)
        $validChars = '0123456789ABCDEFGHJKLMNPRSTUVWXYZ';
        for ($i = 0; $i < strlen($identifier); $i++) {
            if (strpos($validChars, $identifier[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get detailed detection result
     *
     * @param string $identifier
     * @return array Array with 'type', 'confidence', and 'reasons' keys
     */
    public static function analyzeIdentifier(string $identifier): array
    {
        $identifier = strtoupper(trim($identifier));
        $length = strlen($identifier);
        $hasHyphen = strpos($identifier, '-') !== false;
        $reasons = [];

        // Check for Japanese chassis number characteristics
        $japaneseScore = 0;
        if ($hasHyphen) {
            $japaneseScore += 40;
            $reasons[] = 'Contains hyphen separator';
        }
        if ($length >= 9 && $length <= 12) {
            $japaneseScore += 30;
            $reasons[] = 'Length is 9-12 characters';
        }
        if (JapaneseChassisValidator::looksLikeJapaneseChassisNumber($identifier)) {
            $japaneseScore += 30;
            $reasons[] = 'Matches Japanese chassis number pattern';
        }

        // Check for VIN characteristics
        $vinScore = 0;
        if ($length === 17) {
            $vinScore += 50;
            $reasons[] = 'Length is exactly 17 characters';
        }
        if (!$hasHyphen) {
            $vinScore += 20;
            $reasons[] = 'No hyphen (VIN format)';
        }
        if (self::isVin($identifier)) {
            $vinScore += 30;
            $reasons[] = 'Matches VIN character requirements';
        }

        // Determine type and confidence
        if ($japaneseScore > $vinScore) {
            return [
                'type' => 'japanese_chassis_number',
                'confidence' => min(100, $japaneseScore),
                'reasons' => $reasons,
            ];
        } elseif ($vinScore > $japaneseScore) {
            return [
                'type' => 'vin',
                'confidence' => min(100, $vinScore),
                'reasons' => $reasons,
            ];
        } else {
            return [
                'type' => 'unknown',
                'confidence' => 0,
                'reasons' => array_merge($reasons, ['Unable to determine identifier type']),
            ];
        }
    }
}
