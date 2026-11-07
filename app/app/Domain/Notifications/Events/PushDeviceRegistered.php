<?php

namespace Shiri\Domain\Notifications\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a mobile device registered its push token.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class PushDeviceRegistered implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  int  $userId  The device owner.
     * @param  string  $platform  'ios' or 'android'.
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $platform,
    ) {}
}
