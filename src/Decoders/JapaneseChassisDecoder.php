<?php

namespace Shekel\VinPackage\Decoders;

use Shekel\VinPackage\Validators\JapaneseChassisValidator;

/**
 * Japanese Chassis Number Decoder
 *
 * Decodes Japanese Domestic Market (JDM) chassis numbers (frame numbers)
 * using a local database of model codes.
 *
 * Unlike international VINs, Japanese chassis numbers cannot encode:
 * - Model year (year field will be null)
 * - Manufacturing plant location
 * - Check digit (no validation checksum)
 */
class JapaneseChassisDecoder
{
    /**
     * @var JapaneseChassisValidator
     */
    private JapaneseChassisValidator $validator;

    /**
     * @var array|null Cached model codes database
     */
    private ?array $modelCodesDatabase = null;

    /**
     * Path to the Japanese model codes JSON file
     *
     * @var string
     */
    private string $modelCodesPath;

    /**
     * Constructor
     *
     * @param string|null $modelCodesPath Custom path to model codes JSON file
     */
    public function __construct(?string $modelCodesPath = null)
    {
        $this->validator = new JapaneseChassisValidator();
        $this->modelCodesPath = $modelCodesPath ?? dirname(__DIR__, 2) . '/data/japanese_model_codes.json';
    }

    /**
     * Decode a Japanese chassis number
     *
     * @param string $chassisNumber
     * @return array Vehicle information array
     */
    public function decode(string $chassisNumber): array
    {
        $chassisNumber = strtoupper(trim($chassisNumber));

        // Parse the chassis number
        $parsed = $this->validator->parse($chassisNumber);

        if ($parsed === null) {
            return $this->createEmptyResult($chassisNumber, 'Invalid chassis number format');
        }

        $modelCode = $parsed['model_code'];
        $serialNumber = $parsed['serial_number'];

        // Try to find model information in database
        $modelInfo = $this->findModelInfo($modelCode);

        if ($modelInfo === null) {
            return $this->createPartialResult($chassisNumber, $modelCode, $serialNumber);
        }

        return $this->createFullResult($chassisNumber, $modelCode, $serialNumber, $modelInfo);
    }

    /**
     * Find model information from the database
     *
     * @param string $modelCode
     * @return array|null
     */
    private function findModelInfo(string $modelCode): ?array
    {
        $database = $this->loadModelCodesDatabase();

        if ($database === null) {
            return null;
        }

        foreach ($database['manufacturers'] as $manufacturerKey => $manufacturerData) {
            if (isset($manufacturerData['models'][$modelCode])) {
                return [
                    'manufacturer_key' => $manufacturerKey,
                    'manufacturer_name' => $manufacturerData['name'],
                    'country' => $manufacturerData['country'],
                    'serial_length' => $manufacturerData['serial_length'] ?? null,
                    'model' => $manufacturerData['models'][$modelCode],
                ];
            }
        }

        // Try pattern matching for partial model codes
        return $this->findByPattern($modelCode, $database);
    }

    /**
     * Try to find manufacturer by pattern matching
     *
     * @param string $modelCode
     * @param array $database
     * @return array|null
     */
    private function findByPattern(string $modelCode, array $database): ?array
    {
        // Extract potential manufacturer prefix (first 2-3 characters)
        $prefixes = [
            substr($modelCode, 0, 3),
            substr($modelCode, 0, 2),
        ];

        foreach ($database['manufacturers'] as $manufacturerKey => $manufacturerData) {
            foreach ($manufacturerData['models'] as $knownCode => $modelData) {
                foreach ($prefixes as $prefix) {
                    if (strpos($knownCode, $prefix) === 0) {
                        // Found a matching prefix, return manufacturer info
                        return [
                            'manufacturer_key' => $manufacturerKey,
                            'manufacturer_name' => $manufacturerData['name'],
                            'country' => $manufacturerData['country'],
                            'serial_length' => $manufacturerData['serial_length'] ?? null,
                            'model' => null, // Unknown specific model
                            'inferred_from' => $knownCode,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Load the model codes database
     *
     * @return array|null
     */
    private function loadModelCodesDatabase(): ?array
    {
        if ($this->modelCodesDatabase !== null) {
            return $this->modelCodesDatabase;
        }

        if (!file_exists($this->modelCodesPath)) {
            return null;
        }

        $content = file_get_contents($this->modelCodesPath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $this->modelCodesDatabase = $data;
        return $this->modelCodesDatabase;
    }

    /**
     * Create an empty result for invalid chassis numbers
     *
     * @param string $chassisNumber
     * @param string $error
     * @return array
     */
    private function createEmptyResult(string $chassisNumber, string $error): array
    {
        return [
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
            'additional_info' => [
                'chassis_number_structure' => [
                    'raw' => $chassisNumber,
                    'model_code' => null,
                    'serial_number' => null,
                ],
                'identifier_type' => 'japanese_chassis_number',
                'local_decoder_info' => [
                    'decoded_by' => 'japanese_chassis_decoder',
                    'decoding_date' => date('Y-m-d H:i:s'),
                    'partial_info' => true,
                    'database_version' => $this->getDatabaseVersion(),
                    'error' => $error,
                ],
            ],
            'validation' => [
                'error_code' => 'INVALID_FORMAT',
                'error_text' => $error,
                'is_valid' => false,
            ],
        ];
    }

    /**
     * Create a partial result when model code is not in database
     *
     * @param string $chassisNumber
     * @param string $modelCode
     * @param string $serialNumber
     * @return array
     */
    private function createPartialResult(string $chassisNumber, string $modelCode, string $serialNumber): array
    {
        // Try to infer manufacturer from common patterns
        $inferredManufacturer = $this->inferManufacturerFromPattern($modelCode);

        return [
            'make' => $inferredManufacturer['make'] ?? null,
            'model' => null,
            'year' => null, // Cannot be determined from Japanese chassis numbers
            'trim' => null,
            'engine' => null,
            'plant' => null,
            'body_style' => null,
            'fuel_type' => 'Gasoline', // Default assumption
            'transmission' => null,
            'transmission_style' => null,
            'manufacturer' => $inferredManufacturer['manufacturer'] ?? null,
            'country' => 'Japan',
            'additional_info' => [
                'chassis_number_structure' => [
                    'raw' => $chassisNumber,
                    'model_code' => $modelCode,
                    'serial_number' => $serialNumber,
                    'engine_code_prefix' => $this->extractEngineCodePrefix($modelCode),
                    'chassis_designation' => $this->extractChassisDesignation($modelCode),
                ],
                'identifier_type' => 'japanese_chassis_number',
                'local_decoder_info' => [
                    'decoded_by' => 'japanese_chassis_decoder',
                    'decoding_date' => date('Y-m-d H:i:s'),
                    'partial_info' => true,
                    'database_version' => $this->getDatabaseVersion(),
                    'notes' => 'Model code not found in database. Limited information available.',
                ],
            ],
            'validation' => [
                'error_code' => null,
                'error_text' => null,
                'is_valid' => true,
            ],
        ];
    }

    /**
     * Create a full result with all available information
     *
     * @param string $chassisNumber
     * @param string $modelCode
     * @param string $serialNumber
     * @param array $modelInfo
     * @return array
     */
    private function createFullResult(
        string $chassisNumber,
        string $modelCode,
        string $serialNumber,
        array $modelInfo
    ): array {
        $model = $modelInfo['model'] ?? null;

        // Extract engine information
        $engine = null;
        if ($model && isset($model['engine_codes'])) {
            $engine = $this->resolveEngineFromCodes($modelCode, $model['engine_codes']);
        }

        // Get body style (first available)
        $bodyStyle = null;
        if ($model && isset($model['body_styles']) && !empty($model['body_styles'])) {
            $bodyStyle = $model['body_styles'][0];
        }

        // Get production year range as note
        $yearNote = null;
        if ($model && isset($model['production_years'])) {
            $years = $model['production_years'];
            $yearNote = sprintf('%d-%d', $years['start'], $years['end']);
        }

        return [
            'make' => $this->formatMakeName($modelInfo['manufacturer_key']),
            'model' => $model['name'] ?? null,
            'year' => null, // Cannot be determined from Japanese chassis numbers
            'trim' => null,
            'engine' => $engine,
            'plant' => null, // Not encoded in Japanese chassis numbers
            'body_style' => $bodyStyle,
            'fuel_type' => $this->inferFuelType($engine),
            'transmission' => null,
            'transmission_style' => null,
            'manufacturer' => $modelInfo['manufacturer_name'],
            'country' => $modelInfo['country'],
            'additional_info' => [
                'chassis_number_structure' => [
                    'raw' => $chassisNumber,
                    'model_code' => $modelCode,
                    'serial_number' => $serialNumber,
                    'engine_code_prefix' => $this->extractEngineCodePrefix($modelCode),
                    'chassis_designation' => $model['chassis_type'] ?? $this->extractChassisDesignation($modelCode),
                ],
                'identifier_type' => 'japanese_chassis_number',
                'production_years' => $yearNote,
                'available_body_styles' => $model['body_styles'] ?? null,
                'local_decoder_info' => [
                    'decoded_by' => 'japanese_chassis_decoder',
                    'decoding_date' => date('Y-m-d H:i:s'),
                    'partial_info' => false,
                    'database_version' => $this->getDatabaseVersion(),
                ],
            ],
            'validation' => [
                'error_code' => null,
                'error_text' => null,
                'is_valid' => true,
            ],
        ];
    }

    /**
     * Try to infer manufacturer from common model code patterns
     *
     * @param string $modelCode
     * @return array
     */
    private function inferManufacturerFromPattern(string $modelCode): array
    {
        // Common pattern prefixes for Japanese manufacturers
        $patterns = [
            // Toyota patterns
            'JZ' => ['make' => 'Toyota', 'manufacturer' => 'Toyota Motor Corporation'],
            'AE' => ['make' => 'Toyota', 'manufacturer' => 'Toyota Motor Corporation'],
            'SW' => ['make' => 'Toyota', 'manufacturer' => 'Toyota Motor Corporation'],
            'AW' => ['make' => 'Toyota', 'manufacturer' => 'Toyota Motor Corporation'],
            'ST' => ['make' => 'Toyota', 'manufacturer' => 'Toyota Motor Corporation'],
            'SV' => ['make' => 'Toyota', 'manufacturer' => 'Toyota Motor Corporation'],

            // Nissan patterns
            'BN' => ['make' => 'Nissan', 'manufacturer' => 'Nissan Motor Company'],
            'BCN' => ['make' => 'Nissan', 'manufacturer' => 'Nissan Motor Company'],
            'EC' => ['make' => 'Nissan', 'manufacturer' => 'Nissan Motor Company'],
            'ER' => ['make' => 'Nissan', 'manufacturer' => 'Nissan Motor Company'],
            'RPS' => ['make' => 'Nissan', 'manufacturer' => 'Nissan Motor Company'],
            'Z3' => ['make' => 'Nissan', 'manufacturer' => 'Nissan Motor Company'],

            // Honda patterns
            'DC' => ['make' => 'Honda', 'manufacturer' => 'Honda Motor Company'],
            'DB' => ['make' => 'Honda', 'manufacturer' => 'Honda Motor Company'],
            'EK' => ['make' => 'Honda', 'manufacturer' => 'Honda Motor Company'],
            'EG' => ['make' => 'Honda', 'manufacturer' => 'Honda Motor Company'],
            'EP' => ['make' => 'Honda', 'manufacturer' => 'Honda Motor Company'],
            'FD' => ['make' => 'Honda', 'manufacturer' => 'Honda Motor Company'],
            'AP' => ['make' => 'Honda', 'manufacturer' => 'Honda Motor Company'],
            'NA' => ['make' => 'Honda', 'manufacturer' => 'Honda Motor Company'],

            // Subaru patterns
            'GD' => ['make' => 'Subaru', 'manufacturer' => 'Subaru Corporation'],
            'GC' => ['make' => 'Subaru', 'manufacturer' => 'Subaru Corporation'],
            'GR' => ['make' => 'Subaru', 'manufacturer' => 'Subaru Corporation'],
            'VA' => ['make' => 'Subaru', 'manufacturer' => 'Subaru Corporation'],
            'BH' => ['make' => 'Subaru', 'manufacturer' => 'Subaru Corporation'],
            'BP' => ['make' => 'Subaru', 'manufacturer' => 'Subaru Corporation'],
            'SF' => ['make' => 'Subaru', 'manufacturer' => 'Subaru Corporation'],

            // Mazda patterns (Note: FD could be Honda or Mazda)
            'FC' => ['make' => 'Mazda', 'manufacturer' => 'Mazda Motor Corporation'],
            'SE' => ['make' => 'Mazda', 'manufacturer' => 'Mazda Motor Corporation'],
            'NB' => ['make' => 'Mazda', 'manufacturer' => 'Mazda Motor Corporation'],
            'NC' => ['make' => 'Mazda', 'manufacturer' => 'Mazda Motor Corporation'],
            'BK' => ['make' => 'Mazda', 'manufacturer' => 'Mazda Motor Corporation'],

            // Mitsubishi patterns
            'CT' => ['make' => 'Mitsubishi', 'manufacturer' => 'Mitsubishi Motors Corporation'],
            'CP' => ['make' => 'Mitsubishi', 'manufacturer' => 'Mitsubishi Motors Corporation'],
            'CN' => ['make' => 'Mitsubishi', 'manufacturer' => 'Mitsubishi Motors Corporation'],
            'CE' => ['make' => 'Mitsubishi', 'manufacturer' => 'Mitsubishi Motors Corporation'],
            'CZ' => ['make' => 'Mitsubishi', 'manufacturer' => 'Mitsubishi Motors Corporation'],

            // Suzuki patterns
            'EA' => ['make' => 'Suzuki', 'manufacturer' => 'Suzuki Motor Corporation'],
            'ZC' => ['make' => 'Suzuki', 'manufacturer' => 'Suzuki Motor Corporation'],
            'HT' => ['make' => 'Suzuki', 'manufacturer' => 'Suzuki Motor Corporation'],
        ];

        // Check for matching prefix (try longer prefixes first)
        $prefixLengths = [3, 2];
        foreach ($prefixLengths as $length) {
            $prefix = substr($modelCode, 0, $length);
            if (isset($patterns[$prefix])) {
                return $patterns[$prefix];
            }
        }

        return [];
    }

    /**
     * Extract engine code prefix from model code
     *
     * @param string $modelCode
     * @return string|null
     */
    private function extractEngineCodePrefix(string $modelCode): ?string
    {
        // Engine code is typically the first 1-2 letters
        if (preg_match('/^([A-Z]{1,3})/', $modelCode, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract chassis designation from model code
     *
     * @param string $modelCode
     * @return string|null
     */
    private function extractChassisDesignation(string $modelCode): ?string
    {
        // Chassis designation is typically everything after the engine prefix
        if (preg_match('/^[A-Z]{1,3}(.+)$/', $modelCode, $matches)) {
            return $matches[1];
        }
        return $modelCode;
    }

    /**
     * Resolve engine name from engine codes mapping
     *
     * @param string $modelCode
     * @param array $engineCodes
     * @return string|null
     */
    private function resolveEngineFromCodes(string $modelCode, array $engineCodes): ?string
    {
        // Try to match engine code prefix
        $prefix = $this->extractEngineCodePrefix($modelCode);

        if ($prefix !== null) {
            // First try exact match
            if (isset($engineCodes[$prefix])) {
                return $engineCodes[$prefix];
            }

            // Try shorter prefixes
            for ($len = strlen($prefix); $len > 0; $len--) {
                $shortPrefix = substr($prefix, 0, $len);
                if (isset($engineCodes[$shortPrefix])) {
                    return $engineCodes[$shortPrefix];
                }
            }
        }

        // Return first available engine code
        if (!empty($engineCodes)) {
            return reset($engineCodes);
        }

        return null;
    }

    /**
     * Infer fuel type from engine description
     *
     * @param string|null $engine
     * @return string
     */
    private function inferFuelType(?string $engine): string
    {
        if ($engine === null) {
            return 'Gasoline';
        }

        $engineLower = strtolower($engine);

        if (strpos($engineLower, 'rotary') !== false) {
            return 'Gasoline'; // Rotary engines use gasoline
        }

        if (strpos($engineLower, 'diesel') !== false) {
            return 'Diesel';
        }

        if (strpos($engineLower, 'hybrid') !== false) {
            return 'Hybrid';
        }

        if (strpos($engineLower, 'electric') !== false) {
            return 'Electric';
        }

        return 'Gasoline';
    }

    /**
     * Format manufacturer key to display name
     *
     * @param string $manufacturerKey
     * @return string
     */
    private function formatMakeName(string $manufacturerKey): string
    {
        return ucfirst(strtolower($manufacturerKey));
    }

    /**
     * Get the database version
     *
     * @return string
     */
    private function getDatabaseVersion(): string
    {
        $database = $this->loadModelCodesDatabase();

        if ($database && isset($database['metadata']['version'])) {
            return $database['metadata']['version'];
        }

        return 'unknown';
    }

    /**
     * Get all supported manufacturers
     *
     * @return array
     */
    public function getSupportedManufacturers(): array
    {
        $database = $this->loadModelCodesDatabase();

        if ($database === null) {
            return [];
        }

        $manufacturers = [];
        foreach ($database['manufacturers'] as $key => $data) {
            $manufacturers[$key] = $data['name'];
        }

        return $manufacturers;
    }

    /**
     * Get all model codes for a specific manufacturer
     *
     * @param string $manufacturer
     * @return array
     */
    public function getModelCodesForManufacturer(string $manufacturer): array
    {
        $database = $this->loadModelCodesDatabase();

        if ($database === null) {
            return [];
        }

        $manufacturer = strtolower($manufacturer);

        if (!isset($database['manufacturers'][$manufacturer])) {
            return [];
        }

        return array_keys($database['manufacturers'][$manufacturer]['models']);
    }
}
