<?php

namespace Shiri\Domain\Calendar\Support;

use Shiri\Core\Db\Db;

/**
 * 右侧栏「重要时间节点」的数据层
 *
 * 单独一张 zp_milestones，不碰上游任何表。表不存在时自动建并放四条示例
 * （只在首次建表时放，之后用户删光了也不会自己长回来）。
 *
 * 一个节点就三个要素：名称、可选的英文名、目标日期。倒计时天数在渲染时算，
 * 不落库，跨天、跨时区都不会留下过期数据。
 */
class MilestoneBoard
{
    /** 倒计时按本机日期算；服务端 PHP 可能是 UTC，显式钉到用户所在时区 */
    public const TZ = 'Asia/Shanghai';

    public function __construct(private Db $db) {}

    private function conn()
    {
        return $this->db->getConnection();
    }

    /** @return array<int, object> 按日期升序 */
    public function all(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();

            return $this->conn()->table('zp_milestones')
                ->where('userId', $userId)
                ->orderBy('targetDate')
                ->orderBy('id')
                ->get()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $data  title / titleEn / date */
    public function create(int $userId, array $data): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $title = trim((string) ($data['title'] ?? ''));
        $date = trim((string) ($data['date'] ?? ''));
        if ($title === '' || ! self::validDate($date)) {
            return 0;
        }

        try {
            $this->ensureSchema();

            return (int) $this->conn()->table('zp_milestones')->insertGetId([
                'userId' => $userId,
                'title' => mb_substr($title, 0, 120),
                'titleEn' => mb_substr(trim((string) ($data['titleEn'] ?? '')), 0, 120),
                'targetDate' => $date,
            ]);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function delete(int $userId, int $id): void
    {
        if ($userId <= 0 || $id <= 0) {
            return;
        }

        try {
            $this->ensureSchema();
            $this->conn()->table('zp_milestones')
                ->where('userId', $userId)
                ->where('id', $id)
                ->delete();
        } catch (\Throwable) {
            // 删不掉就留着，页面不因此报错
        }
    }

    public static function validDate(string $date): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone(self::TZ));

        return $d !== false && $d->format('Y-m-d') === $date;
    }

    private function ensureSchema(): void
    {
        $conn = $this->conn();
        $found = $conn->select(
            "SELECT COUNT(*) AS n FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'zp_milestones'"
        );
        $isNew = ((int) ($found[0]->n ?? 0)) === 0;

        $conn->statement("
            CREATE TABLE IF NOT EXISTS zp_milestones (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                userId INT NOT NULL,
                title VARCHAR(120) NOT NULL,
                titleEn VARCHAR(120) NOT NULL DEFAULT '',
                targetDate DATE NOT NULL,
                createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_date (userId, targetDate)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if ($isNew) {
            $this->seed();
        }
    }

    /** 首次打开就给出四种状态的样例，标题带「示例」前缀，随手可删 */
    private function seed(): void
    {
        $tz = new \DateTimeZone(self::TZ);
        $today = new \DateTimeImmutable('today', $tz);
        $samples = [
            ['示例 · 近期节点', 'Sample · Coming up', 21],
            ['示例 · 明年此时', 'Sample · Next year', 400],
            ['示例 · 遥远未来', 'Sample · Far out', 1500],
            ['示例 · 已经过去', 'Sample · Passed', -10],
        ];

        $rows = [];
        foreach ($samples as [$title, $titleEn, $offset]) {
            $rows[] = [
                'userId' => (int) session('userdata.id'),
                'title' => $title,
                'titleEn' => $titleEn,
                'targetDate' => $today->modify(($offset >= 0 ? '+' : '').$offset.' days')->format('Y-m-d'),
            ];
        }

        try {
            $this->conn()->table('zp_milestones')->insert($rows);
        } catch (\Throwable) {
            // 示例插不进去不影响功能
        }
    }
}
