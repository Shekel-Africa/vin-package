# Shekel VIN Package

A PHP package for validating Vehicle Identification Numbers (VINs) and retrieving detailed vehicle information.

## Features

- VIN validation based on standard ISO 3779
- VIN decoding to extract vehicle information
- Integration with the NHTSA vehicle database API
- Local VIN decoding as fallback when API is unavailable
- Flexible caching system with provider interface
- Additional VIN analysis methods

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