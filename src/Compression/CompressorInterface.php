<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Compression;

use MattLeads\MessengerCompressBundle\Exception\CompressionException;

/**
 * Implement and tag with "mattleads_messenger_compress.compressor" (autoconfigured) to add an algorithm.
 */
interface CompressorInterface
{
    /** Short, stable identifier written to the X-Compression header. */
    public function name(): string;

    /**
     * @throws CompressionException
     */
    public function compress(string $data, ?int $level = null): string;

    /**
     * @throws CompressionException
     */
    public function decompress(string $data): string;

    public static function isAvailable(): bool;
}
