/*
 * 日历右侧「重要时间节点」栏：添加 / 删除（本机）
 * ---------------------------------------------------------------------------
 * 走 JSON-RPC shiri.rpc.Calendar.Milestones.{add,remove}。
 * 单数组参数的方法，params 必须包一层 { params: {...} }（服务端方法签名
 * 是 add(array $params)，按参数名绑定）。
 * 成功后整页重载：倒计时是按当天日期算的，重载最省事也不会留下过期数字。
 */
(function () {
    'use strict';

    var I = window.RB_LANG || {};
    function T(k, fallback) {
        return I[k] !== undefined ? I[k] : fallback;
    }

    function apiUrl() {
        var explicit = document.body && document.body.dataset ? document.body.dataset.api : '';
        if (explicit) { return explicit; }
        if (window.shiri && window.shiri.appUrl) {
            return String(window.shiri.appUrl).replace(/\/+$/, '') + '/api/jsonrpc';
        }
        return location.origin + '/api/jsonrpc';
    }

    var seq = 1;
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
                id: seq++,
                method: 'shiri.rpc.Calendar.Milestones.' + method,
                params: params || {}
            })
        }).then(function (res) {
            return res.json();
        }).then(function (payload) {
            if (payload && payload.error) {
                throw new Error(payload.error.message || T('ms.err', 'Request failed'));
            }
            return payload ? payload.result : null;
        });
    }

    function init() {
        var panel = document.getElementById('ms-panel');
        if (!panel) { return; }

        var addBtn = document.getElementById('ms-add-btn');
        var form = document.getElementById('ms-form');
        var err = document.getElementById('ms-form-err');
        var submit = form.querySelector('.ms-submit');
        var cancel = form.querySelector('.ms-cancel');
        var titleInput = form.querySelector('[name="title"]');

        function showForm(on) {
            form.hidden = !on;
            addBtn.hidden = on;
            if (on) {
                err.hidden = true;
                titleInput.focus();
            }
        }

        function showErr(msg) {
            err.textContent = msg;
            err.hidden = false;
        }

        addBtn.addEventListener('click', function () { showForm(true); });
        cancel.addEventListener('click', function () { showForm(false); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var title = (form.querySelector('[name="title"]').value || '').trim();
            var titleEn = (form.querySelector('[name="titleEn"]').value || '').trim();
            var date = (form.querySelector('[name="date"]').value || '').trim();

            if (!title || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
                showErr(T('ms.err', 'Check the name and date'));
                return;
            }

            submit.disabled = true;
            submit.textContent = T('ms.saving', 'Saving…');
            err.hidden = true;

            rpc('add', { params: { title: title, titleEn: titleEn, date: date } })
                .then(function (res) {
                    if (!res || !res.ok) {
                        throw new Error((res && res.message) || T('ms.err', 'Check the name and date'));
                    }
                    location.reload();
                })
                .catch(function (ex) {
                    showErr(ex.message || T('ms.err', 'Check the name and date'));
                    submit.disabled = false;
                    submit.textContent = T('ms.save', 'Save');
                });
        });

        panel.addEventListener('click', function (e) {
            var del = e.target && e.target.closest ? e.target.closest('.ms-del') : null;
            if (!del) { return; }
            var item = del.closest('.ms-item');
            if (!item) { return; }

            if (!window.confirm(T('ms.deleteConfirm', 'Delete this date?'))) { return; }

            rpc('remove', { params: { id: parseInt(item.dataset.id, 10) } })
                .then(function (res) {
                    if (!res || !res.ok) {
                        throw new Error((res && res.message) || 'Request failed');
                    }
                    location.reload();
                })
                .catch(function (ex) {
                    window.alert(ex.message || 'Request failed');
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
