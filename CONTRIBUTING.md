# Contributing

Thanks for considering a contribution.

## Setup

```bash
git clone https://github.com/mattleads/messenger-compress-bundle.git
cd messenger-compress-bundle
composer install
composer check
```

## Guidelines

- Open an issue first for anything beyond a small fix, so we can agree on the approach.
- Keep pull requests focused; one change per PR.
- Add or update tests for behaviour changes (`tests/Unit`, `tests/Functional`).
- Run `composer check` (CS, PHPStan level 8, PHPUnit) before pushing — CI runs the same on PHP 8.2–8.4 and Symfony 6.4–8.x.
- Add an entry to `CHANGELOG.md` under *Unreleased*.
- New compressors/encryptors should ship with round-trip, tamper and garbage-input tests like the existing ones.

## Reporting bugs

Please include PHP and Symfony versions, the transport in use (Redis, AMQP, Doctrine, SQS…), your `mattleads_messenger_compress` configuration (keys redacted) and the encoded envelope headers if relevant.
