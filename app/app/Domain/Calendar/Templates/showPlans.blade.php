@extends($layout)

@php
    $t = static fn (string $k, array $r = []) => \Shiri\Domain\Calendar\Support\Lang::t($k, $r);
@endphp

@section('content')

{{-- 例行计划：把「每周固定的安排」一次写好，之后自动变成待办。
     写入走 JSON-RPC（Calendar.Plans.*），提交成功后整页刷新，列表就是最新的。 --}}

<div class="rb-page rb-plans">

    <header class="rb-page-head">
        <div>
            <h1 class="rb-page-title">{{ $t('plans.title') }}</h1>
            <p class="rb-page-sub">
                @if (count($rules) === 0)
                    {{ $t('plans.empty') }}
                @else
                    {{ $t('plans.count', ['n' => count($rules)]) }}
                @endif
            </p>
        </div>
    </header>

    <form class="rb-plan-form" autocomplete="off">
        <input class="rb-plan-title" type="text" placeholder="{{ $t('plans.titlePh') }}" maxlength="200" required>

        <div class="rb-plan-line">
            <label class="rb-plan-field">
                <span>{{ $t('plans.freq') }}</span>
                <select class="rb-plan-freq">
                    <option value="weekly" selected>{{ $t('plans.weekly') }}</option>
                    <option value="daily">{{ $t('plans.daily') }}</option>
                </select>
            </label>

            <div class="rb-plan-days" data-role="weekdays">
                @foreach ($weekdayNames as $iso => $name)
                    <button type="button" class="rb-plan-day" data-day="{{ $iso }}">{{ $name }}</button>
                @endforeach
            </div>

            <label class="rb-plan-field">
                <span>{{ $t('plans.time') }}</span>
                <input class="rb-plan-time" type="text" inputmode="numeric" placeholder="{{ $t('plans.timePh') }}" maxlength="5">
            </label>

            <label class="rb-plan-field">
                <span>{{ $t('plans.start') }}</span>
                <input class="rb-plan-start" type="date" value="{{ $today->format('Y-m-d') }}">
            </label>

            <button class="rb-quickadd-btn" type="submit">{{ $t('plans.add') }}</button>
        </div>

        <p class="rb-plan-hint" data-role="hint">{{ $t('plans.hint') }}</p>
    </form>

    @if (count($rules) > 0)
        <ul class="rb-plan-list">
            @foreach ($rules as $rule)
                <li class="rb-plan-item{{ $rule['active'] ? '' : ' is-off' }}" data-id="{{ $rule['id'] }}">
                    <span class="rb-plan-dot"></span>
                    <span class="rb-plan-item-title">{{ $rule['title'] }}</span>
                    <span class="rb-plan-item-when">{{ $rule['summary'] }}</span>
                    <button type="button" class="rb-plan-toggle" data-id="{{ $rule['id'] }}">
                        {{ $rule['active'] ? $t('plans.disable') : $t('plans.enable') }}
                    </button>
                    <button type="button" class="rb-plan-del" data-id="{{ $rule['id'] }}" title="{{ $t('plans.delete') }}">×</button>
                </li>
            @endforeach
        </ul>

        <p class="rb-footnote">{{ $t('plans.footnote') }}</p>
    @endif

</div>

@push('styles')
{{-- 版本号用文件修改时间，改完立刻生效（避免 WebView 拿旧 CSS 配新 HTML） --}}
@php
    $ltV = static function (string $p): int {
        $f = APP_ROOT.'/public'.$p;
        return is_file($f) ? (int) filemtime($f) : 1;
    };
@endphp
<link rel="stylesheet" href="{{ BASE_URL }}/dist/css/shiri-board.css?v={{ $ltV('/dist/css/shiri-board.css') }}">
@endpush

@push('scripts')
<script src="{{ BASE_URL }}/dist/js/shiri-board.js?v={{ $ltV('/dist/js/shiri-board.js') }}"></script>
@endpush

@endsection
