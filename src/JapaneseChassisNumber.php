<?php

namespace Shekel\VinPackage;

use Shekel\VinPackage\Contracts\VehicleIdentifierInterface;
use Shekel\VinPackage\Validators\JapaneseChassisValidator;
use Shekel\VinPackage\Decoders\JapaneseChassisDecoder;
use Shekel\VinPackage\ValueObjects\VehicleInfo;

/**
 * Japanese Chassis Number class
 *
 * Main entry point for working with Japanese Domestic Market (JDM) chassis numbers.
 * Provides validation, decoding, and structure analysis similar to the Vin class
 * but for Japanese-format vehicle identifiers.
 *
 * Japanese chassis numbers differ from international VINs:
 * - Length: 9-12 characters (vs 17 for VIN)
 * - Format: MODEL_CODE-SERIAL_NUMBER with hyphen separator
 * - No check digit validation
 * - Year cannot be determined from the number
 *
 * Example usage:
 * ```php
 * $chassis = new JapaneseChassisNumber('JZA80-1004956');
 * if ($chassis->isValid()) {
 *     $info = $chassis->getVehicleInfo();
 *     echo $info->getMake();  // Toyota
 *     echo $info->getModel(); // Supra
 * }
 * ```
 */
class JapaneseChassisNumber implements VehicleIdentifierInterface
{
    /**
     * @var string
     */
    private string $chassisNumber;

    /**
     * @var JapaneseChassisValidator
     */
    private JapaneseChassisValidator $validator;

    /**
     * @var JapaneseChassisDecoder
     */
    private JapaneseChassisDecoder $decoder;

    /**
     * Constructor
     *
     * @param string $chassisNumber Japanese chassis number (e.g., JZA80-1004956)
     * @param JapaneseChassisDecoder|null $decoder Custom decoder (optional)
     */
    public function __construct(string $chassisNumber, ?JapaneseChassisDecoder $decoder = null)
    {
        $this->chassisNumber = strtoupper(trim($chassisNumber));
        $this->validator = new JapaneseChassisValidator();
        $this->decoder = $decoder ?? new JapaneseChassisDecoder();
    }

    /**
     * Get the raw chassis number
     *
     * @return string
     */
    public function getChassisNumber(): string
    {
        return $this->chassisNumber;
    }

    /**
     * Get the raw identifier string (implements VehicleIdentifierInterface)
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->chassisNumber;
    }

    /**
     * Validate the chassis number
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->validator->validate($this->chassisNumber);
    }

    /**
     * Get detailed validation error if the chassis number is invalid
     *
     * @return string|true Returns true if valid, error message if invalid
     */
    public function getValidationError()
    {
        return $this->validator->validate($this->chassisNumber, true);
    }

    /**
     * Get the type of identifier
     *
     * @return string
     */
    public function getIdentifierType(): string
    {
        return 'japanese_chassis_number';
    }

    /**
     * Get vehicle information by decoding the chassis number
     *
     * @return VehicleInfo
     * @throws \Exception If chassis number is invalid
     */
    public function getVehicleInfo(): VehicleInfo
    {
        if (!$this->isValid()) {
            throw new \Exception("Invalid Japanese chassis number: {$this->chassisNumber}");
        }

        $data = $this->decoder->decode($this->chassisNumber);
        return VehicleInfo::fromArray($data);
    }

    /**
     * Get the model code portion (before the hyphen)
     *
     * @return string|null
     */
    public function getModelCode(): ?string
    {
        $parsed = $this->validator->parse($this->chassisNumber);
        return $parsed['model_code'] ?? null;
    }

    /**
     * Get the serial number portion (after the hyphen)
     *
     * @return string|null
     */
    public function getSerialNumber(): ?string
    {
        $parsed = $this->validator->parse($this->chassisNumber);
        return $parsed['serial_number'] ?? null;
    }

    /**
     * Get the inferred manufacturer based on model code patterns
     *
     * @return string|null
     */
    public function getManufacturer(): ?string
    {
        if (!$this->isValid()) {
            return null;
        }

        $data = $this->decoder->decode($this->chassisNumber);
        return $data['manufacturer'] ?? null;
    }

    /**
     * Get the inferred make (brand) based on model code patterns
     *
     * @return string|null
     */
    public function getMake(): ?string
    {
        if (!$this->isValid()) {
            return null;
        }

        $data = $this->decoder->decode($this->chassisNumber);
        return $data['make'] ?? null;
    }

    /**
     * Get the model name if available in the database
     *
     * @return string|null
     */
    public function getModel(): ?string
    {
        if (!$this->isValid()) {
            return null;
        }

        $data = $this->decoder->decode($this->chassisNumber);
        return $data['model'] ?? null;
    }

    /**
     * Get the engine information if available
     *
     * @return string|null
     */
    public function getEngine(): ?string
    {
        if (!$this->isValid()) {
            return null;
        }

        $data = $this->decoder->decode($this->chassisNumber);
        return $data['engine'] ?? null;
    }

    /**
     * Check if the chassis number model code exists in the database
     *
     * @return bool
     */
    public function isKnownModelCode(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $data = $this->decoder->decode($this->chassisNumber);
        $partialInfo = $data['additional_info']['local_decoder_info']['partial_info'] ?? true;

        return !$partialInfo;
    }

    /**
     * Get the decoder instance
     *
     * @return JapaneseChassisDecoder
     */
    public function getDecoder(): JapaneseChassisDecoder
    {
        return $this->decoder;
    }

    /**
     * Get all supported manufacturers from the database
     *
     * @return array
     */
    public function getSupportedManufacturers(): array
    {
        return $this->decoder->getSupportedManufacturers();
    }
}
