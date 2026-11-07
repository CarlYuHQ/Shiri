<?php

namespace Shiri\Domain\Projects\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a project was closed/archived (its state changed to -1 from any other state).
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class ProjectArchived implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $projectId  The id of the archived project.
     */
    public function __construct(
        public readonly int $projectId,
    ) {}
}
