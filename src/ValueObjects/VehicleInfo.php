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
            'additional_info' => $this->additionalInfo,
            'validation' => $this->validation,
        ];

        // Include cache metadata if present
        if (isset($this->additionalInfo['cache_metadata'])) {
            $result['cache_metadata'] = $this->additionalInfo['cache_metadata'];
        }

        return $result;
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
        $cacheMetadata = $this->additionalInfo['cache_metadata'] ?? [];
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
