<?php

namespace Shiri\Domain\Calendar\Support;

use Carbon\CarbonInterface;

/**
 * 桌面小卡的「时段主题」
 *
 * 卡片底色跟一天里的光线走：清晨偏暖粉、白天近白、黄昏偏橙紫、夜晚转深蓝。
 * 四套主题只在 CSS 里定义（`.rb-widget[data-theme=…]` 覆盖一组变量），
 * 这里只负责「现在该用哪一套」以及给人看的名字/图标。
 *
 * 服务端先算一次是为了首屏不闪：页面一打开就是对的颜色，
 * 之后由 shiri-board.js 按同样的规则每隔几分钟重算（跨过时段的边界时自动换）。
 */
class CardTheme
{
    public const DAWN = 'dawn';

    public const DAY = 'day';

    public const NOON = 'noon';

    public const DUSK = 'dusk';

    public const NIGHT = 'night';

    /**
     * 全部可选主题（设置页里要列出来）
     *
     * @return array<string, array{label: string, icon: string}>
     */
    public static function all(): array
    {
        return [
            self::DAWN => ['label' => Lang::t('theme.dawn'), 'icon' => '🌅'],
            self::DAY => ['label' => Lang::t('theme.day'), 'icon' => '☀️'],
            self::NOON => ['label' => Lang::t('theme.noon'), 'icon' => '🌞'],
            self::DUSK => ['label' => Lang::t('theme.dusk'), 'icon' => '🌇'],
            self::NIGHT => ['label' => Lang::t('theme.night'), 'icon' => '🌙'],
        ];
    }

    /**
     * 按钟点判断该用哪套主题
     *
     * 5–7 清晨、8–11 白天、11–14 正午、14–17 白天、17–19 黄昏、其余夜晚。
     */
    public static function forHour(int $hour): string
    {
        if ($hour >= 5 && $hour < 8) {
            return self::DAWN;
        }

        if ($hour >= 11 && $hour < 14) {
            return self::NOON;
        }

        if ($hour >= 8 && $hour < 17) {
            return self::DAY;
        }

        if ($hour >= 17 && $hour < 19) {
            return self::DUSK;
        }

        return self::NIGHT;
    }

    /**
     * 解析最终主题：$override 为空或 'auto' 时按当前时间走
     *
     * @return array{key: string, label: string, icon: string, auto: bool}
     */
    public static function resolve(?string $override, ?CarbonInterface $now = null): array
    {
        $now ??= dtHelper()->userNow();
        $autoKey = self::forHour((int) $now->format('G'));
        $isAuto = $override === null || $override === '' || $override === 'auto';
        $key = $isAuto || ! array_key_exists($override, self::all()) ? $autoKey : $override;

        return [
            'key' => $key,
            'label' => self::all()[$key]['label'],
            'icon' => self::all()[$key]['icon'],
            'auto' => $isAuto,
        ];
    }
}
