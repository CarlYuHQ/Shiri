<?php

namespace Shiri\Domain\Calendar\Services;

use Shiri\Domain\Calendar\Support\RecurringBoard;

/**
 * 例行计划的写入口（给页面用 JSON-RPC 调）
 *
 * 路径：/api/jsonrpc → shiri.rpc.Calendar.Plans.save | remove | toggle
 * 只有标了 @api 的方法才能被 RPC 调到，所以这几个方法都带上。
 */
class Plans
{
    public function __construct(private RecurringBoard $board) {}

    /**
     * 新建一条例行计划
     *
     * @param  array<string, mixed>  $params  title/freq/weekdays/timeOfDay/startsOn
     * @return array<string, mixed>
     *
     * @api
     */
    public function save(array $params): array
    {
        $userId = (int) session('userdata.id');
        if ($userId <= 0) {
            return ['ok' => false, 'message' => '未登录'];
        }

        $id = $this->board->create($userId, $params);
        if ($id <= 0) {
            return ['ok' => false, 'message' => '标题不能为空；每周规则至少要选一天'];
        }

        // 立刻把该生成的日子补上，用户不用等下次打开页面
        $created = $this->board->sync($userId);

        return ['ok' => true, 'id' => $id, 'created' => $created];
    }

    /**
     * 删除一条规则（已经生成的历史待办保留）
     *
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
     * 启用 / 停用一条规则
     *
     * @param  array<string, mixed>  $params  id
     * @return array<string, mixed>
     *
     * @api
     */
    public function toggle(array $params): array
    {
        $userId = (int) session('userdata.id');
        $id = (int) ($params['id'] ?? 0);
        if ($userId <= 0 || $id <= 0) {
            return ['ok' => false, 'message' => '参数不对'];
        }

        $this->board->toggle($userId, $id);

        return ['ok' => true];
    }
}
