<?php

namespace Shiri\Domain\TwoFA\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a user verified a code and enabled two-factor authentication.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class TwoFactorEnabled implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $userId  The user who enabled 2FA.
     */
    public function __construct(
        public readonly int $userId,
    ) {}
}
