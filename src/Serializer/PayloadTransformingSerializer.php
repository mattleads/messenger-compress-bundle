<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Serializer;

use MattLeads\MessengerCompressBundle\Compression\CompressorRegistry;
use MattLeads\MessengerCompressBundle\Encryption\EncryptorInterface;
use MattLeads\MessengerCompressBundle\Encryption\KeyRing;
use MattLeads\MessengerCompressBundle\Exception\CompressionException;
use MattLeads\MessengerCompressBundle\Exception\DecryptionFailedException;
use MattLeads\MessengerCompressBundle\Exception\UnknownKeyException;
use MattLeads\MessengerCompressBundle\Exception\UnsupportedAlgorithmException;
use MattLeads\MessengerCompressBundle\Stamp\CompressStamp;
use MattLeads\MessengerCompressBundle\Stamp\EncryptStamp;
use MattLeads\MessengerCompressBundle\Stamp\PlainStamp;
use MattLeads\MessengerCompressBundle\Stamp\TransformedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Decorates any Messenger transport serializer: compresses and/or encrypts the encoded body on the way out,
 * reverses it on the way in. Compression always happens before encryption (ciphertext does not compress).
 *
 * Wire headers:
 *   X-Compression:        compressor name (deflate|gzip|zstd|custom)
 *   X-Encryption:         encryptor name (xchacha20poly1305|custom)
 *   X-Encryption-Key-Id:  key id used, so keys can be rotated without draining queues
 *   X-Payload-Encoding:   "base64" when the (binary) body was base64-encoded for transport safety
 */
final class PayloadTransformingSerializer implements SerializerInterface
{
    public const HEADER_COMPRESSION = 'X-Compression';
    public const HEADER_ENCRYPTION = 'X-Encryption';
    public const HEADER_KEY_ID = 'X-Encryption-Key-Id';
    public const HEADER_ENCODING = 'X-Payload-Encoding';

    public function __construct(
        private readonly SerializerInterface $inner,
        private readonly CompressorRegistry $compressors,
        private readonly ?EncryptorInterface $encryptor,
        private readonly ?KeyRing $keyRing,
        private readonly CompressionPolicy $compression = new CompressionPolicy(),
        private readonly EncryptionPolicy $encryption = new EncryptionPolicy(),
        private readonly string $encoding = 'base64',
    ) {
        if (!\in_array($encoding, ['base64', 'raw'], true)) {
            throw new \InvalidArgumentException(\sprintf('Encoding must be "base64" or "raw", "%s" given.', $encoding));
        }
    }

    public function encode(Envelope $envelope): array
    {
        $encoded = $this->inner->encode($envelope);
        /** @var string $body */
        $body = $encoded['body'];
        /** @var array<string, string> $headers */
        $headers = $encoded['headers'] ?? [];

        $plain = null !== $envelope->last(PlainStamp::class);
        $compressStamp = $envelope->last(CompressStamp::class);
        $encryptStamp = $envelope->last(EncryptStamp::class);
        $transformed = false;

        // 1) Compression
        $algorithm = null;
        $level = $this->compression->level;
        if ($compressStamp instanceof CompressStamp) {
            $algorithm = $compressStamp->algorithm ?? $this->compression->defaultAlgorithm;
            $level = $compressStamp->level ?? $level;
        } elseif (!$plain && ($this->compression->always || (null !== $this->compression->autoThreshold && \strlen($body) >= $this->compression->autoThreshold))) {
            $algorithm = $this->compression->defaultAlgorithm;
        }

        if (null !== $algorithm) {
            $compressed = $this->compressors->get($algorithm)->compress($body, $level);
            // Auto policies only keep the result when it actually helps; an explicit stamp is always honoured.
            if ($compressStamp instanceof CompressStamp || \strlen($compressed) < \strlen($body)) {
                $body = $compressed;
                $headers[self::HEADER_COMPRESSION] = $algorithm;
                $transformed = true;
            }
        }

        // 2) Encryption
        $wantsEncryption = $encryptStamp instanceof EncryptStamp || (!$plain && $this->encryption->always);
        if ($wantsEncryption) {
            if (null === $this->encryptor || null === $this->keyRing || !$this->encryption->enabled) {
                throw new \LogicException('Message requested encryption but mattleads_messenger_compress.encryption is not enabled / has no keys.');
            }
            $keyId = ($encryptStamp instanceof EncryptStamp ? $encryptStamp->keyId : null) ?? $this->keyRing->activeKeyId();
            $body = $this->encryptor->encrypt($body, $this->keyRing->get($keyId));
            $headers[self::HEADER_ENCRYPTION] = $this->encryptor->name();
            $headers[self::HEADER_KEY_ID] = $keyId;
            $transformed = true;
        }

        if ($transformed && 'base64' === $this->encoding) {
            $body = base64_encode($body);
            $headers[self::HEADER_ENCODING] = 'base64';
        }

        $encoded['body'] = $body;
        $encoded['headers'] = $headers;

        return $encoded;
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        /** @var array<string, string> $headers */
        $headers = $encodedEnvelope['headers'] ?? [];
        $compression = $headers[self::HEADER_COMPRESSION] ?? null;
        $encryption = $headers[self::HEADER_ENCRYPTION] ?? null;

        if (null === $compression && null === $encryption) {
            return $this->inner->decode($encodedEnvelope);
        }

        /** @var string $body */
        $body = $encodedEnvelope['body'] ?? throw new MessageDecodingFailedException('Encoded envelope should have at least a "body".');
        $wireSize = \strlen($body);

        if ('base64' === ($headers[self::HEADER_ENCODING] ?? null)) {
            $decoded = base64_decode($body, true);
            if (false === $decoded) {
                throw new MessageDecodingFailedException('Payload is marked base64 but is not valid base64.');
            }
            $body = $decoded;
        }

        $keyId = null;
        if (null !== $encryption) {
            if (null === $this->encryptor || null === $this->keyRing) {
                throw new MessageDecodingFailedException('Received an encrypted message but encryption is not configured on this consumer.');
            }
            if ($encryption !== $this->encryptor->name()) {
                throw new MessageDecodingFailedException(\sprintf('Message was encrypted with "%s" but this consumer uses "%s".', $encryption, $this->encryptor->name()));
            }
            $keyId = $headers[self::HEADER_KEY_ID] ?? throw new MessageDecodingFailedException('Encrypted message is missing the X-Encryption-Key-Id header.');
            try {
                $body = $this->encryptor->decrypt($body, $this->keyRing->get($keyId));
            } catch (UnknownKeyException|DecryptionFailedException $e) {
                throw new MessageDecodingFailedException($e->getMessage(), 0, $e);
            }
        }

        if (null !== $compression) {
            try {
                $body = $this->compressors->get($compression)->decompress($body);
            } catch (UnsupportedAlgorithmException|CompressionException $e) {
                throw new MessageDecodingFailedException($e->getMessage(), 0, $e);
            }
        }

        unset($headers[self::HEADER_COMPRESSION], $headers[self::HEADER_ENCRYPTION], $headers[self::HEADER_KEY_ID], $headers[self::HEADER_ENCODING]);
        $encodedEnvelope['body'] = $body;
        $encodedEnvelope['headers'] = $headers;

        return $this->inner->decode($encodedEnvelope)
            ->with(new TransformedStamp($compression, $encryption, $keyId, $wireSize, \strlen($body)));
    }
}
