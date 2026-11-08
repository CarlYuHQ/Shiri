@extends($layout)

@php
    $t = static fn (string $k, array $r = []) => \Shiri\Domain\Calendar\Support\Lang::t($k, $r);
    $isEn = \Shiri\Domain\Calendar\Support\Lang::isEnglish();
    $fmtDate = static function ($d, bool $withYear = false) use ($isEn) {
        return $isEn
            ? $d->format($withYear ? 'M j, Y' : 'M j')
            : $d->format($withYear ? 'Y年n月j日' : 'n月j日');
    };
@endphp

@section('content')

{{-- 统计：四个数字 + 近 7 天完成条。不做图表库，全部用 div 高度画。 --}}

<div class="rb-page rb-stats">

    <header class="rb-page-head">
        <div>
            <h1 class="rb-page-title">{{ $t('stats.title') }}</h1>
            <p class="rb-page-sub">{{ $t('stats.asOf', ['date' => $fmtDate(dtHelper()->userNow())]) }}</p>
        </div>
    </header>

    <div class="rb-metrics">
        <div class="rb-metric">
            <span class="rb-metric-num">{{ $stats['open'] }}</span>
            <span class="rb-metric-label">{{ $t('stats.open') }}</span>
        </div>
        <div class="rb-metric{{ $stats['overdue'] > 0 ? ' is-neg' : '' }}">
            <span class="rb-metric-num">{{ $stats['overdue'] }}</span>
            <span class="rb-metric-label">{{ $t('stats.overdue') }}</span>
        </div>
        <div class="rb-metric">
            <span class="rb-metric-num">{{ $stats['today'] }}</span>
            <span class="rb-metric-label">{{ $t('stats.todayDue') }}</span>
        </div>
        <div class="rb-metric">
            <span class="rb-metric-num">{{ $stats['doneThisWeek'] }}</span>
            <span class="rb-metric-label">{{ $t('stats.doneWeek') }}</span>
        </div>
        <div class="rb-metric">
            <span class="rb-metric-num">{{ $stats['doneThisMonth'] }}</span>
            <span class="rb-metric-label">{{ $t('stats.doneMonth') }}</span>
        </div>
        <div class="rb-metric">
            <span class="rb-metric-num">{{ $stats['done'] }}</span>
            <span class="rb-metric-label">{{ $t('stats.doneTotal') }}</span>
        </div>
    </div>

    <section class="rb-card">
        <h2 class="rb-card-head">{{ $t('stats.recent') }}</h2>
        <div class="rb-bars">
            @foreach ($stats['recent'] as $day => $count)
                @php
                    $date = \Carbon\CarbonImmutable::parse($day);
                    $h = $count === 0 ? 0 : max(6, (int) round($count / $stats['recentMax'] * 100));
                    // 周日 = 7，与我们 week.N 的编号对齐
                    $weekday = $t('week.'.($date->dayOfWeek === 0 ? 7 : $date->dayOfWeek));
                @endphp
                <div class="rb-bar-col{{ $day === dtHelper()->userNow()->format('Y-m-d') ? ' is-today' : '' }}"
                     title="{{ $t('stats.barTitle', ['date' => $fmtDate($date), 'n' => $count]) }}">
                    <span class="rb-bar-val">{{ $count ?: '' }}</span>
                    <span class="rb-bar-track"><span class="rb-bar{{ $count === 0 ? ' is-zero' : '' }}" style="height:{{ $h }}%"></span></span>
                    <span class="rb-bar-label">{{ $weekday }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rb-card">
        <h2 class="rb-card-head">{{ $t('stats.dist') }}</h2>
        <dl class="rb-kv">
            <div><dt>{{ $t('stats.thisWeek') }}</dt><dd>{{ $stats['thisWeek'] }}</dd></div>
            <div><dt>{{ $t('stats.undated') }}</dt><dd>{{ $stats['undated'] }}</dd></div>
            <div><dt>{{ $t('stats.doneToday') }}</dt><dd>{{ $stats['doneToday'] }}</dd></div>
            <div><dt>{{ $t('stats.total') }}</dt><dd>{{ $stats['total'] }}</dd></div>
        </dl>
        @if ($stats['oldestOpen'])
            <p class="rb-hint">
                {{ $t('stats.oldest') }}<a class="formModal" href="#/tickets/showTicket/{{ $stats['oldestOpen']['id'] }}">{{ $stats['oldestOpen']['title'] }}</a>
                <span class="rb-muted">（{{ $stats['oldestOpen']['dueLabel'] }}）</span>
            </p>
        @endif
    </section>

    <p class="rb-footnote">{{ $t('stats.footnote') }}</p>

</div>

@push('styles')
{{-- 资源版本用文件修改时间：改完样式/脚本立刻生效，不用手动改 ?v=，
     也避免 WebView 拿旧 CSS 配新 HTML（7 列网格塌成一列就是这么来的）。 --}}
@php
    $ltV = static function (string $p): int {
        $f = APP_ROOT.'/public'.$p;
        return is_file($f) ? (int) filemtime($f) : 1;
    };
@endphp
<link rel="stylesheet" href="{{ BASE_URL }}/dist/css/shiri-board.css?v={{ $ltV('/dist/css/shiri-board.css') }}">
@endpush

@endsection
