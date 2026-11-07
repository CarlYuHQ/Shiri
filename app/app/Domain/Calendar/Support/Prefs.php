<?php

namespace Shiri\Domain\Calendar\Support;

use Shiri\Core\Db\Db;

/**
 * 「拾日」自己的用户偏好（语言 / 时段主题 / 卡片不透明度 / 一周起始日）
 *
 * 为什么不用 Shiri 自带的 user settings：那边是给官方前端用的键值结构，
 * 混进去以后升级容易互相踩。这里单独一张 zp_rb_prefs，键值都是我们自己定的。
 */
class Prefs
{
    public const LANG = 'lang';

    public const THEME = 'theme';

    public const CARD_ALPHA = 'cardAlpha';

    public const WEEK_START = 'weekStart';

    /** 默认值：中文、跟随时间、66% 不透明度、周日起始（对齐 macOS 日历） */
    public const DEFAULTS = [
        self::LANG => 'zh',
        self::THEME => 'auto',
        self::CARD_ALPHA => '0.66',
        self::WEEK_START => 'sun',
    ];

    public function __construct(private Db $db) {}

    private function ensureSchema(): void
    {
        $this->db->getConnection()->statement("
            CREATE TABLE IF NOT EXISTS zp_rb_prefs (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                userId INT NOT NULL,
                k VARCHAR(32) NOT NULL,
                v VARCHAR(200) NOT NULL DEFAULT '',
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_key (userId, k)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * 某个用户的全部偏好（缺的用默认值补齐）
     *
     * @return array<string, string>
     */
    public function all(int $userId): array
    {
        $out = self::DEFAULTS;
        if ($userId <= 0) {
            return $out;
        }

        try {
            $this->ensureSchema();
            $rows = $this->db->getConnection()->table('zp_rb_prefs')
                ->where('userId', $userId)
                ->get();

            foreach ($rows as $row) {
                $key = (string) $row->k;
                if (array_key_exists($key, $out)) {
                    $out[$key] = (string) $row->v;
                }
            }
        } catch (\Throwable) {
            // 表建不出来就退回默认值，页面不因此报错
        }

        return $out;
    }

    public function get(int $userId, string $key, ?string $default = null): string
    {
        return $this->all($userId)[$key] ?? ($default ?? (self::DEFAULTS[$key] ?? ''));
    }

    /** 只接受白名单里的键与值，避免写进乱七八糟的东西 */
    public function set(int $userId, string $key, string $value): void
    {
        if ($userId <= 0 || ! array_key_exists($key, self::DEFAULTS)) {
            return;
        }

        $value = match ($key) {
            self::LANG => in_array($value, ['zh', 'en'], true) ? $value : 'zh',
            self::THEME => in_array($value, ['auto', 'dawn', 'day', 'noon', 'dusk', 'night'], true) ? $value : 'auto',
            self::CARD_ALPHA => (string) max(0.15, min(0.95, (float) $value)),
            self::WEEK_START => in_array($value, ['sun', 'mon'], true) ? $value : 'sun',
            default => $value,
        };

        try {
            $this->ensureSchema();
            $this->db->getConnection()->table('zp_rb_prefs')->updateOrInsert(
                ['userId' => $userId, 'k' => $key],
                ['v' => $value]
            );
        } catch (\Throwable) {
            // 写不进去就算了，下次打开还是旧值
        }
    }
}
