<?php

namespace Shekel\VinPackage\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\Decoders\LocalVinDecoder;
use Shekel\VinPackage\ValueObjects\VehicleInfo;
use Shekel\VinPackage\Services\VinDataSourceChain;
use Shekel\VinPackage\Services\VinDataMerger;

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
     * @var VinDataSourceChain|null
     */
    private ?VinDataSourceChain $dataSourceChain = null;

    /**
     * @var VinDataMerger|null
     */
    private ?VinDataMerger $dataMerger = null;

    /**
     * @var string
     */
    private string $executionStrategy = 'fail_fast';

    /**
     * @var bool
     */
    private bool $isExtensible = false;

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
     * Constructor - supports both legacy and extensible architectures
     *
     * Legacy constructor:
     * @param string|null $apiUrl Base URL for the VIN API
     * @param VinCacheInterface|null $cache Cache implementation
     * @param int|null $cacheTtl Default cache TTL in seconds (30 days if null)
     * @param bool $useLocalFallback Whether to use local decoder as fallback
     *
     * Extensible constructor:
     * @param VinDataSourceChain $dataSourceChain Chain of data sources
     * @param VinDataMerger $dataMerger Data merger
     * @param VinCacheInterface|null $cache Cache implementation
     * @param string $executionStrategy Execution strategy (fail_fast or collect_all)
     * @param int|null $cacheTtl Default cache TTL in seconds
     */
    public function __construct(
        $apiUrlOrChain = null,
        $cacheOrMerger = null,
        $cacheTtlOrCache = null,
        $useLocalFallbackOrStrategy = true,
        ?int $cacheTtl = null
    ) {
        // Detect if this is the new extensible architecture
        if ($apiUrlOrChain instanceof VinDataSourceChain) {
            $this->initializeExtensible(
                $apiUrlOrChain,
                $cacheOrMerger,
                $cacheTtlOrCache,
                $useLocalFallbackOrStrategy,
                $cacheTtl
            );
        } else {
            $this->initializeLegacy($apiUrlOrChain, $cacheOrMerger, $cacheTtlOrCache, $useLocalFallbackOrStrategy);
        }
    }

    private function initializeExtensible(
        VinDataSourceChain $dataSourceChain,
        VinDataMerger $dataMerger,
        ?VinCacheInterface $cache,
        string $executionStrategy = 'fail_fast',
        ?int $cacheTtl = null
    ): void {
        $this->isExtensible = true;
        $this->dataSourceChain = $dataSourceChain;
        $this->dataMerger = $dataMerger;
        $this->cache = $cache;
        $this->executionStrategy = $executionStrategy;
        $this->cacheTtl = $cacheTtl ?? 2592000; // 30 days default

        // Initialize local decoder for backward compatibility
        $this->localDecoder = new LocalVinDecoder();
        if ($cache) {
            $this->localDecoder->setCache($cache);
        }
    }

    private function initializeLegacy(
        ?string $apiUrl,
        ?VinCacheInterface $cache,
        ?int $cacheTtl,
        bool $useLocalFallback
    ): void {
        $this->isExtensible = false;
        $this->client = new Client(['timeout' => 15]);

        // Default to NHTSA API if no API URL provided
        $this->apiBaseUrl = $apiUrl ?? 'https://vpic.nhtsa.dot.gov/api/vehicles/decodevinextended/';

        // Set cache and TTL
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl ?? 2592000; // 30 days default

        // Initialize local decoder
        $this->localDecoder = new LocalVinDecoder();

        // Pass the cache to the local decoder if available
        if ($cache) {
            $this->localDecoder->setCache($cache);
        }

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

        if ($this->isExtensible) {
            return $this->decodeExtensible($vin, $skipCache);
        } else {
            return $this->decodeLegacy($vin, $skipCache, $forceRefresh);
        }
    }

    private function decodeExtensible(string $vin, bool $skipCache): VehicleInfo
    {
        $cacheKey = 'vin_data_' . md5($vin);

        // Check cache first if available and not skipped
        if (!$skipCache && $this->cache && $this->cache->has($cacheKey)) {
            $cachedData = $this->cache->get($cacheKey);
            return VehicleInfo::fromArray($cachedData);
        }

        // Execute the data source chain
        $chainResult = $this->dataSourceChain->executeChain($vin, $this->executionStrategy);

        if (!$chainResult->hasSuccessfulResults()) {
            throw new \Exception("No data sources were able to decode VIN: {$vin}");
        }

        // Merge data from successful sources
        $mergedData = $this->dataMerger->merge($chainResult->getSuccessfulResults());

        // Store in cache if available
        if ($this->cache) {
            $this->cache->set($cacheKey, $mergedData, $this->cacheTtl);
        }

        return VehicleInfo::fromArray($mergedData);
    }

    private function decodeLegacy(string $vin, bool $skipCache, bool $forceRefresh): VehicleInfo
    {
        $cacheKey = 'vin_data_' . md5($vin);

        // Check cache first if available and not skipped
        if (!$skipCache && $this->cache && $this->cache->has($cacheKey)) {
            $cachedData = $this->cache->get($cacheKey);
            $cacheMetadata = $cachedData['cache_metadata'] ?? [];

            // If data was NOT locally decoded and came from successful API call, return it
            if (
                !empty($cacheMetadata['decoded_by']) &&
                $cacheMetadata['decoded_by'] === 'nhtsa_api' &&
                ($cacheMetadata['api_call_success'] ?? false)
            ) {
                return VehicleInfo::fromArray($cachedData);
            }

            // If data was locally decoded OR from failed API call, continue to decode locally first
        }

        // Always decode locally first to get base data
        $localData = $this->localDecoder->decode($vin);

        try {
            // Try to get enhanced data from the API
            $response = $this->client->get("{$this->apiBaseUrl}{$vin}?format=json");
            $data = json_decode($response->getBody(), true);

            if (!$data || !isset($data['Results'])) {
                // API returned invalid response, return local data with failure metadata
                return $this->createVehicleInfoWithMetadata($localData, false, 'Invalid API response', $vin);
            }

            // Format API data and merge with local data
            $apiData = $this->formatVehicleData($data['Results'], $vin);
            $mergedData = $this->mergeLocalAndApiData($localData, $apiData);

            // Mark as API-enhanced with success metadata
            $mergedData['cache_metadata'] = [
                'decoded_by' => 'nhtsa_api',
                'api_call_success' => true,
                'decoding_date' => date('Y-m-d H:i:s'),
                'last_api_attempt' => date('Y-m-d H:i:s'),
                'enhanced_from_local' => true
            ];

            // Store in cache if available
            if ($this->cache) {
                $this->cache->set(
                    $cacheKey,
                    $mergedData,
                    $this->cacheTtl
                );
            }

            return VehicleInfo::fromArray($mergedData);
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Connection-related errors (network issues, timeouts)
            return $this->createVehicleInfoWithMetadata(
                $localData,
                false,
                'Connection error: ' . $e->getMessage(),
                $vin
            );
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // HTTP errors (4xx, 5xx)
            return $this->createVehicleInfoWithMetadata($localData, false, 'Request error: ' . $e->getMessage(), $vin);
        } catch (\Exception $e) {
            // Generic catch-all for any other errors
            return $this->createVehicleInfoWithMetadata($localData, false, 'API error: ' . $e->getMessage(), $vin);
        }
    }

    /**
     * Decode a VIN locally without using the API
     *
     * @param string $vin
     * @param bool $apiCallFailed Whether this local decode is due to API failure
     * @return VehicleInfo
     */
    public function decodeLocally(string $vin, bool $apiCallFailed = false): VehicleInfo
    {
        $localData = $this->localDecoder->decode($vin);

        return $this->createVehicleInfoWithMetadata(
            $localData,
            false,
            $apiCallFailed ? 'API call failed, used local fallback' : 'Direct local decode',
            $vin
        );
    }

    /**
     * Format the API response into a more user-friendly structure
     *
     * @param array $results
     * @param string $vin Original VIN for extracting WMI
     * @return array
     */
    private function formatVehicleData(array $results, string $vin): array
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
            'additional_info' => [],
            'validation' => [
                'error_code' => null,
                'error_text' => null,
                'is_valid' => true
            ]
        ];

        // Extract WMI from the VIN (first 3 characters)
        $wmi = substr($vin, 0, 3);
        $vehicle['additional_info']['WMI'] = $wmi;

        // Extract relevant data from the API response
        foreach ($results as $item) {
            // Extract error code and text for validation
            if ($item['Variable'] === 'Error Code') {
                $vehicle['validation']['error_code'] = $item['Value'];
                // If error code is not 0, mark as invalid
                if ($item['Value'] !== '0' && !empty($item['Value'])) {
                    $vehicle['validation']['is_valid'] = false;
                }
                continue;
            }

            if ($item['Variable'] === 'Error Text') {
                $vehicle['validation']['error_text'] = $item['Value'];
                continue;
            }

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

        // If we have manufacturer name and WMI, enhance local database
        if (!empty($vehicle['manufacturer'])) {
            $this->enhanceLocalManufacturerDatabase($wmi, $vehicle['manufacturer']);
        } elseif (!empty($vehicle['make'])) {
            $this->enhanceLocalManufacturerDatabase($wmi, $vehicle['make']);
        }
        // If we have make name and WMI, use that instead for enhancing the database

        return $vehicle;
    }

    /**
     * Enhance the local manufacturer database with data from NHTSA
     *
     * @param string $wmi
     * @param string $manufacturerName
     * @return void
     */
    private function enhanceLocalManufacturerDatabase(string $wmi, string $manufacturerName): void
    {
        // Only use the first 3 characters of the WMI
        $wmi = substr($wmi, 0, 3);

        // Add to the local decoder's manufacturer database
        $this->localDecoder->addManufacturerCode($wmi, $manufacturerName);
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
    public function getManufacturerInfo(
        string $wmi,
        bool $skipCache = false
    ): ?array {
        $cacheKey = 'vin_wmi_' . md5($wmi);

        // Check cache first if available and not skipped
        if (!$skipCache && $this->cache && $this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        try {
            $response = $this->client->get(
                "https://vpic.nhtsa.dot.gov/api/vehicles/GetWMIsForManufacturer/{$wmi}?format=json"
            );
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

    /**
     * Create VehicleInfo with cache metadata
     *
     * @param array $vehicleData
     * @param bool $apiSuccess
     * @param string $notes
     * @param string $vin
     * @return VehicleInfo
     */
    private function createVehicleInfoWithMetadata(
        array $vehicleData,
        bool $apiSuccess,
        string $notes = '',
        string $vin = ''
    ): VehicleInfo {
        $vehicleData['cache_metadata'] = [
            'decoded_by' => $apiSuccess ? 'nhtsa_api' : 'local_decoder',
            'api_call_success' => $apiSuccess,
            'decoding_date' => date('Y-m-d H:i:s'),
            'last_api_attempt' => date('Y-m-d H:i:s'),
            'notes' => $notes
        ];

        // Store in cache if available
        if ($this->cache && !empty($vin)) {
            $cacheKey = 'vin_data_' . md5($vin);
            $this->cache->set($cacheKey, $vehicleData, $this->cacheTtl);
        }

        return VehicleInfo::fromArray($vehicleData);
    }

    /**
     * Merge local and API data, preferring API data when available
     *
     * @param array $localData
     * @param array $apiData
     * @return array
     */
    private function mergeLocalAndApiData(array $localData, array $apiData): array
    {
        // Start with local data as base
        $mergedData = $localData;

        // Override with API data where API provides better information
        $fieldsToMerge = ['make', 'model', 'year', 'trim', 'engine', 'plant', 'body_style',
                         'fuel_type', 'transmission', 'manufacturer', 'country', 'validation'];

        foreach ($fieldsToMerge as $field) {
            if (!empty($apiData[$field])) {
                $mergedData[$field] = $apiData[$field];
            }
        }

        // Merge additional_info, preserving both local and API data
        if (!empty($apiData['additional_info'])) {
            $mergedData['additional_info'] = array_merge(
                $mergedData['additional_info'] ?? [],
                $apiData['additional_info']
            );
        }

        return $mergedData;
    }

    // Methods for extensible architecture support

    public function getDataSources(): array
    {
        return $this->isExtensible ? $this->dataSourceChain->getSources() : [];
    }

    public function getExecutionStrategy(): string
    {
        return $this->executionStrategy;
    }

    public function getMergeStrategy(): string
    {
        return $this->isExtensible ? $this->dataMerger->getMergeStrategy() : 'priority';
    }

    public function getCache(): ?VinCacheInterface
    {
        return $this->cache;
    }

    public function getCacheTTL(): int
    {
        return $this->cacheTtl;
    }

    public function getFieldPriorities(): array
    {
        return $this->isExtensible ? $this->dataMerger->getFieldPriorities() : [];
    }

    public function getConflictResolution(): string
    {
        return $this->isExtensible ? $this->dataMerger->getConflictResolution() : 'priority';
    }
}
