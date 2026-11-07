<?php

namespace Shiri\Domain\Plugins\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a plugin was enabled.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class PluginEnabled implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  string  $plugin  The plugin identifier (folder name).
     */
    public function __construct(
        public readonly string $plugin,
    ) {}
}
