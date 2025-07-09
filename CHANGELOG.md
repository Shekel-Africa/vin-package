# Changelog

All notable changes to the Shekel VIN Package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-07-09

### Added

- Enhanced transmission inference system with comprehensive logic for Toyota, Honda, Ford, and general manufacturers
- Separate `transmission_style` field in VehicleInfo value object for detailed transmission specifications
- `getTransmissionStyle()` method in VehicleInfo class to access transmission style information
- Intelligent transmission detection based on make, model, year, trim, and engine data
- Support for extracting transmission type and style from existing API responses
- Advanced transmission inference for specific vehicle models (e.g., Toyota Camry, Honda Civic, Ford F-150)
- **NEW**: Comprehensive metric system support for dimensional data and fuel economy
- `UnitConverter` utility class for converting between imperial and metric units
- `DimensionalValue` value object for representing measurements with automatic unit conversion
- Enhanced `getDimensions()` and `getMileage()` methods with unit conversion options ('original', 'imperial', 'metric', 'both')
- Support for length conversions (inches ↔ cm/mm, feet ↔ meters)
- Support for weight conversions (lbs ↔ kg)
- Support for fuel economy conversions (mpg ↔ L/100km)
- Support for volume conversions (cubic feet ↔ liters)

### Changed

- **BREAKING**: Restructured transmission field format - `transmission` now returns only "Manual" or "Automatic"
- Transmission style information (e.g., "CVT", "6-Speed", "8-Speed") moved to separate `transmission_style` field
- Updated all data sources (NHTSA, ClearVin, Local) to use new transmission format
- Enhanced transmission inference algorithms to return both type and style separately
- Improved API response parsing to extract transmission details when available
- Enhanced `getDimensions()` method now accepts unit conversion parameters (backward compatible)
- Enhanced `getMileage()` method now accepts unit conversion parameters (backward compatible)

### Fixed

- Fixed transmission inference logic to provide more accurate transmission data
- Resolved issues with transmission data not being returned for certain VINs
- Improved transmission detection for hybrid vehicles (e.g., Prius eCVT)
- Enhanced compatibility between different data sources for transmission information
- **BREAKING**: Resolved conflicting `decoded_by` metadata in `additional_info` vs `cache_metadata`
- Fixed rich data loss issue where ClearVin dimensions, seating, pricing, and mileage were being discarded
- Improved VIN structure data organization and consistency across data sources
- Fixed cache_metadata duplication between top-level and additional_info
- Fixed transmission_style field being lost in multi-source data merging scenarios

### Improved

- **BREAKING**: Restructured `additional_info` for better organization and data separation
- VIN structure data (WMI, VDS, VIS, check_digit) now organized under `additional_info.vin_structure`
- Source-specific details grouped under `additional_info.source_details[source_name]`
- Rich vehicle data (dimensions, seating, pricing, mileage) now consistently preserved and accessible
- Added new getter methods: `getVinStructure()`, `getSourceDetails()`, `getDimensions()`, `getSeating()`, `getPricing()`, `getMileage()`
- Enhanced `isLocallyDecoded()` method to correctly handle multi-source scenarios
- Improved data merging logic to prevent rich data loss and organize metadata properly

## [1.2.1] - 2025-04-15

### Added

- New `VehicleInfo` value object class for better intellisense/autocompletion support
- Type hints and return type declarations in all methods returning vehicle information
- Strongly typed properties for all vehicle attributes (make, model, year, etc.)
- Backward compatibility with array format through `toArray()` method
- Helper methods for accessing additional information fields
- Extraction of error code and error text from NHTSA API responses for VIN validation
- New validation information in `VehicleInfo` with `isValid()`, `getErrorCode()`, and `getErrorText()` methods
- Expanded manufacturer WMI codes database with over 70 global manufacturers
- Dynamic learning and caching of manufacturer codes from NHTSA API responses
- Auto-enhancement of local manufacturer database based on API responses
- Runtime collection of manufacturer codes combining built-in and cached codes
- Direct WMI extraction from VIN to pair with manufacturer data from NHTSA
- Comprehensive tests for manufacturer code caching functionality
- Example file demonstrating manufacturer code learning and caching capabilities

### Changed

- Improved manufacturer code caching with separate runtime collection
- Modified LocalVinDecoder to use cached manufacturer codes without Reflection API
- Enhanced VinDecoderService to extract WMI directly from VIN instead of relying on NHTSA response
- Updated addManufacturerCode method to properly handle WMIs of varying lengths

### Fixed

- Added missing manufacturer codes for Honda and Volkswagen in `LocalVinDecoder`
- Updated tests to work with `VehicleInfo` object instead of array format
- Improved compatibility between API and local decoding results
- Resolved issue with trying to modify class constants at runtime using Reflection
- Fixed bug in LocalVinDecoder where WMIs longer than 3 characters were being rejected instead of trimmed

## [1.2.0] - 2025-04-14

### Added

- New `VinGenerator` utility class for generating valid test VINs
- Region-specific VIN generation (US, EU, JP, KR, CN)
- `VinGeneratorExample.php` to demonstrate test VIN generation
- Comprehensive test for LocalVinDecoder
- PHPUnit configuration file with test suites

### Fixed

- Enhanced VIN validation to handle European and Asian manufacturer patterns
- Added special case handling for Toyota VINs that use different check digit algorithms
- Updated validation rules to be more accommodating of regional VIN formats

## [1.1.0] - 2025-04-14

### Added

- Local VIN decoding functionality as fallback when NHTSA API is unavailable
- Ability to detect and prioritize API calls for previously locally-decoded VINs
- New method `getLocalVehicleInfo()` to decode VINs directly using local database
- New method `isLocallyDecoded()` to check data source (API vs local)
- New method `setLocalFallback()` to enable/disable local decoding fallback
- Local manufacturer and country database with common WMI codes
- Additional example `LocalDecodingExample.php` demonstrating local fallback decoding
- Configurable caching mechanism with user-provided cache implementation
- `VinCacheInterface` for implementing custom cache providers
- Sample array-based cache implementation for reference
- Cache TTL configuration options
- Documentation for caching and local decoding in README
- Composer scripts for running tests and code style checks

### Changed

- `VinDecoderService` now supports local decoding fallback
- All decode responses now include data source metadata
- `getVehicleInfo()` method now has optional parameters for cache control and API prioritization

## [1.0.0] - 2025-04-10

### Added

- Initial release
- VIN validation based on ISO 3779 standard
- VIN decoding through NHTSA API
- VIN structure analysis (WMI, VDS, VIS)
- Basic test suite for validator and decoder
- Documentation and examples
