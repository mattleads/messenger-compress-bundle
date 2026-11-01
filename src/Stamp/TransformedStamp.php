<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Stamp;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * Added to the envelope on the consumer side to describe what was undone while decoding.
 *
 * Useful for logging, metrics and tests. Never sent back over the wire.
 */
final readonly class TransformedStamp implements NonSendableStampInterface
{
    public function __construct(
        public ?string $compression,
        public ?string $encryption,
        public ?string $keyId,
        public int $wireSize,
        public int $rawSize,
    ) {}

    public function ratio(): float
    {
        return 0 === $this->rawSize ? 1.0 : $this->wireSize / $this->rawSize;
    }
}
