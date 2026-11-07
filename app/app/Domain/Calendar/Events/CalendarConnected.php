<?php

namespace Shiri\Domain\Calendar\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a user connected a calendar: generated their iCal feed link or added an external calendar.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class CalendarConnected implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  string  $kind  'ical_feed' or 'external_calendar'.
     */
    public function __construct(
        public readonly string $kind,
    ) {}
}
