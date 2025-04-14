<?php

namespace Shekel\VinPackage\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\Decoders\LocalVinDecoder;
use Shekel\VinPackage\ValueObjects\VehicleInfo;

class VinDecoderService
{
    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var string
     */
    private string $apiBaseUrl;

    /**
     * @var VinCacheInterface|null
     */
    private ?VinCacheInterface $cache;

    /**
     * @var int|null Default cache TTL in seconds (30 days)
     */
    private ?int $cacheTtl;
    
    /**
     * @var LocalVinDecoder
     */
    private LocalVinDecoder $localDecoder;
    
    /**
     * @var bool
     */
    private bool $useLocalFallback = true;

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
     * Constructor
     * 
     * @param string|null $apiUrl Base URL for the VIN API
     * @param VinCacheInterface|null $cache Cache implementation
     * @param int|null $cacheTtl Default cache TTL in seconds (30 days if null)
     * @param bool $useLocalFallback Whether to use local decoder as fallback
     */
    public function __construct(
        ?string $apiUrl = null,
        ?VinCacheInterface $cache = null,
        ?int $cacheTtl = null,
        bool $useLocalFallback = true
    ) {
        $this->client = new Client(['timeout' => 15]);
        
        // Default to NHTSA API if no API URL provided
        $this->apiBaseUrl = $apiUrl ?? 'https://vpic.nhtsa.dot.gov/api/vehicles/decodevin/';
        
        // Set cache and TTL
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl ?? 2592000; // 30 days default
        
        // Initialize local decoder
        $this->localDecoder = new LocalVinDecoder();
        $this->useLocalFallback = $useLocalFallback;
    }

    /**
     * Decode a VIN to get vehicle information
     * 
     * @param string $vin
     * @param bool $skipCache Whether to skip checking the cache
     * @param bool $forceRefresh Whether to force API refresh for locally decoded VINs
     * @return VehicleInfo
     * @throws \Exception On general errors
     * @throws \GuzzleHttp\Exception\RequestException On API request failures
     * @throws \InvalidArgumentException On invalid VIN format
     */
    public function decode(string $vin, bool $skipCache = false, bool $forceRefresh = false): VehicleInfo
    {
        // Validate VIN format
        if (strlen($vin) !== 17) {
            throw new \InvalidArgumentException("Invalid VIN format: VIN must be exactly 17 characters");
        }

        $cacheKey = 'vin_data_' . md5($vin);
        
        // Check cache first if available and not skipped
        if (!$skipCache && $this->cache && $this->cache->has($cacheKey)) {
            $cachedData = $this->cache->get($cacheKey);
            
            // If data was locally decoded and we're not forcing a refresh, return it
            if (!$forceRefresh || empty($cachedData['additional_info']['decoded_by']) || 
                $cachedData['additional_info']['decoded_by'] !== 'local_decoder') {
                return VehicleInfo::fromArray($cachedData);
            }
            
            // Otherwise, try to get fresh data from API
            // but keep the local data as fallback if API fails
        }
        
        try {
            // Try to get data from the API
            $response = $this->client->get("{$this->apiBaseUrl}{$vin}?format=json");
            $data = json_decode($response->getBody(), true);

            if (!$data || !isset($data['Results'])) {
                // API returned invalid response, try local decoder if enabled
                if ($this->useLocalFallback) {
                    return $this->decodeLocally($vin);
                }
                throw new \Exception("Failed to decode VIN: Invalid API response format");
            }

            $result = $this->formatVehicleData($data['Results']);
            
            // Mark as API-decoded
            $result['additional_info']['decoded_by'] = 'nhtsa_api';
            $result['additional_info']['decoding_date'] = date('Y-m-d H:i:s');
            
            // Store in cache if available
            if ($this->cache) {
                $this->cache->set($cacheKey, $result, $this->cacheTtl);
            }

            return VehicleInfo::fromArray($result);
        } 
        catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Connection-related errors (network issues, timeouts)
            if ($this->useLocalFallback) {
                return $this->decodeLocally($vin);
            }
            throw new \Exception("API Connection Error: " . $e->getMessage(), $e->getCode(), $e);
        }
        catch (\GuzzleHttp\Exception\RequestException $e) {
            // HTTP errors (4xx, 5xx)
            if ($this->useLocalFallback) {
                return $this->decodeLocally($vin);
            }
            throw new \Exception("API Request Error: " . $e->getMessage(), $e->getCode(), $e);
        }
        catch (\Exception $e) {
            // Generic catch-all for any other errors
            if ($this->useLocalFallback) {
                return $this->decodeLocally($vin);
            }
            throw new \Exception("API Error: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * Decode a VIN locally without using the API
     * 
     * @param string $vin
     * @return VehicleInfo
     */
    public function decodeLocally(string $vin): VehicleInfo
    {
        $localData = $this->localDecoder->decode($vin);
        
        // Store in cache if available
        if ($this->cache) {
            $cacheKey = 'vin_data_' . md5($vin);
            $this->cache->set($cacheKey, $localData, $this->cacheTtl);
        }
        
        return VehicleInfo::fromArray($localData);
    }

    /**
     * Format the API response into a more user-friendly structure
     * 
     * @param array $results
     * @return array
     */
    private function formatVehicleData(array $results): array
    {
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
            'additional_info' => []
        ];

        // Extract relevant data from the API response
        foreach ($results as $item) {
            if (!isset($item['Value']) || $item['Value'] === null || $item['Value'] === '') {
                continue;
            }
            
            switch ($item['Variable']) {
                case 'Make':
                    $vehicle['make'] = $item['Value'];
                    break;
                case 'Model':
                    $vehicle['model'] = $item['Value'];
                    break;
                case 'Model Year':
                    $vehicle['year'] = $item['Value'];
                    break;
                case 'Trim':
                    $vehicle['trim'] = $item['Value'];
                    break;
                case 'Engine':
                case 'DisplacementL':
                    $vehicle['engine'] = $item['Value'];
                    break;
                case 'Plant City':
                    $vehicle['plant'] = $item['Value'];
                    break;
                case 'Body Class':
                    $vehicle['body_style'] = $item['Value'];
                    break;
                case 'Fuel Type - Primary':
                    $vehicle['fuel_type'] = $item['Value'];
                    break;
                case 'Transmission Style':
                    $vehicle['transmission'] = $item['Value'];
                    break;
                case 'Manufacturer Name':
                    $vehicle['manufacturer'] = $item['Value'];
                    break;
                case 'Plant Country':
                    $vehicle['country'] = $item['Value'];
                    break;
                default:
                    // Store any other valuable information
                    $vehicle['additional_info'][$item['Variable']] = $item['Value'];
                    break;
            }
        }

        return $vehicle;
    }

    /**
     * Decode the model year from VIN year code
     * 
     * @param string $yearCode
     * @return string
     */
    public function decodeModelYear(string $yearCode): string
    {
        return self::YEAR_CODES[$yearCode] ?? 'Unknown';
    }

    /**
     * Get the World Manufacturer Identification mapping
     * 
     * @param string $wmi
     * @param bool $skipCache Whether to skip checking the cache
     * @return array|null
     */
    public function getManufacturerInfo(string $wmi, bool $skipCache = false): ?array
    {
        $cacheKey = 'vin_wmi_' . md5($wmi);
        
        // Check cache first if available and not skipped
        if (!$skipCache && $this->cache && $this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        
        try {
            $response = $this->client->get("https://vpic.nhtsa.dot.gov/api/vehicles/GetWMIsForManufacturer/{$wmi}?format=json");
            $data = json_decode($response->getBody(), true);

            if (!$data || !isset($data['Results']) || empty($data['Results'])) {
                return null;
            }

            $result = $data['Results'][0];
            
            // Store in cache if available
            if ($this->cache) {
                $this->cache->set($cacheKey, $result, $this->cacheTtl);
            }
            
            return $result;
        } catch (GuzzleException $e) {
            return null;
        }
    }
    
    /**
     * Clear cached data for a specific VIN
     *
     * @param string $vin
     * @return bool
     */
    public function clearCacheForVin(string $vin): bool
    {
        if (!$this->cache) {
            return false;
        }
        
        $cacheKey = 'vin_data_' . md5($vin);
        return $this->cache->delete($cacheKey);
    }
    
    /**
     * Set a custom cache TTL
     *
     * @param int|null $ttl
     * @return self
     */
    public function setCacheTtl(?int $ttl): self
    {
        $this->cacheTtl = $ttl;
        return $this;
    }
    
    /**
     * Enable or disable local fallback decoding
     *
     * @param bool $enabled
     * @return self
     */
    public function setLocalFallback(bool $enabled): self
    {
        $this->useLocalFallback = $enabled;
        return $this;
    }
    
    /**
     * Check if data was decoded locally
     *
     * @param VehicleInfo $vehicleInfo
     * @return bool
     */
    public function isLocallyDecoded(VehicleInfo $vehicleInfo): bool
    {
        return $vehicleInfo->isLocallyDecoded();
    }
}