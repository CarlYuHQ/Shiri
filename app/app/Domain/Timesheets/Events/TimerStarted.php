<?php

namespace Shiri\Domain\Timesheets\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after the session user started a timer (punch in) on a ticket.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class TimerStarted implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $ticketId  The ticket the timer runs on.
     */
    public function __construct(
        public readonly int $ticketId,
    ) {}
}
