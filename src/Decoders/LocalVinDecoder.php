<?php

namespace Shekel\VinPackage\Decoders;

/**
 * Local VIN Decoder class
 * Provides basic VIN decoding without external API calls
 */
class LocalVinDecoder
{
    /**
     * Country codes in the first position of VIN
     * 
     * @var array
     */
    private const COUNTRY_CODES = [
        'A' => 'South Africa',
        'B' => 'Angola',
        'C' => 'Benin',
        'D' => 'Egypt',
        'E' => 'Ethiopia',
        'F' => 'Ghana',
        'G' => 'Ivory Coast',
        'H' => 'Kenya',
        'J' => 'Japan',
        'K' => 'Korea (South)',
        'L' => 'China',
        'M' => 'India',
        'N' => 'Iran',
        'P' => 'Philippines',
        'R' => 'Taiwan',
        'S' => 'United Kingdom',
        'T' => 'Switzerland',
        'U' => 'Denmark',
        'V' => 'Austria',
        'W' => 'Germany',
        'X' => 'Russia',
        'Y' => 'Belgium',
        'Z' => 'Italy',
        '1' => 'United States',
        '2' => 'Canada',
        '3' => 'Mexico',
        '4' => 'United States',
        '5' => 'United States',
        '6' => 'Australia',
        '7' => 'New Zealand',
        '8' => 'Argentina',
        '9' => 'Brazil'
    ];
    
    /**
     * Common manufacturer WMI codes
     * 
     * @var array
     */
    private const MANUFACTURER_CODES = [
        '1FT' => 'Ford Motor Company - Trucks',
        '1FA' => 'Ford Motor Company - Cars',
        '1FM' => 'Ford Motor Company - MPVs',
        '1FD' => 'Ford Motor Company - Commercial Vehicles',
        '1G1' => 'Chevrolet - Car',
        '1GC' => 'Chevrolet - Trucks',
        '1GT' => 'GMC - Trucks',
        '1G6' => 'Cadillac',
        '1HG' => 'Honda', // Added Honda code
        '2G1' => 'Chevrolet (Canada)',
        '2T1' => 'Toyota (Canada)',
        '2HG' => 'Honda (Canada)',
        '3FA' => 'Ford (Mexico)',
        '3N1' => 'Nissan (Mexico)',
        '4S4' => 'Subaru',
        '4T1' => 'Toyota',
        '5FN' => 'Honda',
        '5TD' => 'Toyota',
        '5YJ' => 'Tesla',
        'JH4' => 'Acura',
        'JHM' => 'Honda',
        'JN1' => 'Nissan',
        'JT2' => 'Toyota', // Added Toyota code for the test
        'JT4' => 'Toyota',
        'KL4' => 'Daewoo',
        'KM8' => 'Hyundai',
        'KNA' => 'Kia',
        'SCA' => 'Rolls-Royce',
        'SCC' => 'Lotus',
        'SCF' => 'Aston Martin',
        'VF1' => 'Renault',
        'VF3' => 'Peugeot',
        'VF7' => 'Citroën',
        'W04' => 'Buick',
        'W0L' => 'Opel',
        'WA1' => 'Audi SUV',
        'WAU' => 'Audi',
        'WBA' => 'BMW',
        'WDC' => 'Mercedes-Benz SUV',
        'WDD' => 'Mercedes-Benz',
        'WP0' => 'Porsche',
        'WVW' => 'Volkswagen', // Added Volkswagen code
        'YV1' => 'Volvo'
    ];
    
    /**
     * Year code mapping for model year
     * 
     * @var array
     */
    private const YEAR_CODES = [
        'A' => '2010', 'B' => '2011', 'C' => '2012', 'D' => '2013',
        'E' => '2014', 'F' => '2015', 'G' => '2016', 'H' => '2017',
        'J' => '2018', 'K' => '2019', 'L' => '2020', 'M' => '2021',
        'N' => '2022', 'P' => '2023', 'R' => '2024', 'S' => '2025',
        'T' => '1996', 'V' => '1997', 'W' => '1998', 'X' => '1999',
        'Y' => '2000', '1' => '2001', '2' => '2002', '3' => '2003',
        '4' => '2004', '5' => '2005', '6' => '2006', '7' => '2007',
        '8' => '2008', '9' => '2009'
    ];

    /**
     * Decode a VIN locally without using external APIs
     * 
     * @param string $vin
     * @return array
     */
    public function decode(string $vin): array
    {
        // Initialize vehicle data with the structure expected by VehicleInfo
        $vehicle = [
            'make' => null,
            'model' => null,
            'year' => null,
            'trim' => null,
            'engine' => null,
            'plant' => null,
            'body_style' => null,
            'fuel_type' => null,
            'transmission' => null,
            'manufacturer' => null,
            'country' => null,
            'additional_info' => [
                'decoded_by' => 'local_decoder',
                'decoding_date' => date('Y-m-d H:i:s'),
                'wmi' => substr($vin, 0, 3),
                'vds' => substr($vin, 3, 6),
                'vis' => substr($vin, 9, 8),
                'check_digit' => $vin[8],
                'partial_info' => true
            ]
        ];
        
        // Get country of origin from the first character
        $firstChar = $vin[0];
        $vehicle['country'] = self::COUNTRY_CODES[$firstChar] ?? 'Unknown';
        
        // Get manufacturer by WMI (first 3 characters)
        $wmi = substr($vin, 0, 3);
        $manufacturer = self::MANUFACTURER_CODES[$wmi] ?? null;
        
        if ($manufacturer) {
            $vehicle['manufacturer'] = $manufacturer;
            
            // Extract make from manufacturer (before the dash if present)
            $makeParts = explode(' - ', $manufacturer);
            $vehicle['make'] = $makeParts[0];
            
            // Add any model info (after the dash)
            if (isset($makeParts[1])) {
                $vehicle['additional_info']['vehicle_type'] = $makeParts[1];
            }
        }
        
        // Get the model year from the 10th character of the VIN
        $yearCode = $vin[9];
        $vehicle['year'] = isset(self::YEAR_CODES[$yearCode]) ? self::YEAR_CODES[$yearCode] : 'Unknown';
        
        // Determine the assembly plant from the 11th character
        $vehicle['plant'] = 'Plant Code: ' . $vin[10];
        
        // Extract sequential production number
        $vehicle['additional_info']['serial_number'] = substr($vin, 11);
        
        return $vehicle;
    }
    
    /**
     * Get country from first character of VIN
     *
     * @param string $firstChar
     * @return string|null
     */
    public function getCountryFromCode(string $firstChar): ?string
    {
        return self::COUNTRY_CODES[$firstChar] ?? null;
    }
    
    /**
     * Get manufacturer from WMI (first 3 characters of VIN)
     *
     * @param string $wmi
     * @return string|null
     */
    public function getManufacturerFromWMI(string $wmi): ?string
    {
        return self::MANUFACTURER_CODES[$wmi] ?? null;
    }
    
    /**
     * Get model year from year code (10th character of VIN)
     *
     * @param string $yearCode
     * @return string|null
     */
    public function getYearFromCode(string $yearCode): ?string
    {
        return self::YEAR_CODES[$yearCode] ?? null;
    }
}