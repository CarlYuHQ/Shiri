@extends($layout)

@section('content')

@php
    $values = $values ?? [];
    // 原生日期控件只认 ISO 的 Y-m-d；数据库里存的是 Y-m-d H:i:s，直接塞进去输入框会空掉
    $isoDate = static function (?string $value): string {
        try {
            $value = (string) $value;

            return dtHelper()->isValidDateString($value) ? dtHelper()->parseDbDateTime($value)->format('Y-m-d') : '';
        } catch (\Throwable $e) {
            return '';
        }
    };
@endphp

{!! $tpl->displayNotification() !!}

<x-global::actions.modal-header>
    <x-slot:title>{!! __('subtitles.event') !!}</x-slot:title>
</x-global::actions.modal-header>
<form action="{{ BASE_URL }}/calendar/editEvent/{{ $values['id'] }}" method="post" class="formModal">

    @dispatchEvent('afterFormOpen')

    <x-global::forms.label for="description">{!! __('label.title') !!}</x-global::forms.label>
    <x-global::forms.text-input id="description" name="description" value="{{ $tpl->escape($values['description']) }}" /><br />

    <x-global::forms.label for="dateFrom">{!! __('label.start_date') !!}</x-global::forms.label>
    <input type="date" id="event_date_from" autocomplete="off" name="dateFrom" value="{{ $isoDate($values['dateFrom'] ?? '') }}" />

    <div class="par">
        <x-global::forms.label> {!! __('label.start_time') !!}</x-global::forms.label>
        <div class="input-append bootstrap-timepicker">
                <input type="time" id="event_time_from" name="timeFrom" value="{{ format($values['dateFrom'])->time24() }}" />
           </div>
    </div>

    <x-global::forms.label for="dateTo">{!! __('label.end_date') !!}</x-global::forms.label>
    <input type="date" id="event_date_to" autocomplete="off" name="dateTo" value="{{ $isoDate($values['dateTo'] ?? '') }}" />

    <div class="par">
        <x-global::forms.label for="">{!! __('label.end_time') !!} </x-global::forms.label>
        <div class="input-append bootstrap-timepicker">
                <input type="time" id="event_time_to" name="timeTo" value="{{ format($values['dateTo'])->time24() }}" />
           </div>
    </div>

    <label for="allDay">{!! __('label.all_day') !!}</label>
    <input type="checkbox" id="allDay" name="allDay"
    @if ($values['allDay'] === 'true')
        checked="checked"
    @endif
    />

    @dispatchEvent('beforeSubmitButton')

    <div class="clear"></div>
    <br />
    <x-global::forms.button tag="a" link="{{ BASE_URL }}/calendar/delEvent/{{ (int) $_GET['id'] }}" class="formModal delete right" state="danger" variant="outline"><i class="fa fa-trash"></i> {!! __('links.delete') !!}</x-global::forms.button>
    <input type="hidden" value="1" name="save" />
    <x-global::forms.button tag="input" inputType="submit" contentRole="primary" :labelText="__('buttons.save')" name="saveEvent" id="save" />

    <div class="clear"></div>

    @dispatchEvent('beforeFormClose')

</form>

@once
@push('scripts')
<script type="text/javascript">
    // 日期输入已换成原生 type=date，不再挂 jQuery UI datepicker
    jQuery(document).ready(function() {
        var from = document.getElementById('event_date_from');
        var to = document.getElementById('event_date_to');
        if (!from || !to) { return; }
        to.addEventListener('change', function() {
            if (from.value && to.value && to.value < from.value) { to.value = from.value; }
        });
    });
</script>
@endpush
@endonce

@endsection
