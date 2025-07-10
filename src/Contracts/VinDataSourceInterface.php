<?php

namespace Shekel\VinPackage\Contracts;

use Shekel\VinPackage\ValueObjects\VinDataSourceResult;

/**
 * Interface for VIN data sources
 */
interface VinDataSourceInterface
{
    /**
     * Get the name of this data source
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the priority of this data source (lower numbers = higher priority)
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Check if this data source is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Enable or disable this data source
     *
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void;

    /**
     * Check if this data source can handle the given VIN
     *
     * @param string $vin
     * @return bool
     */
    public function canHandle(string $vin): bool;

    /**
     * Decode the VIN using this data source
     *
     * @param string $vin
     * @return VinDataSourceResult
     */
    public function decode(string $vin): VinDataSourceResult;

    /**
     * Get the type of this data source (local, api, web, etc.)
     *
     * @return string
     */
    public function getSourceType(): string;
}
