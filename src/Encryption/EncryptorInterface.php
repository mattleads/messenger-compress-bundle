<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Encryption;

use MattLeads\MessengerCompressBundle\Exception\DecryptionFailedException;

/**
 * Authenticated encryption of an opaque byte string with a 32-byte key.
 */
interface EncryptorInterface
{
    /** Short, stable identifier written to the X-Encryption header. */
    public function name(): string;

    public function keyLength(): int;

    public function encrypt(string $plaintext, string $key): string;

    /**
     * @throws DecryptionFailedException when the ciphertext was tampered with or the key is wrong
     */
    public function decrypt(string $ciphertext, string $key): string;
}
