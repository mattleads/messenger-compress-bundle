<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Serializer;

final readonly class CompressionPolicy
{
    public function __construct(
        public string $defaultAlgorithm = 'deflate',
        public ?int $level = null,
        public ?int $autoThreshold = null,
        public bool $always = false,
    ) {}
}
