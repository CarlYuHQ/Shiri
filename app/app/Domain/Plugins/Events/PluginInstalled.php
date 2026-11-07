<?php

namespace Shiri\Domain\Plugins\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a plugin was installed (registered in the plugin table).
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class PluginInstalled implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  string  $plugin  The plugin identifier (folder name).
     * @param  string  $format  How the plugin is delivered: 'folder' or 'phar'.
     */
    public function __construct(
        public readonly string $plugin,
        public readonly string $format,
    ) {}
}
