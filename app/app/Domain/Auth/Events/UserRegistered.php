<?php

namespace Shiri\Domain\Auth\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after an invited user finished account setup (onboarding) and was activated — the self-signup / invite-acceptance success point.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class UserRegistered implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $userId  The id of the user who registered.
     */
    public function __construct(
        public readonly int $userId,
    ) {}
}
