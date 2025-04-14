<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shekel\VinPackage\Vin;
use Shekel\VinPackage\Examples\ArrayVinCache;
use Shekel\VinPackage\Services\VinDecoderService;

// Example 1: Basic usage with array cache
echo "Example 1: Using simple array cache\n";
echo "---------------------------------\n";

$cache = new ArrayVinCache(3600); // Cache for 1 hour
$vin = new Vin('1HGCM82633A004352', null, $cache);

// First request will hit the API
echo "First request (API call)...\n";
$startTime = microtime(true);
$vehicleInfo = $vin->getVehicleInfo();
$endTime = microtime(true);
echo "Time taken: " . round(($endTime - $startTime) * 1000, 2) . " ms\n";
echo "Make: " . $vehicleInfo->getMake() . ", Model: " . $vehicleInfo->getModel() . "\n\n";

// Second request should use cache
echo "Second request (should use cache)...\n";
$startTime = microtime(true);
$vehicleInfo = $vin->getVehicleInfo();
$endTime = microtime(true);
echo "Time taken: " . round(($endTime - $startTime) * 1000, 2) . " ms\n";
echo "Make: " . $vehicleInfo->getMake() . ", Model: " . $vehicleInfo->getModel() . "\n\n";

// Force fresh data by skipping cache
echo "Third request (force skip cache)...\n";
$startTime = microtime(true);
$vehicleInfo = $vin->getVehicleInfo(true); // Skip cache
$endTime = microtime(true);
echo "Time taken: " . round(($endTime - $startTime) * 1000, 2) . " ms\n";
echo "Make: " . $vehicleInfo->getMake() . ", Model: " . $vehicleInfo->getModel() . "\n\n";

// Example 2: Using PSR-16 SimpleCache adapter (Redis example)
echo "Example 2: Using PSR-16 adapter (pseudocode)\n";
echo "-----------------------------------------\n";
echo "// Create Redis client\n";
echo "\$redisClient = new Redis();\n";
echo "\$redisClient->connect('127.0.0.1', 6379);\n\n";

echo "// Create PSR-16 compatible cache\n";
echo "class RedisCacheAdapter implements \\Shekel\\VinPackage\\Contracts\\VinCacheInterface {\n";
echo "    private \$redis;\n";
echo "    public function __construct(\\Redis \$redis) { \$this->redis = \$redis; }\n";
echo "    public function get(string \$key) { return unserialize(\$this->redis->get(\$key)) ?: null; }\n";
echo "    public function set(string \$key, \$value, ?\$ttl = null): bool { \n";
echo "        return \$this->redis->setex(\$key, \$ttl ?? 3600, serialize(\$value)); \n";
echo "    }\n";
echo "    public function delete(string \$key): bool { return \$this->redis->del(\$key) > 0; }\n";
echo "    public function has(string \$key): bool { return \$this->redis->exists(\$key); }\n";
echo "}\n\n";

echo "// Use Redis cache with VIN package\n";
echo "\$redisCache = new RedisCacheAdapter(\$redisClient);\n";
echo "\$vin = new \\Shekel\\VinPackage\\Vin('WVWZZZ3BZWE689725', null, \$redisCache, 86400);\n";
echo "\$vehicleInfo = \$vin->getVehicleInfo(); // Will be cached in Redis for 24 hours\n";
echo "echo \"Make: \" . \$vehicleInfo->getMake() . \", Model: \" . \$vehicleInfo->getModel();\n";