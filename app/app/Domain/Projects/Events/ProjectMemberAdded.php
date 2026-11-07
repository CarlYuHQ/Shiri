<?php

namespace Shiri\Domain\Projects\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a user was added to a project's team (a new membership row, not an unchanged one).
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class ProjectMemberAdded implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $projectId  The project the user was added to.
     * @param  int  $userId  The user who was added.
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $userId,
    ) {}
}
