@extends($layout)

{{-- 桌面浮层：一张贴在桌面上的迷你月历。
     上面是当月网格（有安排的日子打点、今天实心），下面是今天要做的事。
     背景全透明，拖动由窗口负责，这里只做内容。 --}}

@section('content')

@php
    // 资源版本用文件 mtime：改完悬浮卡脚本立刻生效，不用手动改 ?v=
    $tipV = static function (string $p): int {
        $f = APP_ROOT.'/public'.$p;
        return is_file($f) ? (int) filemtime($f) : 1;
    };
@endphp

<div class="rb-widget" data-theme="{{ $theme }}" data-theme-mode="{{ $themeMode }}"
     style="--rb-card-alpha: {{ number_format($cardAlpha, 2, '.', '') }}"
     {{-- 这一页的「今天」是服务端渲染出来的，不会自己翻页。把渲染时的日期和
          用户时区偏移存下来，脚本据此判断是否已经跨天（见 widget 布局里的脚本）。 --}}
     data-today="{{ $today->format('Y-m-d') }}"
     data-tz-offset="{{ $today->getOffset() }}"
     data-project-id="{{ $defaultProjectId }}">

    <header class="rb-w-head">
        <div class="rb-w-month">
            @if ($isEn)
                <span class="rb-w-mtitle">{{ $monthTitle }}</span>
            @else
                <span class="rb-w-year">{{ $yearLabel }}</span><span class="rb-w-munit">年</span>
                <span class="rb-w-mnum">{{ $monthLabel }}</span><span class="rb-w-munit">月</span>
            @endif
        </div>
        <div class="rb-w-actions">
            @if ($overdueCount > 0)
                <span class="rb-w-badge">{{ str_replace('{n}', $overdueCount, $lt['overdue']) }}</span>
            @endif
            {{-- 时段：清晨 / 白天 / 黄昏 / 夜晚（颜色跟着变，图标只是提示） --}}
            <span class="rb-w-phase" title="{{ $themeLabel }}">{{ $themeIcon }}</span>
            {{-- 不透明度：内联细滑杆，只调卡片玻璃底，文字始终实心 --}}
            <input class="rb-op-range" type="range" min="15" max="95" step="1"
                   value="{{ (int) round($cardAlpha * 100) }}"
                   title="{{ $lt['opacity'] }}" aria-label="{{ $lt['opacity'] }}">
            <a class="rb-w-icon" href="shiri://full" title="{{ $lt['open'] }}">↗</a>
        </div>
    </header>

    <div class="rb-w-stage">
        <div class="rb-w-main">
            <div class="rb-w-cal">
        <div class="rb-w-dow">
            @php
                $allDow = \Shiri\Domain\Calendar\Support\Lang::isEnglish()
                    ? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
                    : ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
                $dowLabels = ($weekStart ?? 'sun') === 'mon'
                    ? array_merge(array_slice($allDow, 1), array_slice($allDow, 0, 1))
                    : $allDow;
            @endphp
            @foreach ($dowLabels as $dow)
                <span>{{ $dow }}</span>
            @endforeach
        </div>

        @foreach ($grid as $week)
            <div class="rb-w-week">
                @foreach ($week as $day)
                    <div class="rb-w-day{{ $day['inMonth'] ? '' : ' is-out' }}{{ $day['isToday'] ? ' is-today' : '' }}{{ $day['weekend'] ? ' is-weekend' : '' }}"
                         title="{{ \Carbon\CarbonImmutable::parse($day['key'])->format($isEn ? 'M j' : 'n月j日') }}">
                        <span class="rb-w-daynum">{{ $day['day'] }}</span>

                        @if (!empty($day['items']))
                            {{-- 大卡：直接把当天的事写进格子（窄屏由 CSS 换成下面那排圆点） --}}
                            <span class="rb-w-chips">
                                @foreach ($day['items'] as $item)
                                    <em class="rb-w-chip{{ !empty($item['color']) ? ' has-color' : '' }}"
                                        @if (!empty($item['color'])) style="--chip: {{ $item['color'] }}" @endif
                                        title="{{ trim($item['time'].' '.$item['title']) }}" data-tip-desc="{{ $item['description'] ?? '' }}">
                                        @if ($item['time'] !== '')
                                            <b>{{ $item['time'] }}</b>
                                        @endif
                                        {{ $item['title'] }}
                                    </em>
                                @endforeach
                                @if ($day['more'] > 0)
                                    <em class="rb-w-chip is-more">+{{ $day['more'] }}</em>
                                @endif
                            </span>
                        @endif

                        @if ($day['dots'] > 0)
                            <span class="rb-w-dots">
                                @for ($i = 0; $i < $day['dots']; $i++)
                                    <i></i>
                                @endfor
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- 重要节点：未来最近的三个，横排一行；没有就不渲染这行 --}}
    @if (count($msItems) > 0)
        <div class="rb-w-ms">
            <span class="rb-w-ms-label">{{ $lt['msTitle'] }}</span>
            <span class="rb-w-ms-items">
                @foreach ($msItems as $m)
                    <span class="rb-w-ms-item" title="{{ $m['dateLabel'] }}">
                        <em>{{ $m['title'] }}</em><b>{{ $m['num'] }}</b><i>{{ $lt['msUnit'] }}</i>
                    </span>
                @endforeach
            </span>
        </div>
    @endif

    <div class="rb-w-today">
        <div class="rb-w-today-head">
            <span>{{ str_replace('{date}', $todayLabel, $lt['today']) }}</span>
            <a class="rb-w-plus" href="shiri://add" title="{{ $lt['add'] }}">＋</a>
        </div>

        @if ($todayCount === 0)
            <p class="rb-w-empty">{{ $lt['empty'] }}</p>
        @else
            <ul class="rb-list rb-w-list">
                @foreach ($todayItems as $item)
                    @if ($item['id'] > 0)
                        <li class="rb-row" data-id="{{ $item['id'] }}" data-bucket="today"
                            data-open-bucket="{{ $item['openBucket'] }}" data-done="0"
                            title="{{ trim($item['time'].' '.$item['title']) }}" data-tip-desc="{{ $item['description'] ?? '' }}">
                            <button class="rb-check" type="button" role="checkbox"
                                    aria-checked="false" aria-label="{{ $lt['markDone'] }}"></button>
                            <span class="rb-row-title">{{ $item['title'] }}</span>
                            @if ($item['time'] !== '')
                                <span class="rb-row-due">{{ $item['time'] }}</span>
                            @endif
                        </li>
                    @else
                        <li class="rb-row is-event" title="{{ trim($item['time'].' '.$item['title']) }}" data-tip-desc="{{ $item['description'] ?? '' }}">
                            <span class="rb-evdot" @if (!empty($item['color'])) style="--dot: {{ $item['color'] }}" @endif></span>
                            <span class="rb-row-title">{{ $item['title'] }}</span>
                            @if ($item['time'] !== '')
                                <span class="rb-row-due">{{ $item['time'] }}</span>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>

            @if ($moreCount > 0)
                <a class="rb-w-more" href="shiri://full">{{ str_replace('{n}', $moreCount, $lt['more']) }}</a>
            @endif
        @endif
    </div>

        </div>

        {{-- 右侧栏：和主窗口日历页一致的三组节点，大卡（≥820px）显示 --}}
        <aside class="rb-w-ms-side">
            <div class="rb-w-ms-side-head">
                <span>{{ $lt['msTitle'] }}</span>
                <span class="rb-w-ms-side-count">{{ $msCount }}</span>
            </div>
            <div class="rb-w-ms-side-list">
                @php
                    $msLabels = ['soon' => $lt['msSoon'], 'far' => $lt['msFar'], 'past' => $lt['msPast']];
                @endphp
                @foreach ($msLabels as $bucket => $label)
                    @if (count($msGroups[$bucket]) > 0)
                        <div class="rb-w-ms-group rb-w-ms-group--{{ $bucket }}">
                            <div class="rb-w-ms-group-label">{{ $label }}</div>
                            @foreach ($msGroups[$bucket] as $m)
                                <div class="rb-w-ms-item-v" title="{{ $m['dateLabel'] }}">
                                    <div class="rb-w-ms-v-text">
                                        <div class="rb-w-ms-v-title">{{ $m['title'] }}</div>
                                        <div class="rb-w-ms-v-date">{{ $m['dateLabel'] }}</div>
                                    </div>
                                    <div class="rb-w-ms-v-count">
                                        @if ($m['num'] !== '')
                                            <b>{{ $m['num'] }}</b><i>{{ $lt['msUnit'] }}</i>
                                        @else
                                            <span class="rb-w-ms-v-today">{{ $lt['msToday'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach

                @if ($msCount === 0)
                    <p class="rb-w-ms-empty">{{ $lt['msEmpty'] }}</p>
                @endif
            </div>
        </aside>
    </div>

</div>

{{-- 悬浮详情卡：非激活面板不会弹原生 title tooltip，这里自己画一张卡片 --}}
<script src="{{ BASE_URL }}/dist/js/shiri-widget-tooltip.js?v={{ $tipV('/dist/js/shiri-widget-tooltip.js') }}"></script>
@endsection
