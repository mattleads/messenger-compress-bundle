# Security Policy

## Supported versions

The latest minor release receives security fixes.

## Reporting a vulnerability

Please do **not** open a public issue for security problems. Email matthew.mochalkin@gmail.com with a description, reproduction steps and, if possible, a suggested fix. You will receive an acknowledgement within 72 hours and a fix or mitigation plan within 14 days for confirmed issues. Credit is given in the release notes unless you prefer otherwise.

## Scope notes

- Encryption uses libsodium's XChaCha20-Poly1305 (IETF) AEAD with a random 192-bit nonce per message. Message headers are not encrypted by design.
- Compression precedes encryption; compressed length can leak plaintext characteristics (CRIME/BREACH class). Avoid compressing attacker-controlled data together with secrets in one message if this matters to your threat model.
