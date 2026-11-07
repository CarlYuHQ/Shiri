<?php

namespace Shiri\Core\Events\Concerns;

use Shiri\Core\Events\EventDispatcher;

/**
 * Shared behavior for class-based domain events.
 *
 * Provides the static dispatch() ergonomic and a default empty legacyHooks() so events
 * introduced after the class-based system don't need to declare one:
 *
 *     TicketUpdated::dispatch(ticketId: $id);
 *
 * Do NOT combine with Laravel's Dispatchable trait — both define dispatch(), and the
 * Dispatchable version routes through the generic object path instead of the
 * ShiriEvent fast path.
 */
trait InteractsWithEvents
{
    /**
     * Default: no legacy string names. Override during the migration window with the
     * exact historical shiri.* name of the CURRENT emit site — rebuilt from a
     * `legacyHook: __FUNCTION__` constructor discriminator when several methods
     * historically fired the same raw hook (see the ShiriEvent docblock).
     *
     * @return array<int, string>
     */
    public function legacyHooks(): array
    {
        return [];
    }

    /**
     * Construct and dispatch this event through the Shiri event dispatcher.
     * Arguments (named arguments included) are forwarded to the constructor.
     */
    public static function dispatch(mixed ...$args): void
    {
        EventDispatcher::dispatch_event(new static(...$args));
    }
}
