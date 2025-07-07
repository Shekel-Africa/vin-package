<?php

namespace Shekel\VinPackage\ValueObjects;

/**
 * Value object representing the result of a VIN data source operation
 */
class VinDataSourceResult
{
    private bool $success;
    private array $data;
    private string $source;
    private ?string $errorMessage;
    private array $metadata;

    public function __construct(
        bool $success,
        array $data,
        string $source,
        ?string $errorMessage = null,
        array $metadata = []
    ) {
        $this->success = $success;
        $this->data = $data;
        $this->source = $source;
        $this->errorMessage = $errorMessage;
        $this->metadata = $metadata;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'source' => $this->source,
            'error_message' => $this->errorMessage,
            'metadata' => $this->metadata
        ];
    }

    public function hasData(): bool
    {
        return !empty($this->data);
    }

    public function getDataValue(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }
}
