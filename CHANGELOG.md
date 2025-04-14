# Changelog

All notable changes to the Shekel VIN Package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- New `VehicleInfo` value object class for better intellisense/autocompletion support
- Type hints and return type declarations in all methods returning vehicle information
- Strongly typed properties for all vehicle attributes (make, model, year, etc.)
- Backward compatibility with array format through `toArray()` method
- Helper methods for accessing additional information fields

### Fixed
- Added missing manufacturer codes for Honda and Volkswagen in `LocalVinDecoder`
- Updated tests to work with `VehicleInfo` object instead of array format
- Improved compatibility between API and local decoding results

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