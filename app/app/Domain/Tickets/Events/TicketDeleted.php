<?php

namespace Shiri\Domain\Tickets\Events;

use Shiri\Core\Events\Concerns\InteractsWithClientEvents;
use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\EmitsClientEvent;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a ticket was deleted.
 *
 * On htmx requests it also tells the browser (lt:tickets:ticket.deleted).
 */
final class TicketDeleted implements EmitsClientEvent, ShiriEvent
{
    use InteractsWithClientEvents, InteractsWithEvents;

    public const CLIENT_EVENT = 'lt:tickets:ticket.deleted';

    /**
     * @param  int  $ticketId  The deleted ticket id.
     * @param  string|null  $legacyHook  TEMPORARY (migration window): the emitting method name —
     *                                   pass __FUNCTION__ — used to rebuild the exact historical
     *                                   string name this site fired under for plugin listeners.
     */
    public function __construct(
        public readonly int $ticketId,
        private readonly ?string $legacyHook = null,
    ) {}

    /**
     * The exact historical string name of the emitting site. Remove with the migration window.
     *
     * @return array<int, string>
     */
    public function legacyHooks(): array
    {
        if ($this->legacyHook === null) {
            return [];
        }

        return ['shiri.domain.tickets.services.tickets.'.$this->legacyHook.'.ticket_deleted'];
    }
}
