<!DOCTYPE html>
<html dir="ltr" lang="{{ \Shiri\Domain\Calendar\Support\Lang::current() === 'en' ? 'en' : 'zh-CN' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \Shiri\Domain\Calendar\Support\Lang::t('app.name') }}</title>
    {{-- 桌面浮层专用布局：没有顶栏、侧栏、页脚，也不引入整站 CSS，
         整页只有一张半透明卡片（背景透明由 window 的 isOpaque=false 配合）。 --}}
{{-- 资源版本用文件修改时间：改完样式/脚本立刻生效，不用手动改 ?v=，
     也避免 WebView 拿旧 CSS 配新 HTML（7 列网格塌成一列就是这么来的）。 --}}
@php
    $ltV = static function (string $p): int {
        $f = APP_ROOT.'/public'.$p;
        return is_file($f) ? (int) filemtime($f) : 1;
    };
@endphp
    <link rel="stylesheet" href="{{ BASE_URL }}/dist/css/shiri-board.css?v={{ $ltV('/dist/css/shiri-board.css') }}">
    <link rel="stylesheet" href="{{ BASE_URL }}/dist/css/shiri-widget.css?v={{ $ltV('/dist/css/shiri-widget.css') }}">
</head>
<body class="rb-widget-body" data-api="{{ BASE_URL }}/api/jsonrpc">
    @yield('content')
    {{-- 文案字典交给脚本（Lang::flat()）：JS 里不再各写一份中文字符串 --}}
    <script>window.RB_LANG = {!! json_encode(\Shiri\Domain\Calendar\Support\Lang::flat(), JSON_UNESCAPED_UNICODE) !!};</script>
    <script src="{{ BASE_URL }}/dist/js/shiri-board.js?v={{ $ltV('/dist/js/shiri-board.js') }}"></script>
    <script>
        // 卡片上的「今天」、今天的日程、本月网格全是服务端渲染的，页面开着不动就永远停在
        // 打开那天（实测：前一天下午开机，第二天上午卡片上还是「今天 · 11月8日 周日」）。
        // 跨天（含睡眠醒来跨天）就整页重载一次。
        //
        // 日期用「渲染时的用户时区偏移」算，不用浏览器本地时区：万一系统时区和
        // Shiri 里的时区设置不一致，前者会天天对不上，那就变成每 30 秒重载一次的死循环。
        (function () {
            var card = document.querySelector('.rb-widget');
            if (!card || !card.dataset.today) {
                return;
            }

            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            var offset = parseInt(card.dataset.tzOffset || '0', 10);

            var userDate = function () {
                var d = new Date(Date.now() + offset * 1000);
                return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate());
            };

            var check = function () {
                if (userDate() !== card.dataset.today) {
                    location.reload();
                }
            };

            setInterval(check, 30000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) { check(); }
            });
            window.addEventListener('focus', check);
        })();
    </script>
</body>
</html>
