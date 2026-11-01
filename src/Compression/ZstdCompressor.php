<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Compression;

use MattLeads\MessengerCompressBundle\Exception\CompressionException;

/**
 * Zstandard via ext-zstd (https://github.com/kjdev/php-ext-zstd). Registered only when the extension is loaded.
 */
final class ZstdCompressor implements CompressorInterface
{
    public const NAME = 'zstd';

    public function name(): string
    {
        return self::NAME;
    }

    public function compress(string $data, ?int $level = null): string
    {
        if (!self::isAvailable()) {
            throw new CompressionException('ext-zstd is not loaded.');
        }
        /** @var string|false $result */
        $result = @zstd_compress($data, $level ?? 3);
        if (false === $result) {
            throw new CompressionException('zstd_compress() failed.');
        }

        return $result;
    }

    public function decompress(string $data): string
    {
        if (!self::isAvailable()) {
            throw new CompressionException('ext-zstd is not loaded.');
        }
        /** @var string|false $result */
        $result = @zstd_uncompress($data);
        if (false === $result) {
            throw new CompressionException('zstd_uncompress() failed: payload is not valid zstd data.');
        }

        return $result;
    }

    public static function isAvailable(): bool
    {
        return \function_exists('zstd_compress') && \function_exists('zstd_uncompress');
    }
}
