<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Tests\Unit\Encryption;

use MattLeads\MessengerCompressBundle\Encryption\KeyRing;
use MattLeads\MessengerCompressBundle\Exception\UnknownKeyException;
use PHPUnit\Framework\TestCase;

final class KeyRingTest extends TestCase
{
    public function testFromEncodedKeysAcceptsPlainAndPrefixedBase64(): void
    {
        $k1 = KeyRing::generateEncodedKey();
        $k2 = KeyRing::generateEncodedKey();
        $ring = KeyRing::fromEncodedKeys(['v1' => $k1, 'v2' => 'base64:' . $k2], 'v2');

        self::assertSame('v2', $ring->activeKeyId());
        self::assertSame(base64_decode($k1, true), $ring->get('v1'));
        self::assertSame(base64_decode($k2, true), $ring->get('v2'));
        self::assertTrue($ring->has('v1'));
        self::assertFalse($ring->has('v3'));
    }

    public function testUnknownKey(): void
    {
        $ring = KeyRing::fromEncodedKeys(['v1' => KeyRing::generateEncodedKey()], 'v1');
        $this->expectException(UnknownKeyException::class);
        $ring->get('v9');
    }

    public function testRejectsWrongLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be 32 raw bytes');
        KeyRing::fromEncodedKeys(['v1' => base64_encode('short')], 'v1');
    }

    public function testRejectsInvalidBase64(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid base64');
        KeyRing::fromEncodedKeys(['v1' => '***'], 'v1');
    }

    public function testRejectsUnknownActiveKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Active key "v2"');
        KeyRing::fromEncodedKeys(['v1' => KeyRing::generateEncodedKey()], 'v2');
    }

    public function testRejectsEmptyRing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new KeyRing([], 'v1');
    }
}
