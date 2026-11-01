<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Encryption;

use MattLeads\MessengerCompressBundle\Exception\DecryptionFailedException;

/**
 * XChaCha20-Poly1305 (IETF) via libsodium: authenticated, misuse-resistant (192-bit random nonce), constant-time.
 *
 * Wire format: nonce (24 bytes) || ciphertext+tag.
 */
final class SodiumEncryptor implements EncryptorInterface
{
    public const NAME = 'xchacha20poly1305';

    public function name(): string
    {
        return self::NAME;
    }

    public function keyLength(): int
    {
        return \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;
    }

    public function encrypt(string $plaintext, string $key): string
    {
        $this->assertKey($key);
        $nonce = random_bytes(\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        return $nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $nonce, $nonce, $key);
    }

    public function decrypt(string $ciphertext, string $key): string
    {
        $this->assertKey($key);
        $nonceLength = \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (\strlen($ciphertext) < $nonceLength + \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new DecryptionFailedException('Ciphertext is too short.');
        }

        $nonce = substr($ciphertext, 0, $nonceLength);
        $body = substr($ciphertext, $nonceLength);

        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($body, $nonce, $nonce, $key);
        } catch (\SodiumException $e) {
            throw new DecryptionFailedException('Decryption failed: ' . $e->getMessage(), 0, $e);
        }

        if (false === $plaintext) {
            throw new DecryptionFailedException('Decryption failed: authentication tag mismatch (wrong key or tampered payload).');
        }

        return $plaintext;
    }

    private function assertKey(string $key): void
    {
        if (\strlen($key) !== $this->keyLength()) {
            throw new \InvalidArgumentException(\sprintf('Key must be exactly %d bytes, %d given.', $this->keyLength(), \strlen($key)));
        }
    }
}
