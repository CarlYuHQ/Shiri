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

    {{-- 颜色：令牌存在 zp_calendar.kind，取值为空即跟主题默认色 --}}
    @php
        $palette = \Shiri\Domain\Calendar\Services\Calendar::EVENT_COLORS;
        $picked = (string) ($values['kind'] ?? '');
        $picked = $picked === '' ? 'default' : $picked;
    @endphp
    <div class="par">
        <label class="lt-label">{!! __('label.color') !!}</label>
        <div class="lt-swatches">
            @foreach ($palette as $colorKey => $colorMeta)
                <label class="lt-swatch" title="{{ $colorMeta['label'] }}">
                    <input type="radio" name="color" value="{{ $colorKey }}"
                           aria-label="{{ $colorMeta['label'] }}"
                           @if ($picked === $colorKey) checked="checked" @endif />
                    <i style="--swatch: {{ $colorMeta['hex'] !== '' ? $colorMeta['hex'] : 'var(--accent1)' }}"></i>
                </label>
            @endforeach
        </div>
    </div>

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
<script type="text/javascript">
    // 日期输入已换成原生 type=date，不再挂 jQuery UI datepicker。
    // 脚本内联而不是 push 到 scripts 栈：这是 partial 渲染，片段布局里没有那个栈，
    // push 进去的内容会被直接丢掉（nyroModal 会执行片段里的内联 script）。
    jQuery(document).ready(function() {
        @if (isset($_GET['closeModal']))
        // 保存成功后服务端回带 closeModal：关掉弹窗，
        // 日历由 modals.js 的 softReload 就地刷新。
        if (jQuery.nmTop()) { jQuery.nmTop().close(); }
        return;
        @endif

        var from = document.getElementById('event_date_from');
        var to = document.getElementById('event_date_to');
        if (!from || !to) { return; }
        to.addEventListener('change', function() {
            if (from.value && to.value && to.value < from.value) { to.value = from.value; }
        });
    });
</script>
@endonce

@endsection
