<?php

declare(strict_types=1);

namespace MattLeads\MessengerCompressBundle\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Opt a single message out of "always" / "auto_threshold" policies.
 *
 * Explicit CompressStamp / EncryptStamp on the same envelope still win.
 */
final readonly class PlainStamp implements StampInterface {}
