<?php

namespace Shiri\Domain\Calendar\Support;

/**
 * 极简文案字典（中文 / English）
 *
 * 只覆盖「拾日」自己写的那些界面文案（我们改过的模板、控制器、数据层），
 * 不去碰 Shiri 内置的语言包——那边几千条，翻译它没有意义也没有必要。
 *
 * 用法：Lang::t('nav.todos') → 当前语言；Lang::t('todos.open', ['n' => 3]) 支持 {n} 占位。
 */
class Lang
{
    public const ZH = 'zh';

    public const EN = 'en';

    /** 当前请求用哪种语言（首次用到时按用户偏好解析一次） */
    private static ?string $locale = null;

    /** @return array<string, string> 语言代码 => 显示名 */
    public static function all(): array
    {
        return [self::ZH => '中文', self::EN => 'English'];
    }

    public static function current(): string
    {
        if (self::$locale !== null) {
            return self::$locale;
        }

        $lang = self::ZH;
        try {
            $userId = (int) session('userdata.id');
            $lang = app(Prefs::class)->get($userId, Prefs::LANG, self::ZH);
        } catch (\Throwable) {
            // 没登录 / 容器没起来：退回中文
        }

        return self::$locale = array_key_exists($lang, self::all()) ? $lang : self::ZH;
    }

    public static function setLocale(?string $locale): void
    {
        self::$locale = $locale !== null && array_key_exists($locale, self::all()) ? $locale : null;
    }

    public static function isEnglish(): bool
    {
        return self::current() === self::EN;
    }

    /**
     * 我们用的语言代码 → Shiri 自己的语言包代码
     *
     * 日历页、各种弹窗里的文案走的是 Shiri 内置的 __()，只有它自己的语言包能换。
     */
    public static function shiriLocale(?string $lang = null): string
    {
        return ($lang ?? self::current()) === self::EN ? 'en-US' : 'zh-CN';
    }

    /**
     * 把偏好里的语言同步给 Shiri 本体
     *
     * 页面渲染前调用（见 layouts/app.blade.php 第一行）：这样 `__('headline.my_calendar')`
     * 这类内置文案和我们自己的字典用的是同一种语言，不会出现「导航英文、日历中文」。
     * 只在 session 里的值不一致时才写，避免每个请求都重新读一遍语言包。
     */
    public static function applyToShiri(): void
    {
        try {
            $locale = self::shiriLocale();

            if (session('usersettings.language') !== $locale) {
                app(\Shiri\Core\Language::class)->setLanguage($locale);
            }
        } catch (\Throwable) {
            // 语言包缺失 / 未登录：保持原样即可，界面不会因此崩
        }
    }

    /**
     * 取一条文案
     *
     * @param  array<string, scalar>  $repl  占位替换，如 ['n' => 3] 对应文案里的 {n}
     */
    public static function t(string $key, array $repl = []): string
    {
        $entry = self::dict()[$key] ?? null;
        if ($entry === null) {
            return $key;   // 没翻译就露 key，方便一眼看出漏了哪条
        }

        $text = $entry[self::current()] ?? $entry[self::ZH];

        foreach ($repl as $name => $value) {
            $text = str_replace('{'.$name.'}', (string) $value, $text);
        }

        return $text;
    }

    /**
     * 当前语言下的全部文案（key => 文案），给前端注入用
     *
     * 页面里 `window.RB_LANG = {...}`，脚本用 RB_LANG[key] 取，
     * 这样 JS 里就不用再各写一份中文字符串。
     *
     * @return array<string, string>
     */
    public static function flat(): array
    {
        $out = ['__locale' => self::current()];
        foreach (self::dict() as $key => $entry) {
            $out[$key] = $entry[self::current()] ?? $entry[self::ZH];
        }

        return $out;
    }

    /**
     * 全部文案
     *
     * @return array<string, array{zh: string, en: string}>
     */
    public static function dict(): array
    {
        return [
            // 全局
            'app.name' => ['zh' => '拾日', 'en' => 'Shiri'],

            // 导航
            'nav.calendar' => ['zh' => '日历', 'en' => 'Calendar'],
            'nav.todos' => ['zh' => '待办', 'en' => 'Tasks'],
            'nav.plans' => ['zh' => '计划', 'en' => 'Plans'],
            'nav.stats' => ['zh' => '统计', 'en' => 'Stats'],
            'nav.settings' => ['zh' => '设置', 'en' => 'Settings'],

            // 时段
            'theme.dawn' => ['zh' => '清晨', 'en' => 'Dawn'],
            'theme.day' => ['zh' => '白天', 'en' => 'Day'],
            'theme.noon' => ['zh' => '正午', 'en' => 'Noon'],
            'theme.dusk' => ['zh' => '黄昏', 'en' => 'Dusk'],
            'theme.night' => ['zh' => '夜晚', 'en' => 'Night'],

            // 待办页
            'todos.title' => ['zh' => '待办', 'en' => 'Tasks'],
            'todos.empty' => ['zh' => '还没有待办', 'en' => 'No tasks yet'],
            'todos.allDone' => ['zh' => '全部做完了', 'en' => 'All done'],
            'todos.openCount' => ['zh' => '{n} 件未完成', 'en' => '{n} open'],
            'todos.overdueCount' => ['zh' => '{n} 件逾期', 'en' => '{n} overdue'],
            'todos.tab.open' => ['zh' => '未完成', 'en' => 'Open'],
            'todos.tab.done' => ['zh' => '已完成', 'en' => 'Done'],
            'todos.tab.all' => ['zh' => '全部', 'en' => 'All'],
            'todos.quickadd' => ['zh' => '记一件要做的事，回车保存', 'en' => 'Add a task, press Enter'],
            'todos.add' => ['zh' => '添加', 'en' => 'Add'],
            'todos.markDone' => ['zh' => '标记为完成', 'en' => 'Mark as done'],
            'todos.markUndone' => ['zh' => '标记为未完成', 'en' => 'Mark as open'],
            'todos.dueTitle' => ['zh' => '截止日期', 'en' => 'Due date'],
            'todos.emptyHint' => ['zh' => '还没有待办。在日历里点任意一天就能加一条，或者在上面直接记一笔。', 'en' => 'No tasks yet — click any day in the calendar, or type one above.'],

            // 报错
            'err.request' => ['zh' => '请求失败', 'en' => 'Request failed'],
            'err.rejected' => ['zh' => '服务端拒绝修改', 'en' => 'Server rejected the change'],
            'err.save' => ['zh' => '保存失败', 'en' => 'Could not save'],
            'err.noProject' => ['zh' => '没有可写入的项目，无法新增待办', 'en' => 'No writable project — cannot add a task'],
            'err.add' => ['zh' => '新增失败', 'en' => 'Could not add the task'],

            // 星期（1=周一 … 7=周日）
            'week.1' => ['zh' => '一', 'en' => 'Mo'],
            'week.2' => ['zh' => '二', 'en' => 'Tu'],
            'week.3' => ['zh' => '三', 'en' => 'We'],
            'week.4' => ['zh' => '四', 'en' => 'Th'],
            'week.5' => ['zh' => '五', 'en' => 'Fr'],
            'week.6' => ['zh' => '六', 'en' => 'Sa'],
            'week.7' => ['zh' => '日', 'en' => 'Su'],

            // 分组
            'bucket.overdue' => ['zh' => '逾期', 'en' => 'Overdue'],
            'bucket.today' => ['zh' => '今天', 'en' => 'Today'],
            'bucket.week' => ['zh' => '本周', 'en' => 'This week'],
            'bucket.later' => ['zh' => '以后', 'en' => 'Later'],
            'bucket.none' => ['zh' => '无截止日', 'en' => 'No due date'],
            'bucket.done' => ['zh' => '已完成', 'en' => 'Done'],

            // 截止文案
            'due.none' => ['zh' => '无截止日', 'en' => 'No due date'],
            'due.today' => ['zh' => '今天', 'en' => 'Today'],
            'due.tomorrow' => ['zh' => '明天', 'en' => 'Tomorrow'],
            'due.dayAfter' => ['zh' => '后天', 'en' => 'In 2 days'],
            'due.yesterday' => ['zh' => '昨天', 'en' => 'Yesterday'],
            'due.daysAgo' => ['zh' => '{n} 天前', 'en' => '{n} days ago'],
            'due.date' => ['zh' => '{m}月{d}日', 'en' => '{mon} {d}'],
            'due.dateYear' => ['zh' => '{y}年{m}月{d}日', 'en' => '{mon} {d}, {y}'],

            // 统计页
            'stats.title' => ['zh' => '统计', 'en' => 'Stats'],
            'stats.asOf' => ['zh' => '截至 {date}', 'en' => 'As of {date}'],
            'stats.open' => ['zh' => '未完成', 'en' => 'Open'],
            'stats.overdue' => ['zh' => '逾期', 'en' => 'Overdue'],
            'stats.todayDue' => ['zh' => '今天到期', 'en' => 'Due today'],
            'stats.doneWeek' => ['zh' => '本周完成', 'en' => 'Done this week'],
            'stats.doneMonth' => ['zh' => '本月完成', 'en' => 'Done this month'],
            'stats.doneTotal' => ['zh' => '累计完成', 'en' => 'Done total'],
            'stats.recent' => ['zh' => '近 7 天完成', 'en' => 'Completed in last 7 days'],
            'stats.barTitle' => ['zh' => '{date}：完成 {n} 件', 'en' => '{date}: {n} done'],
            'stats.dist' => ['zh' => '分布', 'en' => 'Breakdown'],
            'stats.thisWeek' => ['zh' => '本周到期', 'en' => 'Due this week'],
            'stats.undated' => ['zh' => '无截止日', 'en' => 'Undated'],
            'stats.doneToday' => ['zh' => '今天完成', 'en' => 'Done today'],
            'stats.total' => ['zh' => '待办总数', 'en' => 'Total'],
            'stats.oldest' => ['zh' => '最久没动的一条：', 'en' => 'Untouched longest:'],
            'stats.footnote' => [
                'zh' => '「本周 / 本月完成」按待办最后更新时间统计——Shiri 没有单独的完成时间字段，标记完成后只要不再改动，数字就是准的。',
                'en' => '"Done this week/month" counts by last-modified time — Shiri has no separate completion timestamp, so the numbers stay accurate as long as you don\'t edit a finished task.',
            ],

            // 计划页
            'plans.title' => ['zh' => '计划', 'en' => 'Plans'],
            'plans.empty' => ['zh' => '还没有例行计划', 'en' => 'No repeating plans yet'],
            'plans.count' => ['zh' => '{n} 条例行安排，未来三周会自动生成待办', 'en' => '{n} repeating plans; the next 3 weeks are generated automatically'],
            'plans.freq' => ['zh' => '频率', 'en' => 'Repeat'],
            'plans.weekly' => ['zh' => '每周', 'en' => 'Weekly'],
            'plans.daily' => ['zh' => '每天', 'en' => 'Daily'],
            'plans.time' => ['zh' => '时间', 'en' => 'Time'],
            'plans.timePh' => ['zh' => '09:30（可空）', 'en' => '09:30 (optional)'],
            'plans.start' => ['zh' => '开始', 'en' => 'From'],
            'plans.add' => ['zh' => '添加', 'en' => 'Add'],
            'plans.titlePh' => ['zh' => '比如：组会 / 每周复盘 / 交周报', 'en' => 'e.g. standup / weekly review'],
            'plans.hint' => ['zh' => '选星期几（可多选）；不填时间就是一整天的待办。', 'en' => 'Pick weekdays (multiple allowed); leave time empty for an all-day task.'],
            'plans.disable' => ['zh' => '停用', 'en' => 'Pause'],
            'plans.enable' => ['zh' => '启用', 'en' => 'Resume'],
            'plans.delete' => ['zh' => '删除', 'en' => 'Delete'],
            'plans.footnote' => [
                'zh' => '删除规则不会删掉已经生成的待办 —— 那些已经落在日历上了，需要的话单独处理。',
                'en' => 'Deleting a plan keeps the tasks already generated — they are real entries on your calendar.',
            ],
            'plans.errTitle' => ['zh' => '标题不能为空；每周规则至少要选一天', 'en' => 'Title is required; pick at least one weekday'],
            'plans.errDays' => ['zh' => '每周的安排得先选星期几。', 'en' => 'Pick at least one weekday for a weekly plan.'],
            'plans.errTime' => ['zh' => '时间写成 09:30 这样，或者留空表示一整天。', 'en' => 'Use 09:30 format, or leave empty for all-day.'],
            'plans.confirmDelete' => ['zh' => '删除这条例行计划？已经生成的待办会保留。', 'en' => 'Delete this plan? Generated tasks will stay.'],
            'plans.day.1' => ['zh' => '一', 'en' => 'M'],
            'plans.day.2' => ['zh' => '二', 'en' => 'T'],
            'plans.day.3' => ['zh' => '三', 'en' => 'W'],
            'plans.day.4' => ['zh' => '四', 'en' => 'T'],
            'plans.day.5' => ['zh' => '五', 'en' => 'F'],
            'plans.day.6' => ['zh' => '六', 'en' => 'S'],
            'plans.day.7' => ['zh' => '日', 'en' => 'S'],
            'plans.summary.daily' => ['zh' => '每天', 'en' => 'Every day'],
            'plans.summary.weekly' => ['zh' => '每周{days}', 'en' => 'Weekly {days}'],
            'plans.summary.weeklyAll' => ['zh' => '每天', 'en' => 'Every day'],

            // 浮层
            'widget.today' => ['zh' => '今天 · {date}', 'en' => 'Today · {date}'],
            'widget.add' => ['zh' => '加一条', 'en' => 'Add'],
            'widget.open' => ['zh' => '打开拾日', 'en' => 'Open Shiri'],
            'widget.opacity' => ['zh' => '卡片不透明度', 'en' => 'Card opacity'],
            'widget.empty' => ['zh' => '今天没有安排。', 'en' => 'Nothing scheduled today.'],
            'widget.more' => ['zh' => '还有 {n} 件…', 'en' => '{n} more…'],
            'widget.overdue' => ['zh' => '{n} 逾期', 'en' => '{n} overdue'],
            'widget.full' => ['zh' => '打开拾日', 'en' => 'Open Shiri'],

            // 设置页
            'settings.title' => ['zh' => '设置', 'en' => 'Settings'],
            'settings.sub' => ['zh' => '只影响这台机器上的「拾日」', 'en' => 'Applies to Shiri on this Mac only'],
            'settings.language' => ['zh' => '语言', 'en' => 'Language'],
            'settings.languageHint' => ['zh' => '界面文案的语言。日历里的数据（标题、备注）不会翻译。', 'en' => 'UI language only. Your own task titles stay as typed.'],
            'settings.theme' => ['zh' => '桌面卡片配色', 'en' => 'Desktop card theme'],
            'settings.themeHint' => ['zh' => '默认跟着一天的光线走，也可以固定成某一档。', 'en' => 'Follows the time of day by default, or pin one.'],
            'settings.themeAuto' => ['zh' => '跟随时间', 'en' => 'Follow time'],
            'settings.opacity' => ['zh' => '卡片不透明度', 'en' => 'Card opacity'],
            'settings.opacityHint' => ['zh' => '只影响卡片玻璃底，文字始终实心。', 'en' => 'Affects the card glass only; text stays solid.'],
            'settings.weekStart' => ['zh' => '一周从周几开始', 'en' => 'Week starts on'],
            'settings.weekStartHint' => ['zh' => '影响桌面卡片和日历的排列。', 'en' => 'Applies to the desktop card and calendar grid.'],
            'settings.weekMon' => ['zh' => '周一', 'en' => 'Monday'],
            'settings.weekSun' => ['zh' => '周日', 'en' => 'Sunday'],
            'settings.save' => ['zh' => '保存', 'en' => 'Save'],
            'settings.saved' => ['zh' => '已保存', 'en' => 'Saved'],
            'settings.saving' => ['zh' => '保存中…', 'en' => 'Saving…'],

            // 日历右侧栏：重要时间节点 / 遥远未来
            // 倒计时拆成三段（前缀 / 数字 / 后缀），数字单独放大；间距由 CSS 控制，
            // 字典里不留空格，免得 Blade 转义或 HTML 折叠后中英语序错位。
            'ms.title' => ['zh' => '重要时间节点', 'en' => 'Important Dates'],
            'ms.soon' => ['zh' => '近期', 'en' => 'Coming up'],
            'ms.far' => ['zh' => '遥远未来', 'en' => 'Further out'],
            'ms.past' => ['zh' => '已过去', 'en' => 'Passed'],
            'ms.leftPre' => ['zh' => '还有', 'en' => ''],
            'ms.leftPost' => ['zh' => '天', 'en' => 'days left'],
            'ms.agoPre' => ['zh' => '已过去', 'en' => ''],
            'ms.agoPost' => ['zh' => '天', 'en' => 'days ago'],
            'ms.today' => ['zh' => '就是今天', 'en' => 'Today'],
            'ms.unit' => ['zh' => '天', 'en' => 'days'],
            'ms.empty' => ['zh' => '还没有节点，点下方「添加」放一个。', 'en' => 'Nothing here yet — add one below.'],
            'ms.add' => ['zh' => '添加', 'en' => 'Add'],
            'ms.name' => ['zh' => '节点名称', 'en' => 'Name'],
            'ms.nameEn' => ['zh' => '英文名（可选）', 'en' => 'English name (optional)'],
            'ms.date' => ['zh' => '日期', 'en' => 'Date'],
            'ms.save' => ['zh' => '保存', 'en' => 'Save'],
            'ms.cancel' => ['zh' => '取消', 'en' => 'Cancel'],
            'ms.delete' => ['zh' => '删除', 'en' => 'Delete'],
            'ms.deleteConfirm' => ['zh' => '删除这个节点？', 'en' => 'Delete this date?'],
            'ms.err' => ['zh' => '名称或日期不合法', 'en' => 'Check the name and date'],
            'ms.saving' => ['zh' => '保存中…', 'en' => 'Saving…'],
        ];
    }
}
