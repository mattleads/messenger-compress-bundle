<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Tests\Unit\Encryption;

use MattLeads\MessengerCompressBundle\Encryption\SodiumEncryptor;
use MattLeads\MessengerCompressBundle\Exception\DecryptionFailedException;
use PHPUnit\Framework\TestCase;

final class SodiumEncryptorTest extends TestCase
{
    private SodiumEncryptor $encryptor;
    private string $key;

    protected function setUp(): void
    {
        $this->encryptor = new SodiumEncryptor();
        $this->key = random_bytes(32);
    }

    public function testRoundTrip(): void
    {
        $plaintext = '{"secret":"PII"}';
        $ciphertext = $this->encryptor->encrypt($plaintext, $this->key);

        self::assertNotSame($plaintext, $ciphertext);
        self::assertSame($plaintext, $this->encryptor->decrypt($ciphertext, $this->key));
    }

    public function testEveryEncryptionUsesAFreshNonce(): void
    {
        self::assertNotSame($this->encryptor->encrypt('x', $this->key), $this->encryptor->encrypt('x', $this->key));
    }

    public function testWrongKeyFails(): void
    {
        $ciphertext = $this->encryptor->encrypt('x', $this->key);
        $this->expectException(DecryptionFailedException::class);
        $this->encryptor->decrypt($ciphertext, random_bytes(32));
    }

    public function testTamperedCiphertextFails(): void
    {
        $ciphertext = $this->encryptor->encrypt('payload', $this->key);
        $ciphertext[\strlen($ciphertext) - 1] = \chr(\ord($ciphertext[\strlen($ciphertext) - 1]) ^ 0x01);

        $this->expectException(DecryptionFailedException::class);
        $this->encryptor->decrypt($ciphertext, $this->key);
    }

    public function testTooShortCiphertextFails(): void
    {
        $this->expectException(DecryptionFailedException::class);
        $this->encryptor->decrypt('short', $this->key);
    }

    public function testKeyLengthIsEnforced(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->encryptor->encrypt('x', 'too-short');
    }
}
