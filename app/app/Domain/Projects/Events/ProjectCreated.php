<?php

namespace Shiri\Domain\Projects\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a new project was created (not on duplication — see the projectDuplicated string event).
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class ProjectCreated implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $projectId  The id of the created project.
     * @param  string  $type  The project type: 'project', 'strategy', 'program', ...
     */
    public function __construct(
        public readonly int $projectId,
        public readonly string $type,
    ) {}
}
