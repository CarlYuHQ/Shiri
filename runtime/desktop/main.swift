// 拾日 —— 把本地 Web 服务包成一个原生 macOS 窗口
// 构建：见同目录 build.sh
import Cocoa
import WebKit

// MARK: - 常量

private let kBaseURL = URL(string: "http://localhost:8080/")!
private let kHomeURL = kBaseURL
private let kWidgetURL = URL(string: "http://localhost:8080/calendar/desktopWidget")!
private let kTodosURL = URL(string: "http://localhost:8080/calendar/showTodos")!
private let kRuntimeDir = "/Users/carl/WorkBuddy/2026-11-06-12-16-22/runtime"
private let kLocalHosts: Set<String> = ["localhost", "127.0.0.1", "::1"]
private let kAppTitle = "拾日"
private let kInnerScheme = "shiri"

/// 桌面浮层：无边框、置顶、可拖动、整体半透明的小卡。
private enum WidgetDefaults {
    static let enabled = "widgetMode"
    static let preferred = "widgetPreferred"
    static let frame = "widgetFrame"
    static let frameVersion = "widgetFrameVersion"
    static let normalFrame = "normalFrame"
}

/// 浮层默认尺寸/位置变过几次，旧值会一直留在偏好里（用户没重新拖就永远是旧的）。
/// 每次调默认值就把这里 +1，旧的记录作废，重新回到默认位置。
private let kWidgetFrameVersion = 3

/// 默认尺寸：贴近「桌面大日历」的观感。
/// 1728×1117 下占左侧约 60% 宽、从顶部小组件下方一直到程序坞上方。
private let kDefaultWidgetSize = NSSize(width: 1040, height: 790)

/// 默认贴左边、压在屏幕左上角那排小组件（天气/时钟）下面。
/// 桌面小组件没有可查询的 API，这里按它们在 macOS 上的默认高度留出空间；
/// 用户拖过一次之后就按拖的位置走。
private let kDefaultWidgetLeftInset: CGFloat = 14
private let kDefaultWidgetTopInset: CGFloat = 224


/// 桌面小卡所在的层级：桌面图标之上、普通 App 窗口之下。
/// 意思就是「贴在壁纸上」，切到别的 App 时会被它盖住，而不是飘在所有窗口最上面。
private var kDesktopWidgetLevel: NSWindow.Level {
    NSWindow.Level(rawValue: Int(CGWindowLevelForKey(.desktopIconWindow)) + 1)
}



// MARK: - 启动辅助

/// 幂等启动后端（launchd 已注册时走 kickstart，否则手动拉起三个进程）
private func launchBackend() {
    let p = Process()
    p.executableURL = URL(fileURLWithPath: "/bin/bash")
    p.arguments = ["\(kRuntimeDir)/start.sh"]
    p.standardOutput = FileHandle.nullDevice
    p.standardError = FileHandle.nullDevice
    try? p.run()          // 不等待，避免卡住 UI
}

/// 浮层用的窗口。
///
/// 用 NSPanel 而不是 NSWindow，是因为浮层要贴在**桌面层**（桌面图标之上、普通窗口
/// 之下），而普通窗口在这一层被点击时，系统会先把 App 激活到前台、再把第一下点击
/// 吞掉 —— 效果是"点一下没反应，还把正在用的 App 挤掉"。NSPanel 配
/// `.nonactivatingPanel` 可以做到点击不激活 App、事件直接落到面板里。
///
/// 无边框窗口默认 canBecomeKey = false，这里放开，好让 ⌘D 之类的快捷键和输入还能用。
final class WidgetWindow: NSPanel {
    override var canBecomeKey: Bool { true }
}

// MARK: - AppDelegate

@MainActor
final class AppDelegate: NSObject, NSApplicationDelegate {
    private var window: WidgetWindow!
    private var webView: WKWebView!
    private var overlay: NSView!
    private var spinner: NSProgressIndicator!
    private var statusLabel: NSTextField!
    private var retries = 0
    private let maxRetries = 25

    /// 浮层模式
    private var isWidgetMode = false
    private var normalFrame: NSRect?
    private var dragMonitors: [Any] = []
    /// App 不在前台时 WKWebView 不派发 DOM hover；定时读全局鼠标坐标注入页面
    private var hoverTimer: Timer?
    private var hoverInside = false
    private var lastHoverPoint: NSPoint?
    private var lastHoverPushAt = Date.distantPast
    /// 遮挡判定的节流缓存：鼠标不动时也要能感知「有窗口滑到卡片上方」
    private var lastOcclusionAt = Date.distantPast
    private var lastOcclusionPoint = NSPoint(x: -1_000_000, y: -1_000_000)
    private var lastOcclusionHit = false
    private var dragMouseStart: NSPoint = .zero
    private var dragWindowStart: NSPoint = .zero

    /// 从浮层切到主界面后，关掉主界面应该回到浮层（否则 App 还在跑却什么也看不见）
    private var restoreWidgetOnClose = false

    /// 下一次页面加载完把光标放进录入框（小卡「＋」→ 待办页）
    private var focusAddFieldPending = false

    /// 启动时刻。用 `open` 拉起 App 时，系统紧跟着会补一个 reopen 事件，
    /// 那个不该被当成「用户点了程序坞图标」，否则启动即还原的小卡会立刻被翻成主窗口。
    private let launchTime = Date()

    private var widgetMenuItem: NSMenuItem?
    private var opacityMenuItems: [NSMenuItem] = []
    private var minimizeMenuItem: NSMenuItem?
    private var zoomMenuItem: NSMenuItem?

    // MARK: 生命周期

    func applicationDidFinishLaunching(_ notification: Notification) {
        buildMenu()
        buildWindow()
        buildWebView()
        launchBackend()

        window.makeKeyAndOrderFront(nil)
        NSApp.activate(ignoringOtherApps: true)

        // 用过浮层的人：启动就把小卡还原到桌面（这是"常驻桌面"的东西，重启不该消失）。
        // 想开主界面，点程序坞图标即可 —— applicationShouldHandleReopen 会切过去，
        // 关掉主界面又回到小卡。首次使用（没进过浮层）则直接给主界面。
        if UserDefaults.standard.bool(forKey: WidgetDefaults.preferred) {
            restoreWidgetOnClose = true
            enterWidgetMode(animated: false)
        } else {
            load(kHomeURL)
        }
    }

    /// 关窗不退出：桌面浮层是「一直待在那儿」的东西，误按 ⌘W 就把 App 整个关掉
    /// 会让桌面上的东西凭空消失。窗口关了点 Dock 图标就能回来。
    func applicationShouldTerminateAfterLastWindowClosed(_ sender: NSApplication) -> Bool { false }

    func applicationShouldHandleReopen(_ sender: NSApplication, hasVisibleWindows flag: Bool) -> Bool {
        // 点程序坞图标 = 要主界面。浮层窗口在桌面层（被其他 App 的窗口盖着），
        // 单纯 makeKeyAndOrderFront 看不出来，所以先退出浮层再提到前台。
        showMainWindow()
        return true
    }

    func applicationWillTerminate(_ notification: Notification) {
        flushNormalFrame()
        persistFrame()
    }

    func applicationSupportsSecureRestorableState(_ app: NSApplication) -> Bool { true }

    // MARK: 界面搭装

    private func buildWindow() {
        let rect = NSRect(x: 0, y: 0, width: 1320, height: 860)
        window = WidgetWindow(
            contentRect: rect,
            styleMask: [.titled, .closable, .miniaturizable, .resizable, .fullSizeContentView],
            backing: .buffered,
            defer: false
        )
        window.title = kAppTitle
        window.minSize = NSSize(width: 900, height: 600)
        // 普通模式下它就是个普通窗口：不浮动、不按需成为 key
        window.isFloatingPanel = false
        window.becomesKeyOnlyIfNeeded = false
        window.hidesOnDeactivate = false
        window.titlebarAppearsTransparent = false
        window.tabbingMode = .disallowed
        window.delegate = self
        window.isReleasedWhenClosed = false

        // NSWindow 默认 acceptsMouseMovedEvents = false：窗口不是 key 时系统不送
        // mouseMoved，WKWebView 就不会派发 DOM 的 mouseover，悬浮详情卡因此
        // 「必须先点一下窗口才出现」。让窗口无论是否激活都接收鼠标移动事件。
        window.acceptsMouseMovedEvents = true

        // 位置自己记（UserDefaults），不用 setFrameAutosaveName：
        // 浮层模式会把窗口改成很小的尺寸，自动保存会把它当成「上次的大窗口尺寸」记下来，
        // 下次普通模式启动就只剩 900×600 的最小尺寸。
        if let raw = UserDefaults.standard.string(forKey: WidgetDefaults.normalFrame) {
            let saved = NSRectFromString(raw)
            window.setFrame(saved.width > 400 ? saved : rect, display: false)
        } else {
            window.setFrame(rect, display: false)
            window.center()
        }

        let content = NSView(frame: window.contentLayoutRect)
        content.autoresizingMask = [.width, .height]
        window.contentView = content
    }

    private func buildWebView() {
        let cfg = WKWebViewConfiguration()
        // 持久化 cookie / localStorage：登录态（以及主题、语言等偏好）跨启动保留
        cfg.websiteDataStore = .default()
        cfg.applicationNameForUserAgent = "ShiriDesktop/1.0"
        cfg.defaultWebpagePreferences.allowsContentJavaScript = true
        if #available(macOS 12.0, *) {
            cfg.preferences.isElementFullscreenEnabled = true
        }

        // 浮层模式下窗口要跟着鼠标走：WKWebView 会把 mousedown 全吃掉，
        // isMovableByWindowBackground 对它无效，所以由页面通知原生端开始拖动。
        cfg.userContentController.add(self, name: kInnerScheme)
        cfg.userContentController.addUserScript(WKUserScript(
            source: Self.dragScript,
            injectionTime: .atDocumentEnd,
            forMainFrameOnly: true
        ))

        webView = WKWebView(frame: window.contentView!.bounds, configuration: cfg)
        webView.autoresizingMask = [.width, .height]
        webView.navigationDelegate = self
        webView.uiDelegate = self
        webView.allowsBackForwardNavigationGestures = true
        webView.setValue(false, forKey: "drawsBackground")   // 避免加载时白闪
        window.contentView!.addSubview(webView)

        // 加载浮层
        let host = window.contentView!.bounds
        overlay = NSView(frame: host)
        overlay.autoresizingMask = [.width, .height]
        overlay.wantsLayer = true
        overlay.layer?.backgroundColor = NSColor.windowBackgroundColor.cgColor
        overlay.layer?.cornerCurve = .continuous

        spinner = NSProgressIndicator()
        spinner.style = .spinning
        spinner.controlSize = .regular
        spinner.isIndeterminate = true
        spinner.translatesAutoresizingMaskIntoConstraints = false

        statusLabel = NSTextField(labelWithString: "正在启动拾日…")
        statusLabel.font = .systemFont(ofSize: 12)
        statusLabel.textColor = .secondaryLabelColor
        statusLabel.alignment = .center
        statusLabel.translatesAutoresizingMaskIntoConstraints = false

        overlay.addSubview(spinner)
        overlay.addSubview(statusLabel)
        NSLayoutConstraint.activate([
            spinner.centerXAnchor.constraint(equalTo: overlay.centerXAnchor),
            spinner.centerYAnchor.constraint(equalTo: overlay.centerYAnchor),
            statusLabel.centerXAnchor.constraint(equalTo: overlay.centerXAnchor),
            statusLabel.topAnchor.constraint(equalTo: spinner.bottomAnchor, constant: 14),
        ])
        window.contentView!.addSubview(overlay)

        // App 不在前台 / 窗口不是 key 时，WebKit 不维护 hover 状态：mouseover
        // 不派发、光标也不变手型，悬浮详情卡就变成「必须先点一下、再等一会」。
        // 事件监听（含 tracking area）在这种情况下都不可靠，直接定时读全局
        // 鼠标坐标 NSEvent.mouseLocation，换算成 DOM 坐标后注入页面命中测试。
        hoverTimer = Timer.scheduledTimer(timeInterval: 0.05, target: self,
                                          selector: #selector(pollHoverTick),
                                          userInfo: nil, repeats: true)
    }

    // MARK: 桌面浮层

    /// 注入到页面的拖动探针：只在桌面小卡上生效，按在背景空白处即开始拖窗口。
    private static let dragScript = """
    document.addEventListener('mousedown', function (e) {
        if (e.button !== 0) { return; }
        if (!document.body.classList.contains('rb-widget-body')) { return; }
        if (e.target.closest('input, button, a, select, textarea, .rb-check')) { return; }
        try {
            window.webkit.messageHandlers.\(kInnerScheme).postMessage({ action: 'drag' });
        } catch (err) {}
    }, true);
    """

    private func persistFrame() {
        guard isWidgetMode else { return }
        let f = window.frame
        UserDefaults.standard.set(NSStringFromRect(f), forKey: WidgetDefaults.frame)
    }

    /// 普通窗口的位置/尺寸（拖动时防抖，避免每移动一像素写一次偏好）
    private var normalFrameSaveWork: DispatchWorkItem?

    private func persistNormalFrame() {
        guard !isWidgetMode else { return }
        normalFrameSaveWork?.cancel()

        let item = DispatchWorkItem { [weak self] in
            guard let self, !self.isWidgetMode else { return }
            UserDefaults.standard.set(NSStringFromRect(self.window.frame), forKey: WidgetDefaults.normalFrame)
        }
        normalFrameSaveWork = item
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.5, execute: item)
    }

    private func flushNormalFrame() {
        guard !isWidgetMode else { return }
        normalFrameSaveWork?.cancel()
        UserDefaults.standard.set(NSStringFromRect(window.frame), forKey: WidgetDefaults.normalFrame)
    }

    private func savedWidgetFrame() -> NSRect {
        // 版本不符 = 记的是旧版默认尺寸，直接丢掉重来（否则改再大的默认值也不生效）
        let storedVersion = UserDefaults.standard.integer(forKey: WidgetDefaults.frameVersion)
        if storedVersion != kWidgetFrameVersion {
            UserDefaults.standard.removeObject(forKey: WidgetDefaults.frame)
            UserDefaults.standard.set(kWidgetFrameVersion, forKey: WidgetDefaults.frameVersion)
        } else if let raw = UserDefaults.standard.string(forKey: WidgetDefaults.frame) {
            let rect = NSRectFromString(raw)
            if rect.width > 200, rect.height > 160 {
                return rect
            }
        }

        // 默认贴左上：左边留 14pt、顶边让开上面那排桌面小组件。
        // 用 visibleFrame 兜住程序坞/菜单栏，换显示器也不会掉到屏幕外面。
        guard let screen = NSScreen.main else {
            return NSRect(x: 200, y: 200, width: kDefaultWidgetSize.width, height: kDefaultWidgetSize.height)
        }
        let vf = screen.visibleFrame
        let w = kDefaultWidgetSize.width
        let h = kDefaultWidgetSize.height
        let x = vf.minX + kDefaultWidgetLeftInset
        let yTop = screen.frame.maxY - kDefaultWidgetTopInset
        let y = max(vf.minY + 12, yTop - h)

        return NSRect(x: x, y: y, width: w, height: h)
    }

    private func setOverlayBackground(clear: Bool) {
        overlay.layer?.backgroundColor = clear
            ? NSColor.clear.cgColor
            : NSColor.windowBackgroundColor.cgColor
    }

    private func enterWidgetMode(animated: Bool = true) {
        guard !isWidgetMode else { return }
        isWidgetMode = true

        normalFrame = window.frame
        UserDefaults.standard.set(NSStringFromRect(window.frame), forKey: WidgetDefaults.normalFrame)

        window.styleMask = [.borderless, .resizable, .nonactivatingPanel]
        window.isOpaque = false
        window.backgroundColor = .clear
        window.hasShadow = false
        window.isFloatingPanel = false
        window.becomesKeyOnlyIfNeeded = true
        window.hidesOnDeactivate = false
        window.level = kDesktopWidgetLevel
        window.collectionBehavior = [.canJoinAllSpaces, .stationary, .ignoresCycle]
        window.isMovableByWindowBackground = true
        // 浮层永远不是 key window，这条是悬浮能生效的前提
        window.acceptsMouseMovedEvents = true
        // 整窗 alpha 会连文字一起变淡，糊成一片；不透明度交给页面里的
        // --rb-card-alpha（只影响玻璃底）。这里恒为 1。
        window.alphaValue = 1
        window.minSize = NSSize(width: 340, height: 380)

        webView.setValue(false, forKey: "drawsBackground")
        webView.layer?.backgroundColor = NSColor.clear.cgColor
        setOverlayBackground(clear: true)

        window.setFrame(savedWidgetFrame(), display: true, animate: animated)
        // 桌面层 + 非激活面板：makeKeyAndOrderFront 在这里不一定把窗口 front 出来
        // （App 可能不在前台，而面板又不想抢焦点），用 orderFrontRegardless 保证可见。
        window.orderFrontRegardless()
        load(kWidgetURL)

        UserDefaults.standard.set(true, forKey: WidgetDefaults.enabled)
        UserDefaults.standard.set(true, forKey: WidgetDefaults.preferred)
        updateMenuStates()
    }

    private func exitWidgetMode(landingURL: URL? = nil) {
        guard isWidgetMode else { return }
        isWidgetMode = false
        endWindowDrag()

        window.styleMask = [.titled, .closable, .miniaturizable, .resizable, .fullSizeContentView]
        window.becomesKeyOnlyIfNeeded = false
        window.isFloatingPanel = false
        window.isOpaque = true
        window.backgroundColor = .windowBackgroundColor
        window.hasShadow = true
        window.level = .normal
        window.collectionBehavior = []
        window.isMovableByWindowBackground = false
        window.alphaValue = 1
        window.minSize = NSSize(width: 900, height: 600)
        window.title = kAppTitle

        webView.setValue(false, forKey: "drawsBackground")
        setOverlayBackground(clear: false)

        let target = UserDefaults.standard.string(forKey: WidgetDefaults.normalFrame)
            .map { NSRectFromString($0) } ?? NSRect(x: 0, y: 0, width: 1320, height: 860)
        window.setFrame(target.width > 400 ? target : NSRect(x: 0, y: 0, width: 1320, height: 860),
                        display: true, animate: true)

        load(landingURL ?? kHomeURL)

        UserDefaults.standard.set(false, forKey: WidgetDefaults.enabled)
        updateMenuStates()
    }

    @objc private func toggleWidgetMode() {
        if isWidgetMode {
            exitWidgetMode()
        } else {
            enterWidgetMode()
        }
    }

    @objc private func openNormalWindow() {
        showMainWindow()
    }

    /// 从浮层（或隐藏状态）切回主界面：程序坞点击、⌘N、小卡上的 ↗ 都走这里。
    ///
    /// 退出浮层时记住「想要浮层」，这样关掉主界面能自动回到桌面小卡 ——
    /// 否则 App 关窗不退出，就变成一个「还在跑但什么都看不见」的进程。
    private func showMainWindow(landing: URL? = nil) {
        if isWidgetMode {
            restoreWidgetOnClose = true
            exitWidgetMode(landingURL: landing)
        } else if landing != nil {
            load(landing!)
        }

        // 浮层是 .nonactivatingPanel，光 orderFront 不会把 App 提到前台
        NSApp.activate(ignoringOtherApps: true)
        window.makeKeyAndOrderFront(nil)
    }

    @objc private func increaseOpacity() { adjustOpacity(+0.05) }
    @objc private func decreaseOpacity() { adjustOpacity(-0.05) }

    /// ⌥⌘- / ⌥⌘=：让卡片玻璃底透明一点 / 实一点（±5%），由页面写 CSS 变量 + localStorage
    private func adjustOpacity(_ delta: Double) {
        guard isWidgetMode else { return }
        webView.evaluateJavaScript("window.ShiriCard && ShiriCard.step(\(delta))") { _, _ in }
    }


    // 拖动：页面在 mousedown 时通知过来，之后靠本地事件监听跟手移动
    private func beginWindowDrag() {
        guard isWidgetMode else { return }
        endWindowDrag()

        dragMouseStart = NSEvent.mouseLocation
        dragWindowStart = window.frame.origin

        let monitor = NSEvent.addLocalMonitorForEvents(matching: [.leftMouseDragged, .leftMouseUp]) { [weak self] event in
            guard let self else { return event }
            if event.type == .leftMouseUp {
                self.endWindowDrag()
                return nil
            }
            let now = NSEvent.mouseLocation
            self.window.setFrameOrigin(NSPoint(
                x: self.dragWindowStart.x + (now.x - self.dragMouseStart.x),
                y: self.dragWindowStart.y + (now.y - self.dragMouseStart.y)
            ))
            return nil
        }
        if let monitor { dragMonitors.append(monitor) }
    }

    private func endWindowDrag() {
        guard !dragMonitors.isEmpty else { return }
        dragMonitors.forEach { NSEvent.removeMonitor($0) }
        dragMonitors.removeAll()
        persistFrame()
    }

    private func updateMenuStates() {
        widgetMenuItem?.state = isWidgetMode ? .on : .off
        widgetMenuItem?.title = isWidgetMode ? "退出桌面浮层" : "桌面浮层"
        // 浮层没有标题栏，「最小化 / 缩放」对它是无意义的动作
        minimizeMenuItem?.isEnabled = !isWidgetMode
        zoomMenuItem?.isEnabled = !isWidgetMode
        // 不透明度只对桌面浮层有意义（主窗口是不透明窗口）
        opacityMenuItems.forEach { $0.isEnabled = isWidgetMode }
    }

    // MARK: 悬浮坐标注入

    @objc private func pollHoverTick() { pollHover() }

    /// 让页面收起详情框：鼠标真的离开卡片，或者卡片被别的窗口盖住，都走这里。
    private func releaseHover() {
        guard hoverInside else { return }
        hoverInside = false
        lastHoverPoint = nil
        webView.evaluateJavaScript("window.__shiriPointerLeave && window.__shiriPointerLeave()") { _, _ in }
    }

    /// 屏幕点是否被「别的窗口」盖住。
    ///
    /// 走 `NSWindow.windowNumber(at:belowWindowWithWindowNumber:)`：窗口服务器直接
    /// 回答「这一点最前面的是哪个窗口」，跨 App 有效，而且**不需要录屏权限**
    /// （`CGWindowListCopyWindowInfo` 在没有录屏授权时只会返回本 App 的窗口，
    /// 拿它判遮挡会永远判成「没被挡」，等于没修）。
    /// 返回 0 表示该点没有任何窗口，按未遮挡处理——宁可保留悬浮，也不要整块失灵。
    private func hoverObscured(at screenPoint: NSPoint) -> Bool {
        let now = Date()
        let moved = abs(screenPoint.x - lastOcclusionPoint.x) >= 1
                 || abs(screenPoint.y - lastOcclusionPoint.y) >= 1
        // 鼠标不动时 100ms 查一次即可：窗口滑到卡片上方也还能跟上
        if !moved, now.timeIntervalSince(lastOcclusionAt) < 0.1 { return lastOcclusionHit }

        lastOcclusionAt = now
        lastOcclusionPoint = screenPoint
        lastOcclusionHit = computeObscured(at: screenPoint)
        if UserDefaults.standard.bool(forKey: "hoverDebug") {
            appendHoverLog("OCCLU \(lastOcclusionHit ? "covered" : "clear")"
                + " screen=(\(Int(screenPoint.x)),\(Int(screenPoint.y)))")
        }
        return lastOcclusionHit
    }

    private func computeObscured(at screenPoint: NSPoint) -> Bool {
        let top = NSWindow.windowNumber(at: screenPoint, belowWindowWithWindowNumber: 0)
        guard top != 0 else { return false }
        return top != window.windowNumber
    }

    private func pollHover() {
        guard window.isVisible, !webView.isHidden else { return }

        let screenPoint = NSEvent.mouseLocation      // 全局屏幕坐标，左下原点
        let frame = window.frame
        let inside = screenPoint.x >= frame.minX && screenPoint.x <= frame.maxX
                  && screenPoint.y >= frame.minY && screenPoint.y <= frame.maxY

        if !inside {
            releaseHover()
            return
        }

        // 别的 App 的窗口压在小卡上面时，全局坐标依旧落在小卡矩形里。
        // 只看坐标就会「鼠标在别人的窗口上，底下的卡片却弹出详情框」——
        // 小卡本来就在窗口下层，视觉上已经被盖住了，这种情况按离开处理。
        if hoverObscured(at: screenPoint) {
            releaseHover()
            return
        }

        // 鼠标没动就别再打 JS 桥：透明浮层每次 evaluateJavaScript 都会触发一次
        // 内容重绘，20Hz 空转会让窗口看起来在抖。
        // 节流用「时间戳」而不是「上一次调用的回调」：回调一旦丢失（页面导航、
        // WebProcess 忙）单飞闸门会永久卡住，症状就是只有第一次悬浮有效。
        let now = Date()
        guard now.timeIntervalSince(lastHoverPushAt) >= 0.05 else { return }
        if let last = lastHoverPoint,
           abs(last.x - screenPoint.x) < 1, abs(last.y - screenPoint.y) < 1 { return }

        let inWindow = window.convertPoint(fromScreen: screenPoint)
        let p = webView.convert(inWindow, from: nil)
        guard p.x >= 0, p.y >= 0, p.x <= webView.bounds.width, p.y <= webView.bounds.height else { return }

        // 只有真正发起注入才更新去重基准，避免上面的 guard 提前吃掉一帧
        lastHoverPoint = screenPoint
        lastHoverPushAt = now
        hoverInside = true

        // convert 出来的是「相对 webView 顶部」的点；DOM 的 y 要再减掉视口顶部被
        // 遮挡的高度（主窗口的标题栏），并按页面缩放折算成 CSS 像素。
        // 少减这一下，注入点会比光标低 32px —— 鼠标得放在条目上方一格才命中。
        let scale = viewportScale > 0 ? viewportScale : 1
        let x = p.x / scale
        let y = max(0, p.y - viewportInsetTop) / scale
        let debug = UserDefaults.standard.bool(forKey: "hoverDebug")
        if debug {
            appendHoverLog("SEND screen=(\(Int(screenPoint.x)),\(Int(screenPoint.y))) dom=(\(Int(x)),\(Int(y)))"
                + " inset=\(Int(viewportInsetTop)) active=\(NSApp.isActive)")
        }
        webView.evaluateJavaScript(
            String(format: "window.__shiriPointerAt && window.__shiriPointerAt(%.1f, %.1f)", x, y)
        ) { [weak self] result, error in
            guard let self, UserDefaults.standard.bool(forKey: "hoverDebug") else { return }
            let hit = (result as? String) ?? (error != nil ? "ERR:\(error!.localizedDescription)" : "nil")
            self.appendHoverLog("GOT  \(hit)")
            self.logGeometryOnce()
        }
    }

    /// 一次性打印坐标系关键量：window 与 web 的关系、safeArea、以及页面的 innerWidth/Height。
    /// webView 顶部是否与 window 顶部重合、WebKit 有没有给标题栏留「遮挡内缩」，
    /// 决定注入坐标要不要再减一个偏移。
    private var geometryLogged = false
    private func logGeometryOnce() {
        guard !geometryLogged else { return }
        geometryLogged = true
        webView.evaluateJavaScript("[window.innerWidth, window.innerHeight, window.devicePixelRatio].join(',')") { [weak self] r, _ in
            guard let self else { return }
            let js = (r as? String) ?? "-"
            let frame = self.window.frame
            let cb = self.webView.bounds.size
            self.appendHoverLog(
                "DIAG mode=\(self.isWidgetMode ? "widget" : "window") frame=\(Int(frame.width))x\(Int(frame.height))"
                + " web=\(Int(cb.width))x\(Int(cb.height))"
                + " safeTop=\(Int(self.webView.safeAreaInsets.top))"
                + " viewportScale=\(self.viewportScale) viewportInsetTop=\(self.viewportInsetTop)"
                + " webInWindow=\(self.webView.convert(self.webView.bounds, to: nil))"
                + " js(w,h,dpr)=\(js)"
            )
        }
    }

    // MARK: 视口换算
    //
    // 主窗口是 .fullSizeContentView：webView 顶部与窗口顶部重合（webInWindow 原点为
    // (0,0)），但 WebKit 会把 DOM 视口整体下移一个「被标题栏遮挡」的高度抵消掉
    // ——实测窗口 1320×860、safeAreaInsets.top=32、window.innerHeight=828。
    // 于是 webView 的 y 比 DOM 的 y 多算了 32px：注入后页面往下多看一格，
    // 表现为「鼠标要放在条目上方 32px 处（通常是日期那一行）浮窗才出现」。
    // 这里在页面加载完成后量一次视口尺寸，换算成缩放比例与顶部偏移，注入前校正。
    // 浮层是 borderless（视口高度 == webView 高度），量出来偏移就是 0，天然正确。

    private var viewportScale: CGFloat = 1
    private var viewportInsetTop: CGFloat = 0

    private func measureViewport() {
        let size = webView.bounds.size
        webView.evaluateJavaScript("[window.innerWidth, window.innerHeight].join(',')") { [weak self] result, _ in
            guard let self, let raw = result as? String else { return }
            let parts = raw.split(separator: ",").compactMap { Double($0) }
            guard parts.count == 2, parts[0] > 0, parts[1] > 0 else { return }
            let scale = size.width / CGFloat(parts[0])
            guard scale > 0 else { return }
            self.viewportScale = scale
            // 视口高度换成 webView 的点之后，与 webView 高度之差即顶部被遮挡的高度
            let inset = size.height - CGFloat(parts[1]) * scale
            self.viewportInsetTop = max(0, min(inset, 120))
            if UserDefaults.standard.bool(forKey: "hoverDebug") {
                self.appendHoverLog("VIEWPORT web=\(Int(size.width))x\(Int(size.height))"
                    + " js=(\(raw)) scale=\(scale) inset=\(inset) -> used \(self.viewportInsetTop)")
            }
        }
    }

    private func appendHoverLog(_ line: String) {
        let path = "/tmp/shiri-hover.log"
        let data = (line + "\n").data(using: .utf8) ?? Data()
        if let fh = FileHandle(forWritingAtPath: path) {
            fh.seekToEndOfFile()
            fh.write(data)
            fh.closeFile()
        } else {
            try? data.write(to: URL(fileURLWithPath: path))
        }
    }

    // MARK: 加载

    private func currentURL() -> URL {
        isWidgetMode ? kWidgetURL : kHomeURL
    }

    private func load(_ url: URL) {
        // 本机服务，每次都绕过 WKWebView 的启发式缓存：
        // 否则改完模板，窗口还拿着旧 HTML 配新 CSS，页面看不出变化
        webView.load(URLRequest(url: url, cachePolicy: .reloadIgnoringLocalCacheData, timeoutInterval: 20))
    }

    private func showOverlay(_ text: String) {
        statusLabel.stringValue = text
        overlay.isHidden = false
        spinner.startAnimation(nil)
    }

    private func hideOverlay() {
        spinner.stopAnimation(nil)
        overlay.isHidden = true
    }

    private func scheduleRetry(_ reason: String) {
        guard retries < maxRetries else {
            hideOverlay()
            showConnectionFailure()
            return
        }
        retries += 1
        showOverlay("正在等待服务就绪…（第 \(retries) 次）")
        if retries % 5 == 0 { launchBackend() }
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.2) { [weak self] in
            guard let self else { return }
            self.load(self.currentURL())
        }
    }

    private func showConnectionFailure() {
        let html = """
        <!doctype html><meta charset="utf-8">
        <style>
          body{font:14px -apple-system,Helvetica,sans-serif;color:#444;background:#fafafa;
               display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center}
          h2{font-weight:600;margin:0 0 10px;color:#222}
          code{background:#eee;padding:2px 6px;border-radius:4px}
          p{margin:6px 0;line-height:1.6}
        </style>
        <div>
          <h2>连不上本地服务</h2>
          <p>请检查后台服务是否在运行：</p>
          <p><code>bash \(kRuntimeDir)/start.sh</code></p>
          <p>然后按 <b>⌘R</b> 重新加载。</p>
        </div>
        """
        webView.loadHTMLString(html, baseURL: nil)
    }

    // MARK: 菜单

    private func buildMenu() {
        let main = NSMenu()

        // 应用菜单
        let appItem = NSMenuItem()
        let appMenu = NSMenu()
        appMenu.addItem(withTitle: "关于 \(kAppTitle)", action: #selector(NSApplication.orderFrontStandardAboutPanel(_:)), keyEquivalent: "")
        appMenu.addItem(.separator())
        appMenu.addItem(withTitle: "隐藏 \(kAppTitle)", action: #selector(NSApplication.hide(_:)), keyEquivalent: "h")
        let hideOthers = appMenu.addItem(withTitle: "隐藏其他", action: #selector(NSApplication.hideOtherApplications(_:)), keyEquivalent: "h")
        hideOthers.keyEquivalentModifierMask = [.command, .option]
        appMenu.addItem(withTitle: "显示全部", action: #selector(NSApplication.unhideAllApplications(_:)), keyEquivalent: "")
        appMenu.addItem(.separator())
        appMenu.addItem(withTitle: "退出 \(kAppTitle)", action: #selector(NSApplication.terminate(_:)), keyEquivalent: "q")
        appItem.submenu = appMenu
        main.addItem(appItem)

        // 编辑（WKWebView 里的输入框依赖这些 first responder 动作）
        let editItem = NSMenuItem()
        let editMenu = NSMenu(title: "编辑")
        editMenu.addItem(withTitle: "撤销", action: Selector(("undo:")), keyEquivalent: "z")
        let redo = editMenu.addItem(withTitle: "重做", action: Selector(("redo:")), keyEquivalent: "z")
        redo.keyEquivalentModifierMask = [.command, .shift]
        editMenu.addItem(.separator())
        editMenu.addItem(withTitle: "剪切", action: #selector(NSText.cut(_:)), keyEquivalent: "x")
        editMenu.addItem(withTitle: "拷贝", action: #selector(NSText.copy(_:)), keyEquivalent: "c")
        editMenu.addItem(withTitle: "粘贴", action: #selector(NSText.paste(_:)), keyEquivalent: "v")
        editMenu.addItem(withTitle: "全选", action: #selector(NSText.selectAll(_:)), keyEquivalent: "a")
        editItem.submenu = editMenu
        main.addItem(editItem)

        // 显示
        let viewItem = NSMenuItem()
        let viewMenu = NSMenu(title: "显示")
        viewMenu.addItem(withTitle: "重新加载", action: #selector(reloadPage), keyEquivalent: "r")
        viewMenu.addItem(withTitle: "返回", action: #selector(goBack), keyEquivalent: "[")
        viewMenu.addItem(withTitle: "前进", action: #selector(goForward), keyEquivalent: "]")
        viewMenu.addItem(.separator())
        viewMenu.addItem(withTitle: "放大", action: #selector(zoomIn), keyEquivalent: "+")
        viewMenu.addItem(withTitle: "缩小", action: #selector(zoomOut), keyEquivalent: "-")
        viewMenu.addItem(withTitle: "实际大小", action: #selector(zoomReset), keyEquivalent: "0")
        viewMenu.addItem(.separator())
        let lessAlpha = viewMenu.addItem(withTitle: "浮层更透明", action: #selector(decreaseOpacity), keyEquivalent: "-")
        lessAlpha.keyEquivalentModifierMask = [.command, .option]
        let moreAlpha = viewMenu.addItem(withTitle: "浮层更不透明", action: #selector(increaseOpacity), keyEquivalent: "=")
        moreAlpha.keyEquivalentModifierMask = [.command, .option]
        opacityMenuItems = [lessAlpha, moreAlpha]
        viewMenu.addItem(withTitle: "打开拾日窗口", action: #selector(openNormalWindow), keyEquivalent: "n")
        viewMenu.addItem(.separator())
        let full = viewMenu.addItem(withTitle: "进入全屏幕", action: #selector(NSWindow.toggleFullScreen(_:)), keyEquivalent: "f")
        full.keyEquivalentModifierMask = [.command, .control]
        viewItem.submenu = viewMenu
        main.addItem(viewItem)

        // 窗口
        let winItem = NSMenuItem()
        let winMenu = NSMenu(title: "窗口")
        let widget = winMenu.addItem(withTitle: "桌面浮层", action: #selector(toggleWidgetMode), keyEquivalent: "d")
        widget.keyEquivalentModifierMask = [.command]
        widgetMenuItem = widget
        winMenu.addItem(.separator())
        minimizeMenuItem = winMenu.addItem(withTitle: "最小化", action: #selector(NSWindow.miniaturize(_:)), keyEquivalent: "m")
        zoomMenuItem = winMenu.addItem(withTitle: "缩放", action: #selector(NSWindow.performZoom(_:)), keyEquivalent: "")
        winMenu.addItem(.separator())
        winMenu.addItem(withTitle: "在浏览器中打开", action: #selector(openInBrowser), keyEquivalent: "o")
        winMenu.addItem(withTitle: "把窗口前置", action: #selector(NSApplication.arrangeInFront(_:)), keyEquivalent: "")
        winItem.submenu = winMenu
        main.addItem(winItem)

        NSApp.mainMenu = main
    }

    // MARK: 菜单动作

    @objc private func reloadPage() {
        retries = 0
        launchBackend()
        showOverlay("正在重新加载…")
        webView.reload()
    }

    @objc private func goBack() { if webView.canGoBack { webView.goBack() } }
    @objc private func goForward() { if webView.canGoForward { webView.goForward() } }

    @objc private func zoomIn() {
        webView.pageZoom = min(webView.pageZoom + 0.1, 3.0)
    }

    @objc private func zoomOut() {
        webView.pageZoom = max(webView.pageZoom - 0.1, 0.5)
    }

    @objc private func zoomReset() { webView.pageZoom = 1.0 }

    @objc private func openInBrowser() {
        NSWorkspace.shared.open(webView.url ?? kHomeURL)
    }
}

// MARK: - 导航与窗口行为

extension AppDelegate: WKNavigationDelegate {
    func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
        if overlay.isHidden { showOverlay("正在加载…") }
    }

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        retries = 0
        hideOverlay()
        // 页面换了（主窗口 / 浮层 / 尺寸变化）就重量一次视口，悬浮注入靠它换算坐标
        measureViewport()
        if let title = webView.title, !title.isEmpty, title != kAppTitle {
            window.title = "\(title) — \(kAppTitle)"
        } else {
            window.title = kAppTitle
        }

        // 从小卡点「＋」进来的：直接把光标放进录入框，省一次点击
        if focusAddFieldPending {
            focusAddFieldPending = false
            webView.evaluateJavaScript("document.querySelector('.rb-quickadd-text')?.focus()") { _, _ in }
        }
    }

    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        scheduleRetry(error.localizedDescription)
    }

    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        // -1004 连不上 / -1001 超时：属于"服务还没起来"，重试
        let ns = error as NSError
        if ns.code == -1004 || ns.code == -1001 || ns.code == -1003 {
            scheduleRetry(error.localizedDescription)
        } else {
            hideOverlay()
        }
    }

    func webView(_ webView: WKWebView, decidePolicyFor navigationAction: WKNavigationAction,
                 decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        guard let url = navigationAction.request.url else {
            decisionHandler(.allow); return
        }

        // 站点内的下载（导出附件等）
        if navigationAction.shouldPerformDownload {
            decisionHandler(.download); return
        }

        // 小卡上的站内动作（shiri://full 之类），不产生真实导航
        if url.scheme == kInnerScheme {
            switch url.host {
            case "full":
                openNormalWindow()
            case "add":
                // 小卡上点「＋」：切回普通窗口并直接落在待办页，光标就在录入框里
                focusAddFieldPending = true
                showMainWindow(landing: kTodosURL)
            default:
                break
            }
            decisionHandler(.cancel); return
        }

        // 外部链接交给系统浏览器，窗口保持是拾日
        if let host = url.host, !kLocalHosts.contains(host), url.scheme != "about" {
            NSWorkspace.shared.open(url)
            decisionHandler(.cancel); return
        }

        decisionHandler(.allow)
    }

    func webView(_ webView: WKWebView, navigationAction: WKNavigationAction, didBecome download: WKDownload) {
        download.delegate = self
    }

    func webView(_ webView: WKWebView, navigationResponse: WKNavigationResponse, didBecome download: WKDownload) {
        download.delegate = self
    }
}

extension AppDelegate: WKUIDelegate {
    // target="_blank" / window.open
    func webView(_ webView: WKWebView, createWebViewWith configuration: WKWebViewConfiguration,
                 for navigationAction: WKNavigationAction, windowFeatures: WKWindowFeatures) -> WKWebView? {
        guard let url = navigationAction.request.url else { return nil }
        if let host = url.host, kLocalHosts.contains(host) {
            webView.load(navigationAction.request)     // 站内：本窗口打开
        } else {
            NSWorkspace.shared.open(url)               // 站外：系统浏览器
        }
        return nil
    }

    func webViewDidClose(_ webView: WKWebView) {}

    // 页面上的 confirm() —— 日历"快速删除"靠它确认。不实现这个代理方法时
    // WebKit 会直接当成取消，点了删除也没反应。
    func webView(_ webView: WKWebView, runJavaScriptConfirmPanelWithMessage message: String,
                 initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping (Bool) -> Void) {
        let alert = NSAlert()
        let lines = message.components(separatedBy: "\n").filter { !$0.trimmingCharacters(in: .whitespaces).isEmpty }
        alert.messageText = lines.first ?? "确认操作"
        alert.informativeText = lines.dropFirst().joined(separator: "\n")
        alert.alertStyle = .warning
        alert.addButton(withTitle: "确定")
        alert.addButton(withTitle: "取消")

        if let w = window {
            alert.beginSheetModal(for: w) { response in
                completionHandler(response == .alertFirstButtonReturn)
            }
        } else {
            completionHandler(alert.runModal() == .alertFirstButtonReturn)
        }
    }

    // prompt() 同样走原生输入框，避免页面里调用时静默返回 null
    func webView(_ webView: WKWebView, runJavaScriptTextInputPanelWithPrompt prompt: String,
                 defaultText: String?, initiatedByFrame frame: WKFrameInfo,
                 completionHandler: @escaping (String?) -> Void) {
        let alert = NSAlert()
        alert.messageText = prompt
        alert.addButton(withTitle: "确定")
        alert.addButton(withTitle: "取消")

        let field = NSTextField(frame: NSRect(x: 0, y: 0, width: 260, height: 24))
        field.stringValue = defaultText ?? ""
        alert.accessoryView = field

        let handle: (NSApplication.ModalResponse) -> Void = { response in
            completionHandler(response == .alertFirstButtonReturn ? field.stringValue : nil)
        }

        if let w = window {
            alert.beginSheetModal(for: w, completionHandler: handle)
        } else {
            handle(alert.runModal())
        }
    }

    // 页面上的 alert 之类的 JS 弹窗，交给 WebKit 默认处理（返回 nil 即自动允许）
    func webView(_ webView: WKWebView, runJavaScriptAlertPanelWithMessage message: String,
                 initiatedByFrame frame: WKFrameInfo, completionHandler: @escaping () -> Void) {
        let alert = NSAlert()
        alert.messageText = message
        alert.addButton(withTitle: "好")
        if let w = window { alert.beginSheetModal(for: w) { _ in completionHandler() } } else { completionHandler() }
    }
}

extension AppDelegate: WKDownloadDelegate {
    func download(_ download: WKDownload, decideDestinationUsing response: URLResponse,
                  suggestedFilename: String, completionHandler: @escaping (URL?) -> Void) {
        let dir = FileManager.default.urls(for: .downloadsDirectory, in: .userDomainMask).first!
        var target = dir.appendingPathComponent(suggestedFilename)
        var n = 1
        let base = target.deletingPathExtension().lastPathComponent
        let ext = target.pathExtension
        while FileManager.default.fileExists(atPath: target.path) {
            let name = ext.isEmpty ? "\(base) (\(n))" : "\(base) (\(n)).\(ext)"
            target = dir.appendingPathComponent(name)
            n += 1
        }
        completionHandler(target)
    }

    func downloadDidFinish(_ download: WKDownload) {}

    func download(_ download: WKDownload, didFailWithError error: Error, resumeData: Data?) {
        let alert = NSAlert()
        alert.messageText = "下载失败"
        alert.informativeText = error.localizedDescription
        alert.runModal()
    }
}

// MARK: - 浮层拖动 / 尺寸记忆

extension AppDelegate: WKScriptMessageHandler {
    /// 页面按在空白处 → 开始拖动浮层窗口
    func userContentController(_ userContentController: WKUserContentController, didReceive message: WKScriptMessage) {
        guard message.name == kInnerScheme else { return }
        guard let body = message.body as? [String: Any],
              let action = body["action"] as? String else { return }

        if action == "drag" {
            beginWindowDrag()
        }
    }
}

extension AppDelegate: NSWindowDelegate {
    func windowDidEndLiveResize(_ notification: Notification) {
        persistFrame()
        persistNormalFrame()
        // 视口高度变了，悬浮注入的坐标换算基准要跟着重量一次
        measureViewport()
    }

    func windowDidMove(_ notification: Notification) {
        persistNormalFrame()
    }

    /// ⌘W 关掉主界面：如果是从浮层点进来的，就把小卡放回桌面，
    /// 而不是留一个「进程还在、界面上什么都没有」的状态。
    func windowWillClose(_ notification: Notification) {
        guard restoreWidgetOnClose, !isWidgetMode else { return }
        restoreWidgetOnClose = false
        DispatchQueue.main.async { [weak self] in
            self?.enterWidgetMode(animated: false)
        }
    }
}

// MARK: - 入口

// main.swift 的顶层代码在 Swift 6 下不是 MainActor 隔离的，显式声明在主线程执行
MainActor.assumeIsolated {
    let app = NSApplication.shared
    let delegate = AppDelegate()
    app.delegate = delegate
    app.setActivationPolicy(.regular)
    app.run()
}
