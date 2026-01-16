<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shekel\VinPackage\Vin;
use Shekel\VinPackage\Decoders\LocalVinDecoder;

echo "Local VIN Decoding Examples\n";
echo "==========================\n\n";

// Example 1: Decoding VIN using the local decoder only
echo "Example 1: Local VIN decoding only\n";
echo "--------------------------------\n";

$vin = new Vin('1HGCM82633A004352');

// Get vehicle information using local decoder only
$vehicleInfo = $vin->getLocalVehicleInfo();

echo "VIN: 1HGCM82633A004352\n";
echo "Year: " . $vehicleInfo->getYear() . "\n";
echo "Make: " . $vehicleInfo->getMake() . "\n";
echo "Country: " . $vehicleInfo->getCountry() . "\n";
echo "Plant: " . $vehicleInfo->getPlant() . "\n";
echo "Model: " . $vehicleInfo->getModel() . "\n\n";

// Example 2: Using Vin class with local fallback
echo "Example 2: Using Vin with local fallback\n";
echo "-------------------------------------\n";

// Create Vin with local fallback enabled (default)
$vin = new Vin('5YJSA1E11FF000000');

// Try to get full vehicle info (will use API if available, local decoder as fallback)
$vehicleInfo = $vin->getVehicleInfo();

echo "VIN: 5YJSA1E11FF000000\n";
echo "Year: " . $vehicleInfo->getYear() . "\n";
echo "Make: " . $vehicleInfo->getMake() . "\n";
echo "Model: " . ($vehicleInfo->getModel() ?? "Model not available locally") . "\n";

// Example 3: Using new helper methods
echo "\nExample 3: Using helper methods\n";
echo "----------------------------\n";

$vin = new Vin('WVWZZZ3BZWE689725');

echo "VIN: WVWZZZ3BZWE689725\n";
echo "Model Year: " . $vin->getModelYear() . "\n";
echo "Manufacturer: " . $vin->getManufacturerInfo() . "\n";

// Check if the VIN was locally decoded
echo "Is locally decoded: " . ($vin->getVehicleInfo()->isLocallyDecoded() ? "Yes" : "No") . "\n";

// Example 4: Disable local fallback
echo "\nExample 4: Disabling local fallback\n";
echo "-------------------------------\n";

$vin = new Vin('1FTFW1ET5DFA30440');
$vin->setLocalFallback(false);

echo "VIN: 1FTFW1ET5DFA30440\n";
echo "Local fallback disabled\n";
try {
    $vehicleInfo = $vin->getVehicleInfo();
    echo "Make: " . $vehicleInfo->getMake() . ", Model: " . $vehicleInfo->getModel() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . " (API may be unavailable)\n";
    echo "Setting fallback to true...\n";
    $vin->setLocalFallback(true);
    $vehicleInfo = $vin->getVehicleInfo();
    echo "Make: " . $vehicleInfo->getMake() . " (from local decoder)\n";
}