<?php

namespace Shekel\VinPackage\DataSources;

use Shekel\VinPackage\Contracts\VinDataSourceInterface;
use Shekel\VinPackage\Contracts\VinCacheInterface;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;
use Shekel\VinPackage\Decoders\LocalVinDecoder;

/**
 * Local VIN data source using the existing LocalVinDecoder
 */
class LocalVinDataSource implements VinDataSourceInterface
{
    private LocalVinDecoder $decoder;
    private ?VinCacheInterface $cache;
    private bool $enabled = true;
    private int $cacheTtl = 2592000; // 30 days

    public function __construct(?VinCacheInterface $cache = null)
    {
        $this->decoder = new LocalVinDecoder();
        $this->cache = $cache;

        if ($cache) {
            $this->decoder->setCache($cache);
        }
    }

    public function getName(): string
    {
        return 'local';
    }

    public function getPriority(): int
    {
        return 1; // Highest priority (baseline data)
    }

    public function isEnabled(): bool
    {
        return true; // Local source is always enabled
    }

    public function setEnabled(bool $enabled): void
    {
        // Local source cannot be disabled - it's the fallback
        // This is intentionally ignored
    }

    public function canHandle(string $vin): bool
    {
        // Local decoder can handle any VIN format
        return true;
    }

    public function decode(string $vin): VinDataSourceResult
    {
        $startTime = microtime(true);

        try {
            $cacheKey = 'local_vin_' . md5($vin);

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
                        'decoded_by' => 'local_decoder',
                        'execution_time' => $executionTime,
                        'cache_hit' => true,
                        'decoding_date' => date('Y-m-d H:i:s')
                    ]
                );
            }

            // Decode using local decoder
            $data = $this->decoder->decode($vin);
            $executionTime = microtime(true) - $startTime;

            // Add metadata
            $data['additional_info']['decoded_by'] = 'local_decoder';
            $data['additional_info']['decoding_date'] = date('Y-m-d H:i:s');

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
                    'decoded_by' => 'local_decoder',
                    'execution_time' => $executionTime,
                    'cache_hit' => false,
                    'decoding_date' => date('Y-m-d H:i:s')
                ]
            );
        } catch (\Exception $e) {
            // Local decoder should never fail, but handle gracefully
            $executionTime = microtime(true) - $startTime;

            return new VinDataSourceResult(
                false,
                [],
                $this->getName(),
                'Local decoder error: ' . $e->getMessage(),
                [
                    'decoded_by' => 'local_decoder',
                    'execution_time' => $executionTime,
                    'error' => $e->getMessage(),
                    'decoding_date' => date('Y-m-d H:i:s')
                ]
            );
        }
    }

    public function getSourceType(): string
    {
        return 'local';
    }

    public function setCacheTTL(int $ttl): self
    {
        $this->cacheTtl = $ttl;
        return $this;
    }

    public function addManufacturerCode(string $wmi, string $manufacturer): self
    {
        $this->decoder->addManufacturerCode($wmi, $manufacturer);
        return $this;
    }
}
