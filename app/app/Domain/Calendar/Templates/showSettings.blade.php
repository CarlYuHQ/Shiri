@extends($layout)

@php
    $t = static fn (string $k, array $r = []) => \Shiri\Domain\Calendar\Support\Lang::t($k, $r);
@endphp

@section('content')

{{-- 设置：语言 / 卡片配色 / 不透明度 / 一周起始日。
     改动即时保存（JSON-RPC Calendar.Settings.save），换语言后整页刷新一次。 --}}

<div class="rb-page rb-settings">

    <header class="rb-page-head">
        <div>
            <h1 class="rb-page-title">{{ $t('settings.title') }}</h1>
            <p class="rb-page-sub">{{ $t('settings.sub') }}</p>
        </div>
        <span class="rb-set-status" data-role="status" aria-live="polite"></span>
    </header>

    {{-- 语言 --}}
    <section class="rb-set-block">
        <h2 class="rb-set-head">{{ $t('settings.language') }}</h2>
        <p class="rb-hint">{{ $t('settings.languageHint') }}</p>
        <div class="rb-set-opts" data-setting="lang">
            @foreach ($languages as $code => $label)
                <button type="button" class="rb-set-opt{{ $currentLang === $code ? ' is-on' : '' }}"
                        data-key="lang" data-value="{{ $code }}">{{ $label }}</button>
            @endforeach
        </div>
    </section>

    {{-- 卡片配色 --}}
    <section class="rb-set-block">
        <h2 class="rb-set-head">{{ $t('settings.theme') }}</h2>
        <p class="rb-hint">{{ $t('settings.themeHint') }}</p>
        <div class="rb-set-opts" data-setting="theme">
            <button type="button" class="rb-set-opt{{ $prefs['theme'] === 'auto' ? ' is-on' : '' }}"
                    data-key="theme" data-value="auto">{{ $t('settings.themeAuto') }}</button>
            @foreach ($themes as $key => $meta)
                <button type="button" class="rb-set-opt{{ $prefs['theme'] === $key ? ' is-on' : '' }}"
                        data-key="theme" data-value="{{ $key }}">{{ $meta['icon'] }} {{ $t('theme.'.$key) }}</button>
            @endforeach
        </div>
    </section>

    {{-- 不透明度 --}}
    <section class="rb-set-block">
        <h2 class="rb-set-head">{{ $t('settings.opacity') }}</h2>
        <p class="rb-hint">{{ $t('settings.opacityHint') }}</p>
        <div class="rb-set-row">
            <input class="rb-op-range rb-set-opacity" type="range" min="15" max="95" step="1"
                   value="{{ $cardAlphaPct }}" data-key="cardAlpha"
                   aria-label="{{ $t('settings.opacity') }}">
            <span class="rb-set-val" data-role="alpha-value">{{ $cardAlphaPct }}%</span>
        </div>
    </section>

    {{-- 一周起始 --}}
    <section class="rb-set-block">
        <h2 class="rb-set-head">{{ $t('settings.weekStart') }}</h2>
        <p class="rb-hint">{{ $t('settings.weekStartHint') }}</p>
        <div class="rb-set-opts" data-setting="weekStart">
            <button type="button" class="rb-set-opt{{ $prefs['weekStart'] === 'mon' ? ' is-on' : '' }}"
                    data-key="weekStart" data-value="mon">{{ $t('settings.weekMon') }}</button>
            <button type="button" class="rb-set-opt{{ $prefs['weekStart'] === 'sun' ? ' is-on' : '' }}"
                    data-key="weekStart" data-value="sun">{{ $t('settings.weekSun') }}</button>
        </div>
    </section>

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
