/* 日历条目悬浮详情卡：格子太窄，标题总被截断，悬停看完整标题 / 时间 / 描述。
   内容一律用 textContent 构建 —— 标题和描述都是用户输入，不能拼 HTML。
   独立文件加载：不要嵌进页面的主 <script> 里，内层 </script> 会提前截断外层脚本。 */
(function () {
    var tip = null;
    var lastShowEl = null;
    var currentEl = null;

    function ensure() {
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'fc-tip';
            tip.setAttribute('role', 'tooltip');
            document.body.appendChild(tip);
        }
        return tip;
    }

    function hm(d) {
        return d ? String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') : '';
    }

    function md(d, isEn) {
        if (!d) { return ''; }
        return isEn
            ? d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
            : (d.getMonth() + 1) + '月' + d.getDate() + '日';
    }

    function timeLine(ev, lang, isEn) {
        if (ev.allDay) { return lang['cal.allDay'] || (isEn ? 'All day' : '全天'); }

        var s = ev.start, e = ev.end;
        if (!s) { return ''; }

        if (!e || s.toDateString() === e.toDateString()) {
            var b = e && hm(e) !== hm(s) ? '–' + hm(e) : '';
            return md(s, isEn) + ' ' + hm(s) + b;
        }
        return md(s, isEn) + ' ' + hm(s) + ' – ' + md(e, isEn) + ' ' + hm(e);
    }

    function show(ev, el) {
        // 每次悬浮时再读字典：脚本即使早于 window.RB_LANG 注入也不会拿到空对象
        var lang = window.RB_LANG || {};
        var isEn = lang.__locale === 'en';

        var t = ensure();
        t.textContent = '';

        var title = document.createElement('div');
        title.className = 'fc-tip-title';
        title.textContent = ev.title || '';
        t.appendChild(title);

        var when = (ev.extendedProps && ev.extendedProps.whenText) || timeLine(ev, lang, isEn);
        if (when) {
            var line = document.createElement('div');
            line.className = 'fc-tip-when';
            line.textContent = when;
            t.appendChild(line);
        }

        // description 是事件对象顶层自定义字段，FullCalendar 会收进 extendedProps
        var desc = String((ev.extendedProps && ev.extendedProps.description) || '').trim();
        if (desc) {
            var d = document.createElement('div');
            d.className = 'fc-tip-desc';
            d.textContent = desc;
            t.appendChild(d);
        }

        var r = el.getBoundingClientRect();
        t.classList.add('is-on');
        var w = t.offsetWidth, h = t.offsetHeight;
        var left = Math.min(Math.max(8, r.left + r.width / 2 - w / 2), window.innerWidth - w - 8);
        var top = r.top - h - 8;
        if (top < 8) { top = r.bottom + 8; }
        t.style.left = left + 'px';
        t.style.top = top + 'px';
        lastShowEl = el;
    }

    function hide() {
        currentEl = null;
        if (tip) { tip.classList.remove('is-on'); }
    }

    window.__fcTipShow = show;
    window.__fcTipHide = hide;

    // 兜底：不依赖 FullCalendar 的 eventMouseEnter 回调。直接从 DOM 读标题 / 时间，
    // 描述按标题从页面注入的事件数组里查。冒泡到 document 时 FullCalendar 的回调
    // 通常已经画过完整卡片了，那种情况下不要重复绘制。
    function descFor(title) {
        var list = (window.events && window.events.events) || [];
        for (var i = 0; i < list.length; i++) {
            if (list[i].title === title) { return list[i].description || ''; }
        }
        return '';
    }

    function showFromDom(el) {
        var titleEl = el.querySelector('.fc-event-title');
        var timeEl = el.querySelector('.fc-event-time');
        var title = titleEl ? titleEl.textContent.trim() : (el.textContent || '').trim();
        if (!title) { return; }
        show({
            title: title,
            allDay: false,
            start: null,
            extendedProps: {
                description: descFor(title),
                whenText: timeEl ? timeEl.textContent.trim() : ''
            }
        }, el);
    }

    document.addEventListener('mouseover', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('.fc-event') : null;
        if (!el || el === currentEl) { return; }
        if (el === lastShowEl && tip && tip.classList.contains('is-on')) { return; }
        currentEl = el;
        showFromDom(el);
    });

    document.addEventListener('mouseout', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('.fc-event') : null;
        if (!el) { return; }
        if (e.relatedTarget && el.contains(e.relatedTarget)) { return; }
        hide();
    });

    document.addEventListener('mousedown', hide);
})();
