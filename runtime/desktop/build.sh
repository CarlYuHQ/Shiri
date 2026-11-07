#!/bin/bash
# 构建「拾日」原生窗口 App（WKWebView 外壳）
# 产物：~/Applications/拾日.app
set -euo pipefail

NAME="${1:-拾日}"
BASE=/Users/carl/WorkBuddy/2026-11-06-12-16-22
RT=$BASE/runtime
SRC=$RT/desktop/main.swift
ICON_GEN=$RT/desktop/make-icon.py
BUILD=$RT/desktop/build
APP="$HOME/Applications/$NAME.app"

# 可执行文件名用 ASCII，避免中文路径在某些系统调用里出问题
EXE=shiri
BUNDLE_ID=local.shiri.app

mkdir -p "$BUILD"

echo "[1/4] 编译 Swift…"
swiftc -O -target arm64-apple-macos13.0 \
  -sdk "$(xcrun --show-sdk-path)" \
  "$SRC" -o "$BUILD/$EXE" \
  -framework Cocoa -framework WebKit
echo "      完成 $(du -h "$BUILD/$EXE" | cut -f1)"

echo "[2/4] 生成图标…"
# 图标用 Python(PIL) 画 4 倍超采样再降采样，纯图形没有字体依赖
python3 "$ICON_GEN" "$BUILD/icon-1024.png"
ICONSET=$BUILD/App.iconset
rm -rf "$ICONSET"; mkdir -p "$ICONSET"
for pair in "icon_16x16:16" "icon_16x16@2x:32" "icon_32x32:32" "icon_32x32@2x:64" \
            "icon_128x128:128" "icon_128x128@2x:256" "icon_256x256:256" \
            "icon_256x256@2x:512" "icon_512x512:512" "icon_512x512@2x:1024"; do
  name="${pair%%:*}"; px="${pair##*:}"
  sips -z "$px" "$px" "$BUILD/icon-1024.png" --out "$ICONSET/$name.png" >/dev/null
done
iconutil -c icns "$ICONSET" -o "$BUILD/App.icns"
echo "      $(du -h "$BUILD/App.icns" | cut -f1)"

echo "[3/4] 组装 app bundle…"
rm -rf "$APP"
mkdir -p "$APP/Contents/MacOS" "$APP/Contents/Resources"
cp "$BUILD/$EXE" "$APP/Contents/MacOS/$EXE"
cp "$BUILD/App.icns" "$APP/Contents/Resources/App.icns"

cat > "$APP/Contents/Info.plist" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleDevelopmentRegion</key><string>zh_CN</string>
    <key>CFBundleExecutable</key><string>$EXE</string>
    <key>CFBundleIdentifier</key><string>$BUNDLE_ID</string>
    <key>CFBundleInfoDictionaryVersion</key><string>6.0</string>
    <key>CFBundleName</key><string>$NAME</string>
    <key>CFBundleDisplayName</key><string>$NAME</string>
    <key>CFBundleIconFile</key><string>App</string>
    <key>CFBundlePackageType</key><string>APPL</string>
    <key>CFBundleShortVersionString</key><string>1.0</string>
    <key>CFBundleVersion</key><string>1</string>
    <key>LSMinimumSystemVersion</key><string>13.0</string>
    <key>LSApplicationCategoryType</key><string>public.app-category.productivity</string>
    <key>NSHighResolutionCapable</key><true/>
    <key>NSPrincipalClass</key><string>NSApplication</string>
    <key>NSSupportsAutomaticGraphicsSwitching</key><true/>
    <key>NSAppTransportSecurity</key>
    <dict><key>NSAllowsLocalNetworking</key><true/></dict>
</dict>
</plist>
PLIST

echo "[4/4] 签名 + 刷新 LaunchServices…"
codesign --force --deep --sign - "$APP" 2>&1 | sed 's/^/      /' || true
touch "$APP"
/System/Library/Frameworks/CoreServices.framework/Frameworks/LaunchServices.framework/Support/lsregister -f "$APP" || true

echo
echo "✅ 已生成 $APP"
