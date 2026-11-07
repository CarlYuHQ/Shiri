<?php

namespace Shiri\Domain\Calendar\Services;

use Shiri\Domain\Calendar\Support\Lang;
use Shiri\Domain\Calendar\Support\MilestoneBoard;

/**
 * 右侧栏节点的 RPC 入口与渲染数据
 *
 * 路径：/api/jsonrpc → shiri.rpc.Calendar.Milestones.get | add | remove
 * 只有标了 @api 的方法才能被 RPC 调到。
 *
 * view() 不暴露给 RPC，给日历页控制器用：把日期算成「还有多少天」的三段
 * （前缀 / 数字 / 后缀），模板里数字单独放大，中英两种语序都能拼对。
 */
class Milestones
{
    public function __construct(private MilestoneBoard $board) {}

    /**
     * @return array<string, mixed>
     *
     * @api
     */
    public function get(): array
    {
        return ['ok' => true, 'items' => $this->view()['all']];
    }

    /**
     * @param  array<string, mixed>  $params  title / titleEn / date (Y-m-d)
     * @return array<string, mixed>
     *
     * @api
     */
    public function add(array $params): array
    {
        $userId = (int) session('userdata.id');
        if ($userId <= 0) {
            return ['ok' => false, 'message' => '未登录'];
        }

        $id = $this->board->create($userId, $params);
        if ($id <= 0) {
            return ['ok' => false, 'message' => Lang::t('ms.err')];
        }

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param  array<string, mixed>  $params  id
     * @return array<string, mixed>
     *
     * @api
     */
    public function remove(array $params): array
    {
        $userId = (int) session('userdata.id');
        $id = (int) ($params['id'] ?? 0);
        if ($userId <= 0 || $id <= 0) {
            return ['ok' => false, 'message' => '参数不对'];
        }

        $this->board->delete($userId, $id);

        return ['ok' => true];
    }

    /**
     * 给模板用：分组好的列表 + 总数
     *
     * @return array{groups: array<string, array<int, array<string, mixed>>>, all: array<int, array<string, mixed>>, count: int}
     */
    public function view(): array
    {
        $userId = (int) session('userdata.id');
        $isEn = Lang::isEnglish();
        $tz = new \DateTimeZone(MilestoneBoard::TZ);
        $today = new \DateTimeImmutable('today', $tz);

        $all = [];
        foreach ($this->board->all($userId) as $row) {
            if (! MilestoneBoard::validDate((string) $row->targetDate)) {
                continue;
            }

            $date = new \DateTimeImmutable((string) $row->targetDate, $tz);
            $days = (int) $today->diff($date)->format('%r%a');
            $bucket = $days < 0 ? 'past' : ($days > 365 ? 'far' : 'soon');

            if ($days > 0) {
                $pre = Lang::t('ms.leftPre');
                $num = (string) $days;
                $post = Lang::t('ms.leftPost');
            } elseif ($days === 0) {
                $pre = '';
                $num = '';
                $post = Lang::t('ms.today');
            } else {
                $pre = Lang::t('ms.agoPre');
                $num = (string) abs($days);
                $post = Lang::t('ms.agoPost');
            }

            $title = (string) $row->title;
            if ($isEn && (string) $row->titleEn !== '') {
                $title = (string) $row->titleEn;
            }

            $all[] = [
                'id' => (int) $row->id,
                'title' => $title,
                'dateLabel' => $isEn
                    ? $date->format('M j, Y')
                    : $date->format('Y年n月j日'),
                'days' => $days,
                'bucket' => $bucket,
                'pre' => $pre,
                'num' => $num,
                'post' => $post,
            ];
        }

        $order = ['soon' => 0, 'far' => 1, 'past' => 2];
        usort($all, function (array $a, array $b) use ($order): int {
            if ($order[$a['bucket']] !== $order[$b['bucket']]) {
                return $order[$a['bucket']] <=> $order[$b['bucket']];
            }

            return $a['bucket'] === 'past'
                ? ($b['days'] <=> $a['days'])   // 刚过去的排前面
                : ($a['days'] <=> $b['days']);  // 越近的排前面
        });

        $groups = ['soon' => [], 'far' => [], 'past' => []];
        foreach ($all as $item) {
            $groups[$item['bucket']][] = $item;
        }

        return ['groups' => $groups, 'all' => $all, 'count' => count($all)];
    }
}
