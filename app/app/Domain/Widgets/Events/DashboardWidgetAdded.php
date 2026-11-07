<?php

namespace Shiri\Domain\Widgets\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a widget was added to the user's dashboard (became visible; drags and resizes do not fire).
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class DashboardWidgetAdded implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  string  $widgetId  The widget id.
     */
    public function __construct(
        public readonly string $widgetId,
    ) {}
}
