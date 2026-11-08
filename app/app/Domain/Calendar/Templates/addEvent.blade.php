@extends($layout)

@section('content')

@php
    $values = $values ?? [];
@endphp

{!! $tpl->displayNotification() !!}

<x-global::actions.modal-header>
    <x-slot:title>{!! __('subtitles.event') !!}</x-slot:title>
</x-global::actions.modal-header>
<form action="{{ BASE_URL }}/calendar/addEvent/" method="post" class='formModal'>
    @csrf

    @dispatchEvent('afterFormOpen')

    <x-global::forms.label for="description">{!! __('label.title') !!}</x-global::forms.label>
    <x-global::forms.text-input id="description" name="description" value="{{ $tpl->escape($values['description']) }}" /><br />

    <div class="par">
        <x-global::forms.label for="dateFrom">{!! __('label.start_date') !!}</x-global::forms.label>
        {{-- 原生日期控件：旧版靠 initEventDatepickers() 挂 jQuery UI datepicker，
             这里根本没初始化出来，点输入框没有任何反应，日期只能靠手打。
             type=date 自带选择器，WKWebView 也支持；提交的是 ISO（Y-m-d），
             后端 parseUserDateTime() 会转成用户格式，不会丢时间。 --}}
        <input type="date" id="event_date_from" name="dateFrom" value="" autocomplete="off" /><br/>
    </div>
    <div class="par">
        <x-global::forms.label for="">{!! __('label.start_time') !!}</x-global::forms.label>
        <div class="input-append bootstrap-timepicker">
                <input type="time" id="event_time_from" name="timeFrom" value="" />
           </div>
    </div>
    <div class="par">
        <x-global::forms.label for="dateTo">{!! __('label.end_date') !!}</x-global::forms.label>
        <input type="date" id="event_date_to" name="dateTo" value="" autocomplete="off" /><br/>
    </div>
    <div class="par">
        <x-global::forms.label for="">{!! __('label.end_time') !!} </x-global::forms.label>
        <div class="input-append bootstrap-timepicker">
                <input type="time" id="event_time_to" name="timeTo" value="" />
           </div>
    </div>

    <label for="allDay">{!! __('label.all_day') !!}</label>
    <input type="checkbox" id="allDay" name="allDay"
    @if (isset($values['allDay']) && $values['allDay'] === true)
        checked="checked"
    @endif
    /><br /><br />

    @dispatchEvent('beforeSubmitButton')

    <p class="stdformbutton">
        <input type="hidden" value="1" name="save" />
        <x-global::forms.button tag="input" inputType="submit" contentRole="primary" :labelText="__('buttons.save')" name="saveEvent" id="saveEvent" />
    </p>

    @dispatchEvent('beforeFormClose')

</form>

@once
@push('scripts')
<script type="text/javascript">

    @dispatchEvent('scripts.afterOpen')

    // 日期输入已换成原生 type=date，不再挂 jQuery UI datepicker
    // （shiri.calendarController.initEventDatepickers 在这套构建里点不出选择器）。
    jQuery(document).ready(function() {
        var from = document.getElementById('event_date_from');
        var to = document.getElementById('event_date_to');
        if (!from || !to) { return; }

        // 结束日期没填就跟着开始日期走：不填结束日会存成空值，
        // 事件在日历上没有区间，只剩一个孤零零的起点。
        from.addEventListener('change', function() {
            if (from.value && !to.value) { to.value = from.value; }
            if (to.value && to.value < from.value) { to.value = from.value; }
        });
        to.addEventListener('change', function() {
            if (from.value && to.value && to.value < from.value) { to.value = from.value; }
        });
    });

    @dispatchEvent('scripts.beforeClose')

</script>
@endpush
@endonce

@endsection
