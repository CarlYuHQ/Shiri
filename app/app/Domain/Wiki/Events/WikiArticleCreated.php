<?php

namespace Shiri\Domain\Wiki\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a wiki article was created.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class WikiArticleCreated implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $articleId  The id of the new article.
     * @param  int|null  $projectId  The wiki's project id, when known.
     */
    public function __construct(
        public readonly int $articleId,
        public readonly ?int $projectId,
    ) {}
}
