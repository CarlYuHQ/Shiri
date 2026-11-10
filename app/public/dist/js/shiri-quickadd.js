/*
 * 日历内快速新建日程（本机）
 * ---------------------------------------------------------------------------
 * 需求：点日历上任意一天就能加事，不用先点「新增事件」再选日期。
 *
 * 交互：点某天的空白处 → 就地弹出一个小输入条 → 写标题 → 回车保存（Esc 取消，点别处关闭）。
 * 保存走 JSON-RPC Calendar.Calendar.addEvent，成功后调用 FullCalendar 的 addEvent()
 * 把条目挂进它自己的 event store（不整页刷新）。条目带 editEvent 链接，所以
 * shiri-quickdelete.js 会自动给它挂上删除按钮。
 */
(function () {
    'use strict';

    if (window.__shiriQuickAdd) {
        return;
    }
    window.__shiriQuickAdd = true;

    var pop = null;

    // ── 样式（只插一次） ──────────────────────────────────────────────────
    var style = document.createElement('style');
    style.textContent = [
        '.lt-qa{position:fixed;z-index:4000;width:268px;background:#fff;border:1px solid #e3e0db;',
        '  border-radius:10px;box-shadow:0 10px 26px rgba(0,0,0,.13);padding:10px;',
        '  font:13px/1.4 -apple-system,BlinkMacSystemFont,"PingFang SC",Helvetica,sans-serif;color:#22201d;}',
        '.lt-qa-date{font-size:11px;color:#8b8781;margin-bottom:6px;letter-spacing:.02em;}',
        '.lt-qa .lt-qa-title{width:100%;box-sizing:border-box;border:1px solid #e3e0db;border-radius:7px;',
        '  padding:6px 8px;font-size:13px;outline:none;background:#fff;}',
        '.lt-qa-title:focus{border-color:#b9b3aa;}',
        '.lt-qa-row{display:flex;align-items:center;gap:8px;margin-top:7px;}',
        '.lt-qa .lt-qa-time{border:1px solid #e3e0db;border-radius:7px;padding:4px 6px;font-size:12px;',
        '  background:#fff;color:inherit;outline:none;flex:0 0 auto;',
        '  width:98px !important;min-width:0 !important;max-width:98px !important;}',
        '.lt-qa-allday{display:flex;align-items:center;gap:3px;font-size:12px;color:#6d6862;',
        '  cursor:pointer;flex:0 0 auto;white-space:nowrap;}',
        '.lt-qa .lt-qa-save{margin-left:auto;background:#22201d;color:#fff;border:0;border-radius:7px;',
        '  padding:6px 14px;font-size:12px;cursor:pointer;flex:0 0 auto;white-space:nowrap;}',
        '.lt-qa-save:hover{opacity:.85;}',
        '.lt-qa-hint{margin-top:6px;font-size:11px;color:#a9a49d;}',
        '.lt-qa-time:disabled{opacity:.4;}',
    ].join('\n');
    document.head.appendChild(style);

    // ── 工具 ──────────────────────────────────────────────────────────────
    function cellOf(el) {
        while (el && el !== document.body) {
            if (el.classList && el.classList.contains('fc-daygrid-day')) {
                return el;
            }
            el = el.parentElement;
        }
        return null;
    }

    function dateLabel(dateStr) {
        var parts = dateStr.split('-');
        return parts[1] + '月' + parts[2] + '日';
    }

    function plusHour(t) {
        var m = /^(\d{1,2}):(\d{2})$/.exec(t || '');
        var h = m ? parseInt(m[1], 10) : 9;
        var mm = m ? m[2] : '00';
        h = (h + 1) % 24;
        return (h < 10 ? '0' + h : h) + ':' + mm;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function close() {
        if (pop) {
            pop.remove();
            pop = null;
        }
        document.removeEventListener('mousedown', onOutside, true);
        document.removeEventListener('keydown', onKey, true);
    }

    function onOutside(e) {
        if (pop && !pop.contains(e.target)) {
            close();
        }
    }

    function onKey(e) {
        if (!pop) {
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            close();
        }
    }

    // 保存成功后把条目直接插进格子（不刷新页面）
    //
    // 优先交给 FullCalendar：addEvent() 进的是它自己的 event store，条目由它渲染，
    // 圆点/时间/标题的排版、配色、时间格式全都和服务器渲染的那些一模一样，切月份
    // 重绘也不会丢。只有拿不到 calendar 实例时才退化成手搓 DOM（下面那个 fallback）。
    function addEvent(id, title, dateStr, allDay, timeStr) {
        var cal = window.shiriCalendar;
        if (cal && typeof cal.addEvent === 'function') {
            // 链接格式照抄模板里的 CURRENT_URL#/… ：当前页 + hash，只换 hash 不会整页跳。
            // 点条目开编辑弹窗、悬停删除按钮（按 href 里的 id 认条目，见
            // shiri-quickdelete.js）都靠它。
            var pageBase = location.href.split('#')[0];

            cal.addEvent({
                id: String(id),
                title: title,
                start: allDay ? dateStr : dateStr + 'T' + timeStr,
                end: allDay ? dateStr : dateStr + 'T' + plusHour(timeStr),
                allDay: !!allDay,
                url: pageBase + '#/calendar/editEvent/' + id,
                // 和服务端给默认色条目的一致：不选颜色就用主题强调色
                backgroundColor: 'var(--accent1)',
                borderColor: 'var(--accent1)'
            });

            return true;
        }

        return insertChipFallback(dateStr, id, title, allDay, timeStr);
    }

    function insertChipFallback(dateStr, id, title, allDay, timeStr) {
        var cell = document.querySelector('.fc-daygrid-day[data-date="' + dateStr + '"]');
        if (!cell) {
            return false;
        }
        var holder = cell.querySelector('.fc-daygrid-day-events');
        if (!holder) {
            return false;
        }

        var base = (window.shiri && window.shiri.appUrl ? window.shiri.appUrl : '').replace(/\/$/, '');
        var accent = getComputedStyle(document.documentElement).getPropertyValue('--accent1').trim() || '#894329';

        var a = document.createElement('a');
        a.className = 'fc-event fc-event-draggable fc-event-start fc-event-end ' +
            (allDay ? 'fc-daygrid-event fc-daygrid-block-event fc-h-event' : 'fc-daygrid-event fc-daygrid-dot-event');
        a.setAttribute('href', base + '/calendar/showMyCalendar#/calendar/editEvent/' + id);

        if (allDay) {
            a.style.display = 'block';
            a.style.backgroundColor = accent;
            a.style.borderColor = accent;
            a.style.color = '#fff';
            a.innerHTML = '<div class="fc-event-main"><div class="fc-event-main-frame">' +
                '<div class="fc-event-title-container"><div class="fc-event-title fc-sticky">' +
                escapeHtml(title) + '</div></div></div></div>';
        } else {
            // 不要设 display：.fc-daygrid-dot-event 的 flex 布局就是「圆点 + 时间 + 标题
            // 排一行」的来源，改成 block 会把三者各撑成一行（只剩圆点和标题两行可见）。
            a.innerHTML = '<div class="fc-daygrid-event-dot" style="border-color:' + accent + '"></div>' +
                '<div class="fc-event-time">' + escapeHtml(timeStr) + '</div>' +
                '<div class="fc-event-title">' + escapeHtml(title) + '</div>';
        }

        holder.appendChild(a);
        return true;
    }

    // ── 弹出输入条 ────────────────────────────────────────────────────────
    function open(cell) {
        close();

        var dateStr = cell.getAttribute('data-date');
        if (!dateStr) {
            return;
        }

        var rect = cell.getBoundingClientRect();

        pop = document.createElement('div');
        pop.className = 'lt-qa';
        pop.innerHTML =
            '<div class="lt-qa-date">' + dateLabel(dateStr) + ' · 新建</div>' +
            '<input class="lt-qa-title" type="text" placeholder="要做什么？" maxlength="120">' +
            '<div class="lt-qa-row">' +
            '  <input class="lt-qa-time" type="time" value="09:00">' +
            '  <label class="lt-qa-allday"><input type="checkbox" class="lt-qa-chk"> 全天</label>' +
            '  <button class="lt-qa-save" type="button">保存</button>' +
            '</div>' +
            '<div class="lt-qa-hint">回车保存 · Esc 取消</div>';

        document.body.appendChild(pop);

        // 贴在被点的格子右上角，超出视口就回收
        var left = Math.min(rect.left + 8, window.innerWidth - 284);
        var top = Math.min(rect.top + 30, window.innerHeight - 170);
        pop.style.left = Math.max(8, left) + 'px';
        pop.style.top = Math.max(8, top) + 'px';

        var title = pop.querySelector('.lt-qa-title');
        var time = pop.querySelector('.lt-qa-time');
        var chk = pop.querySelector('.lt-qa-chk');
        var save = pop.querySelector('.lt-qa-save');

        title.focus();

        chk.addEventListener('change', function () {
            time.disabled = chk.checked;
        });

        function submit() {
            var text = title.value.trim();
            if (text === '') {
                title.focus();
                return;
            }

            var allDay = chk.checked;
            var t = time.value || '09:00';

            var values = { description: text, allDay: allDay };
            if (allDay) {
                values.dateFrom = dateStr + ' 00:00:00';
                values.dateTo = dateStr + ' 23:59:00';
            } else {
                values.dateFrom = dateStr + ' ' + t + ':00';
                values.dateTo = dateStr + ' ' + plusHour(t) + ':00';
            }

            save.disabled = true;
            save.textContent = '保存中';

            window.shiri.rpc('Calendar.Calendar.addEvent', { values: values }).then(function (id) {
                close();
                if (id && typeof id === 'number') {
                    if (!addEvent(id, text, dateStr, allDay, t)) {
                        window.location.reload();
                        return;
                    }
                    window.jQuery.growl({ message: '已添加：' + text, style: 'success' });
                } else {
                    window.jQuery.growl({ message: '保存失败', style: 'error' });
                }
            }).catch(function (err) {
                close();
                window.jQuery.growl({
                    message: (err && err.message) ? err.message : '保存失败',
                    style: 'error'
                });
            });
        }

        save.addEventListener('click', submit);

        title.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            }
        });

        time.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            }
        });

        // 稍后再挂外部点击，避免当前这次点击立刻把它关掉
        setTimeout(function () {
            document.addEventListener('mousedown', onOutside, true);
            document.addEventListener('keydown', onKey, true);
        }, 0);
    }

    // ── 绑定 ──────────────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        if (pop && pop.contains(e.target)) {
            return;
        }
        if (e.target.closest('.fc-event')) {
            return;   // 点到已有条目 → 走原来的打开逻辑
        }
        if (e.target.closest('.fc-daygrid-day-number')) {
            return;   // 日期数字是跳转链接
        }

        var cell = cellOf(e.target);
        if (!cell || !cell.getAttribute('data-date')) {
            return;
        }

        e.preventDefault();
        open(cell);
    });
})();
