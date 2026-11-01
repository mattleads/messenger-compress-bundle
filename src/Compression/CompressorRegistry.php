<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Compression;

use MattLeads\MessengerCompressBundle\Exception\UnsupportedAlgorithmException;

final class CompressorRegistry
{
    /** @var array<string, CompressorInterface> */
    private array $byName = [];

    /**
     * @param iterable<CompressorInterface> $compressors
     */
    public function __construct(iterable $compressors)
    {
        foreach ($compressors as $compressor) {
            $this->byName[$compressor->name()] = $compressor;
        }
    }

    public function get(string $name): CompressorInterface
    {
        return $this->byName[$name] ?? throw new UnsupportedAlgorithmException(\sprintf('Unknown compression algorithm "%s". Available: %s.', $name, implode(', ', array_keys($this->byName)) ?: '(none)'));
    }

    public function has(string $name): bool
    {
        return isset($this->byName[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->byName);
    }
}
