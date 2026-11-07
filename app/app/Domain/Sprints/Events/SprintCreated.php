<?php

namespace Shiri\Domain\Sprints\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a sprint was created.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class SprintCreated implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $sprintId  The new sprint id.
     * @param  int|null  $projectId  The sprint's project.
     * @param  int|null  $lengthDays  Calendar days from start to end date (null when a date is missing).
     */
    public function __construct(
        public readonly int $sprintId,
        public readonly ?int $projectId,
        public readonly ?int $lengthDays,
    ) {}
}
