<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shekel\VinPackage\Vin;
use Shekel\VinPackage\Examples\ArrayVinCache;

/**
 * This example demonstrates:
 * 1. Local fallback decoding when API fails
 * 2. Prioritizing API calls for previously locally-decoded VINs
 * 3. How to check if data was locally decoded
 */

// Create a cache implementation
$cache = new ArrayVinCache(3600); // Cache for 1 hour

// Create a VIN instance with local fallback enabled
$vin = new Vin('1HGCM82633A004352', null, $cache, null, true);

echo "Example: Local Fallback and API Prioritization\n";
echo "-------------------------------------------\n\n";

// Simulate API failure by intentionally invalidating the API URL
$decoderService = $vin->getDecoderService();
$reflection = new ReflectionClass($decoderService);
$apiUrlProperty = $reflection->getProperty('apiBaseUrl');
$apiUrlProperty->setAccessible(true);
$apiUrlProperty->setValue($decoderService, 'https://invalid-url-that-will-fail.example/api/');

// First attempt - API will fail, should use local decoder
echo "1. First attempt (API will fail, should use local decoder)...\n";
try {
    $info = $vin->getVehicleInfo();
    echo "   Data source: " . ($vin->isLocallyDecoded($info) ? "LOCAL DECODER" : "API") . "\n";
    echo "   Make: " . ($info['make'] ?? 'Unknown') . "\n";
    echo "   Year: " . ($info['year'] ?? 'Unknown') . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Second attempt - Should use cached locally-decoded data
echo "2. Second attempt (should use cached locally-decoded data)...\n";
try {
    $info = $vin->getVehicleInfo();
    echo "   Data source: " . ($vin->isLocallyDecoded($info) ? "LOCAL DECODER" : "API") . "\n";
    echo "   Make: " . ($info['make'] ?? 'Unknown') . "\n";
    echo "   Year: " . ($info['year'] ?? 'Unknown') . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Third attempt - Force API refresh (will fail again)
echo "3. Third attempt (force API refresh, will fail again and use local)...\n";
try {
    $info = $vin->getVehicleInfo(false, true);
    echo "   Data source: " . ($vin->isLocallyDecoded($info) ? "LOCAL DECODER" : "API") . "\n";
    echo "   Make: " . ($info['make'] ?? 'Unknown') . "\n";
    echo "   Year: " . ($info['year'] ?? 'Unknown') . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Restore the API URL to working one
$apiUrlProperty->setValue($decoderService, 'https://vpic.nhtsa.dot.gov/api/vehicles/decodevin/');

// Fourth attempt - Force API refresh (should succeed now)
echo "4. Fourth attempt (force API refresh with valid API URL)...\n";
try {
    $info = $vin->getVehicleInfo(false, true);
    echo "   Data source: " . ($vin->isLocallyDecoded($info) ? "LOCAL DECODER" : "API") . "\n";
    echo "   Make: " . ($info['make'] ?? 'Unknown') . "\n";
    echo "   Year: " . ($info['year'] ?? 'Unknown') . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Fifth attempt - Should use cached API data
echo "5. Fifth attempt (should use cached API data)...\n";
try {
    $info = $vin->getVehicleInfo();
    echo "   Data source: " . ($vin->isLocallyDecoded($info) ? "LOCAL DECODER" : "API") . "\n";
    echo "   Make: " . ($info['make'] ?? 'Unknown') . "\n";
    echo "   Year: " . ($info['year'] ?? 'Unknown') . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Example of directly using local decoder
echo "6. Using local decoder directly (no API call)...\n";
try {
    $info = $vin->getLocalVehicleInfo();
    echo "   Data source: " . ($vin->isLocallyDecoded($info) ? "LOCAL DECODER" : "API") . "\n";
    echo "   Make: " . ($info['make'] ?? 'Unknown') . "\n";
    echo "   Year: " . ($info['year'] ?? 'Unknown') . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}

// Disable local fallback
$vin->setLocalFallback(false);

// Restore the API URL to invalid one
$apiUrlProperty->setValue($decoderService, 'https://invalid-url-that-will-fail.example/api/');

// Attempt with local fallback disabled
echo "7. Attempt with local fallback disabled (should fail)...\n";
try {
    $info = $vin->getVehicleInfo(true); // Skip cache
    echo "   Data source: " . ($vin->isLocallyDecoded($info) ? "LOCAL DECODER" : "API") . "\n";
    echo "   Make: " . ($info['make'] ?? 'Unknown') . "\n";
    echo "   Year: " . ($info['year'] ?? 'Unknown') . "\n\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n\n";
}