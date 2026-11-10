{{-- 一行待办。待办清单页、桌面小卡共用；字段来自 Calendar\Support\TodoBoard。
     两种来源：ticket（打开待办详情）和日程（打开日程编辑弹窗），勾选走不同接口，
     所以 source 必须带出去给 shiri-board.js 分发。 --}}
@php
    $source = $todo['source'] ?? 'ticket';
    $dot = (string) ($todo['color'] ?? '');
@endphp
<li class="rb-row{{ $todo['done'] ? ' is-done' : '' }}{{ $todo['overdue'] ? ' is-overdue' : '' }}{{ $source === 'event' ? ' is-event' : '' }}"
    data-id="{{ $todo['id'] }}"
    data-source="{{ $source }}"
    data-bucket="{{ $todo['bucket'] }}"
    data-open-bucket="{{ $todo['openBucket'] }}"
    data-done="{{ $todo['done'] ? '1' : '0' }}">
    <button class="rb-check" type="button" role="checkbox"
            aria-checked="{{ $todo['done'] ? 'true' : 'false' }}"
            aria-label="{{ $todo['done'] ? \Shiri\Domain\Calendar\Support\Lang::t('todos.markUndone') : \Shiri\Domain\Calendar\Support\Lang::t('todos.markDone') }}"></button>
    {{-- 日程行带颜色点，ticket 行留一个等宽占位，两种行的标题才能左对齐 --}}
    <span class="rb-evdot"@if ($source === 'event' && $dot !== '') style="--dot: {{ $dot }}"@endif aria-hidden="true"></span>
    <a class="rb-row-title formModal"
       href="{{ $source === 'event' ? '#/calendar/editEvent/'.$todo['id'] : '#/tickets/showTicket/'.$todo['id'] }}"
       title="{{ $todo['title'] }}">{{ $todo['title'] }}</a>
    <span class="rb-row-due">{{ $todo['dueLabel'] }}</span>
</li>
