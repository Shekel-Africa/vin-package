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
        '1HG' => 'Honda',
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
        'JT2' => 'Toyota',
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
        'WBS' => 'BMW M',
        'WBX' => 'BMW SUV',
        'WDC' => 'Mercedes-Benz SUV',
        'WDD' => 'Mercedes-Benz',
        'WME' => 'Smart',
        'WP0' => 'Porsche',
        'WP1' => 'Porsche SUV',
        'WUA' => 'Audi Sport/RS',
        'WVG' => 'Volkswagen SUV',
        'WVW' => 'Volkswagen',
        'XTA' => 'Lada/AvtoVAZ',
        'YV1' => 'Volvo',
        'YV4' => 'Volvo SUV',
        'ZFF' => 'Ferrari',
        'ZHW' => 'Lamborghini',
        'SAJ' => 'Jaguar',
        'SAL' => 'Land Rover',
        'JF1' => 'Subaru',
        'JF2' => 'Subaru',
        'TMB' => 'Škoda',
        '3VW' => 'Volkswagen (Mexico)',
        '93H' => 'Honda (Brazil)',
        'NMT' => 'Toyota (Turkey)',
        'VSS' => 'SEAT',
        'MA1' => 'Mahindra',
        'MM8' => 'Mazda',
        'MRH' => 'MG',
        'PL1' => 'Proton'
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
     * @var \Shekel\VinPackage\Contracts\VinCacheInterface|null
     */
    private ?\Shekel\VinPackage\Contracts\VinCacheInterface $cache = null;
    
    /**
     * Runtime manufacturer codes that combine default codes with cached codes
     * 
     * @var array
     */
    private array $runtimeManufacturerCodes = [];
    
    /**
     * Constructor initializes runtime manufacturer codes
     */
    public function __construct()
    {
        // Initialize runtime codes with the default codes
        $this->runtimeManufacturerCodes = self::MANUFACTURER_CODES;
    }

    /**
     * Set the cache implementation for storing manufacturer codes
     * 
     * @param \Shekel\VinPackage\Contracts\VinCacheInterface|null $cache
     * @return self
     */
    public function setCache(?\Shekel\VinPackage\Contracts\VinCacheInterface $cache): self
    {
        $this->cache = $cache;
        
        // If cache is provided, load any stored manufacturer codes
        if ($cache && $cache->has('manufacturer_codes')) {
            $this->loadManufacturerCodesFromCache();
        }
        
        return $this;
    }
    
    /**
     * Load manufacturer codes from cache
     * 
     * @return void
     */
    private function loadManufacturerCodesFromCache(): void
    {
        if (!$this->cache) {
            return;
        }
        
        $cachedCodes = $this->cache->get('manufacturer_codes');
        if (!is_array($cachedCodes)) {
            return;
        }
        
        // Merge cached codes with built-in codes (built-in codes take precedence)
        // This ensures we always have the most accurate and up-to-date information
        $this->runtimeManufacturerCodes = array_merge($cachedCodes, self::MANUFACTURER_CODES);
    }
    /**
     * Extract the World Manufacturer Identifier (WMI) from the VIN
     * 
     * @param string $vin
     * @return string
     */
    private function extractWMI(string $vin): string
    {
        // Extract the first 3 characters of the VIN as WMI
        return substr($vin, 0, 3);
    }

    /**
     * Decode a VIN locally without using external APIs
     * 
     * @param string $vin
     * @return array
     */
    public function decode(string $vin): array
    {
        $wmi = $this->extractWMI($vin);
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
                'wmi' => $wmi,
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
        $manufacturer = $this->getManufacturerFromWMI($wmi);
        
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
     * Uses runtime manufacturer codes which include cached codes
     *
     * @param string $wmi
     * @return string|null
     */
    public function getManufacturerFromWMI(string $wmi): ?string
    {
        return $this->runtimeManufacturerCodes[$wmi] ?? null;
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

    /**
     * Add a new manufacturer code to the local database
     * Used to dynamically enhance the local database with codes from NHTSA
     *
     * @param string $wmi World Manufacturer Identifier (first 3 chars of VIN)
     * @param string $manufacturerName Name of the manufacturer
     * @return void
     */
    public function addManufacturerCode(string $wmi, string $manufacturerName): void
    {
        // Make sure WMI is exactly 3 characters
        if (strlen($wmi) !== 3) {
            return;
        }
        
        // Only add if this WMI doesn't exist in our built-in database
        if (!isset(self::MANUFACTURER_CODES[$wmi])) {
            // Add to runtime codes
            $this->runtimeManufacturerCodes[$wmi] = $manufacturerName;
            
            // Cache the updated manufacturer codes
            $this->cacheManufacturerCodes();
        }
    }
    
    /**
     * Cache the current manufacturer codes
     * 
     * @return void
     */
    private function cacheManufacturerCodes(): void
    {
        if (!$this->cache) {
            return;
        }
        
        // Cache only the runtime codes that aren't in the built-in database
        $cacheCodes = array_diff_key($this->runtimeManufacturerCodes, self::MANUFACTURER_CODES);
        $this->cache->set('manufacturer_codes', $cacheCodes);
    }
    
    /**
     * Get all manufacturer codes currently registered (built-in + cached)
     *
     * @return array
     */
    public function getManufacturerCodes(): array
    {
        return $this->runtimeManufacturerCodes;
    }
}