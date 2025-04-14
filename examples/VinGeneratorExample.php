<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shekel\VinPackage\Utils\VinGenerator;
use Shekel\VinPackage\Vin;
use Shekel\VinPackage\Validators\VinValidator;

// Create a VIN generator
$generator = new VinGenerator();
$validator = new VinValidator();

echo "VIN Generator Example\n";
echo "===================\n\n";

// Generate VINs for different regions
$regions = ['US', 'EU', 'JP', 'KR', 'CN'];

foreach ($regions as $region) {
    echo "Generating VIN for $region region:\n";
    $vin = $generator->generateVin($region);
    echo "  VIN: $vin\n";
    echo "  Valid: " . ($validator->validate($vin) ? "Yes" : "No") . "\n\n";
}

// Generate multiple VINs
echo "Generating 5 random US VINs:\n";
$vins = $generator->generateMultipleVins(5, 'US');
foreach ($vins as $index => $vin) {
    echo "  " . ($index + 1) . ". $vin\n";
}
echo "\n";

// Test a generated VIN with our Vin class
echo "Testing a generated VIN with the Vin class:\n";
$randomVin = $generator->generateVin('JP'); // Generate a Japanese VIN
$vinObj = new Vin($randomVin);

echo "  VIN: " . $vinObj->getVin() . "\n";
echo "  Valid: " . ($vinObj->isValid() ? "Yes" : "No") . "\n";
echo "  WMI: " . $vinObj->getWMI() . "\n";
echo "  VDS: " . $vinObj->getVDS() . "\n"; 
echo "  VIS: " . $vinObj->getVIS() . "\n";
echo "  Model Year: " . $vinObj->getModelYear() . "\n\n";

// Get vehicle info using local decoder for the generated VIN
echo "Local decoding info for the generated VIN:\n";
try {
    $info = $vinObj->getLocalVehicleInfo();
    echo "  Make: " . ($info['make'] ?? "Unknown") . "\n";
    echo "  Country: " . ($info['country'] ?? "Unknown") . "\n";
    echo "  Year: " . ($info['year'] ?? "Unknown") . "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}