<?php

namespace Shekel\VinPackage\DataSources;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shekel\VinPackage\Contracts\VinDataSourceInterface;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;

/**
 * ClearVIN web scraping data source
 */
class ClearVinDataSource implements VinDataSourceInterface
{
    private Client $client;
    private ?VinCacheInterface $cache;
    private int $timeout;
    private bool $enabled = true;
    private int $cacheTtl = 2592000; // 30 days

    public function __construct(
        ?Client $client = null,
        ?VinCacheInterface $cache = null,
        int $timeout = 10
    ) {
        $this->client = $client ?? new Client(['timeout' => $timeout]);
        $this->cache = $cache;
        $this->timeout = $timeout;
    }

    public function getName(): string
    {
        return 'clearvin';
    }

    public function getPriority(): int
    {
        return 3; // Third priority (enhanced details)
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function canHandle(string $vin): bool
    {
        return strlen($vin) === 17 && ctype_alnum($vin);
    }

    public function decode(string $vin): VinDataSourceResult
    {
        if (!$this->enabled) {
            return new VinDataSourceResult(
                false,
                [],
                $this->getName(),
                'ClearVIN source is disabled'
            );
        }

        if (!$this->canHandle($vin)) {
            return new VinDataSourceResult(
                false,
                [],
                $this->getName(),
                'Invalid VIN format for ClearVIN'
            );
        }

        $startTime = microtime(true);
        $cacheKey = 'clearvin_' . md5($vin);

        // Check cache first
        if ($this->cache && $this->cache->has($cacheKey)) {
            $data = $this->cache->get($cacheKey);
            $executionTime = microtime(true) - $startTime;

            return new VinDataSourceResult(
                true,
                $data,
                $this->getName(),
                null,
                [
                    'decoded_by' => 'clearvin',
                    'api_call_success' => true,
                    'execution_time' => $executionTime,
                    'cache_hit' => true,
                    'url' => $this->buildUrl($vin)
                ]
            );
        }

        try {
            $url = $this->buildUrl($vin);
            $requestStart = microtime(true);

            $response = $this->client->get($url);
            $responseTime = microtime(true) - $requestStart;

            $markdown = $response->getBody()->getContents();

            if (empty($markdown)) {
                return $this->createFailureResult(
                    $vin,
                    'Empty response from ClearVIN',
                    microtime(true) - $startTime
                );
            }

            $data = $this->parseMarkdown($markdown, $vin);

            if (empty($data) || !$this->hasVehicleData($data)) {
                return $this->createFailureResult(
                    $vin,
                    'No vehicle data found in ClearVIN response',
                    microtime(true) - $startTime
                );
            }

            $executionTime = microtime(true) - $startTime;

            // Cache the result
            if ($this->cache) {
                $this->cache->set($cacheKey, $data, $this->cacheTtl);
            }

            return new VinDataSourceResult(
                true,
                $data,
                $this->getName(),
                null,
                [
                    'decoded_by' => 'clearvin',
                    'api_call_success' => true,
                    'execution_time' => $executionTime,
                    'cache_hit' => false,
                    'url' => $url,
                    'response_time' => $responseTime,
                    'markdown_length' => strlen($markdown)
                ]
            );
        } catch (GuzzleException $e) {
            return $this->createFailureResult(
                $vin,
                'HTTP error: ' . $e->getMessage(),
                microtime(true) - $startTime
            );
        } catch (\Exception $e) {
            return $this->createFailureResult(
                $vin,
                'Parsing error: ' . $e->getMessage(),
                microtime(true) - $startTime
            );
        }
    }

    public function getSourceType(): string
    {
        return 'web';
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function clearCache(string $vin): bool
    {
        if (!$this->cache) {
            return false;
        }

        $cacheKey = 'clearvin_' . md5($vin);
        return $this->cache->delete($cacheKey);
    }

    private function buildUrl(string $vin): string
    {
        return "https://r.jina.ai/https://www.clearvin.com/en/decoder/decode/{$vin}";
    }

    private function parseMarkdown(string $markdown, string $vin): array
    {
        $data = [
            'make' => null,
            'model' => null,
            'year' => null,
            'trim' => null,
            'engine' => null,
            'plant' => null,
            'body_style' => null,
            'fuel_type' => null,
            'transmission' => null,
            'transmission_style' => null,
            'manufacturer' => null,
            'country' => null,
            'dimensions' => [],
            'seating' => [],
            'pricing' => [],
            'mileage' => [],
            'additional_info' => [
                'vin_structure' => [
                    'WMI' => substr($vin, 0, 3),
                    'VDS' => substr($vin, 3, 6),
                    'VIS' => substr($vin, 9, 8),
                    'check_digit' => $vin[8] ?? '0'
                ]
            ],
            'validation' => [
                'error_code' => null,
                'error_text' => null,
                'is_valid' => true
            ]
        ];

        // Parse YMM (Year Make Model) - the main format used by ClearVin
        if (preg_match('/YMM\s*\n\s*([0-9]{4})\s+([A-Za-z]+)\s+(.+)/', $markdown, $matches)) {
            $data['year'] = trim($matches[1]);
            $data['make'] = trim($matches[2]);
            $data['model'] = trim($matches[3]);
            $data['manufacturer'] = trim($matches[2]); // Same as make for most cases
        } else {
            // Fallback to old format individual field extraction
            $data['make'] = $this->extractField($markdown, 'Make');
            $data['model'] = $this->extractField($markdown, 'Model');
            $data['year'] = $this->extractField($markdown, 'Year');
            $data['manufacturer'] = $data['make']; // Same as make for most cases
        }

        // Parse trim using both formats
        $data['trim'] = $this->extractFieldNewFormat($markdown, 'Trim') ?? $this->extractField($markdown, 'Trim');

        // Parse engine using both formats
        $data['engine'] = $this->extractFieldNewFormat($markdown, 'Engine') ?? $this->extractField($markdown, 'Engine');

        // Parse origin/country using both formats
        $data['country'] = $this->extractFieldNewFormat($markdown, 'Made in') ?? $this->extractField($markdown, 'Origin');

        // Parse body style - try both formats
        $data['body_style'] = $this->extractFieldNewFormat($markdown, 'Style') ?? $this->extractField($markdown, 'Style');

        // Parse mechanical information using both formats
        $data['mileage']['city'] = $this->extractFieldNewFormat($markdown, 'City Mileage') ?? $this->extractField($markdown, 'City Mileage');
        $data['mileage']['highway'] = $this->extractFieldNewFormat($markdown, 'Highway Mileage') ?? $this->extractField($markdown, 'Highway Mileage');

        // Parse dimensions using both formats
        $data['dimensions']['length'] = $this->extractFieldNewFormat($markdown, 'Length') ?? $this->extractField($markdown, 'Length');
        $data['dimensions']['width'] = $this->extractFieldNewFormat($markdown, 'Width') ?? $this->extractField($markdown, 'Width');
        $data['dimensions']['height'] = $this->extractFieldNewFormat($markdown, 'Height') ?? $this->extractField($markdown, 'Height');
        $data['dimensions']['wheelbase'] = $this->extractFieldNewFormat($markdown, 'Wheelbase') ?? $this->extractField($markdown, 'Wheelbase');

        // Parse seating using both formats
        $standardSeating = $this->extractFieldNewFormat($markdown, 'Standard Seating') ?? $this->extractField($markdown, 'Standard Seating');
        if ($standardSeating && is_numeric($standardSeating)) {
            $data['seating']['standardSeating'] = (int)$standardSeating;
        }
        $data['seating']['passengerVolume'] = $this->extractFieldNewFormat($markdown, 'Passenger Volume') ?? $this->extractField($markdown, 'Passenger Volume');

        // Parse pricing using both formats
        $data['pricing']['msrp'] = $this->extractFieldNewFormat($markdown, 'MSRP') ?? $this->extractField($markdown, 'MSRP');
        $data['pricing']['dealerInvoice'] = $this->extractFieldNewFormat($markdown, 'Dealer Invoice') ?? $this->extractField($markdown, 'Dealer Invoice');

        // Parse additional ClearVin specific fields
        $data['additional_info']['clearvin_details'] = array_filter([
            'wheel_drive' => $this->extractFieldNewFormat($markdown, 'Wheel Drive') ?? $this->extractField($markdown, 'Wheel Drive'),
            'safety_rating' => $this->extractFieldNewFormat($markdown, 'Safety Rating') ?? $this->extractField($markdown, 'Safety Rating'),
            'fuel_economy_combined' => $this->extractFieldNewFormat($markdown, 'Combined Fuel Economy') ?? $this->extractField($markdown, 'Combined Fuel Economy'),
            'curb_weight' => $this->extractFieldNewFormat($markdown, 'Curb Weight') ?? $this->extractField($markdown, 'Curb Weight'),
            'cargo_volume' => $this->extractFieldNewFormat($markdown, 'Cargo Volume') ?? $this->extractField($markdown, 'Cargo Volume')
        ]);

        // Keep structured rich data even if some fields are empty
        $data['dimensions'] = array_filter($data['dimensions']);
        $data['seating'] = array_filter($data['seating']);
        $data['pricing'] = array_filter($data['pricing']);
        $data['mileage'] = array_filter($data['mileage']);

        // Preserve rich data structure even if empty (important for API consumers)
        if (empty($data['dimensions'])) {
            $data['dimensions'] = null;
        }
        if (empty($data['seating'])) {
            $data['seating'] = null;
        }
        if (empty($data['pricing'])) {
            $data['pricing'] = null;
        }
        if (empty($data['mileage'])) {
            $data['mileage'] = null;
        }

        // If no transmission data was found, try to infer it
        if (empty($data['transmission'])) {
            $transmissionData = $this->inferTransmission($data, $vin);
            $data['transmission'] = $transmissionData['type'] ?? null;
            $data['transmission_style'] = $transmissionData['style'] ?? null;
        }

        return $data;
    }

    private function extractField(string $markdown, string $fieldName): ?string
    {
        // Try different markdown patterns
        $patterns = [
            "/\*\*{$fieldName}:\*\*\s*([^\n\r]+)/i",           // **Field:** Value
            "/\*\*{$fieldName}\*\*:\s*([^\n\r]+)/i",          // **Field**: Value
            "/{$fieldName}:\s*([^\n\r]+)/i",                  // Field: Value
            "/\*\*{$fieldName}:\*\*\s*(.+?)(?=\n|\r|$)/i",    // **Field:** Value (multiline)
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $markdown, $matches)) {
                $value = trim($matches[1]);

                // Clean up common artifacts
                $value = preg_replace('/\s+/', ' ', $value);
                $value = trim($value, " \t\n\r\0\x0B*");

                return !empty($value) && $value !== 'N/A' ? $value : null;
            }
        }

        return null;
    }

    private function extractFieldNewFormat(string $markdown, string $fieldName): ?string
    {
        // ClearVin uses a new format where field names are on their own line
        // followed by the value on the next line
        $pattern = "/{$fieldName}\s*\n\s*(.+?)(?=\n|$)/i";

        if (preg_match($pattern, $markdown, $matches)) {
            $value = trim($matches[1]);

            // Clean up common artifacts
            $value = preg_replace('/\s+/', ' ', $value);
            $value = trim($value, " \t\n\r\0\x0B*");

            return !empty($value) && $value !== 'N/A' ? $value : null;
        }

        return null;
    }

    private function hasVehicleData(array $data): bool
    {
        // Check if we have at least basic vehicle information
        $basicFields = ['make', 'model', 'year'];

        foreach ($basicFields as $field) {
            if (!empty($data[$field])) {
                return true;
            }
        }

        return false;
    }

    private function createFailureResult(string $vin, string $error, float $executionTime): VinDataSourceResult
    {
        return new VinDataSourceResult(
            false,
            [],
            $this->getName(),
            $error,
            [
                'decoded_by' => 'clearvin',
                'api_call_success' => false,
                'execution_time' => $executionTime,
                'cache_hit' => false,
                'url' => $this->buildUrl($vin),
                'error' => $error
            ]
        );
    }

    /**
     * Infer transmission type and style based on vehicle data
     */
    private function inferTransmission(array $vehicle, string $vin): array
    {
        $make = $vehicle['make'] ?? '';
        $model = $vehicle['model'] ?? '';
        $year = $vehicle['year'] ?? '';
        $trim = $vehicle['trim'] ?? '';

        // Skip inference if we don't have basic data
        if (empty($make) || empty($year)) {
            return ['type' => null, 'style' => null];
        }

        $yearInt = is_numeric($year) ? (int)$year : 0;
        if ($yearInt < 1980 || $yearInt > 2030) {
            return ['type' => null, 'style' => null];
        }

        // Toyota Camry specific logic
        if (stripos($make, 'Toyota') !== false && stripos($model, 'Camry') !== false) {
            if ($yearInt >= 2012 && $yearInt <= 2017) {
                // Check if this is likely a V6 based on trim or engine data
                $engineInfo = $vehicle['engine'] ?? '';
                if (
                    stripos($trim, 'V6') !== false ||
                    stripos($engineInfo, 'V6') !== false ||
                    stripos($engineInfo, '3.5') !== false
                ) {
                    return ['type' => 'Automatic', 'style' => '6-Speed'];
                }
                return ['type' => 'Automatic', 'style' => 'CVT'];
            }
            if ($yearInt >= 2018) {
                return ['type' => 'Automatic', 'style' => '8-Speed'];
            }
        }

        // General inference for other vehicles
        if ($yearInt >= 2015) {
            if (stripos($make, 'Toyota') !== false || stripos($make, 'Honda') !== false) {
                return ['type' => 'Automatic', 'style' => 'CVT/6-Speed'];
            }
            if (
                stripos($make, 'BMW') !== false || stripos($make, 'Mercedes') !== false ||
                stripos($make, 'Audi') !== false || stripos($make, 'Lexus') !== false
            ) {
                return ['type' => 'Automatic', 'style' => '8-Speed'];
            }
            return ['type' => 'Automatic', 'style' => '6-Speed'];
        }

        if ($yearInt >= 2005) {
            return ['type' => 'Automatic', 'style' => '5-Speed'];
        }

        return ['type' => 'Automatic', 'style' => '4-Speed'];
    }
}
