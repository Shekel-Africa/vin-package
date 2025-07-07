<?php

namespace Shekel\VinPackage\ValueObjects;

use Shekel\VinPackage\Utils\UnitConverter;

/**
 * Value object representing a dimensional measurement with units
 */
class DimensionalValue
{
    private ?float $value;
    private ?string $unit;
    private string $originalString;

    public function __construct(?float $value, ?string $unit, string $originalString = '')
    {
        $this->value = $value;
        $this->unit = $unit ? strtolower(trim($unit)) : null;
        $this->originalString = $originalString;
    }

    /**
     * Create a DimensionalValue from a string like "200.20 in"
     *
     * @param string $dimensionString
     * @return self
     */
    public static function fromString(string $dimensionString): self
    {
        $parsed = UnitConverter::parseDimensionString($dimensionString);
        
        return new self(
            $parsed['value'],
            $parsed['unit'],
            $parsed['original']
        );
    }

    /**
     * Create a DimensionalValue with explicit value and unit
     *
     * @param float $value
     * @param string $unit
     * @return self
     */
    public static function create(float $value, string $unit): self
    {
        return new self($value, $unit, UnitConverter::formatValue($value, $unit));
    }

    /**
     * Get the numeric value
     *
     * @return float|null
     */
    public function getValue(): ?float
    {
        return $this->value;
    }

    /**
     * Get the unit
     *
     * @return string|null
     */
    public function getUnit(): ?string
    {
        return $this->unit;
    }

    /**
     * Get the original string representation
     *
     * @return string
     */
    public function getOriginalString(): string
    {
        return $this->originalString;
    }

    /**
     * Check if this dimensional value has valid data
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->value !== null && $this->unit !== null;
    }

    /**
     * Convert to a different unit
     *
     * @param string $targetUnit
     * @param int $precision
     * @return self|null Returns null if conversion is not possible
     */
    public function convertTo(string $targetUnit, int $precision = 1): ?self
    {
        if (!$this->isValid()) {
            return null;
        }

        $convertedValue = UnitConverter::convert($this->value, $this->unit, $targetUnit, $precision);
        
        if ($convertedValue === null) {
            return null;
        }

        return self::create($convertedValue, $targetUnit);
    }

    /**
     * Get imperial representation (if possible)
     *
     * @param int $precision
     * @return self|null
     */
    public function toImperial(int $precision = 1): ?self
    {
        if (!$this->isValid()) {
            return null;
        }

        // Map metric units to imperial equivalents
        $imperialMappings = [
            'cm' => 'in',
            'mm' => 'in',
            'm' => 'ft',
            'meters' => 'ft',
            'kg' => 'lbs',
            'kilograms' => 'lbs',
            'liters' => 'cubic feet',
            'l' => 'cubic feet',
            'l/100km' => 'mpg',
            'liters/100km' => 'mpg'
        ];

        $targetUnit = $imperialMappings[$this->unit] ?? null;
        
        if ($targetUnit) {
            return $this->convertTo($targetUnit, $precision);
        }

        // If already imperial or no mapping, return self
        return $this;
    }

    /**
     * Get metric representation (if possible)
     *
     * @param int $precision
     * @return self|null
     */
    public function toMetric(int $precision = 1): ?self
    {
        if (!$this->isValid()) {
            return null;
        }

        // Map imperial units to metric equivalents
        $metricMappings = [
            'in' => 'cm',
            'inch' => 'cm',
            'inches' => 'cm',
            'ft' => 'm',
            'feet' => 'm',
            'lbs' => 'kg',
            'lb' => 'kg',
            'pounds' => 'kg',
            'cubic feet' => 'liters',
            'cu ft' => 'liters',
            'mpg' => 'l/100km',
            'miles/gallon' => 'l/100km'
        ];

        $targetUnit = $metricMappings[$this->unit] ?? null;
        
        if ($targetUnit) {
            return $this->convertTo($targetUnit, $precision);
        }

        // If already metric or no mapping, return self
        return $this;
    }

    /**
     * Format the value with unit
     *
     * @param int $precision
     * @return string
     */
    public function format(int $precision = 1): string
    {
        if (!$this->isValid()) {
            return $this->originalString ?: 'N/A';
        }

        return UnitConverter::formatValue($this->value, $this->unit, $precision);
    }

    /**
     * Get array representation
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'unit' => $this->unit,
            'original' => $this->originalString,
            'formatted' => $this->format()
        ];
    }

    /**
     * String representation returns the formatted value
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * JSON serialization
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Check if two dimensional values are approximately equal
     *
     * @param DimensionalValue $other
     * @param float $tolerance
     * @return bool
     */
    public function equals(DimensionalValue $other, float $tolerance = 0.01): bool
    {
        if (!$this->isValid() || !$other->isValid()) {
            return false;
        }

        if ($this->unit === $other->unit) {
            return abs($this->value - $other->value) <= $tolerance;
        }

        // Try to convert other to this unit for comparison
        $converted = $other->convertTo($this->unit);
        if ($converted && $converted->isValid()) {
            return abs($this->value - $converted->value) <= $tolerance;
        }

        return false;
    }

    /**
     * Create a dimensional value collection from an array of string values
     *
     * @param array $dimensions Array with keys like 'length', 'width', etc.
     * @return array
     */
    public static function fromArray(array $dimensions): array
    {
        $result = [];
        
        foreach ($dimensions as $key => $value) {
            if (is_string($value)) {
                $result[$key] = self::fromString($value);
            } elseif (is_array($value) && isset($value['value'], $value['unit'])) {
                $result[$key] = self::create($value['value'], $value['unit']);
            } else {
                $result[$key] = new self(null, null, (string) $value);
            }
        }
        
        return $result;
    }

    /**
     * Convert a collection of dimensional values to array format
     *
     * @param array $dimensionalValues
     * @param string $format 'original', 'imperial', 'metric', or 'both'
     * @param int $precision
     * @return array
     */
    public static function collectionToArray(array $dimensionalValues, string $format = 'original', int $precision = 1): array
    {
        $result = [];
        
        foreach ($dimensionalValues as $key => $dimValue) {
            if (!($dimValue instanceof self)) {
                $result[$key] = $dimValue;
                continue;
            }

            switch ($format) {
                case 'imperial':
                    $converted = $dimValue->toImperial($precision);
                    $result[$key] = $converted ? $converted->format($precision) : $dimValue->getOriginalString();
                    break;
                    
                case 'metric':
                    $converted = $dimValue->toMetric($precision);
                    $result[$key] = $converted ? $converted->format($precision) : $dimValue->getOriginalString();
                    break;
                    
                case 'both':
                    $imperial = $dimValue->toImperial($precision);
                    $metric = $dimValue->toMetric($precision);
                    $result[$key] = [
                        'original' => $dimValue->getOriginalString(),
                        'imperial' => $imperial ? $imperial->format($precision) : null,
                        'metric' => $metric ? $metric->format($precision) : null
                    ];
                    break;
                    
                case 'original':
                default:
                    $result[$key] = $dimValue->getOriginalString();
                    break;
            }
        }
        
        return $result;
    }
}