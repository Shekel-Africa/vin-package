# Shekel VIN Package

A PHP package for validating Vehicle Identification Numbers (VINs) and retrieving detailed vehicle information. Supports international VINs from all regions and Japanese Domestic Market (JDM) chassis numbers.

## Features

- VIN validation based on ISO 3779 standard
- **Regional VIN support** - North America, Europe, UK, China, Japan, and more
- **Japanese chassis number support** - JDM format (e.g., `JZA80-1004956`)
- **Auto-detection** - Automatically identifies VIN type and validates accordingly
- Integration with the NHTSA vehicle database API
- Local VIN decoding as fallback when API is unavailable
- Comprehensive WMI (World Manufacturer Identifier) database
- Flexible caching system with provider interface

## Requirements

- PHP 7.4 or higher
- Guzzle HTTP client

## Installation

```bash
composer require shekel/vin-package
```

## Basic Usage

```php
<?php

use Shekel\VinPackage\Vin;

// Initialize with a VIN
$vin = new Vin('1HGCM82633A004352');

// Validate the VIN
if ($vin->isValid()) {
    // Get vehicle information
    $vehicleInfo = $vin->getVehicleInfo();
    
    echo "Make: " . $vehicleInfo['make'] . "\n";
    echo "Model: " . $vehicleInfo['model'] . "\n";
    echo "Year: " . $vehicleInfo['year'] . "\n";
} else {
    echo "Invalid VIN";
}
```

## Regional VIN Support

The package supports VINs from all major regions with appropriate validation rules:

| Region | WMI Prefix | Check Digit | Manufacturers |
|--------|------------|-------------|---------------|
| **North America** | 1-5 | Required | Ford, GM, Chrysler, Tesla, etc. |
| **United Kingdom** | S | Optional | Jaguar (SAJ), Land Rover (SAL), Aston Martin (SCF) |
| **Germany** | W | Optional | BMW (WBA), Audi (WAU), Mercedes (WDD), VW (WVW), Porsche (WP0) |
| **China** | L | Optional | BYD, Geely, SAIC, Chery, NIO, etc. (121 manufacturers) |
| **Japan** | J | Optional | Toyota, Honda, Nissan, Mazda, Subaru, etc. |
| **France** | VF | Optional | Renault (VF1), Peugeot (VF3), Citroën (VF7) |
| **Italy** | Z | Optional | Ferrari (ZFF), Lamborghini (ZHW) |

```php
<?php

use Shekel\VinPackage\Vin;

// UK vehicle (Land Rover)
$ukVin = new Vin('SALGS2EF8GA123456');
echo $ukVin->isValid(); // true - validates format only, no check digit required

// Chinese vehicle (BYD)
$chinaVin = new Vin('LGXCE6CB5N0123456');
echo $chinaVin->isValid(); // true

// German vehicle (BMW)
$germanVin = new Vin('WBAPH5C55BA123456');
echo $germanVin->isValid(); // true

// North American vehicle - check digit is validated
$usVin = new Vin('1HGCM82633A004352');
echo $usVin->isValid(); // true - includes check digit validation
```

## Japanese Chassis Numbers (JDM)

Japanese Domestic Market vehicles use a different format than international 17-character VINs:

```
Format: MODEL_CODE-SERIAL_NUMBER
Example: JZA80-1004956 (Toyota Supra)
```

### Using Japanese Chassis Numbers

```php
<?php

use Shekel\VinPackage\JapaneseChassisNumber;

$chassis = new JapaneseChassisNumber('JZA80-1004956');

if ($chassis->isValid()) {
    echo "Model Code: " . $chassis->getModelCode() . "\n";     // JZA80
    echo "Serial: " . $chassis->getSerialNumber() . "\n";       // 1004956
    echo "Make: " . $chassis->getMake() . "\n";                 // Toyota
    echo "Model: " . $chassis->getModel() . "\n";               // Supra
    echo "Engine: " . $chassis->getEngine() . "\n";             // 2JZ-GTE (3.0L I6 Twin Turbo)

    $info = $chassis->getVehicleInfo();
    echo "Country: " . $info->getCountry() . "\n";              // Japan
    echo "Manufacturer: " . $info->getManufacturer() . "\n";    // Toyota Motor Corporation
}
```

### Supported JDM Manufacturers

| Manufacturer | Example Model Codes |
|--------------|---------------------|
| **Toyota** | JZA80 (Supra), AE86 (Corolla), SV30 (Camry) |
| **Nissan** | BNR32/33/34 (Skyline GT-R), S13/14/15 (Silvia) |
| **Honda** | DC2 (Integra Type R), EK9 (Civic Type R), NA1 (NSX) |
| **Subaru** | GDB (WRX STI), GC8 (Impreza) |
| **Mazda** | FD3S (RX-7), NA6C (MX-5) |
| **Mitsubishi** | CT9A (Lancer Evo), GTO |

## Auto-Detection with VehicleIdentifierFactory

The package can automatically detect whether an identifier is a standard VIN or Japanese chassis number:

```php
<?php

use Shekel\VinPackage\VehicleIdentifierFactory;

// Auto-detect and create appropriate handler
$vehicle1 = VehicleIdentifierFactory::create('1HGCM82633A004352');  // Returns Vin instance
$vehicle2 = VehicleIdentifierFactory::create('JZA80-1004956');       // Returns JapaneseChassisNumber instance

// Both implement VehicleIdentifierInterface
echo $vehicle1->getIdentifierType(); // "vin"
echo $vehicle2->getIdentifierType(); // "japanese_chassis_number"

// Detect type without creating instance
$type = VehicleIdentifierFactory::detectType('BNR32-305366'); // "japanese_chassis_number"

// Analyze identifier with confidence score
$analysis = VehicleIdentifierFactory::analyzeIdentifier('JZA80-1004956');
// Returns: ['type' => 'japanese_chassis_number', 'confidence' => 1.0, 'reasons' => [...]]

// Create specific types explicitly
$vin = VehicleIdentifierFactory::createVin('1HGCM82633A004352');
$chassis = VehicleIdentifierFactory::createJapaneseChassis('JZA80-1004956');
```

## VIN Structure Analysis

```php
<?php

use Shekel\VinPackage\Vin;

$vin = new Vin('1HGCM82633A004352');

// Get VIN structure components
echo "World Manufacturer Identifier (WMI): " . $vin->getWMI() . "\n";
echo "Vehicle Descriptor Section (VDS): " . $vin->getVDS() . "\n";
echo "Vehicle Identifier Section (VIS): " . $vin->getVIS() . "\n";
echo "Model Year: " . $vin->getModelYear() . "\n";
```

## API Validation Errors

When the NHTSA API returns validation errors for a VIN (e.g., invalid check digit), the package preserves this information in the result. This is important because a VIN might be structurally valid but fail API validation.

```php
<?php

use Shekel\VinPackage\Vin;

$vin = new Vin('1HGCM82633A004352');
$vehicleInfo = $vin->getVehicleInfo();

// Check if the API reported any validation errors
if ($vehicleInfo->hasApiValidationError()) {
    echo "API reported an error for this VIN\n";
    echo "Error Code: " . $vehicleInfo->getErrorCode() . "\n";
    echo "Error Text: " . $vehicleInfo->getErrorText() . "\n";

    // Get additional error details if available
    if ($vehicleInfo->getAdditionalErrorText()) {
        echo "Details: " . $vehicleInfo->getAdditionalErrorText() . "\n";
    }

    // Check if the API suggested a corrected VIN
    if ($suggestedVin = $vehicleInfo->getSuggestedVin()) {
        echo "Suggested VIN: " . $suggestedVin . "\n";
    }
}

// Get a complete validation summary
$summary = $vehicleInfo->getValidationSummary();
// Returns: [
//     'is_valid' => false,
//     'has_api_error' => true,
//     'error_code' => '6',
//     'error_text' => 'Incomplete VIN; check digit fails',
//     'additional_error_text' => 'Position 9 should be X',
//     'suggested_vin' => null,
//     'api_source' => 'nhtsa_api'
// ]

// Note: Vehicle info may still be available even when validation fails
echo "Make: " . $vehicleInfo->getMake() . "\n";  // May still return data
echo "Model: " . $vehicleInfo->getModel() . "\n";
```

### Available Validation Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `isValid()` | `bool` | Whether the VIN passed API validation |
| `hasApiValidationError()` | `bool` | Whether the API reported any errors |
| `getErrorCode()` | `?string` | NHTSA error code |
| `getErrorText()` | `?string` | Human-readable error message |
| `getAdditionalErrorText()` | `?string` | Additional error details |
| `getSuggestedVin()` | `?string` | API-suggested corrected VIN |
| `getApiResponse()` | `?array` | Full API response details |
| `getValidationSummary()` | `array` | Complete validation summary |

## Caching Support

The package provides a flexible caching system that allows you to implement your own caching strategy:

```php
<?php

use Shekel\VinPackage\Vin;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\Examples\ArrayVinCache;

// Use the provided simple array cache (for development)
$cache = new ArrayVinCache(3600); // Cache for 1 hour

// Or implement your own cache using Redis, Memcached, etc.
class RedisCache implements VinCacheInterface {
    private $redis;
    
    public function __construct(\Redis $redis) {
        $this->redis = $redis;
    }
    
    // Implementation of cache methods...
    // See VinCacheInterface for required methods
}

// Use the cache with VIN package
$vin = new Vin('1HGCM82633A004352', null, $cache);
$vehicleInfo = $vin->getVehicleInfo();
```

## Local Decoding and API Fallback

The package can use local decoding when the NHTSA API is unavailable:

```php
<?php

use Shekel\VinPackage\Vin;
use Shekel\VinPackage\Examples\ArrayVinCache;

$cache = new ArrayVinCache();

// Enable local fallback (enabled by default)
$vin = new Vin('1HGCM82633A004352', null, $cache, null, true);

// Get vehicle info - will try API first, then local decoder if API fails
$vehicleInfo = $vin->getVehicleInfo();

// Check if data came from local decoder
if ($vin->isLocallyDecoded($vehicleInfo)) {
    echo "Data was decoded locally due to API unavailability\n";
}

// Force refresh from API for a previously locally-decoded VIN
$vehicleInfo = $vin->getVehicleInfo(false, true);

// Get data using only local decoder (no API call)
$localInfo = $vin->getLocalVehicleInfo();
```

## Custom API Integration

```php
<?php

use Shekel\VinPackage\Vin;
use Shekel\VinPackage\Services\VinDecoderService;

// Use a custom VIN decoder API
$customDecoder = new VinDecoderService('https://your-custom-api.com/vin/');
$vin = new Vin('1HGCM82633A004352', $customDecoder);

// Use the custom decoder
$vehicleInfo = $vin->getVehicleInfo();
```

## License

MIT