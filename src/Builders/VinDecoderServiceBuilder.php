<?php

namespace Shekel\VinPackage\Builders;

use Shekel\VinPackage\Services\VinDecoderService;
use Shekel\VinPackage\Services\VinDataSourceChain;
use Shekel\VinPackage\Services\VinDataMerger;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\Contracts\VinDataSourceInterface;
use Shekel\VinPackage\DataSources\LocalVinDataSource;
use Shekel\VinPackage\DataSources\NhtsaApiDataSource;
use Shekel\VinPackage\DataSources\ClearVinDataSource;
use GuzzleHttp\Client;

/**
 * Builder for creating configured VinDecoderService instances
 */
class VinDecoderServiceBuilder
{
    private VinDataSourceChain $chain;
    private VinDataMerger $merger;
    private ?VinCacheInterface $cache = null;
    private int $cacheTtl = 2592000; // 30 days
    private string $executionStrategy = 'fail_fast';
    private string $mergeStrategy = 'priority';
    private array $fieldPriorities = [];
    private string $conflictResolution = 'priority';
    private array $disabledSources = [];
    private array $removedSources = [];

    public function __construct()
    {
        $this->chain = new VinDataSourceChain();
        $this->merger = new VinDataMerger();
    }

    public function addLocalSource(): self
    {
        if (!in_array('local', $this->removedSources)) {
            $source = new LocalVinDataSource($this->cache);
            $source->setCacheTTL($this->cacheTtl);

            if (in_array('local', $this->disabledSources)) {
                $source->setEnabled(false);
            }

            $this->chain->addSource($source);
        }

        return $this;
    }

    public function addNhtsaSource(?string $apiUrl = null, int $timeout = 15): self
    {
        if (!in_array('nhtsa_api', $this->removedSources)) {
            $client = new Client(['timeout' => $timeout]);
            $source = new NhtsaApiDataSource($client, $this->cache, $apiUrl, $timeout);

            if (in_array('nhtsa_api', $this->disabledSources)) {
                $source->setEnabled(false);
            }

            $this->chain->addSource($source);
        }

        return $this;
    }

    public function addClearVinSource(int $timeout = 10): self
    {
        if (!in_array('clearvin', $this->removedSources)) {
            $client = new Client(['timeout' => $timeout]);
            $source = new ClearVinDataSource($client, $this->cache, $timeout);

            if (in_array('clearvin', $this->disabledSources)) {
                $source->setEnabled(false);
            }

            $this->chain->addSource($source);
        }

        return $this;
    }

    public function addCustomSource(VinDataSourceInterface $source): self
    {
        if (!in_array($source->getName(), $this->removedSources)) {
            if (in_array($source->getName(), $this->disabledSources)) {
                $source->setEnabled(false);
            }

            $this->chain->addSource($source);
        }

        return $this;
    }

    public function setExecutionStrategy(string $strategy): self
    {
        $validStrategies = ['fail_fast', 'collect_all'];

        if (!in_array($strategy, $validStrategies)) {
            throw new \InvalidArgumentException(
                "Invalid execution strategy: {$strategy}. Valid options: " . implode(', ', $validStrategies)
            );
        }

        $this->executionStrategy = $strategy;
        return $this;
    }

    public function setMergeStrategy(string $strategy): self
    {
        $validStrategies = ['priority', 'best_effort', 'complete'];

        if (!in_array($strategy, $validStrategies)) {
            throw new \InvalidArgumentException(
                "Invalid merge strategy: {$strategy}. Valid options: " . implode(', ', $validStrategies)
            );
        }

        $this->mergeStrategy = $strategy;
        return $this;
    }

    public function setCache(VinCacheInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    public function setCacheTTL(int $ttl): self
    {
        $this->cacheTtl = $ttl;
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

    public function enableSource(string $sourceName): self
    {
        $this->disabledSources = array_filter(
            $this->disabledSources,
            fn($name) => $name !== $sourceName
        );

        return $this;
    }

    public function disableSource(string $sourceName): self
    {
        if (!in_array($sourceName, $this->disabledSources)) {
            $this->disabledSources[] = $sourceName;
        }

        return $this;
    }

    public function removeSource(string $sourceName): self
    {
        if (!in_array($sourceName, $this->removedSources)) {
            $this->removedSources[] = $sourceName;
        }

        // Also remove from disabled list if present
        $this->disabledSources = array_filter(
            $this->disabledSources,
            fn($name) => $name !== $sourceName
        );

        return $this;
    }

    public function build(): VinDecoderService
    {
        // If no sources added, add default sources
        if (empty($this->chain->getSources())) {
            $this->addLocalSource();
            $this->addNhtsaSource();
        }

        // Apply disable and remove operations to chain
        foreach ($this->disabledSources as $sourceName) {
            $this->chain->disableSource($sourceName);
        }

        foreach ($this->removedSources as $sourceName) {
            $this->chain->removeSource($sourceName);
        }

        // Sort sources by priority
        $this->chain->sortByPriority();

        // Configure merger
        $this->merger->setMergeStrategy($this->mergeStrategy);
        $this->merger->setConflictResolution($this->conflictResolution);

        foreach ($this->fieldPriorities as $field => $priorities) {
            $this->merger->setFieldPriority($field, $priorities);
        }

        // Create and return the service
        return new VinDecoderService(
            $this->chain,
            $this->merger,
            $this->cache,
            $this->executionStrategy,
            $this->cacheTtl
        );
    }

    /**
     * Create a minimal configuration with just local source
     */
    public static function minimal(): self
    {
        return (new self())->addLocalSource();
    }

    /**
     * Create a standard configuration with local and NHTSA sources
     */
    public static function standard(): self
    {
        return (new self())
            ->addLocalSource()
            ->addNhtsaSource();
    }

    /**
     * Create a full configuration with all available sources
     */
    public static function full(): self
    {
        return (new self())
            ->addLocalSource()
            ->addNhtsaSource()
            ->addClearVinSource()
            ->setExecutionStrategy('collect_all')
            ->setMergeStrategy('priority');
    }
}
