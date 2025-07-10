<?php

namespace Shekel\VinPackage\ValueObjects;

/**
 * ValueObject class for vehicle information
 * Provides strongly typed properties for better IDE support and intellisense
 */
class VehicleInfo
{
    /**
     * @var string|null Vehicle make (manufacturer brand)
     */
    private ?string $make;

    /**
     * @var string|null Vehicle model
     */
    private ?string $model;

    /**
     * @var string|null Vehicle model year
     */
    private ?string $year;

    /**
     * @var string|null Vehicle trim level
     */
    private ?string $trim;

    /**
     * @var string|null Vehicle engine information
     */
    private ?string $engine;

    /**
     * @var string|null Manufacturing plant
     */
    private ?string $plant;

    /**
     * @var string|null Body style
     */
    private ?string $bodyStyle;

    /**
     * @var string|null Fuel type
     */
    private ?string $fuelType;

    /**
     * @var string|null Transmission type
     */
    private ?string $transmission;

    /**
     * @var string|null Transmission style
     */
    private ?string $transmissionStyle;

    /**
     * @var string|null Manufacturer name
     */
    private ?string $manufacturer;

    /**
     * @var string|null Country of manufacture
     */
    private ?string $country;

    /**
     * @var array Additional information not covered by standard properties
     */
    private array $additionalInfo;

    /**
     * @var array|null Validation information from NHTSA
     */
    private ?array $validation;

    /**
     * Create a new VehicleInfo object from an array of data
     *
     * @param array $data Vehicle data array
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();

        $instance->make = $data['make'] ?? null;
        $instance->model = $data['model'] ?? null;
        $instance->year = $data['year'] ?? null;
        $instance->trim = $data['trim'] ?? null;
        $instance->engine = $data['engine'] ?? null;
        $instance->plant = $data['plant'] ?? null;
        $instance->bodyStyle = $data['body_style'] ?? null;
        $instance->fuelType = $data['fuel_type'] ?? null;
        $instance->transmission = $data['transmission'] ?? null;
        $instance->transmissionStyle = $data['transmission_style'] ?? null;
        $instance->manufacturer = $data['manufacturer'] ?? null;
        $instance->country = $data['country'] ?? null;
        $instance->additionalInfo = $data['additional_info'] ?? [];
        $instance->validation = $data['validation'] ?? [
            'error_code' => null,
            'error_text' => null,
            'is_valid' => true
        ];

        // Handle rich data fields (store in additional_info for backward compatibility)
        $richDataFields = ['dimensions', 'seating', 'pricing', 'mileage'];
        foreach ($richDataFields as $field) {
            if (isset($data[$field])) {
                $instance->additionalInfo[$field] = $data[$field];
            }
        }

        // Handle cache metadata
        if (isset($data['cache_metadata'])) {
            $instance->additionalInfo['cache_metadata'] = $data['cache_metadata'];
        }

        return $instance;
    }

    /**
     * Convert the object back to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'trim' => $this->trim,
            'engine' => $this->engine,
            'plant' => $this->plant,
            'body_style' => $this->bodyStyle,
            'fuel_type' => $this->fuelType,
            'transmission' => $this->transmission,
            'transmission_style' => $this->transmissionStyle,
            'manufacturer' => $this->manufacturer,
            'country' => $this->country,
            'dimensions' => $this->getDimensions(),
            'seating' => $this->getSeating(),
            'pricing' => $this->getPricing(),
            'mileage' => $this->getMileage(),
            'additional_info' => $this->getCleanAdditionalInfo(),
            'validation' => $this->validation,
        ];

        // Include cache metadata at top level if present
        if (isset($this->additionalInfo['cache_metadata'])) {
            $result['cache_metadata'] = $this->additionalInfo['cache_metadata'];
        }

        return $result;
    }

    /**
     * Get additional info without cache metadata to prevent duplication
     *
     * @return array
     */
    private function getCleanAdditionalInfo(): array
    {
        $cleanInfo = $this->additionalInfo;
        
        // Remove cache_metadata from additional_info since it's shown at top level
        unset($cleanInfo['cache_metadata']);
        
        // Also remove rich data fields since they're shown at top level
        unset($cleanInfo['dimensions'], $cleanInfo['seating'], $cleanInfo['pricing'], $cleanInfo['mileage']);
        
        return $cleanInfo;
    }

    /**
     * Get vehicle make (manufacturer brand)
     *
     * @return string|null
     */
    public function getMake(): ?string
    {
        return $this->make;
    }

    /**
     * Get vehicle model
     *
     * @return string|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get vehicle model year
     *
     * @return string|null
     */
    public function getYear(): ?string
    {
        return $this->year;
    }

    /**
     * Get vehicle trim level
     *
     * @return string|null
     */
    public function getTrim(): ?string
    {
        return $this->trim;
    }

    /**
     * Get vehicle engine information
     *
     * @return string|null
     */
    public function getEngine(): ?string
    {
        return $this->engine;
    }

    /**
     * Get manufacturing plant
     *
     * @return string|null
     */
    public function getPlant(): ?string
    {
        return $this->plant;
    }

    /**
     * Get body style
     *
     * @return string|null
     */
    public function getBodyStyle(): ?string
    {
        return $this->bodyStyle;
    }

    /**
     * Get fuel type
     *
     * @return string|null
     */
    public function getFuelType(): ?string
    {
        return $this->fuelType;
    }

    /**
     * Get transmission type
     *
     * @return string|null
     */
    public function getTransmission(): ?string
    {
        return $this->transmission;
    }

    /**
     * Get transmission style
     *
     * @return string|null
     */
    public function getTransmissionStyle(): ?string
    {
        return $this->transmissionStyle;
    }

    /**
     * Get manufacturer name
     *
     * @return string|null
     */
    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    /**
     * Get country of manufacture
     *
     * @return string|null
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * Get additional information not covered by standard properties
     *
     * @return array
     */
    public function getAdditionalInfo(): array
    {
        return $this->additionalInfo;
    }

    /**
     * Get a specific value from additional info
     *
     * @param string $key The key to look up
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function getAdditionalValue(string $key, $default = null)
    {
        return $this->additionalInfo[$key] ?? $default;
    }

    /**
     * Check if data was decoded locally rather than via API
     *
     * @return bool
     */
    public function isLocallyDecoded(): bool
    {
        // Check if local decoder was used (either as primary or as one of multiple sources)
        $cacheMetadata = $this->additionalInfo['cache_metadata'] ?? [];

        // For extensible architecture, check if local was one of the sources
        if (isset($cacheMetadata['sources']) && is_array($cacheMetadata['sources'])) {
            return in_array('local', $cacheMetadata['sources']) || in_array('local_decoder', $cacheMetadata['sources']);
        }

        // For legacy architecture, check direct decoded_by field
        if (isset($this->additionalInfo['source_details']['local']['local_decoder_info']['decoded_by'])) {
            return $this->additionalInfo['source_details']['local']['local_decoder_info']['decoded_by'] === 'local_decoder';
        }

        // Fallback to old format
        return ($cacheMetadata['decoded_by'] ?? $this->additionalInfo['decoded_by'] ?? '') === 'local_decoder';
    }

    /**
     * Get validation information for the VIN
     *
     * @return array|null
     */
    public function getValidation(): ?array
    {
        return $this->validation;
    }

    /**
     * Get VIN structure information (WMI, VDS, VIS, check digit)
     *
     * @return array|null
     */
    public function getVinStructure(): ?array
    {
        return $this->additionalInfo['vin_structure'] ?? null;
    }

    /**
     * Get source-specific details
     *
     * @param string|null $source Specific source name, or null for all sources
     * @return array|null
     */
    public function getSourceDetails(?string $source = null): ?array
    {
        $sourceDetails = $this->additionalInfo['source_details'] ?? [];

        if ($source !== null) {
            return $sourceDetails[$source] ?? null;
        }

        return $sourceDetails ?: null;
    }

    /**
     * Get vehicle dimensions (length, width, height, wheelbase)
     *
     * @param string $unit 'original', 'imperial', 'metric', or 'both'
     * @param int $precision Number of decimal places for conversions
     * @return array|null
     */
    public function getDimensions(string $unit = 'original', int $precision = 1): ?array
    {
        $dimensionsData = $this->getAdditionalValue('dimensions');
        
        if (!$dimensionsData || !is_array($dimensionsData)) {
            return $dimensionsData;
        }

        // If requesting original format, return as-is
        if ($unit === 'original') {
            return $dimensionsData;
        }

        // Convert using DimensionalValue
        $dimensionalValues = DimensionalValue::fromArray($dimensionsData);
        return DimensionalValue::collectionToArray($dimensionalValues, $unit, $precision);
    }

    /**
     * Get seating information
     *
     * @return array|null
     */
    public function getSeating(): ?array
    {
        return $this->getAdditionalValue('seating');
    }

    /**
     * Get pricing information (MSRP, dealer invoice, etc.)
     *
     * @return array|null
     */
    public function getPricing(): ?array
    {
        return $this->getAdditionalValue('pricing');
    }

    /**
     * Get fuel economy/mileage information
     *
     * @param string $unit 'original', 'imperial', 'metric', or 'both'
     * @param int $precision Number of decimal places for conversions
     * @return array|null
     */
    public function getMileage(string $unit = 'original', int $precision = 1): ?array
    {
        $mileageData = $this->getAdditionalValue('mileage');
        
        if (!$mileageData || !is_array($mileageData)) {
            return $mileageData;
        }

        // If requesting original format, return as-is
        if ($unit === 'original') {
            return $mileageData;
        }

        // Convert using DimensionalValue
        $dimensionalValues = DimensionalValue::fromArray($mileageData);
        return DimensionalValue::collectionToArray($dimensionalValues, $unit, $precision);
    }

    /**
     * Check if the VIN is valid according to NHTSA
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->validation['is_valid'] ?? true;
    }

    /**
     * Get the error code from NHTSA validation
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->validation['error_code'] ?? null;
    }

    /**
     * Get the error text from NHTSA validation
     *
     * @return string|null
     */
    public function getErrorText(): ?string
    {
        return $this->validation['error_text'] ?? null;
    }
}
