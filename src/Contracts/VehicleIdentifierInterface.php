<?php

namespace Shekel\VinPackage\Contracts;

use Shekel\VinPackage\ValueObjects\VehicleInfo;

/**
 * Interface for vehicle identification systems
 * Supports both international VINs and Japanese chassis numbers
 */
interface VehicleIdentifierInterface
{
    /**
     * Get the raw identifier string
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * Validate the identifier
     *
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Get detailed validation error if the identifier is invalid
     *
     * @return string|true Returns true if valid, error message if invalid
     */
    public function getValidationError();

    /**
     * Get vehicle information
     *
     * @return VehicleInfo
     * @throws \Exception If identifier is invalid
     */
    public function getVehicleInfo(): VehicleInfo;

    /**
     * Get the type of identifier
     *
     * @return string One of: 'vin', 'japanese_chassis_number'
     */
    public function getIdentifierType(): string;
}
