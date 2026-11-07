<?php

namespace Shiri\Domain\Blueprints\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a canvas item was created on any canvas type (including goals and ideas). Wiki articles fire WikiArticleCreated instead.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class CanvasItemCreated implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $canvasItemId  The id of the new item.
     * @param  string  $type  The canvas database type of the item's board (e.g. 'swotcanvas', 'goalcanvas', 'idea').
     * @param  int|null  $projectId  The item's project id, when known.
     */
    public function __construct(
        public readonly int $canvasItemId,
        public readonly string $type,
        public readonly ?int $projectId,
    ) {}
}
