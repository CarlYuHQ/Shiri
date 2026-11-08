@extends($layout)

@section('content')

@php
    // The calendar's last view (saved by the view select via Api.Api.setSubmenuState).
    $myCalendarView = app(\Shiri\Core\UI\UiState::class)->get('myCalendarView', 'dayGridMonth');
    $t = static fn (string $k): string => \Shiri\Domain\Calendar\Support\Lang::t($k);
@endphp

<x-global::layout.page-header icon="fa {{ $tpl->getModulePicture() }}" :eyebrow="__('headline.calendar')">
    {!! __('headline.my_calendar') !!}
</x-global::layout.page-header>

{!! $tpl->displayNotification() !!}

<div class="maincontent">

    <x-global::layout.content-box id="my-calendar" :leftWidth="240">
        <x-slot:left>
            <x-global::elements.section-header as="h5" class="tw-pb-m">{{ __('label.calendars') }}</x-global::elements.section-header>

            <ul class="simpleList">
                <li><span class="indicatorCircle" style="background:var(--accent1)"></span>{{ __('label.events') }}</li>
                <li><span class="indicatorCircle" style="background:var(--accent2)"></span>{{ __('label.projects_and_tasks') }}</li>

            @foreach ($externalCalendars as $calendars)
                <li>
                    @if (empty($calendars['managedByPlugin']))
                    <x-global::actions.dropdown icon="fa fa-ellipsis-h" trigger-class="editHeadline" style="float:right;">
                        <li>
                            <a href="#/calendar/editExternal/{{ $calendars['id'] }}"><i class="fa-solid fa-pen-to-square"></i> {!! __('links.edit_calendar') !!}</a>
                        </li>
                        <li><a href="#/calendar/delExternalCalendar/{{ $calendars['id'] }}" class="delete"><i class="fa fa-trash"></i> {!! __('links.delete_external_calendar') !!}</a></li>

                    </x-global::actions.dropdown>
                    @endif
                    <span class="indicatorCircle" style="background:{{ $calendars['colorClass'] }}"></span>{{ $calendars['name'] }}

                </li>
            @endforeach

            </ul>
            <hr />
            <a href="#/calendar/connectCalendar" class="formModal" style="display:block; margin-bottom:8px; margin-left:-5px;"><i class="fa-regular fa-calendar-plus" style="width:16px;"></i> {!! __('label.connect_calendar') !!}</a>
            <a href="#/calendar/calendarSettings" class="formModal" style="margin-left:-5px;"><i class="fa fa-cog" style="width:16px;"></i> {!! __('label.calendar_settings') !!}</a>
        </x-slot:left>
        {{-- One toolbar row: the two outer groups share the free space, so the title stays centred
             and the view controls stay on one line. --}}
        <div class="calendarToolbar tw-flex tw-flex-wrap tw-items-center tw-gap-sm tw-mb-m">
            <div class="tw-flex-1">
                <x-global::forms.button tag="a" link="#/calendar/addEvent" contentRole="primary" class="formModal"><i class='fa fa-plus'></i> {!! __('buttons.add_event') !!}</x-global::forms.button>
            </div>
            <div class="fc-center center" id="calendarTitle">
                <h2>..</h2>
            </div>
            <div class="tw-flex-1 tw-flex tw-items-center tw-justify-end tw-gap-xs">
                <x-global::forms.select id="my-select" width="sm" class="tw-mb-none">
                    <option class="fc-timeGridDay-button fc-button fc-state-default fc-corner-right" value="timeGridDay" {{ $myCalendarView == 'timeGridDay' ? 'selected' : '' }}>{{ __('buttons.day') }}</option>
                    <option class="fc-timeGridWeek-button fc-button fc-state-default fc-corner-right" value="timeGridWeek" {{ $myCalendarView == 'timeGridWeek' ? 'selected' : '' }}>{{ __('buttons.week') }}</option>
                    <option class="fc-dayGridMonth-button fc-button fc-state-default fc-corner-right" value="dayGridMonth" {{ $myCalendarView == 'dayGridMonth' ? 'selected' : '' }}>{{ __('buttons.month') }}</option>
                    <option class="fc-multiMonthYear-button fc-button fc-state-default fc-corner-right" value="multiMonthYear" {{ $myCalendarView == 'multiMonthYear' ? 'selected' : '' }}>{{ __('label.year') }}</option>
                </x-global::forms.select>
                <button class="fc-today-button btn btn-default" type="button">{{ __('label.today_lowercase') }}</button>
                <button class="fc-prev-button btn btn-default" type="button">
                    <span class="fc-icon fc-icon-chevron-left"></span>
                </button>
                <button class="fc-next-button btn btn-default" type="button">
                    <span class="fc-icon fc-icon-chevron-right"></span>
                </button>
                <x-global::forms.button tag="a" link="#/calendar/export" contentRole="default">{{ __('links.export') }}</x-global::forms.button>
            </div>
        </div>
        {{-- 日历 + 右侧「重要时间节点」栏。包一层 cal-stage 做横向 flex，
             工具条留在上面横跨整行，不受影响。 --}}
        <div class="cal-stage">
            <div id="calendar"></div>

            <aside class="ms-panel" id="ms-panel">
                <details class="ms-details" open>
                    <summary class="ms-head">
                        <span class="ms-head-title">{{ $t('ms.title') }}</span>
                        <span class="ms-head-count">{{ $msCount }}</span>
                    </summary>

                    <div class="ms-body">
                        @foreach (['soon', 'far', 'past'] as $bucket)
                            @if (count($msGroups[$bucket]) > 0)
                                <section class="ms-group ms-group--{{ $bucket }}">
                                    <h3 class="ms-group-label">{{ $t('ms.'.$bucket) }}</h3>
                                    <ul class="ms-list">
                                        @foreach ($msGroups[$bucket] as $m)
                                            <li class="ms-item ms-item--{{ $m['bucket'] }}" data-id="{{ $m['id'] }}">
                                                <div class="ms-item-top">
                                                    <div class="ms-item-text">
                                                        <div class="ms-item-date">{{ $m['dateLabel'] }}</div>
                                                        <div class="ms-item-title">{{ $m['title'] }}</div>
                                                    </div>
                                                    <button type="button" class="ms-del"
                                                            title="{{ $t('ms.delete') }}"
                                                            aria-label="{{ $t('ms.delete') }}">&times;</button>
                                                </div>
                                                <div class="ms-countdown">
                                                    <span class="ms-pre">{{ $m['pre'] }}</span><span class="ms-num">{{ $m['num'] }}</span><span class="ms-post">{{ $m['post'] }}</span>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endif
                        @endforeach

                        @if ($msCount === 0)
                            <p class="ms-empty">{{ $t('ms.empty') }}</p>
                        @endif
                    </div>
                </details>

                <button type="button" class="ms-add-btn" id="ms-add-btn">+ {{ $t('ms.add') }}</button>

                <form class="ms-form" id="ms-form" hidden>
                    <label><span>{{ $t('ms.name') }}</span><input type="text" name="title" maxlength="120" required></label>
                    <label><span>{{ $t('ms.nameEn') }}</span><input type="text" name="titleEn" maxlength="120"></label>
                    <label><span>{{ $t('ms.date') }}</span><input type="date" name="date" required></label>
                    <div class="ms-form-actions">
                        <button type="submit" class="ms-submit">{{ $t('ms.save') }}</button>
                        <button type="button" class="ms-cancel">{{ $t('ms.cancel') }}</button>
                    </div>
                    <p class="ms-form-err" id="ms-form-err" hidden></p>
                </form>
            </aside>
        </div>
    </x-global::layout.content-box>


</div>

@once
@push('scripts')
{{-- 资源版本用文件修改时间：改完样式/脚本立刻生效，不用手动改 ?v=，
     也避免 WebView 拿旧 CSS 配新 HTML（7 列网格塌成一列就是这么来的）。 --}}
@php
    $ltV = static function (string $p): int {
        $f = APP_ROOT.'/public'.$p;
        return is_file($f) ? (int) filemtime($f) : 1;
    };
@endphp
<script type='text/javascript'>

    @dispatchEvent('scripts.afterOpen')


    jQuery(document).ready(function() {

        //shiri.calendarController.initCalendar(events);
        shiri.calendarController.initExportModal();

    });
    var eventSources = [];

    var events = {events: [
        @foreach ($calendar as $calendarEvent)
        {
            title: {!! json_encode($calendarEvent['title']) !!},
            description: {!! json_encode($calendarEvent['description'] ?? '') !!},

            start: new Date({{ format($calendarEvent['dateFrom'])->jsTimestamp() }}),
            @if (isset($calendarEvent['dateTo']))
            end: new Date({{ format($calendarEvent['dateTo'])->jsTimestamp() }}),
            @endif
            @if (isset($calendarEvent['allDay']) && $calendarEvent['allDay'] === true)
            allDay: true,
            @else
            allDay: false,
            @endif
            enitityId: {{ $calendarEvent['id'] }},
            @if (isset($calendarEvent['eventType']) && $calendarEvent['eventType'] == 'calendar')
            url: '{{ CURRENT_URL }}#/calendar/editEvent/{{ $calendarEvent['id'] }}',
            backgroundColor: '{{ $calendarEvent['backgroundColor'] ?? 'var(--accent2)' }}',
            borderColor: '{{ $calendarEvent['borderColor'] ?? 'var(--accent2)' }}',
            enitityType: "event",
            dateContext: '{{ $calendarEvent['dateContext'] ?? 'plan' }}',
            @else
            url: '{{ CURRENT_URL }}#/tickets/showTicket/{{ $calendarEvent['id'] }}?projectId={{ $calendarEvent['projectId'] }}',
            backgroundColor: '{{ $calendarEvent['backgroundColor'] ?? 'var(--accent2)' }}',
            borderColor: '{{ $calendarEvent['borderColor'] ?? 'var(--accent2)' }}',
            enitityType: "ticket",
            dateContext: '{{ $calendarEvent['dateContext'] ?? 'edit' }}',
            @endif
        },
        @endforeach
    ]};

    eventSources.push(events);

    @foreach ($externalCalendars as $externalCalendar)
        @if (empty($externalCalendar['managedByPlugin']))
        eventSources.push(
            {
                url: '{{ BASE_URL }}/calendar/externalCal/{{ $externalCalendar['id'] }}',
                format: 'ics',
                color: '{{ $externalCalendar['colorClass'] }}',
                editable: false,
            }
        );
        @endif
    @endforeach


    document.addEventListener('DOMContentLoaded', function() {
        const heightWindow = jQuery("body").height() - 210;

        const calendarEl = document.getElementById('calendar');

        const calendar = new FullCalendar.Calendar(calendarEl, {
                timeZone: shiri.i18n.__("usersettings.timezone"),
                height: '100%',
                stickyHeaderDates: true,
                initialView: '{{ $myCalendarView }}',
                eventSources:eventSources,
                editable: true,
                headerToolbar: false,
                dayHeaderFormat: shiri.dateHelper.getFormatFromSettings("dateformat", "luxon"),
                eventTimeFormat: shiri.dateHelper.getFormatFromSettings("timeformat", "luxon"),
                slotLabelFormat: shiri.dateHelper.getFormatFromSettings("timeformat", "luxon"),
                {{-- 设置页的「一周从周几开始」优先于语言包默认值（0=周日 1=周一） --}}
                firstDay: {{ $weekStartFirstDay ?? 0 }},
                views: {
                    timeGridDay: {

                    },
                    timeGridWeek: {

                    },
                    dayGridMonth: {
                        dayHeaderFormat: { weekday: 'short' },
                    },
                    multiMonthYear: {
                        showNonCurrentDates: true,
                        multiMonthTitleFormat: { month: 'long', year: 'numeric' },
                        dayHeaderFormat: { weekday: 'short' },
                    },
                    multiMonthOneMonth: {
                        type: 'multiMonth',
                        duration: {months: 1},
                        multiMonthTitleFormat: {month: 'long', year: 'numeric'},
                        dayHeaderFormat: {weekday: 'short'},
                    },
                    listWeek: {
                        listDayFormat: {weekday: 'long'},
                        listDaySideFormat: shiri.dateHelper.getFormatFromSettings("dateformat", "luxon"),
                    }
                },
                nowIndicator: true,
                bootstrapFontAwesome: {
                    close: 'fa-times',
                    prev: 'fa-chevron-left',
                    next: 'fa-chevron-right',
                    prevYear: 'fa-angle-double-left',
                    nextYear: 'fa-angle-double-right'
                },
                eventDrop: function (event) {

                    if(event.event.extendedProps.enitityType == "ticket") {

                        let dataVal = {};

                        if(event.event.extendedProps.dateContext == "due") {

                            dataVal = {
                                id: event.event.extendedProps.enitityId,
                                dateToFinish: event.event.startStr
                            }

                        }else{
                            dataVal = {
                                id: event.event.extendedProps.enitityId,
                                editFrom: event.event.startStr,
                                editTo: event.event.endStr
                            }
                        }

                        shiri.rpc('Tickets.Tickets.patchTicket', { id: dataVal.id, values: dataVal })
                            .catch(function (error) {
                                jQuery.growl({ message: (error && error.message) ? error.message : shiri.i18n.__("short_notifications.not_saved"), style: "error" });
                                event.revert();
                                console.error('Could not update ticket dates', error);
                            });

                    }else if(event.event.extendedProps.enitityType == "event") {

                        shiri.rpc('Calendar.Calendar.patch', {
                            id: event.event.extendedProps.enitityId,
                            params: {
                                dateFrom: event.event.startStr,
                                dateTo: event.event.endStr
                            }
                        }).then(function (success) {
                            // Denied/failed update resolves to false — undo the visual move.
                            if (! success) { event.revert(); }
                        }).catch(function (error) {
                            console.error('Could not update event dates', error);
                            event.revert();
                        })
                    }
                },
                eventResize: function (event) {

                    if(event.event.extendedProps.enitityType == "ticket") {

                        let dataVal = {};

                        if(event.event.extendedProps.dateContext == "due") {

                            dataVal = {
                                id: event.event.extendedProps.enitityId,
                                dateToFinish: event.event.startStr
                            }

                        }else{
                            dataVal = {
                                id: event.event.extendedProps.enitityId,
                                editFrom: event.event.startStr,
                                editTo: event.event.endStr
                            }
                        }

                        shiri.rpc('Tickets.Tickets.patchTicket', { id: dataVal.id, values: dataVal })
                            .catch(function (error) {
                                jQuery.growl({ message: (error && error.message) ? error.message : shiri.i18n.__("short_notifications.not_saved"), style: "error" });
                                event.revert();
                                console.error('Could not update ticket dates', error);
                            });


                    }else if(event.event.extendedProps.enitityType == "event") {

                        shiri.rpc('Calendar.Calendar.patch', {
                            id: event.event.extendedProps.enitityId,
                            params: {
                                dateFrom: event.event.startStr,
                                dateTo: event.event.endStr
                            }
                        }).then(function (success) {
                            // Denied/failed update resolves to false — undo the visual move.
                            if (! success) { event.revert(); }
                        }).catch(function (error) {
                            console.error('Could not update event dates', error);
                            event.revert();
                        })
                    }

                },
                eventMouseEnter: function (info) {
                    window.__fcTipShow(info.event, info.el);
                },
                eventMouseLeave: function () {
                    window.__fcTipHide();
                },
                eventDragStart: function () {
                    window.__fcTipHide();
                },

            }
            );
        calendar.setOption('locale', shiri.i18n.__("language.code"));
        calendar.render();
        calendar.scrollToTime( Date.now() );
        jQuery("#calendarTitle h2").text(calendar.getCurrentData().viewTitle);

        jQuery('.fc-prev-button').click(function() {
            calendar.prev();
            calendar.getCurrentData()
            jQuery("#calendarTitle h2").text(calendar.getCurrentData().viewTitle);
        });
        jQuery('.fc-next-button').click(function() {
            calendar.next();
            jQuery("#calendarTitle h2").text(calendar.getCurrentData().viewTitle);
        });
        jQuery('.fc-today-button').click(function() {
            calendar.today();
            jQuery("#calendarTitle h2").text(calendar.getCurrentData().viewTitle);
        });
        jQuery("#my-select").on("change", function(e){

            calendar.changeView(jQuery("#my-select option:selected").val());

            shiri.rpc('Api.Api.setSubmenuState', {
                submenu: "myCalendarView",
                state: jQuery("#my-select option:selected").val()
            }).catch(function (e) { console.error('Could not update submenu state', e); });

        });
    });

    @dispatchEvent('scripts.beforeClose')

</script>
@endpush
@endonce

<style type="text/css">
    .maincontent .maincontentinner {
        height:calc(100vh - 165px);
    }

    /* The calendar takes the height the toolbar leaves (the toolbar wraps on narrow screens). */
    .maincontent .lt-content .lt-content-main {
        display:flex;
        flex-direction:column;
    }

    .maincontent .lt-content .lt-content-main #calendar {
        flex:1 1 0;
        min-height:0;
    }

    /* Stacked (narrow screens): the calendar pane gets the full height, below the calendar list. */
    @media (max-width: 991px) {
        .maincontent .maincontentinner.lt-content {
            height:auto;
        }

        .maincontent .lt-content .lt-content-main {
            height:calc(100vh - 165px);
        }
    }
</style>
@push('styles')
{{-- 本机补丁：日历高度与条目排版（见 public/dist/css/shiri-calendar.css） --}}
<link rel="stylesheet" href="{{ BASE_URL }}/dist/css/shiri-calendar.css?v={{ $ltV('/dist/css/shiri-calendar.css') }}">
{{-- 右侧「重要时间节点」栏 --}}
<link rel="stylesheet" href="{{ BASE_URL }}/dist/css/shiri-milestones.css?v={{ $ltV('/dist/css/shiri-milestones.css') }}">
@endpush

@push('scripts')
{{-- 本机补丁：给日历条目加悬停 ×／右键快速删除（见 public/dist/js/shiri-quickdelete.js） --}}
<script src="{{ BASE_URL }}/dist/js/shiri-quickdelete.js?v={{ $ltV('/dist/js/shiri-quickdelete.js') }}"></script>
{{-- 本机补丁：点日历空白处就地新建日程（见 public/dist/js/shiri-quickadd.js） --}}
<script src="{{ BASE_URL }}/dist/js/shiri-quickadd.js?v={{ $ltV('/dist/js/shiri-quickadd.js') }}"></script>
{{-- 右侧栏：添加 / 删除节点 --}}
<script src="{{ BASE_URL }}/dist/js/shiri-milestones.js?v={{ $ltV('/dist/js/shiri-milestones.js') }}"></script>
{{-- 日历条目悬浮详情卡：完整标题 / 时间 / 描述（独立文件，避免嵌进主脚本被截断） --}}
<script src="{{ BASE_URL }}/dist/js/shiri-calendar-tooltip.js?v={{ $ltV('/dist/js/shiri-calendar-tooltip.js') }}"></script>
@endpush

@endsection
