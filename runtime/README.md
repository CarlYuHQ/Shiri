# 拾日（本机极简版）

把一个开源 PHP 框架（AGPL-3.0，见 `NOTICE.md`）整体白标成 Shiri，瘦身成「打开就是日历」的小工具，内部仍用它的
日历与待办数据表，界面只留四个页面：**日历 / 待办 / 计划 / 统计**，外加一个可以钉在
桌面上的半透明小卡。

## 一键操作

| 想做的事 | 做法 |
| --- | --- |
| 打开 | 点程序坞 / 启动台里的 **拾日**（`~/Applications/拾日.app`） |
| 加日程 | 直接点日历上任意一天，就地输入标题 → 回车 |
| 加待办 | 日历点一天 / 待办页顶部「记一件要做的事」→ 回车 |
| 标记完成 | 待办页、桌面小卡上点左侧圆圈；再点一次取消 |
| 改 / 删 | 点条目打开原编辑页；日历里悬停条目右上角 **×** 或右键直接删 |
| 钉到桌面 | **⌘D** 切桌面浮层（1040×790，贴屏幕左上）；点程序坞图标回主窗口 |
| 浮层透明度 | 卡片右上角那截细滑杆（也支持菜单「显示 → 浮层更透明/更不透明」，⌥⌘- / ⌥⌘=） |
| 关窗口 | ⌘W 只关窗口，App 留在程序坞（点图标就回来）；真正退出用 ⌘Q |
| 手动启停服务 | `bash start.sh` / `bash stop.sh` |
| 重建窗口 App | `bash desktop/build.sh`（图标由 `desktop/make-icon.py` 现画，见「图标与标记」） |

## 「拾日」和「日历」是什么关系

「拾日」是**应用的名字**（左上角那三个字），「日历 / 待办 / 计划 / 统计 / 设置」是它里面的**五个页面**。
打开 App 落在日历页，因为日历是最常用的入口。左上角的「拾日」只是标题，不可点，
免得和「日历」这个 tab 重复。

## 五个页面

| 页面 | 地址 | 内容 |
| --- | --- | --- |
| 日历 | `/calendar/showMyCalendar` | 月/周/日/年视图，点空白格加日程，悬停 × 删除 |
| 待办 | `/calendar/showTodos` | 按「逾期 / 今天 / 本周 / 以后 / 无截止日 / 已完成」分组，勾选即完成，顶部一行快速录入 |
| 计划 | `/calendar/showPlans` | 例行安排（每天 / 每周几），保存后自动生成未来三周的待办 |
| 统计 | `/calendar/showStats` | 未完成 / 逾期 / 今天到期 / 本周完成 / 本月完成 / 累计完成，近 7 天完成条形图 |
| 设置 | `/calendar/showSettings` | 语言、桌面卡片配色、卡片不透明度、一周从周几开始 |

前四页共用一份数据（分配给自己的 ticket）。取数逻辑集中在
`app/Domain/Calendar/Support/TodoBoard.php`，页面控制器只负责把结果交给模板。

## 桌面浮层（迷你月历）

一张 **1040×790** 的无边框半透明月历卡（`/calendar/desktopWidget`），
默认**贴着屏幕左边、压在左上角那排桌面小组件（天气/时钟）下面**（左 14pt、顶 224pt）：

```
┌──────────────────────────────────────────┐
│ 2026年11月                            🌞 ─ ↗ │
│ 周日   周一   周二   周三   周四   周五   周六 │
├──────┬──────┬──────┬──────┬──────┬──────┬──────┤
│    1 │    2 │    3 │    4 │    5 │    6 │  (7) │
│      │      │      │      │      │      │ 09:00 组会…│
├──────┼──────┼──────┼──────┼──────┼──────┼──────┤
│    8 │    9 │   10 │   11 │   12 │   13 │   14 │
│      │      │      │      │10:00 交报告│    │      │
├──────┼──────┼──────┼──────┼──────┼──────┼──────┤
│  ... 按行填满，前后月淡出                                │
├──────┴──────┴──────┴──────┴──────┴──────┴──────┤
│ 今天 · 11月7日 周六                     ＋ │
│ ○ 组会：汇报实验                    09:00 │
└──────────────────────────────────────────┘
```

- 借用 macOS 日历的版式：标题写「2026年11月」（英文是 `November 2026`），表头 **周日 → 周六**，格子之间是很淡的网格线，
  日期数字只占格子右上角一角（`clamp(13px,2.2vh,20px)`，1040×790 下约 17px），今天用强调色实心圆
- 网格默认**周日起始**、补齐前后月（设置页可改成周一）
- **有安排的日子直接写出事项**：宽卡（≥560pt）每格最多 2 条小色条（时间 + 标题，超出省略号，
  再多显示 `+N`）；窄卡自动退回 1–3 个橘色小点
- 底部只列**今天**的事（最多 5 条，多出的显示「还有 N 件…」）；待办可勾选，日历事件用圆点区分、不可勾
- 右上 **↗** 回主窗口；**＋** 切到主窗口并直接落在待办页（光标已落在录入框）
- 按在卡片空白处可以直接拖动；位置、尺寸都会记住（UserDefaults）
- **不透明度可调**：头部右侧内联一截细滑杆（15%–95%，轨道 2pt、滑块 9pt），拖动实时生效；
  也可以走菜单 `显示 → 浮层更透明/更不透明`（⌥⌘- / ⌥⌘=，每档 5%）。
  调的是**卡片玻璃底**的 CSS 变量 `--rb-card-alpha`，不是窗口 `alphaValue`——
  整窗 alpha 会把文字一起糊掉，低透明度下基本没法读；现在文字始终实心。
  值存服务端（`zp_rb_prefs`，见下面「设置页」），主窗口和浮层共用同一份。
  **变量必须写在卡片元素自己的 style 上**（`card.style.setProperty`）——模板给 `.rb-widget`
  写了内联 `--rb-card-alpha`，而渐变底就画在这个元素上，只写 `:root` 会被内联声明盖掉，
  表现就是「滑杆拖了没反应」。滑杆的 `value` 也跟着服务端值走，否则刷新后滑块位置和实际透明度对不上。
- **时段主题**：卡片底色跟一天的光线走 —— 清晨（5–7，暖粉）、白天（8–11、14–17，近白）、
  正午（11–14，日光暖白转天光蓝）、黄昏（17–19，橙紫）、夜晚（其余，深蓝 + 反白文字）。
  服务端先按钟点算一次（`Support/CardTheme.php`），首屏就是对的颜色；
  页面每分钟重算一遍，跨过时段边界会自动换。规则只写在 CSS
  （`.rb-widget[data-theme=…]` 覆盖一组变量），JS 只负责挑 key。
  右上角的小图标（🌅/☀️/🌞/🌇/🌙）标出当前是哪一档。
- **尺寸随窗口走**：字号全部 `clamp()` 跟视口高度缩放，月历 6 行 `flex:1` 平分剩余高度，
  所以默认 1040×790 下网格是撑满的，拉到最小 340×380 也不重叠不裁切。
  改默认尺寸/位置时把 `main.swift` 里的 `kWidgetFrameVersion` +1，否则偏好里存的旧 frame 会一直生效
- 透明是两层的：窗口 `isOpaque = false` + `backgroundColor = .clear`，
  页面 `html/body` 透明、卡片本身 `rgba(255,255,255,.66)` + 毛玻璃，
  卡片外面直接透出桌面；深色桌面下卡片自动转深灰（`prefers-color-scheme`）
- 拖动靠页面 `mousedown` → `WKScriptMessageHandler` 通知原生端开始跟手移动：
  WKWebView 会吃掉 mousedown，`isMovableByWindowBackground` 对它无效
- 浮层**贴在桌面层**：`level = CGWindowLevelForKey(.desktopIconWindow) + 1`，
  也就是桌面图标之上、普通 App 窗口之下。切到别的 App 时它会被盖住，按 F11 / 显示桌面
  就能看到——这是"桌面小部件"而不是"悬浮窗"
- 窗口是 **NSPanel + `.nonactivatingPanel`**，不是普通 NSWindow：桌面层的普通窗口被点击时，
  系统会先把 App 激活到前台再把第一下点击吞掉（勾选要点两次，还会把正在用的 App 挤掉）。
  NSPanel 的 nonactivatingPanel 让点击不激活 App、事件直接进面板
- 桌面层 + 非激活面板下 `makeKeyAndOrderFront` 不一定把窗口 front 出来（实测
  `CGWindowListCopyWindowInfo` 里 `kCGWindowIsOnscreen = false`），要用 `orderFrontRegardless()`
- 关窗不退出 App（`applicationShouldTerminateAfterLastWindowClosed` 返回 false）；
  最小化/缩放在浮层下禁用（没有标题栏，动作无意义）
- **程序坞图标 = 主窗口**：`applicationShouldHandleReopen` 先退出浮层再 `activate` + `makeKeyAndOrderFront`。
  桌面层的小卡被别的窗口盖着时，光 orderFront 是看不出来的——这就是之前"点图标没反应"的原因
- **启动行为**：进过浮层的人（`widgetPreferred`）启动即还原小卡；关掉主窗口自动回到小卡
  （`windowWillClose`），不会留下"进程还在但什么都没有"的状态。首次使用直接给主窗口
- 启动后 2.5s 内系统会补发一个 reopen 事件（`open` 拉起 App 时），那个不算"点了图标"，
  否则「启动即小卡」会立刻被翻成主窗口——`applicationShouldHandleReopen` 里按 `launchTime` 挡掉
- 格子是 `repeat(7, minmax(0, 1fr))` 而不是 `repeat(7, 1fr)`：`1fr` 等价 `minmax(auto,1fr)`，
  格子里放不换行的日程条时会把整列撑宽，各列宽度不一致

## 例行计划（每周固定的安排）

「每周一、四 09:30 组会」这种，在 `/calendar/showPlans` 里写一次就行：

- 频率只有两档：**每天 / 每周**（每周可多选星期几）；时间可留空 = 一整天的待办
- 规则存 `zp_recurring`；生成结果**是真的待办**（`zp_tickets`），所以勾选、统计、
  逾期判断、日历打点全部复用现有逻辑，一行都没改
- 打开任意待办相关页面时补生成「今天起 21 天内」的条目（`RecurringBoard::sync()`），
  靠 `zp_recurring_log(ruleId, dateKey)` 唯一键保证同一条规则同一天只生成一次 ——
  不需要常驻定时任务，也不会重复堆
- 建 ticket 直接走 `TicketsRepository::addTicket()`，绕开 `quickAddTicket()` 的邮件通知
  （否则每生成一条都发一封「新待办」邮件）
- 时间按用户时区换算成 UTC 存库（`09:00` → `01:00Z`），和日历快速添加同一口径
- **删规则不删已生成的待办**：那些已经落在日历上了。这是「落库」方案的已知取舍，
  换虚拟投影就不会有这问题，但勾选/统计要单独处理

## 设置页

`/calendar/showSettings` 四组开关，改完**即时保存**（`Calendar.Settings.save`，JSON-RPC），没有「保存」按钮：

| 设置 | 取值 | 影响 |
| --- | --- | --- |
| 语言 | 中文 / English | 界面文案。包含我们自己的字典和框架内置文案 |
| 桌面卡片配色 | 跟随时间 / 清晨 / 白天 / 正午 / 黄昏 / 夜晚 | 浮层的底色；固定某一档后不再随钟点变 |
| 卡片不透明度 | 15%–95%（默认 66%） | 卡片玻璃底 `--rb-card-alpha`，文字始终实心 |
| 一周从周几开始 | 周一 / 周日（默认周日） | 浮层表头与日历排列 |

- 全部存在 `zp_rb_prefs(userId, k, v)`（`Support/Prefs.php`），**服务端是唯一真值**，
  没有 localStorage 副本——浮层滑杆、菜单、设置页改的是同一条记录
- **语言是两层**：我们改过的模板/控制器走 `Support/Lang.php` 的字典（`Lang::t('nav.todos')`），
  日历页、弹层这些没改过的地方走框架自己的 `__()` + `app/Language/zh-CN.ini|en-US.ini`。
  只存字典会出现「导航英文、日历中文」，所以每次渲染前调 `Lang::applyToShiri()`
  （`layouts/app.blade.php` 第一行 + 五个页面控制器开头），把偏好同步成 `Language::setLanguage()`。
  `session('usersettings.language')` 与偏好一致时不重复写

## 右侧节点栏（重要时间节点）

日历页右侧一栏，放重要日期和遥远未来的日子，按「还有多少天」倒计时。

- **分组**：近期（≤365 天）→ 遥远未来（>365 天）→ 已过去（置灰沉底），
  组内按天数升序，刚过去的排最前；标题栏的数字是总条数，`<details>` 可整栏折叠
- **倒计时是三段拼的**（前缀 / 数字 / 后缀），数字单独放大：中文「还有 **21** 天」、
  英文「**21** days left」，今天的条目没有数字、直接放大「就是今天 / Today」。
  拆段是为了中英语序都能拼对，字典里不留空格，间距交给 CSS
- **天数按本机日期算**：服务端 PHP 可能是 UTC，`MilestoneBoard::TZ` 显式钉到
  `Asia/Shanghai`，`today` 与目标日期都取整天，跨天不会出现半天的偏差
- **数据**：`zp_milestones(userId, title, titleEn, targetDate)`，代码 `CREATE TABLE IF NOT EXISTS`
  自建；**只在首次建表时**插四条「示例 ·」种子（覆盖近期/遥远/已过去四种状态），
  之后删光了不会自己长回来
- **RPC**：`shiri.rpc.Calendar.Milestones.{get,add,remove}`（`Services/Milestones.php`，
  单数组参数照例包 `{params:{...}}`）；添加 / 删除成功后整页重载，倒计时永远不会过期
- **双语**：节点标题存中英两份（`title` / `titleEn`），英文界面优先显示英文名；
  分组与倒计时文案在 `Lang.php` 的 `ms.*` 键
- 文件：`Support/MilestoneBoard.php`（表与 CRUD）、`Services/Milestones.php`（RPC 与渲染数据）、
  `public/dist/css/shiri-milestones.css`、`public/dist/js/shiri-milestones.js`；
  侧栏在 `.cal-stage` 里与 `#calendar` 横向并排，窗口 <820px 才堆到日历下面
- JS 里的文案也从字典来：模板注入 `window.RB_LANG = Lang::flat()`，脚本用 `T('todos.markDone')`
  取，兜底值仍是中文——脚本单独跑不会甩出一串 key
- 浮层和主窗口是两个 webview，设置改完不会自动刷新：浮层每 30 秒问一次
  `Calendar.Settings.get`，配色/不透明度/语言跟当前显示不一致就 `location.reload()`；
  自己拖滑杆时先记下本地状态（`markSeen()`），不会把自己的改动当外部改动而狂刷

截图：`screenshots/设置页.png`、`screenshots/设置页-英文.png`。

## 瘦身做了什么

| 原来 | 现在 |
| --- | --- |
| 左侧导航 10 项（仪表盘/待办/里程碑/目标/想法/蓝图/数据室/工时表/公司…） | 全部去掉，导航栏整块移除 |
| 顶栏：项目选择器、搜索、通知、更新、插件、帮助、用户菜单 | 只剩应用名「拾日」+ 日历/待办/计划/统计/设置五个链接 |
| 登录 / 注册 / 找回密码页 | `/auth/login` 直接 303 回日历（本就开着 127.0.0.1 自动登录） |
| 首页 = 仪表盘 | 首页 = 日历（`$defaultRoute = 'calendar.showMyCalendar'`） |
| 玻璃质感、渐变背景、多层阴影卡片 | 白底 + 一条发丝边框，`.rightpanel::before` 的背景图已关 |
| 日历页「图例」左栏、页面大标题卡、导出按钮 | 去掉，网格整宽 |

改动位置（都是小改，方便日后对照）：

- `app/Core/Controller/Frontcontroller.php` — 默认首页改成日历
- `app/Domain/Auth/Controllers/Login.php` — 登录页重定向
- `app/Domain/Menu/Repositories/Menu.php` — 菜单结构只留日历（`NON_PROJECT_MENUS` 常量还有用，别删）
- `app/Views/Templates/layouts/app.blade.php` — 极简顶栏 + 三个导航项
- `app/Views/Templates/sections/header.blade.php` — 引全局 `shiri-base.css`（放在整站 CSS 之后）、标题改「拾日」
- `public/dist/css/shiri-base.css` — 全局极简主题、顶栏导航样式
- `public/dist/css/shiri-calendar.css` + 日历模板 `@push('styles')` — 日历排版
- `public/dist/js/shiri-quickadd.js` — 点空白处新建日程
- `public/dist/js/shiri-quickdelete.js` — 悬停 × / 右键删除
- `app/Domain/Calendar/Controllers/ShowTodos.php`、`ShowStats.php`、`DesktopWidget.php` — 三个新页面
- `app/Domain/Calendar/Support/TodoBoard.php` — 待办数据层（分组、排序、统计口径）
- `app/Domain/Calendar/Templates/showTodos.blade.php`、`showStats.blade.php`、`desktopWidget.blade.php`、`partials/todoRow.blade.php`
- `app/Views/Templates/layouts/widget.blade.php` — 浮层小卡专用布局（无顶栏侧栏，自带透明底）
- `public/dist/css/shiri-board.css`、`shiri-widget.css`、`public/dist/js/shiri-board.js` — 三个页面的样式与交互

> 新增的类名统一用 `rb-` 前缀。**不要用 `lt-`**：框架自带的设计系统占着这个前缀
> （`.lt-stats` 是 `display:grid`、`.lt-card`、`.lt-list`、`.lt-check` 都有定义），
> 撞上之后页面布局会直接散架，而且症状很难看出来。

## 改名：日程本 → 拾日

一键改名的清单（下次再改照这个走，漏一处就会出现「窗口叫新名字、Dock 还是旧名字」）：

| 位置 | 字段 |
| --- | --- |
| `app/Domain/Calendar/Support/Lang.php` | `app.name`（zh `拾日` / en `Shiri`），页面标题、导航、浮层标题都读它 |
| `app/Views/Templates/sections/header.blade.php` | `<title>`（也走 `Lang::t('app.name')`） |
| `desktop/main.swift` | `kAppTitle`、`kInnerScheme`（`shiri://`，同时是 JS 消息通道名）、UA 字符串、`window.ShiriCard` |
| `desktop/build.sh` | `NAME`、`EXE=shiri`、`BUNDLE_ID=local.shiri.app` |
| `config/.env` | `LEAN_SITENAME`（只影响 `<meta name="description">`） |
| Dock plist | `persistent-apps` 里的 `file-label` + `_CFURLString`（保留 GUID，不会重排图标） |
| `~/Library/LaunchAgents/local.shiri.*.plist` | `Label` + 所有绝对路径（模板在 `runtime/launchagents/`） |
| 顶层目录 | `app/`（程序本体）、`runtime/`（运行环境），配置里所有绝对路径同步改 |

**换 bundle id 会丢偏好**（`local.riliben.app` → `local.shiri.app` 是两个域）：
`widgetMode / widgetPreferred / widgetFrame / widgetFrameVersion / normalFrame` 要手动搬。

```bash
for k in normalFrame widgetPreferred widgetMode widgetFrameVersion; do
  defaults write local.shiri.app "$k" -string "$(defaults read local.riliben.app $k)"
done
```

坐标是字符串（`"{{14, 103}, {1040, 790}}"`），**必须加 `-string`**，否则 `defaults` 会按 plist 语法解析失败。

## 白标：把所有上游品牌标识换成 `shiri`

这不只是改个显示名。后端是从上游派生来的，标识散在代码里，全部换掉了（下表的
「旧」一列一律写作「上游 X」，具体字符串由 `--from` 参数传入，脚本里不存）：

| 旧 | 新 | 说明 |
| --- | --- | --- |
| 上游命名空间 | `Shiri\` | PHP 命名空间、类名、静态调用（881 个文件、7422 处） |
| 上述名字派生的类 | `ShiriEvent` / `ShiriException` / `UpdateShiri` | 类名与**文件名**同步 |
| 上游的 JSON-RPC 前缀 | `shiri.rpc.X.Y.z` | PHP 与前端两侧必须一起改 |
| 上游的 JS 全局 | `window.shiri` | PHP 注入 + 上游 bundle 两端同步 |
| 上游的帮助/文档外链 | `*.shiri.local` | 界面已无入口，指本地免误跳 |
| 上游泳的日志文件与 id 前缀 | `shiri-*` | 日志文件名等 |
| 上游的顶层目录名 | `app/` / `runtime/` | 顶层目录 |
| 本项目早期的前端资源名 | `public/dist/shiri-*` | |
| 旧的 launchd 标签 | `local.shiri.*` | plist 收进 `runtime/launchagents/` |
| 旧的 mariadb socket | `/tmp/mariadb_shiri.sock` | |
| 旧的库名 / 账号 | `shiri` | `bash runtime/rename-db.sh`（旧名从 .env 读，要 sudo，见下） |

一次性脚本：`python3 tools/whitelabel.py --from <旧品牌名>`（幂等，`--dry-run` 先看规模）。
**升级上游后必须再跑一次**——`tools/restore.sh` 铺进来的是带原品牌名的原始文件。

### 改的时候踩到的四个坑

1. **自动加载器的前缀长度**。`vendor/composer/autoload_static.php` 里有
   `'上游名\\' => 9`（PSR-4 前缀长度），`Shiri\` 是 6；而且它被放在按首字母分的
   `'L'` 桶里，改名后得挪到 `'S'` 桶。桶只是"这个首字母有没有 PSR-4 前缀"的开关，
   放错桶时**类映射仍然能靠 classmap 命中**，只有 classmap 里没有的新类
   （比如我们自己加的 `ShowTodos`）会突然 500——很容易误判成业务代码的问题。
2. **`autoload_classmap.php` 里混着 latin-1 字节**（`©`）。按 UTF-8 读会抛
   `UnicodeDecodeError`，脚本静默跳过整份文件，于是 721 条类映射留着重名前缀。
   读文件要 `errors="surrogateescape"`。
3. **缓存里存的是绝对路径**。改目录名后整站 500，报 `Template global::layouts.app not found`
   ——清 `storage/framework/{viewPaths,composerPaths}.php`、`views/*.php`、
   `cache/installation/data` 即恢复（连同 `bootstrap/cache/*.php`）。
4. **数据库里也有两处**：`zp_work_structure_elements.domain_reference` 存的是类路径
   （形如「上游名\Domain\Tickets」），改名后要 `update ... replace(domain_reference,'上游名','Shiri')`，
   否则画布/目标页面找不到模块。

### 数据库改名要你自己跑

MariaDB 的 `root` 只允许 unix_socket 认证，沙箱里拿不到 sudo：

```bash
bash runtime/rename-db.sh
```

它建 `shiri` 库 → 把表 `RENAME` 过去（只动元数据，秒级）→ 建 `shiri` 账号授权 →
改 `.env` → 清缓存 → 验证 200 → **最后才**删旧库和旧账号。任一步失败就停，旧库不动。

### 仍然不同的名字（不是漏改）

- `LEAN_*` 环境变量前缀（`.env` 与 `config/*.php` 成对出现，不含品牌全名）
- `zp_` 表前缀、`/calendar/*` 路由
- `NOTICE.md` 里的上游署名（AGPL-3.0 要求保留）

## 组成

```
app/                   PHP 后端（Shiri\\ 命名空间，含 vendor）
runtime/
  nginx.conf           nginx 配置，只监听回环（127.0.0.1:8080 + [::1]:8080）
  php-fpm.conf         php-fpm，127.0.0.1:9000
  start.sh stop.sh     手动启停（launchd 已注册时自动走 launchctl）
  install-autostart.sh 注册/迁移 launchd（local.shiri.*）
  launchagents/        launchd plist 模板（__BASE__ 由脚本替换）
  desktop/main.swift   原生窗口外壳源码（WKWebView + 桌面浮层）
  desktop/build.sh     编译 + 打包成 ~/Applications/拾日.app
  desktop/make-icon.py App 图标生成器（PIL，超采样）
  screenshots/         各页面与浮层的截图（离屏 WKWebView 拍的）
  logs/                nginx / mariadb 日志
```

## 自动登录

打开窗口无需输入密码，靠两层机制：

1. `app/config/.env` 里 `LEAN_SESSION_EXPIRATION = 525600`（1 年），
   登录态 cookie 长期有效，Window 的 WebKit 数据目录会持久保存它。
2. `LEAN_AUTOLOGIN_USER = '2033948701@qq.com'`：即使 cookie 被清掉，
   `app/Core/Middleware/AuthCheck.php` 的 `maybeAutoLogin()` 也会在
   **请求来自回环地址**时直接建立该用户的会话。

安全边界：nginx 只绑回环（IPv4 + IPv6 两个），所以免密入口只对本机进程可见；
局域网/外网访问不到，也就不存在"别人也能免密进"的问题。之所以同时监听 `[::1]`：
`BASE_URL` 用的是 `localhost`，macOS 会先解析到 `::1`，只绑 `127.0.0.1` 时
WebKit 取 CSS/JS 可能直接失败（页面变成裸样式）。

关掉自动登录：把 `.env` 里 `LEAN_AUTOLOGIN_USER` 整行注释掉，重启 php-fpm
（或 `launchctl kickstart -k gui/$(id -u)/local.shiri.phpfpm`），即恢复密码登录。

## 日历快速删除（本机补丁）

官方日历删一条内容要「点开条目 → 弹窗里点删除 → 再确认」。本机加了两处快捷方式：

- **鼠标悬停**任一日程条目，右上角出现红色 **×**，点一下 → 原生确认框 → 删掉
- **右键**条目，同样直接走删除确认

规则：事件（个人日程）调 `Calendar.Calendar.delEvent`；待办（项目任务）调
`Tickets.Tickets.delete`，删除后不可恢复。外部日历（ICS 订阅）条目保持只读，不挂按钮。

实现：`app/public/dist/js/shiri-quickdelete.js`（独立文件，不在发行包清单里，
升级不会覆盖）+ `app/Domain/Calendar/Templates/showMyCalendar.blade.php` 里一行 `<script>`。
升级上游后模板会被覆盖，需要把那行标签加回去。

窗口 App 里确认框是原生 sheet，依赖 `main.swift` 里的
`runJavaScriptConfirmPanelWithMessage`；若换成浏览器访问则用浏览器自带 confirm。

## 日历页排版微调（本机）

`app/public/dist/css/shiri-calendar.css`，由日历模板的
`@push('styles')` 引入，只影响日历页：

- 日历高度改成 `calc(100vh - 112px)`，并在 `.maincontentinner.lt-content` 上补
  `min-height: calc(100vh - 66px)`——原来容器是内容撑高的，日历会提前 80px 收住，底部留一大块白
- 左侧「日历 / 图例」栏 240px → 184px（组件把宽度写在内联 `--lt-content-left-w` 上，
  所以要覆盖 `.lt-content-side--left` 的 width）
- 工具条 `flex-wrap: nowrap`，不再把「今天 ‹ › 导出」折成两行
- 条目（chip）：圆角 6px、内边距、行高统一；纯时间条目用 `color-mix(currentColor 9%)`
  兑出淡底色，右侧标题省略号截断；月视图里隐藏时间（一格只有 ~120px，时间会挤掉标题），
  日/周视图的时间照常显示
- 日期数字 13px/600、今天高亮、上下月日期淡化

想调回原样：删掉模板里那行 `<link>` 即可。

## 图标与标记

**App 图标**：奶油色圆角方上排 3×3 的月历格子，**中间那格被拈起来**——放大到 1.75 倍、转 -12°、
带投影，填成陶土色。读法：从一整月的格子里把今天单独拿出来。

```
┌──────────┐
│  ▢ ▢ ▢   │   底：奶油渐变 #FDFAF5 → #F0E8DC
│  ▢ ▨ ▢   │   格：#221C19 @ 16%（实心块，不是线框）
│  ▢ ▢ ▢   │   抬起块：#D2673E → #AC4224，1.75× 格宽，-12°
└──────────┘
```

- 生成器 `desktop/make-icon.py`（PIL，无字体依赖），**4 倍超采样再降采样**，1024 直出会有锯齿
- 格子用**实心块**而不是线框：线框在 64pt 以下会连成一片灰网，实心块至少还是「几个点」
- 格子浓度 16%（`CELL_ALPHA=40`）。10% 太淡、64pt 就整体糊掉；再深会抢抬起那块的注意力
- 抬起块 1.75× 格宽、-12°：1.95× 会把四周格子压得只剩碎片，1.65× 又看不出「被拈起来」。
  这三个数是拿 256/96/48 三档并排比出来的
- 投影是「纯色层 + 同形状蒙版 + 高斯模糊」，不是 PIL 的 `ImageDraw.shadow`——
  后者在小尺寸下会糊成一块脏边

导航里的 `dist/img/shiri-mark.svg` 是同一套几何（3×3 + 倾斜块），18px 显示。
小尺寸下淡格子会退成背景纹理，主要靠陶土块撑识别度，这是这个设计在 18px 的固有取舍。

改图标：`python3 desktop/make-icon.py /tmp/x.png` 看效果 → `bash desktop/build.sh`
（脚本会自己调它、切 iconset、重新签名、刷新 LaunchServices）→ `killall Dock` 换程序坞图标。

截图：`screenshots/图标.png`。

## 程序坞图标

Dock 里的「拾日」条目写在 `~/Library/Preferences/com.apple.dock.plist` 的
`persistent-apps` 里。**踩过的坑**：用 `defaults write ... -array-add` 传
`file:///...%E6%97%A5%E7%A8%8B...app/` 会被再编码一次（`%` → `%25`），
路径因此不存在，Dock 只能显示一个**问号**、鼠标悬停的标题还是
`%E6%97%A5%E7%A8%8B%E6%9C%AC` 这种原始编码串。

正确做法：直接用 plistlib 改 `persistent-apps`，条目里给中文 `file-label` +
单次编码的 `_CFURLString`（`_CFURLStringType = 15`），然后 `killall cfprefsd; killall Dock`。
改之前先备份 plist（Dock 崩溃会重置成默认布局）。

## 静态资源缓存

模板里的 CSS/JS 版本号是 `?v={{ $ltV('/dist/css/xxx.css') }}`，取的是**文件修改时间**
（`$ltV` 在模板开头的 `@php` 块里）。改完样式/脚本立刻生效，不用手动改版本号。

这件事踩过两次坑：后端的响应没有 `Cache-Control`，WebKit 用启发式缓存留着旧 CSS，
于是**新 HTML 配了旧 CSS**——从"列表版"换"月历版"时，`.rb-w-week` 的 7 列网格规则没生效，
42 个日期竖着堆成一列。另外 `nginx.conf` 里也给静态资源加了 `Cache-Control: no-cache`
（每次条件请求，命中就 304），算第二道保险。

## 升级上游时注意

`AuthCheck.php` 的 `maybeAutoLogin()` 是对程序本体的一处补丁，覆盖安装新版前请先备份该文件，
升级后把 `maybeAutoLogin()` 方法 + `authenticateWeb()` 里的那行调用重新加回去。

另外这些**新增**文件不在发行包清单里，升级不会被动，可以原样留着：

- `app/Domain/Calendar/Controllers/` 下 `ShowTodos.php`、`ShowStats.php`、`DesktopWidget.php`
- `app/Domain/Calendar/Support/TodoBoard.php`
- `app/Domain/Calendar/Templates/` 下三个页面模板 + `partials/`
- `app/Views/Templates/layouts/widget.blade.php`
- `public/dist/css/` 与 `public/dist/js/` 下的 `shiri-*` 文件

但**被覆盖**的模板要重新加回引用：`layouts/app.blade.php`（顶栏）、
`sections/header.blade.php`（shiri-base.css）、`showMyCalendar.blade.php`（两行 script/link）。

## 数据口径说明

- 待办的「本周」= 本周一到周日（与服务端 `CarbonInterface::SUNDAY` 一致）
- 「本周 / 本月完成」按待办最后的更新时间统计：框架没有单独的完成时间字段，
  标记完成后只要不再改动，数字就是准的（页面上也写了这句）
- 数据库存 UTC，界面按 `Asia/Shanghai` 显示；`0000-00-00` / `1969-12-31` 是框架
  的空日期哨兵值，统一当作「无截止日」
