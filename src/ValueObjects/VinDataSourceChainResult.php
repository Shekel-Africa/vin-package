<?php

namespace Shekel\VinPackage\ValueObjects;

/**
 * Value object representing the result of executing a chain of VIN data sources
 */
class VinDataSourceChainResult
{
    private array $successfulResults;
    private array $failedResults;
    private string $executionStrategy;
    private float $totalExecutionTime;
    private array $metadata;

    public function __construct(
        array $successfulResults = [],
        array $failedResults = [],
        string $executionStrategy = 'fail_fast',
        float $totalExecutionTime = 0.0,
        array $metadata = []
    ) {
        $this->successfulResults = $successfulResults;
        $this->failedResults = $failedResults;
        $this->executionStrategy = $executionStrategy;
        $this->totalExecutionTime = $totalExecutionTime;
        $this->metadata = $metadata;
    }

    public function getSuccessfulResults(): array
    {
        return $this->successfulResults;
    }

    public function getFailedResults(): array
    {
        return $this->failedResults;
    }

    public function hasSuccessfulResults(): bool
    {
        return !empty($this->successfulResults);
    }

    public function hasFailedResults(): bool
    {
        return !empty($this->failedResults);
    }

    public function getExecutionStrategy(): string
    {
        return $this->executionStrategy;
    }

    public function getTotalExecutionTime(): float
    {
        return $this->totalExecutionTime;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getAllResults(): array
    {
        return array_merge($this->successfulResults, $this->failedResults);
    }

    public function getResultBySource(string $sourceName): ?VinDataSourceResult
    {
        $allResults = $this->getAllResults();

        foreach ($allResults as $result) {
            if ($result->getSource() === $sourceName) {
                return $result;
            }
        }

        return null;
    }

    public function getSuccessfulSources(): array
    {
        return array_map(fn($result) => $result->getSource(), $this->successfulResults);
    }

    public function getFailedSources(): array
    {
        return array_map(fn($result) => $result->getSource(), $this->failedResults);
    }

    public function toArray(): array
    {
        return [
            'successful_results' => array_map(fn($result) => $result->toArray(), $this->successfulResults),
            'failed_results' => array_map(fn($result) => $result->toArray(), $this->failedResults),
            'execution_strategy' => $this->executionStrategy,
            'total_execution_time' => $this->totalExecutionTime,
            'metadata' => $this->metadata
        ];
    }
}
