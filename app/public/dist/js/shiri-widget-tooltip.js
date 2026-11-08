/* 桌面浮层悬浮详情卡。
   浮层是 nonactivatingPanel：macOS 不给非激活面板弹原生 title tooltip，
   WKWebView 在 App 不在前台时也不派发 mouseover。所以悬浮完全由原生层
   注入鼠标坐标驱动，页面只做命中测试。
   单一状态机：不再叠加 mouseover / mousemove / mouseout 多条路径，
   否则它们会互相 hide→show，透明浮层反复重绘看起来就是「窗口抖动」。 */
(function () {
    var tip = null;
    var current = null;
    var lastX = -1, lastY = -1;
    var SELECTOR = '.rb-w-chip, .rb-row, .rb-w-ms-item-v, .rb-w-ms-item';

    function ensure() {
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'rb-w-tip';
            tip.setAttribute('role', 'tooltip');
            document.body.appendChild(tip);
        }
        return tip;
    }

    function hide() {
        current = null;
        if (tip) { tip.classList.remove('is-on'); }
    }

    function show(el) {
        var raw = (el.getAttribute('title') || '').trim();
        if (!raw) { hide(); return; }

        // 条目的 title 形如「19:00 与黄老师讨论」；全天条目没有时间前缀
        var m = raw.match(/^(\d{1,2}:\d{2})\s+(.*)$/);
        var time = m ? m[1] : '';
        var title = m ? m[2] : raw;
        var desc = (el.getAttribute('data-tip-desc') || '').trim();

        var t = ensure();
        t.textContent = '';

        var h = document.createElement('div');
        h.className = 'rb-w-tip-title';
        h.textContent = title;
        t.appendChild(h);

        if (time) {
            var w = document.createElement('div');
            w.className = 'rb-w-tip-when';
            w.textContent = time;
            t.appendChild(w);
        }

        if (desc) {
            var d = document.createElement('div');
            d.className = 'rb-w-tip-desc';
            d.textContent = desc;
            t.appendChild(d);
        }

        var r = el.getBoundingClientRect();
        t.classList.add('is-on');
        var width = t.offsetWidth, height = t.offsetHeight;
        var left = Math.min(Math.max(8, r.left), window.innerWidth - width - 8);
        var top = r.top - height - 8;
        if (top < 8) { top = Math.min(r.bottom + 8, window.innerHeight - height - 8); }
        t.style.left = Math.max(8, left) + 'px';
        t.style.top = Math.max(8, top) + 'px';
        current = el;
    }

    function pointerAt(x, y) {
        // 同坐标不重复命中：静止时原生层已停止注入，这里再挡一层
        if (x === lastX && y === lastY) { return; }
        lastX = x; lastY = y;

        var el = document.elementFromPoint(x, y);
        var item = el && el.closest ? el.closest(SELECTOR) : null;
        if (!item) { if (current) { hide(); } return; }
        if (item === current) { return; }
        show(item);
    }

    window.__shiriPointerAt = pointerAt;
    window.__shiriPointerLeave = function () { lastX = -1; lastY = -1; hide(); };

    // 点击 / 失焦时清理，不参与常规 hover 路径
    document.addEventListener('mousedown', hide);
    window.addEventListener('blur', function () { lastX = -1; lastY = -1; hide(); });
})();
