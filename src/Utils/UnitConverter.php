<?php

namespace Shekel\VinPackage\Utils;

/**
 * Utility class for converting between different unit systems
 */
class UnitConverter
{
    /**
     * Conversion constants for length measurements
     */
    private const INCH_TO_CM = 2.54;
    private const INCH_TO_MM = 25.4;
    private const FOOT_TO_METER = 0.3048;

    /**
     * Conversion constants for weight measurements
     */
    private const LB_TO_KG = 0.453592;

    /**
     * Conversion constants for volume measurements
     */
    private const CUBIC_FOOT_TO_LITER = 28.3168;
    private const GALLON_TO_LITER = 3.78541;

    /**
     * Convert inches to centimeters
     *
     * @param float $inches
     * @param int $precision
     * @return float
     */
    public static function inchesToCm(float $inches, int $precision = 1): float
    {
        return round($inches * self::INCH_TO_CM, $precision);
    }

    /**
     * Convert inches to millimeters
     *
     * @param float $inches
     * @param int $precision
     * @return float
     */
    public static function inchesToMm(float $inches, int $precision = 0): float
    {
        return round($inches * self::INCH_TO_MM, $precision);
    }

    /**
     * Convert centimeters to inches
     *
     * @param float $cm
     * @param int $precision
     * @return float
     */
    public static function cmToInches(float $cm, int $precision = 1): float
    {
        return round($cm / self::INCH_TO_CM, $precision);
    }

    /**
     * Convert millimeters to inches
     *
     * @param float $mm
     * @param int $precision
     * @return float
     */
    public static function mmToInches(float $mm, int $precision = 1): float
    {
        return round($mm / self::INCH_TO_MM, $precision);
    }

    /**
     * Convert feet to meters
     *
     * @param float $feet
     * @param int $precision
     * @return float
     */
    public static function feetToMeters(float $feet, int $precision = 2): float
    {
        return round($feet * self::FOOT_TO_METER, $precision);
    }

    /**
     * Convert meters to feet
     *
     * @param float $meters
     * @param int $precision
     * @return float
     */
    public static function metersToFeet(float $meters, int $precision = 1): float
    {
        return round($meters / self::FOOT_TO_METER, $precision);
    }

    /**
     * Convert pounds to kilograms
     *
     * @param float $pounds
     * @param int $precision
     * @return float
     */
    public static function lbsToKg(float $pounds, int $precision = 1): float
    {
        return round($pounds * self::LB_TO_KG, $precision);
    }

    /**
     * Convert kilograms to pounds
     *
     * @param float $kg
     * @param int $precision
     * @return float
     */
    public static function kgToLbs(float $kg, int $precision = 1): float
    {
        return round($kg / self::LB_TO_KG, $precision);
    }

    /**
     * Convert cubic feet to liters
     *
     * @param float $cubicFeet
     * @param int $precision
     * @return float
     */
    public static function cubicFeetToLiters(float $cubicFeet, int $precision = 1): float
    {
        return round($cubicFeet * self::CUBIC_FOOT_TO_LITER, $precision);
    }

    /**
     * Convert liters to cubic feet
     *
     * @param float $liters
     * @param int $precision
     * @return float
     */
    public static function litersToCubicFeet(float $liters, int $precision = 1): float
    {
        return round($liters / self::CUBIC_FOOT_TO_LITER, $precision);
    }

    /**
     * Convert miles per gallon to liters per 100 kilometers
     *
     * @param float $mpg
     * @param int $precision
     * @return float
     */
    public static function mpgToL100km(float $mpg, int $precision = 1): float
    {
        if ($mpg <= 0) {
            return 0;
        }

        // Formula: L/100km = 235.214583 / mpg
        return round(235.214583 / $mpg, $precision);
    }

    /**
     * Convert liters per 100 kilometers to miles per gallon
     *
     * @param float $l100km
     * @param int $precision
     * @return float
     */
    public static function l100kmToMpg(float $l100km, int $precision = 1): float
    {
        if ($l100km <= 0) {
            return 0;
        }

        // Formula: mpg = 235.214583 / (L/100km)
        return round(235.214583 / $l100km, $precision);
    }

    /**
     * Parse a dimensional string and extract numeric value and unit
     *
     * @param string $dimensionString
     * @return array{value: float|null, unit: string|null, original: string}
     */
    public static function parseDimensionString(string $dimensionString): array
    {
        $trimmed = trim($dimensionString);

        if (empty($trimmed) || $trimmed === 'N/A') {
            return [
                'value' => null,
                'unit' => null,
                'original' => $dimensionString
            ];
        }

        // Match patterns like "200.20 in", "78.10 inches", "5.2 ft", "18 miles/gallon", "2500 lbs"
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([a-zA-Z\/]+)/', $trimmed, $matches)) {
            return [
                'value' => (float) $matches[1],
                'unit' => trim($matches[2]),
                'original' => $dimensionString
            ];
        }

        return [
            'value' => null,
            'unit' => null,
            'original' => $dimensionString
        ];
    }

    /**
     * Convert a dimensional measurement to different units
     *
     * @param float $value
     * @param string $fromUnit
     * @param string $toUnit
     * @param int $precision
     * @return float|null
     */
    public static function convert(float $value, string $fromUnit, string $toUnit, int $precision = 1): ?float
    {
        $fromUnit = strtolower(trim($fromUnit));
        $toUnit = strtolower(trim($toUnit));

        if ($fromUnit === $toUnit) {
            return round($value, $precision);
        }

        // Length conversions
        switch ($fromUnit) {
            case 'in':
            case 'inch':
            case 'inches':
                switch ($toUnit) {
                    case 'cm':
                        return self::inchesToCm($value, $precision);
                    case 'mm':
                        return self::inchesToMm($value, $precision);
                }
                break;

            case 'cm':
                switch ($toUnit) {
                    case 'in':
                    case 'inch':
                    case 'inches':
                        return self::cmToInches($value, $precision);
                }
                break;

            case 'mm':
                switch ($toUnit) {
                    case 'in':
                    case 'inch':
                    case 'inches':
                        return self::mmToInches($value, $precision);
                }
                break;

            case 'ft':
            case 'feet':
                switch ($toUnit) {
                    case 'm':
                    case 'meter':
                    case 'meters':
                        return self::feetToMeters($value, $precision);
                }
                break;

            case 'm':
            case 'meter':
            case 'meters':
                switch ($toUnit) {
                    case 'ft':
                    case 'feet':
                        return self::metersToFeet($value, $precision);
                }
                break;

            // Weight conversions
            case 'lbs':
            case 'lb':
            case 'pounds':
                switch ($toUnit) {
                    case 'kg':
                    case 'kilograms':
                        return self::lbsToKg($value, $precision);
                }
                break;

            case 'kg':
            case 'kilograms':
                switch ($toUnit) {
                    case 'lbs':
                    case 'lb':
                    case 'pounds':
                        return self::kgToLbs($value, $precision);
                }
                break;

            // Volume conversions
            case 'cubic feet':
            case 'cu ft':
                switch ($toUnit) {
                    case 'liters':
                    case 'l':
                        return self::cubicFeetToLiters($value, $precision);
                }
                break;

            case 'liters':
            case 'l':
                switch ($toUnit) {
                    case 'cubic feet':
                    case 'cu ft':
                        return self::litersToCubicFeet($value, $precision);
                }
                break;

            // Fuel economy conversions
            case 'miles/gallon':
            case 'mpg':
                switch ($toUnit) {
                    case 'l/100km':
                    case 'liters/100km':
                        return self::mpgToL100km($value, $precision);
                }
                break;

            case 'l/100km':
            case 'liters/100km':
                switch ($toUnit) {
                    case 'miles/gallon':
                    case 'mpg':
                        return self::l100kmToMpg($value, $precision);
                }
                break;
        }

        return null; // Conversion not supported
    }

    /**
     * Get common unit abbreviations for a given unit system
     *
     * @param string $system 'imperial' or 'metric'
     * @return array
     */
    public static function getCommonUnits(string $system = 'metric'): array
    {
        switch (strtolower($system)) {
            case 'imperial':
                return [
                    'length' => ['in', 'ft'],
                    'weight' => ['lbs'],
                    'volume' => ['cubic feet'],
                    'fuel_economy' => ['mpg']
                ];

            case 'metric':
                return [
                    'length' => ['cm', 'mm', 'm'],
                    'weight' => ['kg'],
                    'volume' => ['liters'],
                    'fuel_economy' => ['l/100km']
                ];

            default:
                return [];
        }
    }

    /**
     * Format a converted value with appropriate unit suffix
     *
     * @param float $value
     * @param string $unit
     * @param int $precision
     * @return string
     */
    public static function formatValue(float $value, string $unit, int $precision = 1): string
    {
        return number_format($value, $precision) . ' ' . $unit;
    }
}
