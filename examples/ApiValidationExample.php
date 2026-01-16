<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Shekel\VinPackage\Vin;
use Shekel\VinPackage\Cache\ArrayVinCache;

echo "API Validation Error Handling Examples\n";
echo "======================================\n\n";

// Example 1: Handling VINs with API validation errors
echo "Example 1: Checking for API Validation Errors\n";
echo "----------------------------------------------\n";

$cache = new ArrayVinCache(3600);

// This VIN may have validation issues detected by the API
$vin = new Vin('1HGCM82633A004352', null, $cache);

try {
    $vehicleInfo = $vin->getVehicleInfo();

    // Always check if the API reported validation errors
    if ($vehicleInfo->hasApiValidationError()) {
        echo "API reported validation error!\n";
        echo "  Error Code: " . $vehicleInfo->getErrorCode() . "\n";
        echo "  Error Text: " . $vehicleInfo->getErrorText() . "\n";

        // Check for additional error details
        if ($additionalError = $vehicleInfo->getAdditionalErrorText()) {
            echo "  Additional Details: {$additionalError}\n";
        }

        // Check if API suggested a corrected VIN
        if ($suggestedVin = $vehicleInfo->getSuggestedVin()) {
            echo "  Suggested VIN: {$suggestedVin}\n";
            echo "  Consider using the suggested VIN for more accurate results.\n";
        }
    } else {
        echo "VIN validated successfully by API.\n";
    }

    // Vehicle info is still available even with validation errors
    echo "\nVehicle Information (may be partial):\n";
    echo "  Make: " . ($vehicleInfo->getMake() ?? 'N/A') . "\n";
    echo "  Model: " . ($vehicleInfo->getModel() ?? 'N/A') . "\n";
    echo "  Year: " . ($vehicleInfo->getYear() ?? 'N/A') . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Example 2: Getting a complete validation summary
echo "Example 2: Complete Validation Summary\n";
echo "--------------------------------------\n";

$vin2 = new Vin('WVWZZZ3BZWE689725', null, $cache);

try {
    $vehicleInfo = $vin2->getVehicleInfo();

    // Get comprehensive validation summary
    $summary = $vehicleInfo->getValidationSummary();

    echo "Validation Summary:\n";
    echo "  Is Valid: " . ($summary['is_valid'] ? 'Yes' : 'No') . "\n";
    echo "  Has API Error: " . ($summary['has_api_error'] ? 'Yes' : 'No') . "\n";
    echo "  Error Code: " . ($summary['error_code'] ?? 'None') . "\n";
    echo "  Error Text: " . ($summary['error_text'] ?? 'None') . "\n";
    echo "  Additional Error: " . ($summary['additional_error_text'] ?? 'None') . "\n";
    echo "  Suggested VIN: " . ($summary['suggested_vin'] ?? 'None') . "\n";
    echo "  API Source: " . ($summary['api_source'] ?? 'Unknown') . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Example 3: Accessing full API response details
echo "Example 3: Full API Response Details\n";
echo "------------------------------------\n";

$vin3 = new Vin('1FTFW1ET5DFC10312', null, $cache);

try {
    $vehicleInfo = $vin3->getVehicleInfo();

    // Get the full API response object
    $apiResponse = $vehicleInfo->getApiResponse();

    if ($apiResponse) {
        echo "API Response Details:\n";
        echo "  Source: " . ($apiResponse['source'] ?? 'Unknown') . "\n";
        echo "  Has Error: " . ($apiResponse['has_error'] ? 'Yes' : 'No') . "\n";

        if ($apiResponse['has_error']) {
            echo "  Error Code: " . $apiResponse['error_code'] . "\n";
            echo "  Error Text: " . $apiResponse['error_text'] . "\n";
        }
    } else {
        echo "No API response available (may be using local decoder).\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Example 4: Differentiating format validation vs API validation
echo "Example 4: Format vs API Validation\n";
echo "-----------------------------------\n";

// A VIN can be:
// 1. Format valid (passes ISO 3779 structure checks)
// 2. API valid (NHTSA database recognizes it without errors)

$testVin = '1HGCM82633A004352';
$vin4 = new Vin($testVin, null, $cache);

echo "VIN: {$testVin}\n";

// Check format validity (local check)
$formatValid = $vin4->isValid();
echo "  Format Valid: " . ($formatValid ? 'Yes' : 'No') . "\n";

if ($formatValid) {
    $vehicleInfo = $vin4->getVehicleInfo();

    // Check API validity (from NHTSA response)
    $apiValid = !$vehicleInfo->hasApiValidationError();
    echo "  API Valid: " . ($apiValid ? 'Yes' : 'No') . "\n";

    if (!$apiValid) {
        echo "  Note: VIN format is correct but API found issues.\n";
        echo "  This can happen with:\n";
        echo "    - Incorrect check digit\n";
        echo "    - Unknown manufacturer codes\n";
        echo "    - Incomplete VIN patterns in NHTSA database\n";
    }
}
echo "\n";

// Example 5: Best practices for handling validation
echo "Example 5: Best Practices for Validation Handling\n";
echo "-------------------------------------------------\n";

echo "Recommended validation flow:\n\n";

echo "1. First, check format validity:\n";
echo "   \$vin = new Vin(\$vinString);\n";
echo "   if (!\$vin->isValid()) {\n";
echo "       // Handle format error - VIN structure is wrong\n";
echo "   }\n\n";

echo "2. Then, get vehicle info and check API validation:\n";
echo "   \$info = \$vin->getVehicleInfo();\n";
echo "   if (\$info->hasApiValidationError()) {\n";
echo "       // Log warning but continue - data may still be useful\n";
echo "       \$error = \$info->getErrorText();\n";
echo "   }\n\n";

echo "3. Check for suggested corrections:\n";
echo "   if (\$suggested = \$info->getSuggestedVin()) {\n";
echo "       // Consider re-querying with suggested VIN\n";
echo "   }\n\n";

echo "4. Use validation summary for comprehensive checks:\n";
echo "   \$summary = \$info->getValidationSummary();\n";
echo "   // Contains all validation info in one array\n\n";

echo "Key Points:\n";
echo "  - Vehicle data may be available even with validation errors\n";
echo "  - API errors indicate NHTSA database issues, not necessarily invalid VINs\n";
echo "  - Always log validation errors for troubleshooting\n";
echo "  - Consider the suggested VIN when errors occur\n";
