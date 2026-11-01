<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Serializer;

final readonly class EncryptionPolicy
{
    public function __construct(
        public bool $enabled = false,
        public bool $always = false,
    ) {}
}
