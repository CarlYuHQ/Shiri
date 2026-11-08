/* 桌面浮层悬浮详情卡。
   浮层窗口是 .nonactivatingPanel：macOS 不会给非激活面板弹原生 title tooltip，
   所以不能只靠 title 属性，必须自己画一张卡片。
   用 document 上的事件委托，条目被勾选 / 增删后不用重新绑定。 */
(function () {
    var tip = null;
    var current = null;

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

    function target(e) {
        if (!e.target || !e.target.closest) { return null; }
        return e.target.closest('.rb-w-chip, .rb-row, .rb-w-ms-item-v, .rb-w-ms-item');
    }

    document.addEventListener('mouseover', function (e) {
        var el = target(e);
        if (!el || el === current) { return; }
        show(el);
    });

    document.addEventListener('mouseout', function (e) {
        var el = target(e);
        if (!el) { return; }
        // 在同一个条目内部移动不算离开
        if (e.relatedTarget && el.contains(e.relatedTarget)) { return; }
        hide();
    });

    document.addEventListener('mousedown', hide);
    window.addEventListener('blur', hide);
    window.addEventListener('scroll', hide, true);

    // 浮层是 nonactivatingPanel，永远不会成为 key window：原生层把鼠标坐标
    // 推过来后自己命中测试，不依赖 WebKit 的 hover 状态机。
    function pointerAt(x, y) {
        var el = document.elementFromPoint(x, y);
        var item = el && el.closest
            ? el.closest('.rb-w-chip, .rb-row, .rb-w-ms-item-v, .rb-w-ms-item')
            : null;
        if (!item) { if (current) { hide(); } return; }
        if (item === current) { return; }
        show(item);
    }

    window.__shiriPointerAt = pointerAt;
    window.__shiriPointerLeave = hide;

    document.addEventListener('mousemove', function (e) {
        pointerAt(e.clientX, e.clientY);
    }, { passive: true });
})();
