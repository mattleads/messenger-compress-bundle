# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

[Unreleased]: https://github.com/mattleads/messenger-compress-bundle/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/mattleads/messenger-compress-bundle/releases/tag/v0.1.0

## [0.1.0] - 2026-11-01

### Added
- `PayloadTransformingSerializer`: decorates any Messenger transport serializer with compression and authenticated encryption.
- Stamps: `CompressStamp`, `EncryptStamp`, `PlainStamp`; consumer-side `TransformedStamp`.
- Compressors: deflate, gzip, zstd (when `ext-zstd` is loaded); pluggable `CompressorInterface`.
- Encryption: XChaCha20-Poly1305 via libsodium; pluggable `EncryptorInterface`; `KeyRing` with key ids for rotation.
- Policies: `auto_threshold`, `always` (compression), `always` (encryption), `decorate_default_serializer`.
- `messenger-compress:generate-key` console command.
- Base64 (default) or raw body encoding.
