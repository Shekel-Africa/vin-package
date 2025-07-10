<?php

namespace Shekel\VinPackage\Tests\Fixtures;

class VinTestData
{
    public const VALID_VIN_TOYOTA = '5TDYK3DC8DS290235';
    public const VALID_VIN_HONDA = '1HGBH41JXMN109186';
    public const VALID_VIN_FORD = '1FTFW1ET5DFC10312';
    public const INVALID_VIN_SHORT = '12345';
    public const INVALID_VIN_LONG = '5TDYK3DC8DS2902351';
    public const INVALID_VIN_CHARS = 'INVALID_VIN_FORMAT';

    public static function getLocalData(string $vin = self::VALID_VIN_TOYOTA): array
    {
        return [
            'make' => 'Toyota',
            'model' => null,
            'year' => '2013',
            'trim' => null,
            'engine' => null,
            'plant' => null,
            'body_style' => null,
            'fuel_type' => null,
            'transmission' => null,
            'manufacturer' => 'Toyota Motor Corporation',
            'country' => 'United States',
            'additional_info' => [
                'WMI' => substr($vin, 0, 3),
                'VDS' => substr($vin, 3, 6),
                'VIS' => substr($vin, 9, 8),
                'decoded_by' => 'local_decoder',
                'decoding_date' => '2024-01-01 12:00:00'
            ],
            'validation' => [
                'error_code' => null,
                'error_text' => null,
                'is_valid' => true
            ]
        ];
    }

    public static function getNhtsaData(string $vin = self::VALID_VIN_TOYOTA): array
    {
        return [
            'make' => 'TOYOTA',
            'model' => 'Sienna',
            'year' => '2013',
            'trim' => 'XLE',
            'engine' => '3.5L V6 DOHC',
            'plant' => 'Princeton, Indiana',
            'body_style' => 'MULTIPURPOSE PASSENGER VEHICLE (MPV)',
            'fuel_type' => 'Gasoline',
            'transmission' => 'Automatic',
            'manufacturer' => 'TOYOTA MOTOR MANUFACTURING, INDIANA, INC.',
            'country' => 'UNITED STATES (USA)',
            'additional_info' => [
                'WMI' => substr($vin, 0, 3),
                'Plant City' => 'Princeton',
                'Plant State' => 'Indiana',
                'Vehicle Type' => 'MULTIPURPOSE PASSENGER VEHICLE (MPV)',
                'Series' => 'Sienna',
                'decoded_by' => 'nhtsa_api',
                'decoding_date' => '2024-01-01 12:00:00'
            ],
            'validation' => [
                'error_code' => '0',
                'error_text' => null,
                'is_valid' => true
            ]
        ];
    }

    public static function getClearVinData(string $vin = self::VALID_VIN_TOYOTA): array
    {
        return [
            'make' => 'Toyota',
            'model' => 'Sienna',
            'year' => '2013',
            'trim' => 'XLE FWD 8-Passenger V6',
            'engine' => '3.5L V6 EFI DOHC 24V',
            'plant' => null,
            'body_style' => 'SPORTS VAN',
            'fuel_type' => null,
            'transmission' => null,
            'manufacturer' => null,
            'country' => 'UNITED STATES',
            'dimensions' => [
                'length' => '200.20 in',
                'width' => '78.10 in',
                'height' => '70.70 in',
                'wheelbase' => '119.30 in'
            ],
            'seating' => [
                'standardSeating' => 8,
                'passengerVolume' => 'N/A'
            ],
            'pricing' => [
                'msrp' => '$33,360 USD',
                'dealerInvoice' => '$30,691 USD'
            ],
            'mileage' => [
                'city' => '18 miles/gallon',
                'highway' => '25 miles/gallon'
            ],
            'additional_info' => [
                'WMI' => substr($vin, 0, 3),
                'origin' => 'UNITED STATES',
                'style' => 'SPORTS VAN',
                'age' => '12 year(s)',
                'wheelDrive' => 'FWD',
                'decoded_by' => 'clearvin',
                'decoding_date' => '2024-01-01 12:00:00'
            ],
            'validation' => [
                'error_code' => null,
                'error_text' => null,
                'is_valid' => true
            ]
        ];
    }

    public static function getMergedData(string $vin = self::VALID_VIN_TOYOTA): array
    {
        return [
            'make' => 'TOYOTA', // From NHTSA (higher priority)
            'model' => 'Sienna', // From NHTSA
            'year' => '2013', // Consistent across all sources
            'trim' => 'XLE FWD 8-Passenger V6', // From ClearVIN (most detailed)
            'engine' => '3.5L V6 EFI DOHC 24V', // From ClearVIN (more detailed)
            'plant' => 'Princeton, Indiana', // From NHTSA
            'body_style' => 'MULTIPURPOSE PASSENGER VEHICLE (MPV)', // From NHTSA (official)
            'fuel_type' => 'Gasoline', // From NHTSA
            'transmission' => 'Automatic', // From NHTSA
            'manufacturer' => 'TOYOTA MOTOR MANUFACTURING, INDIANA, INC.', // From NHTSA (official)
            'country' => 'UNITED STATES (USA)', // From NHTSA (official format)
            'dimensions' => [
                'length' => '200.20 in',
                'width' => '78.10 in',
                'height' => '70.70 in',
                'wheelbase' => '119.30 in'
            ],
            'seating' => [
                'standardSeating' => 8,
                'passengerVolume' => 'N/A'
            ],
            'pricing' => [
                'msrp' => '$33,360 USD',
                'dealerInvoice' => '$30,691 USD'
            ],
            'mileage' => [
                'city' => '18 miles/gallon',
                'highway' => '25 miles/gallon'
            ],
            'additional_info' => [
                'WMI' => substr($vin, 0, 3),
                'VDS' => substr($vin, 3, 6),
                'VIS' => substr($vin, 9, 8),
                'Plant City' => 'Princeton',
                'Plant State' => 'Indiana',
                'Vehicle Type' => 'MULTIPURPOSE PASSENGER VEHICLE (MPV)',
                'Series' => 'Sienna',
                'origin' => 'UNITED STATES',
                'style' => 'SPORTS VAN',
                'age' => '12 year(s)',
                'wheelDrive' => 'FWD'
            ],
            'validation' => [
                'error_code' => '0',
                'error_text' => null,
                'is_valid' => true
            ],
            'cache_metadata' => [
                'sources' => ['local', 'nhtsa_api', 'clearvin'],
                'total_execution_time' => 1.5,
                'source_details' => [
                    'local' => ['execution_time' => 0.1, 'cache_hit' => false],
                    'nhtsa_api' => ['execution_time' => 0.8, 'api_version' => 'v1'],
                    'clearvin' => ['execution_time' => 0.6, 'markdown_length' => 1024]
                ]
            ]
        ];
    }

    public static function getHondaLocalData(): array
    {
        return [
            'make' => 'Honda',
            'model' => null,
            'year' => '2021',
            'trim' => null,
            'engine' => null,
            'plant' => null,
            'body_style' => null,
            'fuel_type' => null,
            'transmission' => null,
            'manufacturer' => 'Honda Motor Co., Ltd.',
            'country' => 'United States',
            'additional_info' => [
                'WMI' => '1HG',
                'VDS' => 'BH41JX',
                'VIS' => 'MN109186',
                'decoded_by' => 'local_decoder',
                'decoding_date' => '2024-01-01 12:00:00'
            ],
            'validation' => [
                'error_code' => null,
                'error_text' => null,
                'is_valid' => true
            ]
        ];
    }

    public static function getFordLocalData(): array
    {
        return [
            'make' => 'Ford',
            'model' => null,
            'year' => '2013',
            'trim' => null,
            'engine' => null,
            'plant' => null,
            'body_style' => null,
            'fuel_type' => null,
            'transmission' => null,
            'manufacturer' => 'Ford Motor Company',
            'country' => 'United States',
            'additional_info' => [
                'WMI' => '1FT',
                'VDS' => 'FW1ET5',
                'VIS' => 'DFC10312',
                'decoded_by' => 'local_decoder',
                'decoding_date' => '2024-01-01 12:00:00'
            ],
            'validation' => [
                'error_code' => null,
                'error_text' => null,
                'is_valid' => true
            ]
        ];
    }

    public static function getInvalidVinData(): array
    {
        return [
            'make' => 'Unknown',
            'model' => null,
            'year' => 'Unknown',
            'trim' => null,
            'engine' => null,
            'plant' => null,
            'body_style' => null,
            'fuel_type' => null,
            'transmission' => null,
            'manufacturer' => 'Unknown',
            'country' => 'Unknown',
            'additional_info' => [
                'WMI' => 'UNK',
                'VDS' => 'NOWN',
                'VIS' => 'INVALID',
                'decoded_by' => 'local_decoder',
                'decoding_date' => '2024-01-01 12:00:00'
            ],
            'validation' => [
                'error_code' => '1',
                'error_text' => 'Invalid VIN format',
                'is_valid' => false
            ]
        ];
    }

    public static function getValidVins(): array
    {
        return [
            self::VALID_VIN_TOYOTA,
            self::VALID_VIN_HONDA,
            self::VALID_VIN_FORD,
            '2T1BURHE0JC014139', // Toyota Corolla
            'WBXHT910X0WW36596', // BMW
            '5FRYD3H26GB001734', // Honda Pilot
            'JM1DKFC73G0109637', // Mazda
            '1GCCS14W3R8176642', // Chevrolet
            '1N4AL3AP8DC257929', // Nissan
            'KM8J33A48HU133419'  // Hyundai
        ];
    }

    public static function getInvalidVins(): array
    {
        return [
            self::INVALID_VIN_SHORT,
            self::INVALID_VIN_LONG,
            self::INVALID_VIN_CHARS,
            '', // Empty
            '5TDYK3DC8DS29023I', // Contains I
            '5TDYK3DC8DS29023O', // Contains O
            '5TDYK3DC8DS29023Q', // Contains Q
            'AAAAAAAAAAAAAAAAA', // All A's
            '11111111111111111', // All 1's
            'abcdefghijklmnopq'  // Lowercase
        ];
    }

    public static function getTestVinsByMake(): array
    {
        return [
            'Toyota' => [
                '5TDYK3DC8DS290235', // Sienna
                '2T1BURHE0JC014139', // Corolla
                '4T1BF1FK5CU000001'  // Camry
            ],
            'Honda' => [
                '1HGBH41JXMN109186', // Accord
                '5FRYD3H26GB001734', // Pilot
                '19UUA8F2XGA000001'  // Ridgeline
            ],
            'Ford' => [
                '1FTFW1ET5DFC10312', // F-150
                '1FA6P8TH0J5000001', // Mustang
                '1FMCU0HD0KUA00001'  // Escape
            ],
            'BMW' => [
                'WBXHT910X0WW36596', // X3
                'WBA3B1C50DF000001', // 3 Series
                'WBS3T9C58FP000001'  // M3
            ]
        ];
    }

    public static function getCacheKeys(string $vin): array
    {
        $hash = md5($vin);

        return [
            'vin_data' => 'vin_data_' . $hash,
            'local' => 'local_vin_' . $hash,
            'nhtsa_api' => 'nhtsa_api_' . $hash,
            'clearvin' => 'clearvin_' . $hash,
            'metadata' => 'vin_metadata_' . $hash
        ];
    }

    public static function getExpectedFieldPriorities(): array
    {
        return [
            'make' => ['nhtsa_api', 'clearvin', 'local'],
            'model' => ['nhtsa_api', 'clearvin', 'local'],
            'year' => ['nhtsa_api', 'clearvin', 'local'],
            'trim' => ['clearvin', 'nhtsa_api', 'local'],
            'engine' => ['clearvin', 'nhtsa_api', 'local'],
            'dimensions' => ['clearvin'],
            'pricing' => ['clearvin'],
            'mileage' => ['clearvin'],
            'validation' => ['nhtsa_api']
        ];
    }
}
