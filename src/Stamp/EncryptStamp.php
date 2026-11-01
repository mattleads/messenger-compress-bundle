<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Ask the transport serializer to encrypt this message's payload (authenticated encryption).
 */
final readonly class EncryptStamp implements StampInterface
{
    /**
     * @param string|null $keyId key id from the configured key ring; null = active key
     */
    public function __construct(
        public ?string $keyId = null,
    ) {}
}
