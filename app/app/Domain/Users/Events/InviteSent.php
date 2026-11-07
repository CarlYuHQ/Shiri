<?php

namespace Shiri\Domain\Users\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after an invitation email was sent to an invited user (first invite or resend).
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class InviteSent implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $userId  The id of the invited user (the invitee).
     */
    public function __construct(
        public readonly int $userId,
    ) {}
}
