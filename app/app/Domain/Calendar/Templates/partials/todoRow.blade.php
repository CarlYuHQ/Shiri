{{-- 一行待办。待办清单页、桌面小卡共用；字段来自 Calendar\Support\TodoBoard::todos()。 --}}
<li class="rb-row{{ $todo['done'] ? ' is-done' : '' }}{{ $todo['overdue'] ? ' is-overdue' : '' }}"
    data-id="{{ $todo['id'] }}"
    data-bucket="{{ $todo['bucket'] }}"
    data-open-bucket="{{ $todo['openBucket'] }}"
    data-done="{{ $todo['done'] ? '1' : '0' }}">
    <button class="rb-check" type="button" role="checkbox"
            aria-checked="{{ $todo['done'] ? 'true' : 'false' }}"
            aria-label="{{ $todo['done'] ? \Shiri\Domain\Calendar\Support\Lang::t('todos.markUndone') : \Shiri\Domain\Calendar\Support\Lang::t('todos.markDone') }}"></button>
    <a class="rb-row-title formModal" href="#/tickets/showTicket/{{ $todo['id'] }}" title="{{ $todo['title'] }}">{{ $todo['title'] }}</a>
    <span class="rb-row-due">{{ $todo['dueLabel'] }}</span>
</li>
