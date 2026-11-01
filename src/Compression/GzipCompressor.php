<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Compression;

use MattLeads\MessengerCompressBundle\Exception\CompressionException;

/**
 * Same algorithm as deflate but with a gzip container (18 bytes larger, interoperable with CLI tools).
 */
final class GzipCompressor implements CompressorInterface
{
    public const NAME = 'gzip';

    public function name(): string
    {
        return self::NAME;
    }

    public function compress(string $data, ?int $level = null): string
    {
        $result = @gzencode($data, $level ?? -1);
        if (false === $result) {
            throw new CompressionException('gzencode() failed.');
        }

        return $result;
    }

    public function decompress(string $data): string
    {
        $result = @gzdecode($data);
        if (false === $result) {
            throw new CompressionException('gzdecode() failed: payload is not valid gzip data.');
        }

        return $result;
    }

    public static function isAvailable(): bool
    {
        return \function_exists('gzencode');
    }
}
