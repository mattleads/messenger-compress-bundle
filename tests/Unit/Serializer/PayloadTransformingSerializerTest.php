<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Tests\Unit\Serializer;

use MattLeads\MessengerCompressBundle\Compression\CompressorRegistry;
use MattLeads\MessengerCompressBundle\Compression\DeflateCompressor;
use MattLeads\MessengerCompressBundle\Compression\GzipCompressor;
use MattLeads\MessengerCompressBundle\Encryption\KeyRing;
use MattLeads\MessengerCompressBundle\Encryption\SodiumEncryptor;
use MattLeads\MessengerCompressBundle\Serializer\CompressionPolicy;
use MattLeads\MessengerCompressBundle\Serializer\EncryptionPolicy;
use MattLeads\MessengerCompressBundle\Serializer\PayloadTransformingSerializer;
use MattLeads\MessengerCompressBundle\Stamp\CompressStamp;
use MattLeads\MessengerCompressBundle\Stamp\EncryptStamp;
use MattLeads\MessengerCompressBundle\Stamp\PlainStamp;
use MattLeads\MessengerCompressBundle\Stamp\TransformedStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class PayloadTransformingSerializerTest extends TestCase
{
    private const K1 = 'v1';
    private const K2 = 'v2';

    private KeyRing $keyRing;

    protected function setUp(): void
    {
        $this->keyRing = KeyRing::fromEncodedKeys([
            self::K1 => KeyRing::generateEncodedKey(),
            self::K2 => KeyRing::generateEncodedKey(),
        ], self::K2);
    }

    private function serializer(
        CompressionPolicy $compression = new CompressionPolicy(),
        EncryptionPolicy $encryption = new EncryptionPolicy(enabled: true),
        string $encoding = 'base64',
        ?KeyRing $keyRing = null,
    ): PayloadTransformingSerializer {
        return new PayloadTransformingSerializer(
            new PhpSerializer(),
            new CompressorRegistry([new DeflateCompressor(), new GzipCompressor()]),
            new SodiumEncryptor(),
            $keyRing ?? $this->keyRing,
            $compression,
            $encryption,
            $encoding,
        );
    }

    private function envelope(int $size = 5_000): Envelope
    {
        return new Envelope(new DummyMessage(str_repeat('payload ', (int) ceil($size / 8))), [new BusNameStamp('messenger.bus.default')]);
    }

    public function testUnstampedMessagesPassThroughUntouched(): void
    {
        $serializer = $this->serializer();
        $inner = new PhpSerializer();
        $envelope = $this->envelope();

        $encoded = $serializer->encode($envelope);

        self::assertSame($inner->encode($envelope)['body'], $encoded['body']);
        self::assertArrayNotHasKey(PayloadTransformingSerializer::HEADER_COMPRESSION, self::headers($encoded));
        self::assertArrayNotHasKey(PayloadTransformingSerializer::HEADER_ENCODING, self::headers($encoded));

        $decoded = $serializer->decode($encoded);
        self::assertEquals($envelope->getMessage(), $decoded->getMessage());
        self::assertNull($decoded->last(TransformedStamp::class));
    }

    public function testCompressStamp(): void
    {
        $serializer = $this->serializer();
        $envelope = $this->envelope()->with(new CompressStamp());

        $encoded = $serializer->encode($envelope);

        self::assertSame('deflate', self::headers($encoded)[PayloadTransformingSerializer::HEADER_COMPRESSION]);
        self::assertSame('base64', self::headers($encoded)[PayloadTransformingSerializer::HEADER_ENCODING]);
        self::assertArrayNotHasKey(PayloadTransformingSerializer::HEADER_ENCRYPTION, self::headers($encoded));
        self::assertLessThan(500, \strlen($encoded['body']));
        self::assertNotFalse(base64_decode($encoded['body'], true));

        $decoded = $serializer->decode($encoded);
        self::assertEquals($envelope->getMessage(), $decoded->getMessage());
        $stamp = $decoded->last(TransformedStamp::class);
        self::assertInstanceOf(TransformedStamp::class, $stamp);
        self::assertSame('deflate', $stamp->compression);
        self::assertNull($stamp->encryption);
        self::assertLessThan(0.2, $stamp->ratio());
        // wire headers are removed before the inner serializer sees them
        self::assertArrayNotHasKey(PayloadTransformingSerializer::HEADER_COMPRESSION, $this->headersOf($decoded));
    }

    public function testCompressStampCanPickAlgorithmAndLevel(): void
    {
        $encoded = $this->serializer()->encode($this->envelope()->with(new CompressStamp('gzip', 9)));
        self::assertSame('gzip', self::headers($encoded)[PayloadTransformingSerializer::HEADER_COMPRESSION]);
        self::assertSame("\x1f\x8b", substr((string) base64_decode($encoded['body'], true), 0, 2), 'gzip magic bytes');
    }

    public function testEncryptStamp(): void
    {
        $serializer = $this->serializer();
        $envelope = $this->envelope()->with(new EncryptStamp());

        $encoded = $serializer->encode($envelope);

        self::assertSame('xchacha20poly1305', self::headers($encoded)[PayloadTransformingSerializer::HEADER_ENCRYPTION]);
        self::assertSame(self::K2, self::headers($encoded)[PayloadTransformingSerializer::HEADER_KEY_ID], 'active key is used by default');
        self::assertStringNotContainsString('payload', (string) base64_decode($encoded['body'], true));

        $decoded = $serializer->decode($encoded);
        self::assertEquals($envelope->getMessage(), $decoded->getMessage());
        $stamp = $decoded->last(TransformedStamp::class);
        self::assertInstanceOf(TransformedStamp::class, $stamp);
        self::assertSame(self::K2, $stamp->keyId);
    }

    public function testEncryptStampWithExplicitKeyAndRotation(): void
    {
        $serializer = $this->serializer();
        $encoded = $serializer->encode($this->envelope()->with(new EncryptStamp(self::K1)));
        self::assertSame(self::K1, self::headers($encoded)[PayloadTransformingSerializer::HEADER_KEY_ID]);

        // A consumer whose active key moved on still decodes messages produced with the old key.
        $rotated = $this->serializer(keyRing: KeyRing::fromEncodedKeys([
            self::K1 => base64_encode($this->keyRing->get(self::K1)),
            'v3' => KeyRing::generateEncodedKey(),
        ], 'v3'));
        self::assertEquals($this->envelope()->getMessage(), $rotated->decode($encoded)->getMessage());
    }

    public function testCompressThenEncrypt(): void
    {
        $serializer = $this->serializer();
        $envelope = $this->envelope()->with(new CompressStamp(), new EncryptStamp());

        $encoded = $serializer->encode($envelope);
        self::assertSame('deflate', self::headers($encoded)[PayloadTransformingSerializer::HEADER_COMPRESSION]);
        self::assertSame('xchacha20poly1305', self::headers($encoded)[PayloadTransformingSerializer::HEADER_ENCRYPTION]);
        self::assertLessThan(600, \strlen($encoded['body']), 'compression happened before encryption');

        $decoded = $serializer->decode($encoded);
        self::assertEquals($envelope->getMessage(), $decoded->getMessage());
    }

    public function testAutoThresholdCompressesLargeBodiesOnly(): void
    {
        $serializer = $this->serializer(new CompressionPolicy(autoThreshold: 1024));

        $small = $serializer->encode($this->envelope(100));
        self::assertArrayNotHasKey(PayloadTransformingSerializer::HEADER_COMPRESSION, self::headers($small));

        $large = $serializer->encode($this->envelope(10_000));
        self::assertSame('deflate', self::headers($large)[PayloadTransformingSerializer::HEADER_COMPRESSION]);
    }

    public function testAutoCompressionIsSkippedWhenItDoesNotHelp(): void
    {
        $incompressible = random_bytes(3000);
        $inner = new class ($incompressible) implements SerializerInterface {
            public function __construct(private readonly string $body) {}

            public function decode(array $encodedEnvelope): Envelope
            {
                return new Envelope(new DummyMessage($encodedEnvelope['body']));
            }

            public function encode(Envelope $envelope): array
            {
                return ['body' => $this->body, 'headers' => []];
            }
        };
        $serializer = new PayloadTransformingSerializer($inner, new CompressorRegistry([new DeflateCompressor()]), null, null, new CompressionPolicy(always: true));

        $encoded = $serializer->encode(new Envelope(new DummyMessage('ignored')));
        self::assertArrayNotHasKey(PayloadTransformingSerializer::HEADER_COMPRESSION, self::headers($encoded));
        self::assertSame($incompressible, $encoded['body']);

        // ...but an explicit stamp is always honoured, even when it costs a few bytes
        $forced = $serializer->encode(new Envelope(new DummyMessage('ignored'), [new CompressStamp()]));
        self::assertSame('deflate', self::headers($forced)[PayloadTransformingSerializer::HEADER_COMPRESSION]);
    }

    public function testAlwaysPoliciesAndPlainStampOptOut(): void
    {
        $serializer = $this->serializer(new CompressionPolicy(always: true), new EncryptionPolicy(enabled: true, always: true));

        $encoded = $serializer->encode($this->envelope());
        self::assertSame('deflate', self::headers($encoded)[PayloadTransformingSerializer::HEADER_COMPRESSION]);
        self::assertSame('xchacha20poly1305', self::headers($encoded)[PayloadTransformingSerializer::HEADER_ENCRYPTION]);

        $plain = $serializer->encode($this->envelope()->with(new PlainStamp()));
        self::assertArrayNotHasKey(PayloadTransformingSerializer::HEADER_COMPRESSION, self::headers($plain));
        self::assertArrayNotHasKey(PayloadTransformingSerializer::HEADER_ENCRYPTION, self::headers($plain));

        // explicit stamps beat PlainStamp
        $forced = $serializer->encode($this->envelope()->with(new PlainStamp(), new EncryptStamp()));
        self::assertSame('xchacha20poly1305', self::headers($forced)[PayloadTransformingSerializer::HEADER_ENCRYPTION]);
    }

    public function testRawEncodingSkipsBase64(): void
    {
        $serializer = $this->serializer(encoding: 'raw');
        $encoded = $serializer->encode($this->envelope()->with(new CompressStamp()));

        self::assertArrayNotHasKey(PayloadTransformingSerializer::HEADER_ENCODING, self::headers($encoded));
        self::assertEquals($this->envelope()->getMessage(), $serializer->decode($encoded)->getMessage());
    }

    public function testEncryptionRequestedButNotConfiguredIsALogicError(): void
    {
        $serializer = new PayloadTransformingSerializer(new PhpSerializer(), new CompressorRegistry([new DeflateCompressor()]), null, null);
        $this->expectException(\LogicException::class);
        $serializer->encode($this->envelope()->with(new EncryptStamp()));
    }

    public function testUnknownKeyOnDecodeBecomesDecodingFailure(): void
    {
        $encoded = $this->serializer()->encode($this->envelope()->with(new EncryptStamp()));
        $encoded['headers'][PayloadTransformingSerializer::HEADER_KEY_ID] = 'nope';

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('No encryption key with id "nope"');
        $this->serializer()->decode($encoded);
    }

    public function testTamperedBodyBecomesDecodingFailure(): void
    {
        $encoded = $this->serializer()->encode($this->envelope()->with(new EncryptStamp()));
        $raw = (string) base64_decode($encoded['body'], true);
        $raw[40] = \chr(\ord($raw[40]) ^ 0xFF);
        $encoded['body'] = base64_encode($raw);

        $this->expectException(MessageDecodingFailedException::class);
        $this->serializer()->decode($encoded);
    }

    public function testCorruptCompressedBodyBecomesDecodingFailure(): void
    {
        $encoded = $this->serializer()->encode($this->envelope()->with(new CompressStamp()));
        $encoded['body'] = base64_encode('not deflate');

        $this->expectException(MessageDecodingFailedException::class);
        $this->serializer()->decode($encoded);
    }

    public function testUnknownCompressionAlgorithmOnDecodeBecomesDecodingFailure(): void
    {
        $encoded = $this->serializer()->encode($this->envelope()->with(new CompressStamp()));
        $encoded['headers'][PayloadTransformingSerializer::HEADER_COMPRESSION] = 'brotli';

        $this->expectException(MessageDecodingFailedException::class);
        $this->serializer()->decode($encoded);
    }

    public function testEncryptedMessageOnConsumerWithoutEncryptionBecomesDecodingFailure(): void
    {
        $encoded = $this->serializer()->encode($this->envelope()->with(new EncryptStamp()));
        $consumer = new PayloadTransformingSerializer(new PhpSerializer(), new CompressorRegistry([new DeflateCompressor()]), null, null);

        $this->expectException(MessageDecodingFailedException::class);
        $consumer->decode($encoded);
    }

    public function testInvalidEncodingIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->serializer(encoding: 'hex');
    }

    /**
     * @param array{body: string, headers?: array<string, string>} $encoded
     *
     * @return array<string, string>
     */
    private static function headers(array $encoded): array
    {
        return $encoded['headers'] ?? [];
    }

    /** @return array<string, string> */
    private function headersOf(Envelope $envelope): array
    {
        return self::headers((new PhpSerializer())->encode($envelope));
    }
}

final class DummyMessage
{
    public function __construct(public string $payload) {}
}
