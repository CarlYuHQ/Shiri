<?php

namespace Shiri\Domain\Projects\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a project status update (a project comment carrying a status) was posted. CommentAdded fires as well.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class ProjectStatusUpdatePosted implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $projectId  The project the update was posted on.
     * @param  int  $commentId  The id of the status update comment.
     * @param  string  $status  The posted status (green, yellow, red, ...).
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $commentId,
        public readonly string $status,
    ) {}
}
