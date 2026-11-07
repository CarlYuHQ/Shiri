<?php

namespace Shiri\Domain\Auth\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a user completed the invite onboarding flow (the historical `onboarding_finished` point).
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class OnboardingCompleted implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $userId  The id of the user who finished onboarding.
     */
    public function __construct(
        public readonly int $userId,
    ) {}
}
