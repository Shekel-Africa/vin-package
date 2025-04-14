<?php

namespace Shekel\VinPackage\Examples;

use Shekel\VinPackage\Contracts\VinCacheInterface;

/**
 * Example implementation of VinCacheInterface using a simple array cache
 * For demonstration purposes only - not recommended for production use
 */
class ArrayVinCache implements VinCacheInterface
{
    /**
     * @var array Cache storage
     */
    private array $cache = [];
    
    /**
     * @var array Cache expiration times
     */
    private array $expiration = [];
    
    /**
     * @var int Default TTL in seconds
     */
    private int $defaultTtl;
    
    /**
     * Constructor
     *
     * @param int $defaultTtl Default TTL in seconds (default: 1 hour)
     */
    public function __construct(int $defaultTtl = 3600)
    {
        $this->defaultTtl = $defaultTtl;
    }
    
    /**
     * @inheritDoc
     */
    public function get(string $key)
    {
        if (!$this->has($key)) {
            return null;
        }
        
        return $this->cache[$key];
    }
    
    /**
     * @inheritDoc
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        
        $this->cache[$key] = $value;
        $this->expiration[$key] = time() + $ttl;
        
        return true;
    }
    
    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        unset($this->cache[$key]);
        unset($this->expiration[$key]);
        
        return true;
    }
    
    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        // Check if key exists
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        // Check if expired
        if (isset($this->expiration[$key]) && time() > $this->expiration[$key]) {
            // Remove expired item
            $this->delete($key);
            return false;
        }
        
        return true;
    }
}