<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Encryption;

use MattLeads\MessengerCompressBundle\Exception\UnknownKeyException;

/**
 * Holds raw keys by id. New messages use the active key; any known key can decode (rotation without downtime).
 */
final class KeyRing
{
    public const KEY_BYTES = 32;

    /**
     * @param array<string, string> $keys id => raw 32-byte key
     */
    public function __construct(
        private readonly array $keys,
        private readonly string $activeKeyId,
    ) {
        if ([] === $keys) {
            throw new \InvalidArgumentException('KeyRing needs at least one key.');
        }
        foreach ($keys as $id => $key) {
            if (self::KEY_BYTES !== \strlen($key)) {
                throw new \InvalidArgumentException(\sprintf('Key "%s" must be %d raw bytes, %d given.', $id, self::KEY_BYTES, \strlen($key)));
            }
        }
        if (!isset($keys[$activeKeyId])) {
            throw new \InvalidArgumentException(\sprintf('Active key "%s" is not in the key ring (%s).', $activeKeyId, implode(', ', array_keys($keys))));
        }
    }

    /**
     * @param array<string, string> $encodedKeys id => base64 key (an optional "base64:" prefix is accepted)
     */
    public static function fromEncodedKeys(array $encodedKeys, string $activeKeyId): self
    {
        $raw = [];
        foreach ($encodedKeys as $id => $encoded) {
            $encoded = str_starts_with($encoded, 'base64:') ? substr($encoded, 7) : $encoded;
            $decoded = base64_decode($encoded, true);
            if (false === $decoded) {
                throw new \InvalidArgumentException(\sprintf('Key "%s" is not valid base64.', $id));
            }
            $raw[(string) $id] = $decoded;
        }

        return new self($raw, $activeKeyId);
    }

    public static function generateEncodedKey(): string
    {
        return base64_encode(random_bytes(self::KEY_BYTES));
    }

    public function activeKeyId(): string
    {
        return $this->activeKeyId;
    }

    /**
     * @throws UnknownKeyException
     */
    public function get(string $keyId): string
    {
        return $this->keys[$keyId] ?? throw new UnknownKeyException(\sprintf('No encryption key with id "%s" is configured. Known ids: %s.', $keyId, implode(', ', array_keys($this->keys))));
    }

    public function has(string $keyId): bool
    {
        return isset($this->keys[$keyId]);
    }
}
