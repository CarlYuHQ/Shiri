<?php

namespace Shiri\Domain\Timesheets\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a NEW timesheet row was created (hours added onto an existing day/ticket/kind row or edits do not fire).
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class TimeEntryCreated implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int|null  $ticketId  The ticket the time was booked on.
     * @param  float  $hours  The hours of the new entry.
     * @param  int|null  $projectId  The ticket's project id, when known.
     */
    public function __construct(
        public readonly ?int $ticketId,
        public readonly float $hours,
        public readonly ?int $projectId,
    ) {}
}
