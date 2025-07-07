<?php

namespace Shekel\VinPackage\Services;

use Shekel\VinPackage\Contracts\VinDataSourceInterface;
use Shekel\VinPackage\ValueObjects\VinDataSourceChainResult;

/**
 * Manages and executes a chain of VIN data sources
 */
class VinDataSourceChain
{
    private array $sources = [];

    public function addSource(VinDataSourceInterface $source): self
    {
        $this->sources[] = $source;
        return $this;
    }

    public function removeSource(string $name): self
    {
        $this->sources = array_filter(
            $this->sources,
            fn($source) => $source->getName() !== $name
        );

        // Re-index array
        $this->sources = array_values($this->sources);

        return $this;
    }

    public function enableSource(string $name): self
    {
        foreach ($this->sources as $source) {
            if ($source->getName() === $name) {
                $source->setEnabled(true);
                break;
            }
        }

        return $this;
    }

    public function disableSource(string $name): self
    {
        foreach ($this->sources as $source) {
            if ($source->getName() === $name) {
                $source->setEnabled(false);
                break;
            }
        }

        return $this;
    }

    public function getEnabledSources(): array
    {
        $enabledSources = array_filter(
            $this->sources,
            fn($source) => $source->isEnabled()
        );

        // Sort by priority (lower numbers = higher priority)
        usort($enabledSources, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

        return $enabledSources;
    }

    public function getSources(): array
    {
        return $this->sources;
    }

    public function executeChain(string $vin, string $strategy = 'fail_fast'): VinDataSourceChainResult
    {
        $startTime = microtime(true);
        $successfulResults = [];
        $failedResults = [];

        $enabledSources = $this->getEnabledSources();

        if (empty($enabledSources)) {
            return new VinDataSourceChainResult(
                [],
                [],
                $strategy,
                microtime(true) - $startTime,
                ['message' => 'No enabled sources available']
            );
        }

        foreach ($enabledSources as $source) {
            if (!$source->canHandle($vin)) {
                continue;
            }

            $result = $source->decode($vin);

            if ($result->isSuccess()) {
                $successfulResults[] = $result;

                // For fail_fast strategy, stop on first success
                if ($strategy === 'fail_fast') {
                    break;
                }
            } else {
                $failedResults[] = $result;
            }
        }

        $totalExecutionTime = microtime(true) - $startTime;

        return new VinDataSourceChainResult(
            $successfulResults,
            $failedResults,
            $strategy,
            $totalExecutionTime,
            [
                'total_sources' => count($enabledSources),
                'successful_count' => count($successfulResults),
                'failed_count' => count($failedResults)
            ]
        );
    }

    public function reorderSources(array $order): self
    {
        $reorderedSources = [];

        // Add sources in the specified order
        foreach ($order as $sourceName) {
            foreach ($this->sources as $source) {
                if ($source->getName() === $sourceName) {
                    $reorderedSources[] = $source;
                    break;
                }
            }
        }

        // Add any remaining sources that weren't in the order list
        foreach ($this->sources as $source) {
            if (!in_array($source->getName(), $order)) {
                $reorderedSources[] = $source;
            }
        }

        $this->sources = $reorderedSources;

        return $this;
    }

    public function sortByPriority(): self
    {
        usort($this->sources, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

        return $this;
    }

    public function getSourceByName(string $name): ?VinDataSourceInterface
    {
        foreach ($this->sources as $source) {
            if ($source->getName() === $name) {
                return $source;
            }
        }

        return null;
    }

    public function hasSource(string $name): bool
    {
        return $this->getSourceByName($name) !== null;
    }
}
