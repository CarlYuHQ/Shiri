<?php

namespace Shiri\Domain\Tickets\Events;

use Shiri\Core\Events\Concerns\InteractsWithClientEvents;
use Shiri\Core\Events\Concerns\InteractsWithEvents;
use Shiri\Core\Events\Contracts\EmitsClientEvent;
use Shiri\Core\Events\Contracts\ShiriEvent;

/**
 * Fired after a ticket's subtasks changed: a subtask was added, edited or deleted.
 *
 * The subtask itself also fires TicketCreated/TicketUpdated/TicketDeleted; this event exists
 * for the parent, so whatever shows the parent's subtask list (the ticket modal) can refresh.
 * On htmx requests it tells the browser lt:tickets:subtask.updated plus the parent-scoped name.
 */
final class SubtasksUpdated implements EmitsClientEvent, ShiriEvent
{
    use InteractsWithClientEvents, InteractsWithEvents;

    public const CLIENT_EVENT = 'lt:tickets:subtask.updated';

    /**
     * @param  int  $parentTicketId  The ticket whose subtasks changed.
     */
    public function __construct(
        public readonly int $parentTicketId,
    ) {}

    /**
     * Scope the client event to the parent ticket.
     */
    public function clientScope(): int
    {
        return $this->parentTicketId;
    }
}
