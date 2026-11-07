/*
 * 「拾日」待办交互
 * ---------------------------------------------------------------------------
 * 服务三个页面，共用一套标记：
 *   /calendar/showTodos      待办清单（分组 + 筛选 + 快速录入）
 *   /calendar/desktopWidget  桌面浮层小卡（今天的待办）
 *   /calendar/showStats      统计（纯展示，本脚本不介入）
 *
 * 数据一律走 JSON-RPC（和官方前端同一个入口 /api/jsonrpc），不刷新页面：
 *   勾选     Tickets.Tickets.markTicketDone / markTicketReopen
 *   加一条   Tickets.Tickets.quickAddTicket
 *
 * 不依赖 window.shiri（浮层页面根本不加载整站 JS），所以这里自己发请求。
 */
(function () {
    'use strict';

    if (window.__shiriBoard) {
        return;
    }
    window.__shiriBoard = true;

    // ── 文案 ────────────────────────────────────────────────────────────────
    // 模板会先注入 window.RB_LANG（当前语言的 key => 文案，见 Lang::flat()）。
    // 没注入就退回中文兜底，脚本单独跑也不会甩出一串 key。
    var I18N = window.RB_LANG || {};
    var IS_EN = I18N.__locale === 'en';

    function T(key, fallback) {
        var v = I18N[key];
        if (typeof v === 'string' && v !== '') {
            return v;
        }
        return fallback === undefined ? key : fallback;
    }

    /** {n} / {date} 这类占位替换 */
    function F(text, repl) {
        return String(text).replace(/\{(\w+)\}/g, function (m, k) {
            return repl && repl[k] !== undefined ? repl[k] : m;
        });
    }

    // ── RPC ────────────────────────────────────────────────────────────────
    function apiUrl() {
        var explicit = document.body && document.body.dataset ? document.body.dataset.api : '';
        if (explicit) {
            return explicit;
        }
        if (window.shiri && window.shiri.appUrl) {
            return String(window.shiri.appUrl).replace(/\/+$/, '') + '/api/jsonrpc';
        }
        return location.origin + '/api/jsonrpc';
    }

    var nextId = 1;

    function rpc(method, params) {
        return fetch(apiUrl(), {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                jsonrpc: '2.0',
                method: 'shiri.rpc.' + method,
                params: params || {},
                id: nextId++
            })
        }).then(function (res) {
            return res.json();
        }).then(function (payload) {
            if (payload && payload.error) {
                throw new Error(payload.error.message || T('err.request', '请求失败'));
            }
            return payload ? payload.result : null;
        });
    }

    // ── 日期小工具（与服务端 TodoBoard::dueLabel 保持同一口径） ────────────
    function startOfDay(d) {
        return new Date(d.getFullYear(), d.getMonth(), d.getDate());
    }

    function parseDay(iso) {
        if (!iso) {
            return null;
        }
        var parts = iso.split('-').map(Number);
        if (parts.length < 3 || !parts[0]) {
            return null;
        }
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function todayISO() {
        var now = new Date();
        return [
            now.getFullYear(),
            ('0' + (now.getMonth() + 1)).slice(-2),
            ('0' + now.getDate()).slice(-2)
        ].join('-');
    }

    var MONTHS_EN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    function dueLabel(day) {
        if (!day) {
            return T('due.none', '无截止日');
        }
        var diff = Math.round((startOfDay(day) - startOfDay(new Date())) / 86400000);
        if (diff === 0) { return T('due.today', '今天'); }
        if (diff === 1) { return T('due.tomorrow', '明天'); }
        if (diff === 2) { return T('due.dayAfter', '后天'); }
        if (diff === -1) { return T('due.yesterday', '昨天'); }
        if (diff < -1 && diff > -8) { return F(T('due.daysAgo', '{n} 天前'), { n: Math.abs(diff) }); }
        return IS_EN
            ? MONTHS_EN[day.getMonth()] + ' ' + day.getDate()
            : (day.getMonth() + 1) + '月' + day.getDate() + '日';
    }

    /** 周日为一周结束（与服务端 CarbonInterface::SUNDAY 一致）。 */
    function bucketFor(day) {
        if (!day) {
            return 'none';
        }
        var today = startOfDay(new Date());
        if (day < today) {
            return 'overdue';
        }
        if (day.getTime() === today.getTime()) {
            return 'today';
        }
        var daysToSunday = 7 - today.getDay();
        var weekEnd = new Date(today.getTime() + daysToSunday * 86400000);
        return day <= weekEnd ? 'week' : 'later';
    }

    // ── 计数与空状态 ────────────────────────────────────────────────────────
    /** 重新数一遍每组里可见的行，顺手刷新页头的「N 件未完成」。 */
    function refreshCounts(root) {
        var openCount = 0;
        var overdueCount = 0;

        root.querySelectorAll('.rb-row').forEach(function (row) {
            if (row.dataset.done === '1') {
                return;
            }
            openCount++;
            if (row.classList.contains('is-overdue')) {
                overdueCount++;
            }
        });

        var sub = root.querySelector('[data-role="summary"]');
        if (sub) {
            var total = root.querySelectorAll('.rb-row').length;
            if (total === 0) {
                sub.textContent = T('todos.empty', '还没有待办');
            } else if (openCount === 0) {
                sub.textContent = T('todos.allDone', '全部做完了');
            } else if (overdueCount > 0) {
                sub.innerHTML = F(T('todos.openCount', '{n} 件未完成'), { n: openCount })
                    + '<span class="rb-dot">·</span>'
                    + '<span class="rb-neg">' + F(T('todos.overdueCount', '{n} 件逾期'), { n: overdueCount }) + '</span>';
            } else {
                sub.textContent = F(T('todos.openCount', '{n} 件未完成'), { n: openCount });
            }
        }

        root.querySelectorAll('.rb-group').forEach(function (section) {
            var visible = section.querySelectorAll('.rb-row:not(.is-hidden)').length;
            var counter = section.querySelector('.rb-group-count');
            if (counter) {
                counter.textContent = visible;
            }
            section.classList.toggle('is-hidden', visible === 0);
        });
    }

    // ── 勾选完成 / 取消完成 ─────────────────────────────────────────────────
    /** 小卡里没有「已完成」分组，勾掉的条目淡出移走，空了补一句空状态。 */
    function removeFromWidget(row) {
        row.style.transition = 'opacity .18s ease, transform .18s ease';
        row.style.opacity = '0';
        row.style.transform = 'translateX(6px)';

        setTimeout(function () {
            row.remove();
            var widget = document.querySelector('.rb-widget');
            if (!widget) {
                return;
            }
            var remain = widget.querySelectorAll('.rb-row').length;
            var empty = widget.querySelector('.rb-w-empty');
            if (remain === 0 && !empty) {
                var p = document.createElement('p');
                p.className = 'rb-w-empty';
                p.textContent = T('widget.empty', '今天没有安排。');
                var more = widget.querySelector('.rb-w-more');
                widget.insertBefore(p, more || widget.querySelector('.rb-quickadd'));
            }
        }, 200);
    }

    function moveRow(row, done) {
        row.dataset.done = done ? '1' : '0';
        row.classList.toggle('is-done', done);
        row.classList.toggle('is-overdue', !done && row.dataset.openBucket === 'overdue');

        var aria = row.querySelector('.rb-check');
        if (aria) {
            aria.setAttribute('aria-checked', done ? 'true' : 'false');
            aria.setAttribute('aria-label', done ? T('todos.markUndone', '标记为未完成') : T('todos.markDone', '标记为完成'));
        }

        // 换组：已完成的沉到「已完成」，取消的回到按截止日算的那一组
        var target = done ? 'done' : row.dataset.openBucket;
        var section = document.querySelector('.rb-group[data-bucket="' + target + '"]');
        var list = section ? section.querySelector('.rb-list') : null;

        if (!list) {
            if (done) {
                removeFromWidget(row);
            }
            return;
        }

        if (done) {
            list.insertBefore(row, list.firstChild);
        } else {
            list.appendChild(row);
        }
    }

    function toggleRow(row) {
        var id = parseInt(row.dataset.id, 10);
        if (!id || row.classList.contains('is-busy')) {
            return;
        }

        var wasDone = row.dataset.done === '1';
        row.classList.add('is-busy');

        rpc(wasDone ? 'Tickets.Tickets.markTicketReopen' : 'Tickets.Tickets.markTicketDone', { id: id })
            .then(function (ok) {
                row.classList.remove('is-busy');
                if (ok === false) {
                    throw new Error(T('err.rejected', '服务端拒绝修改'));
                }
                moveRow(row, !wasDone);
                refreshCounts(document);
            })
            .catch(function (err) {
                row.classList.remove('is-busy');
                console.warn('[shiri] toggle failed:', err);
            });
    }

    // ── 快速添加 ───────────────────────────────────────────────────────────
    function buildRow(id, title, day) {
        var li = document.createElement('li');
        li.className = 'rb-row';
        li.dataset.id = id;
        li.dataset.done = '0';

        var bucket = bucketFor(day);
        li.dataset.bucket = bucket;
        li.dataset.openBucket = bucket;
        li.classList.toggle('is-overdue', bucket === 'overdue');

        var check = document.createElement('button');
        check.className = 'rb-check';
        check.type = 'button';
        check.setAttribute('role', 'checkbox');
        check.setAttribute('aria-checked', 'false');
        check.setAttribute('aria-label', T('todos.markDone', '标记为完成'));

        var link = document.createElement('a');
        link.className = 'rb-row-title formModal';
        link.href = '#/tickets/showTicket/' + id;
        link.title = title;
        link.textContent = title;

        var due = document.createElement('span');
        due.className = 'rb-row-due';
        due.textContent = dueLabel(day);

        li.appendChild(check);
        li.appendChild(link);
        li.appendChild(due);

        return li;
    }

    function addTodo(form) {
        var root = form.closest('.rb-board, .rb-widget');
        if (!root) {
            return;
        }

        var input = form.querySelector('.rb-quickadd-text');
        var dateInput = form.querySelector('.rb-quickadd-date');
        var title = (input && input.value || '').trim();
        if (!title) {
            return;
        }

        var projectId = parseInt(root.dataset.projectId || '0', 10);
        if (!projectId) {
            console.warn('[shiri] ' + T('err.noProject', '没有可写入的项目，无法新增待办'));
            return;
        }

        var iso = (dateInput && dateInput.value) || todayISO();
        var day = parseDay(iso);

        input.disabled = true;

        rpc('Tickets.Tickets.quickAddTicket', {
            // 这个方法只收一个数组参数，JSON-RPC 要按参数名再包一层
            params: {
                projectId: projectId,
                headline: title,
                dateToFinish: iso + ' 00:00:00',
                type: 'task'
            }
        }).then(function (result) {
            input.disabled = false;

            // 成功后接口回 ticket id；失败会回 {status:'error'} 或 false
            var id = typeof result === 'number' ? result : (result && result.id) || 0;
            if (!id) {
                throw new Error(T('err.add', '新增失败'));
            }

            input.value = '';

            var row = buildRow(id, title, day);
            var bucket = row.dataset.bucket;
            var section = document.querySelector('.rb-group[data-bucket="' + bucket + '"]');
            var list = section && section.querySelector('.rb-list');

            if (list) {
                list.appendChild(row);
                refreshCounts(document);
            } else {
                // 对应的分组还不存在（首次往空列表里加东西），整页刷新最省事
                location.reload();
                return;
            }

            // 小卡里不做「已完成」分组，加完就保持原样
            if (bucket === 'done') {
                location.reload();
            }
        }).catch(function (err) {
            input.disabled = false;
            console.warn('[shiri] add failed:', err);
        });
    }

    // ── 筛选 ───────────────────────────────────────────────────────────────
    function applyFilter(filter) {
        var root = document.querySelector('.rb-board');
        if (!root) {
            return;
        }

        root.querySelectorAll('.rb-row').forEach(function (row) {
            var done = row.dataset.done === '1';
            var show = filter === 'all' || (filter === 'done' ? done : !done);
            row.classList.toggle('is-hidden', !show);
        });

        refreshCounts(root);
    }

    // ── 事件绑定 ───────────────────────────────────────────────────────────
    document.addEventListener('click', function (event) {
        var check = event.target.closest('.rb-check');
        if (check) {
            event.preventDefault();
            toggleRow(check.closest('.rb-row'));
            return;
        }

        var tab = event.target.closest('.rb-tab');
        if (tab) {
            event.preventDefault();
            document.querySelectorAll('.rb-tab').forEach(function (el) {
                el.classList.toggle('is-active', el === tab);
            });
            applyFilter(tab.dataset.filter || 'all');
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('.rb-quickadd');
        if (form) {
            event.preventDefault();
            addTodo(form);
        }
    });

    // 回车即保存（表单里有 input，不加这层单行输入也会提交，这里只是保证焦点不丢）
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }
        var input = event.target.closest('.rb-quickadd-text');
        if (!input) {
            return;
        }
        event.preventDefault();
        var form = input.closest('.rb-quickadd');
        if (form) {
            addTodo(form);
        }
    });


    // ── 卡片不透明度 + 时段主题（桌面浮层） ────────────────────────────────
    // 真值存在服务端（zp_rb_prefs），页面上由模板注入初始值；
    // 拖动滑杆只改 CSS 变量并防抖回写，不再用 localStorage 记第二份。
    var CARD_ALPHA_MIN = 0.15;
    var CARD_ALPHA_MAX = 0.95;

    var THEME_ICONS = { dawn: '🌅', day: '☀️', noon: '🌞', dusk: '🌇', night: '🌙' };
    var THEME_LABELS = {
        dawn: T('theme.dawn', '清晨'),
        day: T('theme.day', '白天'),
        noon: T('theme.noon', '正午'),
        dusk: T('theme.dusk', '黄昏'),
        night: T('theme.night', '夜晚')
    };

    function savePrefs(payload) {
        return rpc('Calendar.Settings.save', { params: payload });
    }

    /**
     * 卡片元素。浮层页就是 .rb-widget；设置页没有卡片，返回 null。
     *
     * 注意：模板会在这个元素上写内联 `--rb-card-alpha`，而渐变底也画在它身上，
     * 所以改值必须写在**卡片自己**的 style 上——只写 :root 会被内联声明盖掉（滑杆看起来就是坏的）。
     */
    function cardEl() {
        return document.querySelector('.rb-widget');
    }

    function currentCardAlpha() {
        var card = cardEl();
        var host = card || document.documentElement;
        var v = parseFloat(
            host.style.getPropertyValue('--rb-card-alpha')
            || getComputedStyle(host).getPropertyValue('--rb-card-alpha')
        );
        return isNaN(v) ? 0.66 : v;
    }

    /** 同时写到卡片和 :root：卡片管渐变底，:root 留给设置页的预览 */
    function writeCardAlpha(v) {
        var val = v.toFixed(2);
        var card = cardEl();
        if (card) {
            card.style.setProperty('--rb-card-alpha', val);
        }
        document.documentElement.style.setProperty('--rb-card-alpha', val);
    }

    function applyCardAlpha(alpha, persist) {
        var v = Math.min(CARD_ALPHA_MAX, Math.max(CARD_ALPHA_MIN, alpha));
        writeCardAlpha(v);

        var range = document.querySelector('.rb-op-range');
        if (range) {
            range.value = String(Math.round(v * 100));
        }

        if (persist) {
            savePrefs({ cardAlpha: v.toFixed(2) });
        }

        markSeen();   // 本地改的，别再被下面的轮询当成「别处改了」

        return v;
    }

    // 时段：5–7 清晨、8–11 白天、11–14 正午、14–17 白天、17–19 黄昏、其余夜晚
    // （与 CardTheme.php 同一套规则，改一处必须同步另一处）
    function phaseForHour(hour) {
        if (hour >= 5 && hour < 8) { return 'dawn'; }
        if (hour >= 11 && hour < 14) { return 'noon'; }
        if (hour >= 8 && hour < 17) { return 'day'; }
        if (hour >= 17 && hour < 19) { return 'dusk'; }
        return 'night';
    }

    function applyTheme() {
        var card = document.querySelector('.rb-widget');
        if (!card) {
            return;
        }

        var mode = card.dataset.themeMode || 'auto';
        var key = mode === 'auto' ? phaseForHour(new Date().getHours()) : mode;
        card.dataset.theme = key;

        var phase = document.querySelector('.rb-w-phase');
        if (phase) {
            phase.textContent = THEME_ICONS[key] || '';
            phase.title = THEME_LABELS[key] || '';
        }
    }

    // 给原生菜单 / 其它页面用
    window.ShiriCard = {
        get: currentCardAlpha,
        set: function (alpha) { return applyCardAlpha(alpha, true); },
        step: function (delta) { return applyCardAlpha(currentCardAlpha() + delta, true); },
        theme: function (value) {
            savePrefs({ theme: value });
            var card = document.querySelector('.rb-widget');
            if (card) {
                card.dataset.themeMode = value;
            }
            applyTheme();
            markSeen();
            return value;
        }
    };

    // 浮层和主窗口是两个 webview，设置页改完不会自动刷新。
    // 这里定期问一次服务端：配色 / 不透明度 / 语言跟本页显示的不一致就整页重载。
    function widgetSig(themeMode, alpha) {
        return String(themeMode) + '|' + Number(alpha).toFixed(2);
    }

    var seenSig = null;

    /** 记下本页当前显示的状态（自己改的时候调用，免得轮询把本地改动当外部改动） */
    function markSeen() {
        var card = document.querySelector('.rb-widget');
        if (card) {
            seenSig = widgetSig(card.dataset.themeMode || 'auto', currentCardAlpha());
        }
    }

    if (document.querySelector('.rb-widget')) {
        applyTheme();
        markSeen();

        setInterval(function () {
            if (seenSig === null) {
                return;
            }
            rpc('Calendar.Settings.get', {}).then(function (p) {
                if (!p) {
                    return;
                }
                if (p.lang && I18N.__locale && p.lang !== I18N.__locale) {
                    location.reload();
                    return;
                }
                if (widgetSig(p.theme, p.cardAlpha) !== seenSig) {
                    location.reload();
                }
            }).catch(function () {
                // 服务没起来就下一轮再说
            });
        }, 30000);

        // 只有「跟随时间」才需要每分钟看一眼是否跨过时段边界
        setInterval(function () {
            var card = document.querySelector('.rb-widget');
            if (card && (card.dataset.themeMode || 'auto') === 'auto') {
                applyTheme();
            }
        }, 60000);

        var alphaTimer = null;
        document.addEventListener('input', function (event) {
            var range = event.target.closest('.rb-op-range');
            if (!range) {
                return;
            }
            applyCardAlpha(parseInt(range.value, 10) / 100, false);
            clearTimeout(alphaTimer);
            alphaTimer = setTimeout(function () {
                savePrefs({ cardAlpha: (parseInt(range.value, 10) / 100).toFixed(2) });
            }, 350);
        });
    }

    // ── 设置页 ───────────────────────────────────────────────────────────────
    var settingsPage = document.querySelector('.rb-settings');

    if (settingsPage) {
        var statusEl = settingsPage.querySelector('[data-role="status"]');
        var savedText = settingsPage.dataset.msgSaved || T('settings.saved', '已保存');

        function say(text) {
            if (!statusEl) {
                return;
            }
            statusEl.textContent = text;
            setTimeout(function () {
                if (statusEl.textContent === text) {
                    statusEl.textContent = '';
                }
            }, 1600);
        }

        function fail(err) {
            if (statusEl) {
                statusEl.textContent = (err && err.message) || T('err.save', '保存失败');
            }
        }

        settingsPage.addEventListener('click', function (event) {
            var opt = event.target.closest('.rb-set-opt');
            if (!opt) {
                return;
            }

            var group = opt.closest('.rb-set-opts');
            [].slice.call(group.querySelectorAll('.rb-set-opt')).forEach(function (b) {
                b.classList.remove('is-on');
            });
            opt.classList.add('is-on');

            var payload = {};
            payload[opt.dataset.key] = opt.dataset.value;

            savePrefs(payload).then(function (res) {
                if (!res || res.ok !== true) {
                    throw new Error((res && res.message) || T('err.save', '保存失败'));
                }
                // 语言要整页重渲染才生效
                if (opt.dataset.key === 'lang') {
                    location.reload();
                    return;
                }
                say(savedText);
            }).catch(fail);
        });

        var setRange = settingsPage.querySelector('.rb-set-opacity');
        var alphaLabel = settingsPage.querySelector('[data-role="alpha-value"]');
        if (setRange) {
            var setTimer = null;
            setRange.addEventListener('input', function () {
                var pct = parseInt(setRange.value, 10);
                if (alphaLabel) {
                    alphaLabel.textContent = pct + '%';
                }
                writeCardAlpha(pct / 100);
                clearTimeout(setTimer);
                setTimer = setTimeout(function () {
                    savePrefs({ cardAlpha: (pct / 100).toFixed(2) }).then(function () {
                        say(savedText);
                    }).catch(fail);
                }, 350);
            });
        }
    }

    // ── 例行计划（只有「计划」页用） ─────────────────────────────────────────
    var planForm = document.querySelector('.rb-plan-form');

    if (planForm) {
        var freqSel = planForm.querySelector('.rb-plan-freq');
        var daysWrap = planForm.querySelector('[data-role="weekdays"]');
        var hint = planForm.querySelector('[data-role="hint"]');
        var dayButtons = [].slice.call(planForm.querySelectorAll('.rb-plan-day'));

        function syncFreqUi() {
            if (daysWrap) {
                daysWrap.style.display = freqSel.value === 'daily' ? 'none' : '';
            }
        }

        freqSel.addEventListener('change', syncFreqUi);
        syncFreqUi();

        dayButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.classList.toggle('is-on');
            });
        });

        planForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var title = (planForm.querySelector('.rb-plan-title').value || '').trim();
            if (!title) {
                return;
            }

            var days = dayButtons
                .filter(function (b) { return b.classList.contains('is-on'); })
                .map(function (b) { return parseInt(b.dataset.day, 10); });

            if (freqSel.value === 'weekly' && days.length === 0) {
                hint.textContent = T('plans.errDays', '每周的安排得先选星期几。');
                hint.classList.add('is-warn');
                return;
            }

            var timeValue = (planForm.querySelector('.rb-plan-time').value || '').trim();
            if (timeValue !== '' && !/^([01]\d|2[0-3]):[0-5]\d$/.test(timeValue)) {
                hint.textContent = T('plans.errTime', '时间写成 09:30 这样，或者留空表示一整天。');
                hint.classList.add('is-warn');
                return;
            }

            var payload = {
                title: title,
                freq: freqSel.value,
                weekdays: days,
                timeOfDay: timeValue,
                startsOn: (planForm.querySelector('.rb-plan-start').value || '')
            };

            var submit = planForm.querySelector('.rb-quickadd-btn');
            if (submit) {
                submit.disabled = true;
            }

            rpc('Calendar.Plans.save', { params: payload }).then(function (result) {
                if (!result || result.ok !== true) {
                    throw new Error((result && result.message) || T('err.save', '保存失败'));
                }
                // 规则列表和刚生成的待办都在服务端算，整页刷新最省事
                location.reload();
            }).catch(function (err) {
                if (submit) {
                    submit.disabled = false;
                }
                hint.textContent = err && err.message ? err.message : T('err.save', '保存失败');
                hint.classList.add('is-warn');
            });
        });

        // 删除 / 启用停用：列表是服务端渲染的，用事件委托
        document.addEventListener('click', function (event) {
            var del = event.target.closest('.rb-plan-del');
            if (del) {
                if (!window.confirm(T('plans.confirmDelete', '删除这条例行计划？已经生成的待办会保留。'))) {
                    return;
                }
                rpc('Calendar.Plans.remove', { params: { id: parseInt(del.dataset.id, 10) } })
                    .then(function () { location.reload(); });
                return;
            }

            var toggle = event.target.closest('.rb-plan-toggle');
            if (toggle) {
                rpc('Calendar.Plans.toggle', { params: { id: parseInt(toggle.dataset.id, 10) } })
                    .then(function () { location.reload(); });
            }
        });
    }

    // 待办清单默认只看未完成（与高亮的 tab 一致）；小卡一次性显示全部
    if (document.querySelector('.rb-board')) {
        applyFilter('open');
    }
})();
