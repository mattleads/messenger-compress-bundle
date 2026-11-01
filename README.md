# Messenger Compress Bundle

[![CI](https://github.com/mattleads/messenger-compress-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/mattleads/messenger-compress-bundle/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/mattleads/messenger-compress-bundle/v)](https://packagist.org/packages/mattleads/messenger-compress-bundle)
[![Total Downloads](https://poser.pugx.org/mattleads/messenger-compress-bundle/downloads)](https://packagist.org/packages/mattleads/messenger-compress-bundle)
[![License](https://poser.pugx.org/mattleads/messenger-compress-bundle/license)](LICENSE)

Transparent **compression** and **authenticated encryption** for [Symfony Messenger](https://symfony.com/doc/current/messenger.html) payloads.
Opt in per message with a stamp, or enforce it per transport — your handlers never notice.

```php
$bus->dispatch(new GenerateBulkInvoices($lines), [new CompressStamp(), new EncryptStamp()]);
```

## Why

* **Broker limits and cost.** Bulk exports, imports and sync jobs easily exceed SQS's 256 KB or Redis/RabbitMQ comfort zones. Deflate typically shrinks JSON/serialized payloads 5–20×.
* **PII at rest in the queue.** Queues are rarely covered by the same controls as databases. Encrypting the body means Redis dumps, RabbitMQ disks, Doctrine `messenger_messages` rows and dead-letter queues never hold plain text.
* **No handler changes.** It is a transport serializer decorator: compression and decryption happen on the wire, the envelope your handler receives is unchanged (plus an informational stamp).

## Requirements

* PHP 8.2+ with `ext-zlib` and `ext-sodium` (both bundled with PHP)
* Symfony 6.4, 7.x or 8.x
* Optional: `ext-zstd` for the `zstd` compressor

## Installation

```bash
composer require mattleads/messenger-compress-bundle
```

Without Symfony Flex, register the bundle:

```php
// config/bundles.php
return [
    // ...
    MattLeads\MessengerCompressBundle\MattLeadsMessengerCompressBundle::class => ['all' => true],
];
```

## Quick start

### 1. Point a transport at the serializer

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                serializer: mattleads_messenger_compress.serializer
```

Or wrap the default serializer so **every** transport gets it:

```yaml
# config/packages/mattleads_messenger_compress.yaml
mattleads_messenger_compress:
    decorate_default_serializer: true
```

### 2. Compress

```php
use MattLeads\MessengerCompressBundle\Stamp\CompressStamp;

$bus->dispatch($message, [new CompressStamp()]);            // configured default (deflate)
$bus->dispatch($message, [new CompressStamp('zstd', 3)]);   // explicit algorithm + level
```

### 3. Encrypt

Generate a key and keep it out of version control:

```bash
bin/console messenger-compress:generate-key -v
# MESSENGER_ENCRYPTION_KEY_V1=…   → .env.local / your secrets vault
```

```yaml
mattleads_messenger_compress:
    encryption:
        enabled: true
        keys:
            v1: '%env(MESSENGER_ENCRYPTION_KEY_V1)%'
```

```php
use MattLeads\MessengerCompressBundle\Stamp\EncryptStamp;

$bus->dispatch($message, [new EncryptStamp()]);
$bus->dispatch($message, [new CompressStamp(), new EncryptStamp()]); // compress first, then encrypt
```

## Policies: do it for every message

```yaml
mattleads_messenger_compress:
    compression:
        default_algorithm: deflate   # deflate | gzip | zstd
        level: 6                     # null = algorithm default
        auto_threshold: 4096         # compress any body >= 4 KiB, no stamp needed
        always: false                # or compress everything
    encryption:
        enabled: true
        always: true                 # encrypt everything on this serializer
        keys: { v1: '%env(MESSENGER_ENCRYPTION_KEY_V1)%' }
```

Opt a single message out of `always` / `auto_threshold` with `PlainStamp`. Explicit `CompressStamp` / `EncryptStamp` always win.
Automatic compression is only kept when it actually shrinks the body; an explicit stamp is always honoured.

## Key rotation without draining queues

```yaml
mattleads_messenger_compress:
    encryption:
        enabled: true
        active_key: v2                       # new messages use v2
        keys:
            v1: '%env(MESSENGER_ENCRYPTION_KEY_V1)%'   # still decodes messages already in flight
            v2: '%env(MESSENGER_ENCRYPTION_KEY_V2)%'
```

Every encrypted message carries `X-Encryption-Key-Id`, so consumers pick the right key. Deploy the new key to all consumers first, then switch `active_key`, then remove the old key once the queues have cycled.

## What goes over the wire

| Header | Value | Meaning |
|---|---|---|
| `X-Compression` | `deflate` \| `gzip` \| `zstd` | body was compressed with this algorithm |
| `X-Encryption` | `xchacha20poly1305` | body was encrypted with this scheme |
| `X-Encryption-Key-Id` | e.g. `v2` | key used, for rotation |
| `X-Payload-Encoding` | `base64` | body is base64 (default; keeps JSON-based transports happy) |

Set `encoding: raw` to skip base64 on transports that accept binary bodies (AMQP) and save ~33 %.

On the consumer side the envelope gets a `TransformedStamp` (`compression`, `encryption`, `keyId`, `wireSize`, `rawSize`, `ratio()`) — handy for logging and metrics. It is never sent back out.

## Security notes

* **Authenticated encryption**: XChaCha20-Poly1305 (IETF) via libsodium, 192-bit random nonce per message, constant-time verification. Tampered or wrongly-keyed payloads fail with `MessageDecodingFailedException`, which Messenger routes to your failure transport instead of crash-looping the worker.
* Compression happens **before** encryption (ciphertext does not compress). Be aware that compressed-then-encrypted length can leak information about the plaintext (CRIME/BREACH class); do not compress attacker-controlled data mixed with secrets in one message if that matters to your threat model.
* Keys are 32 random bytes, base64-encoded in configuration. Feed them from a secrets vault or `secrets:set`, never commit them.
* Headers are not encrypted. Messenger stamps travel as headers (`X-Message-Stamp-*`) — keep secrets in the message body, not in stamps.

## Extending

Register your own compressor or encryptor by implementing `CompressorInterface` / `EncryptorInterface`; compressors are autoconfigured via the `mattleads_messenger_compress.compressor` tag.

```php
final class BrotliCompressor implements CompressorInterface { /* name(): 'brotli' … */ }
```

```php
$bus->dispatch($message, [new CompressStamp('brotli')]);
```

## Full configuration reference

```yaml
mattleads_messenger_compress:
    inner_serializer: messenger.default_serializer   # service id to wrap
    decorate_default_serializer: false
    encoding: base64                                 # base64 | raw
    compression:
        default_algorithm: deflate
        level: null
        auto_threshold: null
        always: false
    encryption:
        enabled: false
        always: false
        active_key: null                             # defaults to the last key
        keys: {}                                     # id => base64 32-byte key
```

## Testing

```bash
composer install
composer check   # php-cs-fixer, phpstan (level 8), phpunit
```

## Contributing

Issues and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Security issues: see [SECURITY.md](SECURITY.md).

## License

MIT — see [LICENSE](LICENSE).
