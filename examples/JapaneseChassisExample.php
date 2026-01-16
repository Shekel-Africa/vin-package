<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shekel\VinPackage\JapaneseChassisNumber;
use Shekel\VinPackage\VehicleIdentifierFactory;
use Shekel\VinPackage\Vin;

echo "Japanese Chassis Number Examples\n";
echo "================================\n\n";

// Example 1: Direct usage of JapaneseChassisNumber class
echo "Example 1: Direct Japanese Chassis Number Decoding\n";
echo "--------------------------------------------------\n";

$chassisNumbers = [
    'JZA80-1004956',   // Toyota Supra
    'BNR32-305366',    // Nissan Skyline GT-R R32
    'DC2-1234567',     // Honda Integra Type R
    'GDB-123456',      // Subaru Impreza WRX STI
    'FD3S-123456',     // Mazda RX-7
];

foreach ($chassisNumbers as $chassisNumber) {
    $chassis = new JapaneseChassisNumber($chassisNumber);

    if ($chassis->isValid()) {
        echo "Chassis: {$chassisNumber}\n";
        echo "  Model Code: " . $chassis->getModelCode() . "\n";
        echo "  Serial Number: " . $chassis->getSerialNumber() . "\n";
        echo "  Make: " . $chassis->getMake() . "\n";
        echo "  Model: " . $chassis->getModel() . "\n";
        echo "  Engine: " . ($chassis->getEngine() ?? 'N/A') . "\n";
        echo "\n";
    } else {
        echo "Invalid chassis number: {$chassisNumber}\n";
        echo "  Error: " . $chassis->getValidationError() . "\n\n";
    }
}

// Example 2: Using VehicleInfo object
echo "Example 2: Full Vehicle Info from Japanese Chassis\n";
echo "--------------------------------------------------\n";

$supra = new JapaneseChassisNumber('JZA80-1004956');
$info = $supra->getVehicleInfo();

echo "Toyota Supra (JZA80) Details:\n";
echo "  Make: " . $info->getMake() . "\n";
echo "  Model: " . $info->getModel() . "\n";
echo "  Engine: " . $info->getEngine() . "\n";
echo "  Country: " . $info->getCountry() . "\n";
echo "  Manufacturer: " . $info->getManufacturer() . "\n";
echo "  Production Years: " . $info->getProductionYears() . "\n";
echo "  Is Japanese Vehicle: " . ($info->isJapaneseVehicle() ? 'Yes' : 'No') . "\n";
echo "  Identifier Type: " . $info->getIdentifierType() . "\n";

// Get chassis structure
$structure = $info->getChassisNumberStructure();
if ($structure) {
    echo "  Chassis Structure:\n";
    echo "    Model Code: " . $structure['model_code'] . "\n";
    echo "    Serial Number: " . $structure['serial_number'] . "\n";
}
echo "\n";

// Example 3: Auto-detection with VehicleIdentifierFactory
echo "Example 3: Auto-Detection with VehicleIdentifierFactory\n";
echo "-------------------------------------------------------\n";

$identifiers = [
    '1HGCM82633A004352',   // Standard 17-char VIN (Honda Accord)
    'JZA80-1004956',       // Japanese chassis number (Toyota Supra)
    'BNR34-305366',        // Japanese chassis number (Nissan Skyline GT-R R34)
    'WVWZZZ3BZWE689725',   // European VIN (Volkswagen)
];

foreach ($identifiers as $identifier) {
    // Detect the type
    $type = VehicleIdentifierFactory::detectType($identifier);
    echo "Identifier: {$identifier}\n";
    echo "  Detected Type: {$type}\n";

    // Analyze with confidence
    $analysis = VehicleIdentifierFactory::analyzeIdentifier($identifier);
    echo "  Confidence: " . ($analysis['confidence'] * 100) . "%\n";
    echo "  Reasons: " . implode(', ', $analysis['reasons']) . "\n";

    // Create the appropriate handler
    $vehicle = VehicleIdentifierFactory::create($identifier);

    if ($vehicle->isValid()) {
        $vehicleInfo = $vehicle->getVehicleInfo();
        echo "  Make: " . ($vehicleInfo->getMake() ?? 'Unknown') . "\n";
        echo "  Model: " . ($vehicleInfo->getModel() ?? 'Unknown') . "\n";
    }
    echo "\n";
}

// Example 4: Validation examples
echo "Example 4: Validation Examples\n";
echo "------------------------------\n";

$testCases = [
    'JZA80-1004956'  => 'Valid Toyota Supra',
    'JZA80'          => 'Missing serial number',
    'JZA80-123'      => 'Serial too short',
    'J-1234567'      => 'Model code too short',
    'JZA80_1234567'  => 'Wrong separator',
    'ABCDEFG-123456' => 'Model code too long',
];

foreach ($testCases as $chassis => $description) {
    $jdm = new JapaneseChassisNumber($chassis);
    $valid = $jdm->isValid() ? 'VALID' : 'INVALID';
    $error = $jdm->getValidationError();

    echo "{$chassis} ({$description})\n";
    echo "  Status: {$valid}\n";
    if ($error) {
        echo "  Error: {$error}\n";
    }
    echo "\n";
}

// Example 5: Comparing VIN vs Japanese Chassis Number
echo "Example 5: VIN vs Japanese Chassis Comparison\n";
echo "---------------------------------------------\n";

// Same car, different markets
$usVin = new Vin('JT2JA82J9T0012345');  // US-spec Toyota Supra
$jdmChassis = new JapaneseChassisNumber('JZA80-1004956');  // JDM Toyota Supra

echo "US-spec Toyota (17-char VIN):\n";
if ($usVin->isValid()) {
    echo "  VIN: " . $usVin->getVin() . "\n";
    echo "  WMI: " . $usVin->getWMI() . "\n";
    echo "  Model Year: " . $usVin->getModelYear() . "\n";
}
echo "\n";

echo "JDM Toyota (Chassis Number):\n";
if ($jdmChassis->isValid()) {
    echo "  Chassis: " . $jdmChassis->getChassisNumber() . "\n";
    echo "  Model Code: " . $jdmChassis->getModelCode() . "\n";
    echo "  Note: Year cannot be determined from JDM chassis numbers\n";
}
echo "\n";

echo "Key Differences:\n";
echo "  - VINs are 17 characters with check digit at position 9\n";
echo "  - Japanese chassis numbers are 9-12 chars in MODEL-SERIAL format\n";
echo "  - VINs encode the model year; chassis numbers do not\n";
echo "  - Both can identify manufacturer, model, and often engine type\n";
