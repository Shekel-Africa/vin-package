<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shekel\VinPackage\Vin;
use Shekel\VinPackage\ValueObjects\DimensionalValue;
use Shekel\VinPackage\Utils\UnitConverter;

echo "=== VIN Package Metric System Support Example ===\n\n";

// Example VIN (Toyota Sienna)
$vinString = '5TDYK3DC8DS290235';

// Initialize VIN decoder with the VIN
$vin = new Vin($vinString);

echo "Decoding VIN: {$vinString}\n\n";

try {
    // Get vehicle information
    $vehicleInfo = $vin->getVehicleInfo();
    
    echo "Vehicle: {$vehicleInfo->getYear()} {$vehicleInfo->getMake()} {$vehicleInfo->getModel()}\n";
    echo "Trim: {$vehicleInfo->getTrim()}\n\n";
    
    // === DIMENSIONS EXAMPLE ===
    echo "=== DIMENSIONAL DATA ===\n\n";
    
    // Get original dimensions (as stored)
    $originalDimensions = $vehicleInfo->getDimensions('original');
    if ($originalDimensions) {
        echo "Original Dimensions:\n";
        foreach ($originalDimensions as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        echo "\n";
        
        // Get dimensions in metric system
        echo "Metric Dimensions:\n";
        $metricDimensions = $vehicleInfo->getDimensions('metric');
        foreach ($metricDimensions as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        echo "\n";
        
        // Get dimensions in both systems
        echo "Both Systems:\n";
        $bothDimensions = $vehicleInfo->getDimensions('both');
        foreach ($bothDimensions as $key => $data) {
            echo "  {$key}:\n";
            echo "    Imperial: {$data['imperial']}\n";
            echo "    Metric: {$data['metric']}\n";
        }
        echo "\n";
    }
    
    // === FUEL ECONOMY EXAMPLE ===
    echo "=== FUEL ECONOMY DATA ===\n\n";
    
    $originalMileage = $vehicleInfo->getMileage('original');
    if ($originalMileage) {
        echo "Original Fuel Economy:\n";
        foreach ($originalMileage as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        echo "\n";
        
        // Get fuel economy in metric system (L/100km)
        echo "Metric Fuel Economy:\n";
        $metricMileage = $vehicleInfo->getMileage('metric');
        foreach ($metricMileage as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        echo "\n";
        
        // Get fuel economy in both systems
        echo "Both Systems:\n";
        $bothMileage = $vehicleInfo->getMileage('both');
        foreach ($bothMileage as $key => $data) {
            echo "  {$key}:\n";
            echo "    Imperial: {$data['imperial']}\n";
            echo "    Metric: {$data['metric']}\n";
        }
        echo "\n";
    }
    
    // === DIRECT UNIT CONVERSION EXAMPLES ===
    echo "=== DIRECT UNIT CONVERSION EXAMPLES ===\n\n";
    
    // Example 1: Length conversion
    echo "Length Conversion Examples:\n";
    $inches = 200.2;
    $cm = UnitConverter::inchesToCm($inches);
    $mm = UnitConverter::inchesToMm($inches);
    echo "  {$inches} inches = {$cm} cm = {$mm} mm\n";
    
    $meters = 2.5;
    $feet = UnitConverter::metersToFeet($meters);
    echo "  {$meters} meters = {$feet} feet\n\n";
    
    // Example 2: Weight conversion
    echo "Weight Conversion Examples:\n";
    $pounds = 3500;
    $kg = UnitConverter::lbsToKg($pounds);
    echo "  {$pounds} lbs = {$kg} kg\n";
    
    $kilograms = 1500;
    $lbs = UnitConverter::kgToLbs($kilograms);
    echo "  {$kilograms} kg = {$lbs} lbs\n\n";
    
    // Example 3: Fuel economy conversion
    echo "Fuel Economy Conversion Examples:\n";
    $mpg = 25;
    $l100km = UnitConverter::mpgToL100km($mpg);
    echo "  {$mpg} mpg = {$l100km} L/100km\n";
    
    $liters100km = 8.5;
    $milesPerGallon = UnitConverter::l100kmToMpg($liters100km);
    echo "  {$liters100km} L/100km = {$milesPerGallon} mpg\n\n";
    
    // === DIMENSIONAL VALUE OBJECT EXAMPLES ===
    echo "=== DIMENSIONAL VALUE OBJECT EXAMPLES ===\n\n";
    
    // Create dimensional values
    $length = DimensionalValue::fromString('200.2 in');
    $width = DimensionalValue::create(78.1, 'in');
    
    echo "Original Values:\n";
    echo "  Length: {$length}\n";
    echo "  Width: {$width}\n\n";
    
    // Convert to metric
    $lengthMetric = $length->toMetric();
    $widthMetric = $width->toMetric();
    
    echo "Converted to Metric:\n";
    echo "  Length: {$lengthMetric}\n";
    echo "  Width: {$widthMetric}\n\n";
    
    // Create fuel economy dimensional value
    $cityMpg = DimensionalValue::fromString('18 miles/gallon');
    $cityMetric = $cityMpg->toMetric();
    
    echo "Fuel Economy Conversion:\n";
    echo "  City (Imperial): {$cityMpg}\n";
    echo "  City (Metric): {$cityMetric}\n\n";
    
    // === PARSING AND CONVERSION ===
    echo "=== PARSING AND CONVERSION ===\n\n";
    
    $dimensionStrings = [
        '200.2 in',
        '78.1 inches',
        '2.5 ft',
        '18 mpg',
        '25 miles/gallon',
        '2500 lbs'
    ];
    
    echo "Parsing dimensional strings:\n";
    foreach ($dimensionStrings as $str) {
        $parsed = UnitConverter::parseDimensionString($str);
        echo "  '{$str}' -> Value: {$parsed['value']}, Unit: {$parsed['unit']}\n";
        
        // Try to convert to metric if possible
        if ($parsed['value'] !== null && $parsed['unit'] !== null) {
            $dimValue = DimensionalValue::create($parsed['value'], $parsed['unit']);
            $metric = $dimValue->toMetric();
            if ($metric && $metric->getUnit() !== $parsed['unit']) {
                echo "    Metric equivalent: {$metric}\n";
            }
        }
    }
    echo "\n";
    
    // === COMMON UNITS REFERENCE ===
    echo "=== COMMON UNITS REFERENCE ===\n\n";
    
    $imperialUnits = UnitConverter::getCommonUnits('imperial');
    $metricUnits = UnitConverter::getCommonUnits('metric');
    
    echo "Imperial Units:\n";
    foreach ($imperialUnits as $type => $units) {
        echo "  {$type}: " . implode(', ', $units) . "\n";
    }
    echo "\n";
    
    echo "Metric Units:\n";
    foreach ($metricUnits as $type => $units) {
        echo "  {$type}: " . implode(', ', $units) . "\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "=== Example Complete ===\n";