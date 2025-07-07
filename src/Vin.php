<?php

namespace Shekel\VinPackage;

use Shekel\VinPackage\Validators\VinValidator;
use Shekel\VinPackage\Services\VinDecoderService;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\ValueObjects\VehicleInfo;

class Vin
{
    /**
     * @var string
     */
    private string $vin;

    /**
     * @var VinValidator
     */
    private VinValidator $validator;

    /**
     * @var VinDecoderService
     */
    private VinDecoderService $decoderService;

    /**
     * Constructor
     *
     * @param string $vin Vehicle Identification Number
     * @param VinDecoderService|null $decoderService Custom decoder service (optional)
     * @param VinCacheInterface|null $cache Cache implementation (optional)
     * @param int|null $cacheTtl Cache TTL in seconds (optional, defaults to 30 days)
     * @param bool $useLocalFallback Whether to use local decoder as fallback when API fails
     */
    public function __construct(
        string $vin,
        ?VinDecoderService $decoderService = null,
        ?VinCacheInterface $cache = null,
        ?int $cacheTtl = null,
        bool $useLocalFallback = true
    ) {
        $this->vin = strtoupper(trim($vin));
        $this->validator = new VinValidator();

        if ($decoderService) {
            $this->decoderService = $decoderService;
        } else {
            $this->decoderService = new VinDecoderService(null, $cache, $cacheTtl, $useLocalFallback);
        }
    }

    /**
     * Get the VIN
     *
     * @return string
     */
    public function getVin(): string
    {
        return $this->vin;
    }

    /**
     * Validate the VIN
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->validator->validate($this->vin);
    }

    /**
     * Get detailed validation error if the VIN is invalid
     *
     * @return string|true Returns true if valid, error message if invalid
     */
    public function getValidationError()
    {
        return $this->validator->validate($this->vin, true);
    }

    /**
     * Get VIN information
     *
     * @param bool $skipCache Whether to skip the cache and fetch fresh data
     * @param bool $forceApiRefresh Whether to force API refresh for locally decoded VINs
     * @return VehicleInfo
     * @throws \Exception If VIN is invalid
     */
    public function getVehicleInfo(bool $skipCache = false, bool $forceApiRefresh = false): VehicleInfo
    {
        if (!$this->isValid()) {
            throw new \Exception("Invalid VIN: {$this->vin}");
        }

        return $this->decoderService->decode($this->vin, $skipCache, $forceApiRefresh);
    }

    /**
     * Get VIN information using only local decoding (no API call)
     *
     * @return VehicleInfo
     * @throws \Exception If VIN is invalid
     */
    public function getLocalVehicleInfo(): VehicleInfo
    {
        if (!$this->isValid()) {
            throw new \Exception("Invalid VIN: {$this->vin}");
        }

        return $this->decoderService->decodeLocally($this->vin);
    }

    /**
     * Get the World Manufacturer Identifier (WMI) - first 3 characters
     *
     * @return string
     */
    public function getWMI(): string
    {
        return substr($this->vin, 0, 3);
    }

    /**
     * Get the Vehicle Descriptor Section (VDS) - characters 4-9
     *
     * @return string
     */
    public function getVDS(): string
    {
        return substr($this->vin, 3, 6);
    }

    /**
     * Get the Vehicle Identifier Section (VIS) - last 8 characters
     *
     * @return string
     */
    public function getVIS(): string
    {
        return substr($this->vin, 9, 8);
    }

    /**
     * Get the model year from the VIN
     *
     * @return string
     */
    public function getModelYear(): string
    {
        // The 10th character of the VIN represents the model year
        $yearCode = substr($this->vin, 9, 1);

        return $this->decoderService->decodeModelYear($yearCode);
    }

    /**
     * Get manufacturer information based on the WMI
     *
     * @param bool $skipCache Whether to skip the cache and fetch fresh data
     * @return array|null
     */
    public function getManufacturerInfo(bool $skipCache = false): ?array
    {
        return $this->decoderService->getManufacturerInfo($this->getWMI(), $skipCache);
    }

    /**
     * Clear cached data for this VIN
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        return $this->decoderService->clearCacheForVin($this->vin);
    }

    /**
     * Get the decoder service
     *
     * @return VinDecoderService
     */
    public function getDecoderService(): VinDecoderService
    {
        return $this->decoderService;
    }

    /**
     * Check if the data for this VIN was decoded locally rather than from the API
     *
     * @param VehicleInfo $data Vehicle data previously retrieved
     * @return bool
     */
    public function isLocallyDecoded(VehicleInfo $data): bool
    {
        return $this->decoderService->isLocallyDecoded($data);
    }

    /**
     * Enable or disable local fallback decoding
     *
     * @param bool $enabled
     * @return self
     */
    public function setLocalFallback(bool $enabled): self
    {
        $this->decoderService->setLocalFallback($enabled);
        return $this;
    }
}
