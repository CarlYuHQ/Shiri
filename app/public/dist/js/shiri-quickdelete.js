/*
 * Shiri 本机补丁：日历条目"一键删除"
 * ---------------------------------------------------------------------------
 * 官方日历要删一条日程/待办：点开条目 → 弹窗里找删除 → 再确认，共四步；
 * 同名条目多的时候尤其烦。本脚本给每个可删除的日历条目加一个悬停出现的
 * 红色 ×（右键同样有效），点一下就确认删除。
 *
 * 类型从条目的链接里判断，不依赖 FullCalendar 内部对象：
 *   事件   ...#/calendar/editEvent/<id>      → RPC Calendar.Calendar.delEvent
 *   待办   ...#/tickets/showTicket/<id>      → RPC Tickets.Tickets.delete
 * 外部日历（ICS 订阅）条目没有这两种链接，不会被加按钮，保持只读。
 *
 * 由 app/Domain/Calendar/Templates/showMyCalendar.blade.php 引入。
 * 升级 Shiri 后需重新加上那张 <script> 标签（本文件不会被覆盖，因为
 * 它不在发行包的文件清单里）。
 */
(function () {
    'use strict';

    if (window.__shiriQuickDelete) {
        return;
    }
    window.__shiriQuickDelete = true;

    var TICKET_RE = /#\/tickets\/showTicket\/(\d+)/;
    var EVENT_RE = /#\/calendar\/editEvent\/(\d+)/;
    var EVENT_SELECTOR = '#calendar .fc-event';
    var MARK_ATTR = 'data-lt-qd';

    // 已删除的条目，key = "ticket:12" / "event:7"。删完把节点摘掉后，翻月或切视图
    // 会让 FullCalendar 用内部缓存重新渲染同一个条目，这里负责再把它按下去。
    var deleted = {};

    // ── 样式（只插一次） ──────────────────────────────────────────────────
    var style = document.createElement('style');
    style.textContent = [
        '#calendar .fc-event .lt-qd{',
        '  position:absolute;top:1px;right:2px;width:14px;height:14px;',
        '  border-radius:50%;background:#c62828;color:#fff;',
        '  font:700 12px/13px -apple-system,Helvetica,Arial,sans-serif;',
        '  text-align:center;cursor:pointer;z-index:20;',
        '  opacity:0;transition:opacity .12s ease-in;',
        '  box-shadow:0 1px 2px rgba(0,0,0,.4);user-select:none;',
        '}',
        '#calendar .fc-event:hover .lt-qd{opacity:1;}',
    ].join('\n');
    document.head.appendChild(style);

    // ── 工具 ──────────────────────────────────────────────────────────────
    function parseTarget(el) {
        var href = el.getAttribute && el.getAttribute('href');
        if (!href) {
            return null;
        }

        var m = href.match(TICKET_RE);
        if (m) {
            return { type: 'ticket', id: parseInt(m[1], 10) };
        }

        m = href.match(EVENT_RE);
        if (m) {
            return { type: 'event', id: parseInt(m[1], 10) };
        }

        return null;
    }

    function label(el) {
        var t = el.getAttribute('title');
        if (t) {
            return t;
        }

        var node = el.querySelector('.fc-event-title');
        return (node ? node.textContent : el.textContent || '').trim();
    }

    function success(result, type) {
        if (type === 'ticket') {
            // Tickets.delete: true 成功；false / {msg,type:'error'} 失败
            return result === true;
        }

        // Calendar.delEvent: 返回被删 id；false 失败
        return result !== false && result !== null && result !== undefined;
    }

    function removeEntry(el, ev) {
        var target = parseTarget(el);
        var name = label(el);

        var message = target.type === 'ticket'
            ? '删除待办「' + name + '」？\n\n待办会从项目里彻底删除，不能撤销。'
            : '删除日程「' + name + '」？\n\n此操作不能撤销。';

        if (!window.confirm(message)) {
            return;
        }

        el.style.opacity = '0.45';

        var method = target.type === 'ticket'
            ? 'Tickets.Tickets.delete'
            : 'Calendar.Calendar.delEvent';

        window.shiri.rpc(method, { id: target.id }).then(function (result) {
            if (!success(result, target.type)) {
                el.style.opacity = '';
                var reason = (result && result.msg)
                    ? window.shiri.i18n.__(result.msg)
                    : '删除失败，可能没有权限。';
                window.jQuery.growl({ message: reason, style: 'error' });
                return;
            }

            if (ev && typeof ev.remove === 'function') {
                ev.remove();
            } else {
                deleted[target.type + ':' + target.id] = true;
                el.remove();
            }

            window.jQuery.growl({ message: '已删除：' + name, style: 'success' });
        }).catch(function (error) {
            el.style.opacity = '';
            window.jQuery.growl({
                message: (error && error.message) ? error.message : '删除失败',
                style: 'error'
            });
        });
    }

    // ── 挂按钮 ────────────────────────────────────────────────────────────
    function decorate(el) {
        if (el.getAttribute(MARK_ATTR)) {
            return;
        }

        var target = parseTarget(el);
        if (!target) {
            return;   // 外部日历 / 只读条目
        }

        el.setAttribute(MARK_ATTR, '1');

        if (window.getComputedStyle(el).position === 'static') {
            el.style.position = 'relative';
        }

        var btn = document.createElement('span');
        btn.className = 'lt-qd';
        btn.textContent = '×';
        btn.setAttribute('title', '删除');
        btn.setAttribute('role', 'button');

        btn.addEventListener('mousedown', function (e) {
            e.stopPropagation();       // 别把 FullCalendar 的拖拽带起来
        });

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            removeEntry(el, null);
        });

        el.addEventListener('contextmenu', function (e) {
            e.preventDefault();
            e.stopPropagation();
            removeEntry(el, null);
        });

        el.appendChild(btn);
    }

    function scan() {
        var nodes = document.querySelectorAll(EVENT_SELECTOR);

        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            var target = parseTarget(el);

            if (target && deleted[target.type + ':' + target.id]) {
                el.remove();        // 重新渲染出来的已删条目
                continue;
            }

            if (!el.getAttribute(MARK_ATTR)) {
                decorate(el);
            }
        }
    }

    // 视图切换 / 翻月都会重建 DOM，用 observer 持续补挂。
    // 用 setTimeout 而不是 requestAnimationFrame：窗口不可见时 rAF 会被节流，
    // 而 DOM 变化本身不会因此减少。
    var pending = false;
    function schedule() {
        if (pending) {
            return;
        }
        pending = true;
        window.setTimeout(function () {
            pending = false;
            scan();
        }, 30);
    }

    new MutationObserver(schedule).observe(document.documentElement, {
        childList: true,
        subtree: true
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', schedule);
    } else {
        schedule();
    }
})();
