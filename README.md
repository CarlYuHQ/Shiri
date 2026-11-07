<div align="center">

<img src="runtime/screenshots/logo.png" width="160" alt="Shiri app icon">

# Shiri

**A month calendar pinned to the desktop — and the full schedule behind it.**

[**English**](README.md) | [中文](README_CN.md)

[![GitHub stars](https://img.shields.io/github/stars/CarlYuHQ/Shiri?style=social)](https://github.com/CarlYuHQ/Shiri/stargazers)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](NOTICE.md)
[![Platform](https://img.shields.io/badge/platform-macOS-black)](runtime/desktop/main.swift)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4)](https://www.php.net/)

</div>

---

Calendar apps have gotten heavy: sign-in screens, dashboards, project management, team invites — when all I want is to see what is on this month. Shiri goes the other way. It opens straight into a calendar with no extra screens, and pins a translucent month card to the desktop, next to the wallpaper, so a glance is enough.

- **Five views** — Calendar / Todos / Plans / Stats / Settings. Todos, recurring plans and stats share one data path, so checkbox, overdue and streak logic is written once.
- **Desktop widget** — sits above desktop icons but below normal windows; drag it from any empty spot, and its palette follows the daylight (dawn / day / noon / dusk / night).
- **Native shell** — Swift + WKWebView wrapped into a macOS window, the icon built programmatically, and local services (nginx / php-fpm / MariaDB, loopback only) managed by launchd.

<div align="center">
  <img src="runtime/screenshots/桌面浮层.png" width="760" alt="Shiri desktop widget">
</div>

## Views

|  | View | Details |
|--|------|---------|
| **Calendar** | Opens straight into the calendar | Month grid + event dialog; add / today / prev-next in the toolbar are all custom |
| **Todos** | Check to complete | Grouped by day; hover for ×, right-click to delete, overdue items flagged |
| **Plans** | Recurring tasks without cron | Generates the next 21 days on page open, deduplicated by a unique key — missed days are backfilled |
| **Stats** | See your rhythm at a glance | Completions, overdue, streaks; measured by each todo's last-updated time |
| **Settings** | Everything adjustable | UI language (中文 / English), time-of-day theme, card opacity, week start |
| **Milestones** | Important dates at the right | A sidebar next to the calendar: countdown in days, grouped into coming up / further out / passed |
| **Desktop widget** | A calendar beside the wallpaper | Translucent card with a live opacity slider; settings sync within 30 seconds |

| Calendar | Todos |
| --- | --- |
| ![Calendar](runtime/screenshots/日历页.png) | ![Todos](runtime/screenshots/待办页.png) |
| **Plans** | **Stats** |
| ![Plans](runtime/screenshots/计划页.png) | ![Stats](runtime/screenshots/统计页.png) |

## Quick Start

Built for one machine: clone, lay down an upstream release, configure the database, start the services.

```bash
# 1. Lay a clean upstream release into app/
#    --ignore-existing fills gaps without overwriting files this project changed
bash tools/restore.sh /path/to/upstream-release.tar.gz
#    Then re-run the whitelabel pass (pass the old brand name yourself)
python3 tools/whitelabel.py --from <upstream-name>

# 2. Database config
cp app/config/sample.env app/config/.env
#    LEAN_DB_HOST / LEAN_DB_USER / LEAN_DB_PASSWORD / LEAN_DB_DATABASE

# 3. Dependencies and schema
cd app && composer install --no-dev
#    Open it once in a browser to finish setup, or import the official schema

# 4. Start the services
bash runtime/start.sh          # default http://localhost:8080

# 5. (optional) autostart + native app bundle
bash runtime/install-autostart.sh
bash runtime/desktop/build.sh
```

Custom tables (`zp_recurring`, `zp_recurring_log`, `zp_rb_prefs`) are created by `CREATE TABLE IF NOT EXISTS` in code — no extra import needed.

**Not in the repo** (large / contains credentials / regenerable):

| Excluded | How to restore |
| --- | --- |
| `app/vendor/` (183 MB) | `composer install --no-dev` |
| Upstream assets under `app/public/assets` and `public/dist` (75 MB) | Overlay from the official 3.13.2 release |
| `app/config/.env` | Copy `app/config/sample.env`, see above |
| `storage/`, `userfiles/`, `logs/`, `runtime/tmp/` | Generated at runtime |

## Project Structure

```
app/
  app/                     PHP backend (Shiri\ namespace, all project changes)
  public/dist/css|js|img/  only the front-end files written by this project
runtime/
  desktop/main.swift       macOS native shell (WKWebView + desktop widget)
  desktop/build.sh         builds the native .app bundle
  desktop/make-icon.py     app icon generator
  nginx.conf php-fpm.conf  local service config (loopback only)
  start.sh stop.sh         manual start / stop
  install-autostart.sh     registers launchd agents (local.shiri.*)
  launchagents/            launchd plist templates
  README.md                implementation notes: pitfalls, trade-offs, data semantics (the main doc)
tools/
  restore.sh               lays down an upstream release
  whitelabel.py            brand replacement (--from <upstream-name>)
```

## Notable Implementation Details

- **Backend logic untouched**: Calendar, Todos, Plans and Stats all read from one file, `app/Domain/Calendar/Support/TodoBoard.php`; controllers only pass parameters.
- **Two i18n layers**: rewritten templates use our own `Support/Lang.php` dictionary, while the calendar page and dialogs still use the framework's `__()`. `Lang::applyToShiri()` aligns them before render — otherwise you get "English nav, Chinese calendar".
- **Two separate webviews**: the widget polls server preferences every 30 seconds and reloads itself when they drift.
- **Whitelabel is replayable**: PHP namespace, class names, RPC prefix, JS global and database account are all renamed by an idempotent script — re-run it after any upstream upgrade.

For the deeper pitfalls (desktop window level and non-activating panels, window frame migration, which layer a CSS variable must live on, icon legibility at 32pt...) see [`runtime/README.md`](runtime/README.md).

## License

The backend under `app/` is derived from an AGPL-3.0 open-source framework (attribution and origin in [`NOTICE.md`](NOTICE.md)); the original license text is kept with the release bundle. Everything written for this project (native shell, front end, icon, whitelabel script) is for personal use on this machine, with no separate license.
