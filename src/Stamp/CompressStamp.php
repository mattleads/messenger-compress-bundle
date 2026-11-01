<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Ask the transport serializer to compress this message's payload.
 *
 * The stamp is consumed by PayloadTransformingSerializer and is not sent over the wire.
 */
final readonly class CompressStamp implements StampInterface
{
    /**
     * @param string|null $algorithm compressor name (deflate, gzip, zstd); null = configured default
     * @param int|null    $level     algorithm-specific level; null = configured default
     */
    public function __construct(
        public ?string $algorithm = null,
        public ?int $level = null,
    ) {}
}
