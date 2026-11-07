<?php

namespace Shiri\Domain\Api\Events;

use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a JSON-RPC method call by a non-session caller (API key, Bearer token, MCP) succeeded. The web UI's own RPC calls do not fire it.
 *
 * Introduced with the class-based event system, so it has no legacy string name.
 */
final class ApiMethodCalled implements ShiriEvent
{
    use InteractsWithEvents;

    /**
     * @param  string  $method  The normalised method, {module}.{service}.{method} (e.g. tickets.tickets.patch).
     * @param  string  $channel  'api' or 'mcp'.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $channel,
    ) {}
}
