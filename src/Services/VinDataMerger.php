<?php

namespace Shekel\VinPackage\Services;

use Shekel\VinPackage\ValueObjects\VinDataSourceResult;

/**
 * Merges data from multiple VIN data sources
 */
class VinDataMerger
{
    private string $mergeStrategy = 'priority';
    private string $conflictResolution = 'priority';
    private array $fieldPriorities = [];

    /**
     * Default field priorities by source
     */
    private const DEFAULT_PRIORITIES = [
        'make' => ['nhtsa_api', 'clearvin', 'local'],
        'model' => ['nhtsa_api', 'clearvin', 'local'],
        'year' => ['nhtsa_api', 'clearvin', 'local'],
        'trim' => ['clearvin', 'nhtsa_api', 'local'],
        'engine' => ['clearvin', 'nhtsa_api', 'local'],
        'plant' => ['nhtsa_api', 'clearvin', 'local'],
        'body_style' => ['nhtsa_api', 'clearvin', 'local'],
        'fuel_type' => ['nhtsa_api', 'clearvin', 'local'],
        'transmission' => ['nhtsa_api', 'clearvin', 'local'],
        'manufacturer' => ['nhtsa_api', 'clearvin', 'local'],
        'country' => ['nhtsa_api', 'clearvin', 'local'],
        'validation' => ['nhtsa_api'],
        'dimensions' => ['clearvin'],
        'seating' => ['clearvin'],
        'pricing' => ['clearvin'],
        'mileage' => ['clearvin']
    ];

    public function merge(array $results): array
    {
        if (empty($results)) {
            return [];
        }

        // Filter to only successful results
        $successfulResults = array_filter($results, fn($result) => $result->isSuccess());

        if (empty($successfulResults)) {
            return [];
        }

        if (count($successfulResults) === 1) {
            $result = reset($successfulResults);
            return $this->addMergeMetadata($result->getData(), [$result]);
        }

        return $this->performMerge($successfulResults);
    }

    public function setMergeStrategy(string $strategy): self
    {
        $this->mergeStrategy = $strategy;
        return $this;
    }

    public function setFieldPriority(string $field, array $sourcePriority): self
    {
        $this->fieldPriorities[$field] = $sourcePriority;
        return $this;
    }

    public function setConflictResolution(string $resolution): self
    {
        $this->conflictResolution = $resolution;
        return $this;
    }

    private function performMerge(array $results): array
    {
        switch ($this->mergeStrategy) {
            case 'priority':
                return $this->mergeByPriority($results);
            case 'best_effort':
                return $this->mergeBestEffort($results);
            case 'complete':
                return $this->mergeComplete($results);
            default:
                return $this->mergeByPriority($results);
        }
    }

    private function mergeByPriority(array $results): array
    {
        $merged = [];
        $sourceMap = [];

        // Create source map with metadata
        foreach ($results as $result) {
            $sourceMap[$result->getSource()] = array_merge(
                $result->getData(),
                ['metadata' => $result->getMetadata()]
            );
        }

        // Merge standard fields
        $standardFields = [
            'make', 'model', 'year', 'trim', 'engine', 'plant',
            'body_style', 'fuel_type', 'transmission', 'manufacturer', 'country'
        ];

        foreach ($standardFields as $field) {
            $merged[$field] = $this->getFieldValue($field, $sourceMap);
        }

        // Merge special fields
        $merged = $this->mergeSpecialFields($merged, $sourceMap);

        // Merge additional_info
        $merged['additional_info'] = $this->mergeAdditionalInfo($results);

        // Handle validation (NHTSA only)
        $merged['validation'] = $this->getValidationData($sourceMap);

        return $this->addMergeMetadata($merged, $results);
    }

    private function mergeBestEffort(array $results): array
    {
        $merged = [];

        foreach ($results as $result) {
            $data = $result->getData();

            foreach ($data as $key => $value) {
                if (!isset($merged[$key]) || $this->isEmpty($merged[$key])) {
                    $merged[$key] = $value;
                }
            }
        }

        return $this->addMergeMetadata($merged, $results);
    }

    private function mergeComplete(array $results): array
    {
        // Find the most complete result
        $mostComplete = null;
        $maxFields = 0;

        foreach ($results as $result) {
            $data = $result->getData();
            $nonEmptyFields = count(array_filter($data, fn($value) => !$this->isEmpty($value)));

            if ($nonEmptyFields > $maxFields) {
                $maxFields = $nonEmptyFields;
                $mostComplete = $result;
            }
        }

        if ($mostComplete) {
            $merged = $mostComplete->getData();

            // Fill in missing fields from other sources
            foreach ($results as $result) {
                if ($result === $mostComplete) {
                    continue;
                }

                $data = $result->getData();
                foreach ($data as $key => $value) {
                    if (!isset($merged[$key]) || $this->isEmpty($merged[$key])) {
                        $merged[$key] = $value;
                    }
                }
            }

            return $this->addMergeMetadata($merged, $results);
        }

        return $this->mergeByPriority($results);
    }

    private function getFieldValue(string $field, array $sourceMap)
    {
        if ($this->conflictResolution === 'newest') {
            return $this->getNewestFieldValue($field, $sourceMap);
        }

        $priorities = $this->fieldPriorities[$field] ??
            self::DEFAULT_PRIORITIES[$field] ??
            ['nhtsa_api', 'clearvin', 'local'];

        foreach ($priorities as $source) {
            if (isset($sourceMap[$source][$field]) && !$this->isEmpty($sourceMap[$source][$field])) {
                return $sourceMap[$source][$field];
            }
        }

        // Fallback: return first non-empty value
        foreach ($sourceMap as $data) {
            if (isset($data[$field]) && !$this->isEmpty($data[$field])) {
                return $data[$field];
            }
        }

        return null;
    }

    private function getNewestFieldValue(string $field, array $sourceMap)
    {
        $candidates = [];

        foreach ($sourceMap as $source => $data) {
            if (isset($data[$field]) && !$this->isEmpty($data[$field])) {
                $timestamp = $data['metadata']['timestamp'] ?? 0;
                $candidates[] = [
                    'value' => $data[$field],
                    'timestamp' => $timestamp,
                    'source' => $source
                ];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort by timestamp descending (newest first)
        usort($candidates, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $candidates[0]['value'];
    }

    private function mergeSpecialFields(array $merged, array $sourceMap): array
    {
        // Handle special fields that only come from specific sources
        $specialFields = ['dimensions', 'seating', 'pricing', 'mileage'];

        foreach ($specialFields as $field) {
            if (isset($sourceMap['clearvin'][$field])) {
                $merged[$field] = $sourceMap['clearvin'][$field];
            }
        }

        return $merged;
    }

    private function mergeAdditionalInfo(array $results): array
    {
        $merged = [];

        foreach ($results as $result) {
            $data = $result->getData();
            if (isset($data['additional_info']) && is_array($data['additional_info'])) {
                $merged = array_merge($merged, $data['additional_info']);
            }
        }

        return $merged;
    }

    private function getValidationData(array $sourceMap): array
    {
        // Prefer NHTSA validation data
        if (isset($sourceMap['nhtsa_api']['validation'])) {
            return $sourceMap['nhtsa_api']['validation'];
        }

        // Fallback to any available validation
        foreach ($sourceMap as $data) {
            if (isset($data['validation'])) {
                return $data['validation'];
            }
        }

        return [
            'error_code' => null,
            'error_text' => null,
            'is_valid' => true
        ];
    }

    private function addMergeMetadata(array $data, array $results): array
    {
        $sources = array_map(fn($result) => $result->getSource(), $results);
        $totalExecutionTime = array_sum(array_map(
            fn($result) => $result->getMetadataValue('execution_time', 0),
            $results
        ));

        $sourceDetails = [];
        foreach ($results as $result) {
            $sourceDetails[$result->getSource()] = $result->getMetadata();
        }

        $data['cache_metadata'] = [
            'sources' => $sources,
            'total_execution_time' => $totalExecutionTime,
            'source_details' => $sourceDetails
        ];

        return $data;
    }

    private function isEmpty($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    public function getMergeStrategy(): string
    {
        return $this->mergeStrategy;
    }

    public function getFieldPriorities(): array
    {
        return $this->fieldPriorities;
    }

    public function getConflictResolution(): string
    {
        return $this->conflictResolution;
    }
}
