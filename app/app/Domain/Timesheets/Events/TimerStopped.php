<?php

namespace Shiri\Domain\Timesheets\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after the session user's running timer on a ticket was stopped and its time booked.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class TimerStopped implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $ticketId  The ticket the timer ran on.
     * @param  float  $hours  The hours booked (0 for runs under a minute).
     * @param  bool  $automatic  True when the timer was stopped automatically because the ticket was completed.
     */
    public function __construct(
        public readonly int $ticketId,
        public readonly float $hours,
        public readonly bool $automatic,
    ) {}
}
