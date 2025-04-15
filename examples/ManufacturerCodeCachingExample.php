<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shekel\VinPackage\Cache\ArrayVinCache;
use Shekel\VinPackage\Services\VinDecoderService;

echo "Manufacturer Code Caching Example\n";
echo "================================\n\n";

// Create a simple array-based cache implementation
$cache = new ArrayVinCache(3600); // Cache for 1 hour

// Create a VIN decoder service with our cache
$decoder = new VinDecoderService(null, $cache);

// Example VINs for different manufacturers
$hyundaiVin = 'KMHDN45D82U399999'; // Hyundai Elantra
$kiaVin = 'KNDPC3AC8F7999999';     // Kia Sportage

echo "Step 1: First API decoding will learn manufacturer codes\n";
echo "---------------------------------------------------------\n";

// Decode Hyundai VIN with API - this will add the 'KMH' WMI to our cache
try {
    $hyundaiInfo = $decoder->decode($hyundaiVin);
    echo "✓ Decoded Hyundai VIN successfully\n";
    echo "  Make: " . $hyundaiInfo->getMake() . "\n";
    echo "  Manufacturer: " . $hyundaiInfo->getManufacturer() . "\n";
    echo "  WMI: " . $hyundaiInfo->getAdditionalValue('WMI') . "\n\n";
} catch (Exception $e) {
    echo "× Error decoding Hyundai VIN: " . $e->getMessage() . "\n";
    echo "  (API connection may be unavailable)\n\n";
}

// Decode Kia VIN with API - this will add the 'KND' WMI to our cache
try {
    $kiaInfo = $decoder->decode($kiaVin);
    echo "✓ Decoded Kia VIN successfully\n";
    echo "  Make: " . $kiaInfo->getMake() . "\n";
    echo "  Manufacturer: " . $kiaInfo->getManufacturer() . "\n";
    echo "  WMI: " . $kiaInfo->getAdditionalValue('WMI') . "\n\n";
} catch (Exception $e) {
    echo "× Error decoding Kia VIN: " . $e->getMessage() . "\n";
    echo "  (API connection may be unavailable)\n\n";
}

echo "Step 2: Create a new VIN with the same WMI (KMH) as our Hyundai\n";
echo "--------------------------------------------------------------\n";
$newVin = 'KMH' . 'XX99999999999';

// Create a new decoder service that will load the manufacturer codes from cache
$newDecoder = new VinDecoderService(null, $cache);

// Use local decoder which should now recognize the KMH code
$localInfo = $newDecoder->decodeLocally($newVin);
echo "✓ Local decoding with cached manufacturer code:\n";
echo "  Manufacturer: " . $localInfo->getManufacturer() . "\n\n";

echo "Step 3: Examining cached manufacturer codes\n";
echo "-------------------------------------------\n";
echo "Inspect the manufacturer codes that have been cached:\n";

// Get access to the local decoder instance
$localDecoder = new ReflectionProperty($newDecoder, 'localDecoder');
$localDecoder->setAccessible(true);
$localDecoderInstance = $localDecoder->getValue($newDecoder);

// Get all manufacturer codes (built-in + cached)
$codes = $localDecoderInstance->getManufacturerCodes();

// Display only the newly added codes from API responses
$builtInCodes = (new ReflectionClass($localDecoderInstance))->getConstant('MANUFACTURER_CODES');
$learnedCodes = array_diff_key($codes, $builtInCodes);

echo "Learned manufacturer codes:\n";
foreach ($learnedCodes as $wmi => $manufacturer) {
    echo "  $wmi => $manufacturer\n";
}
echo "\n";

echo "Step 4: Testing with a different VIN decoder instance (simulating app restart)\n";
echo "--------------------------------------------------------------------------\n";

// Create a completely new decoder with the same cache
// This simulates what would happen after an application restart
$freshDecoder = new VinDecoderService(null, $cache);

// Test decoding a new VIN with one of our cached WMIs
$freshVin = 'KND' . 'YY88888888888'; // Using the Kia WMI

$freshInfo = $freshDecoder->decodeLocally($freshVin);
echo "✓ Fresh decoder using cached manufacturer code:\n";
echo "  Manufacturer: " . $freshInfo->getManufacturer() . "\n\n";

echo "Conclusion\n";
echo "==========\n";
echo "This example demonstrates how the VIN decoder automatically learns\n";
echo "manufacturer codes from NHTSA API responses and stores them in cache.\n";
echo "These cached codes persist between application restarts and enhance\n";
echo "the local decoder's capabilities over time.\n";