@extends($layout)

@php
    $t = static fn (string $k, array $r = []) => \Shiri\Domain\Calendar\Support\Lang::t($k, $r);
@endphp

@section('content')

{{-- 待办清单：日历负责「排在哪天」，这一页负责「还剩哪些」。
     结构上刻意保持三段：标题+筛选、一行快速录入、按 bucket 分组的列表。
     勾选与新增都由 shiri-board.js 走 JSON-RPC，不刷新页面。 --}}

<div class="rb-page rb-board" data-project-id="{{ $defaultProjectId }}">

    <header class="rb-page-head">
        <div>
            <h1 class="rb-page-title">{{ $t('todos.title') }}</h1>
            <p class="rb-page-sub" data-role="summary">
                @if ($openCount === 0 && $doneCount === 0)
                    {{ $t('todos.empty') }}
                @elseif ($openCount === 0)
                    {{ $t('todos.allDone') }}
                @else
                    {{ $t('todos.openCount', ['n' => $openCount]) }}@if ($overdueCount > 0)<span class="rb-dot">·</span><span class="rb-neg">{{ $t('todos.overdueCount', ['n' => $overdueCount]) }}</span>@endif
                @endif
            </p>
        </div>
        <div class="rb-tabs" role="tablist">
            <button type="button" class="rb-tab is-active" data-filter="open" role="tab">{{ $t('todos.tab.open') }}</button>
            <button type="button" class="rb-tab" data-filter="done" role="tab">{{ $t('todos.tab.done') }}</button>
            <button type="button" class="rb-tab" data-filter="all" role="tab">{{ $t('todos.tab.all') }}</button>
        </div>
    </header>

    <form class="rb-quickadd" autocomplete="off">
        <input class="rb-quickadd-text" type="text" placeholder="{{ $t('todos.quickadd') }}" maxlength="200">
        <input class="rb-quickadd-date" type="date" value="{{ dtHelper()->userNow()->format('Y-m-d') }}" title="{{ $t('todos.dueTitle') }}">
        <button class="rb-quickadd-btn" type="submit" title="{{ $t('todos.add') }}">{{ $t('todos.add') }}</button>
    </form>

    <div class="lt-groups">
        @foreach ($groups as $bucket => $group)
            <section class="rb-group" data-bucket="{{ $bucket }}">
                <h2 class="rb-group-head">
                    {{ $bucketLabels[$bucket] }}
                    <span class="rb-group-count">{{ count($group['todos']) }}</span>
                </h2>
                <ul class="rb-list">
                    @foreach ($group['todos'] as $todo)
                        @include('calendar::partials.todoRow', ['todo' => $todo])
                    @endforeach
                </ul>
            </section>
        @endforeach

        @if (empty($groups))
            <p class="rb-empty">{{ $t('todos.emptyHint') }}</p>
        @endif
    </div>

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

@push('scripts')
<script src="{{ BASE_URL }}/dist/js/shiri-board.js?v={{ $ltV('/dist/js/shiri-board.js') }}"></script>
@endpush

@endsection
