{{-- 语言：先在渲染任何内置文案之前把偏好同步给 Shiri 本体，
     否则日历页这类用 __() 的地方会停在上一次的语言上。 --}}
@php \Shiri\Domain\Calendar\Support\Lang::applyToShiri(); @endphp
<!DOCTYPE html>
<html dir="{{ __('language.direction') }}" lang="{{ __('language.code') }}">
<head>
    @include('global::sections.header')
    @stack('styles')
</head>

<body class="" hx-ext="preload" hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>

    @include('global::sections.appAnnouncement')

    <div class="mainwrapper menu{{ session("menuState") ?? "closed" }}">

        @php
            // 极简顶栏：应用名 + 三个页面。项目选择器 / 搜索 / 通知 / 帮助 / 用户菜单都已移除。
            $ltRoute = app('request')->getCurrentRoute();
            $ltNav = [
                ['label' => \Shiri\Domain\Calendar\Support\Lang::t('nav.calendar'), 'href' => BASE_URL.'/calendar/showMyCalendar', 'match' => 'showMyCalendar'],
                ['label' => \Shiri\Domain\Calendar\Support\Lang::t('nav.todos'), 'href' => BASE_URL.'/calendar/showTodos', 'match' => 'showTodos'],
                ['label' => \Shiri\Domain\Calendar\Support\Lang::t('nav.plans'), 'href' => BASE_URL.'/calendar/showPlans', 'match' => 'showPlans'],
                ['label' => \Shiri\Domain\Calendar\Support\Lang::t('nav.stats'), 'href' => BASE_URL.'/calendar/showStats', 'match' => 'showStats'],
                ['label' => \Shiri\Domain\Calendar\Support\Lang::t('nav.settings'), 'href' => BASE_URL.'/calendar/showSettings', 'match' => 'showSettings'],
            ];
        @endphp
        <div class="header lt-topbar">
            <div class="headerinner">
                {{-- 品牌名只是标题：可点的话它就等于「日历」这个 tab，重复且容易误解 --}}
                <div class="lt-brand">
                    <img class="lt-brand-mark" src="{{ BASE_URL }}/dist/img/shiri-mark.svg" alt="" width="18" height="18">
                    <span>{{ \Shiri\Domain\Calendar\Support\Lang::t('app.name') }}</span>
                </div>
                <nav class="lt-nav">
                    @foreach ($ltNav as $item)
                        @php
                            $isActive = str_contains($ltRoute, $item['match'])
                                || ($item['match'] === 'showMyCalendar'
                                    && str_starts_with($ltRoute, 'calendar.')
                                    && ! str_contains($ltRoute, 'showTodos')
                                    && ! str_contains($ltRoute, 'showPlans')
                                    && ! str_contains($ltRoute, 'showSettings')
                                    && ! str_contains($ltRoute, 'showStats'));
                        @endphp
                        <a href="{{ $item['href'] }}" @class(['is-active' => $isActive])>{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            </div>
        </div>

        <div class="overlay" style="position: relative">
            <div class="leftpanel">
                <div class="leftmenu">
                    @include('menu::menu')
                </div><!-- leftmenu -->
            </div>
            <div class="rightpanel {{ $section }}">
                <div class="primaryContent">
                    @include('global::sections.appUrlWarning')
                    @isset($action, $module)
                        @include("$module::$action")
                    @else
                        @yield('content')
                    @endisset
                    <div class="clearfix"></div>
                    @include('global::sections.footer')
                </div>

            </div>

        </div><!-- rightpanel -->

        <div class="menu-backdrop" aria-hidden="true"></div>

    </div><!-- mainwrapper -->

    @include('global::sections.pageBottom')
    {{-- 文案字典交给脚本（Lang::flat()）：JS 里不再各写一份中文字符串 --}}
    <script>window.RB_LANG = {!! json_encode(\Shiri\Domain\Calendar\Support\Lang::flat(), JSON_UNESCAPED_UNICODE) !!};</script>
    {{-- Page init scripts. Kept in a container so the in-place refresh after a modal (modals.js
         softReload) can re-run them without re-running the layout's own scripts. --}}
    <div id="lt-page-scripts">
        @stack('scripts')
    </div>
    @include('help::helpermodal')
</body>

</html>
