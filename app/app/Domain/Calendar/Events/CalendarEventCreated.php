<?php

namespace Shiri\Domain\Calendar\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a calendar event was created.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class CalendarEventCreated implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $eventId  The new event id.
     * @param  bool  $allDay  Whether it is an all-day event.
     */
    public function __construct(
        public readonly int $eventId,
        public readonly bool $allDay,
    ) {}
}
