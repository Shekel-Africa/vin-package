<?php

namespace Shekel\VinPackage\Contracts;

/**
 * Interface for VIN data cache implementations
 */
interface VinCacheInterface
{
    /**
     * Get an item from the cache
     *
     * @param string $key The cache key
     * @return mixed|null The cached data or null if not found
     */
    public function get(string $key);

    /**
     * Store an item in the cache
     *
     * @param string $key The cache key
     * @param mixed $value The data to store
     * @param int|null $ttl Time-to-live in seconds (null for default TTL)
     * @return bool True if stored successfully
     */
    public function set(string $key, $value, ?int $ttl = null): bool;

    /**
     * Remove an item from the cache
     *
     * @param string $key The cache key
     * @return bool True if removed successfully
     */
    public function delete(string $key): bool;

    /**
     * Check if an item exists in the cache and is valid
     *
     * @param string $key The cache key
     * @return bool True if the item exists and is valid
     */
    public function has(string $key): bool;
}
