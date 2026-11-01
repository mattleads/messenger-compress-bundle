<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Compression;

use MattLeads\MessengerCompressBundle\Exception\CompressionException;

final class DeflateCompressor implements CompressorInterface
{
    public const NAME = 'deflate';

    public function name(): string
    {
        return self::NAME;
    }

    public function compress(string $data, ?int $level = null): string
    {
        $result = @gzdeflate($data, $level ?? -1);
        if (false === $result) {
            throw new CompressionException('gzdeflate() failed.');
        }

        return $result;
    }

    public function decompress(string $data): string
    {
        $result = @gzinflate($data);
        if (false === $result) {
            throw new CompressionException('gzinflate() failed: payload is not valid deflate data.');
        }

        return $result;
    }

    public static function isAvailable(): bool
    {
        return \function_exists('gzdeflate');
    }
}
