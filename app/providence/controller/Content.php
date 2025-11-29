<?php
/**
 * 内容管理控制器（公告、弹窗、活动）
 */

namespace app\providence\controller;

use think\facade\Db;

class Content
{
    /**
     * 获取公告列表
     * GET /providence/announcements
     */
    public function announcements()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $status = input('get.status', '', 'trim');

        $where = [];

        if (!empty($keyword)) {
            $where[] = ['title', 'like', "%{$keyword}%"];
        }

        if ($status !== '') {
            $where[] = ['status', '=', $status ? 1 : 0];
        }

        $list = Db::name('announcement')
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        foreach ($list as &$item) {
            $item['is_important'] = (bool)($item['is_important'] ?? 0);
            $item['status'] = (bool)($item['status'] ?? 0);
        }

        $total = Db::name('announcement')->where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 获取弹窗列表
     * GET /providence/popups
     */
    public function popups()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $status = input('get.status', '', 'trim');

        $where = [];

        if (!empty($keyword)) {
            $where[] = ['title', 'like', "%{$keyword}%"];
        }

        if ($status !== '') {
            $where[] = ['status', '=', $status ? 1 : 0];
        }

        $list = Db::name('popup')
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        foreach ($list as &$item) {
            $item['status'] = (bool)($item['status'] ?? 0);
        }

        $total = Db::name('popup')->where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 创建弹窗
     * POST /providence/popups
     */
    public function createPopup()
    {
        $data = input('post.');

        // 验证必填字段
        if (empty($data['title'])) {
            return error('标题不能为空');
        }
        if (empty($data['content'])) {
            return error('内容不能为空');
        }

        $insertData = [
            'title' => $data['title'],
            'content' => $data['content'],
            'image' => $data['image'] ?? '',
            'status' => isset($data['status']) ? ($data['status'] ? 1 : 0) : 1,
            'trigger_type' => $data['trigger_type'] ?? $data['trigger'] ?? 'login',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // 处理时间字段（如果有）
        if (!empty($data['start_time'])) {
            $insertData['start_time'] = $data['start_time'];
        }
        if (!empty($data['end_time'])) {
            $insertData['end_time'] = $data['end_time'];
        }

        $id = Db::name('popup')->insertGetId($insertData);

        return success('创建成功', ['id' => $id]);
    }

    /**
     * 更新弹窗
     * PUT /providence/popups/:id
     */
    public function updatePopup($id)
    {
        $data = input('post.');

        $popup = Db::name('popup')->where('id', $id)->find();
        if (!$popup) {
            return error('弹窗不存在');
        }

        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        if (isset($data['content'])) {
            $updateData['content'] = $data['content'];
        }
        if (isset($data['image'])) {
            $updateData['image'] = $data['image'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'] ? 1 : 0;
        }
        if (isset($data['trigger_type']) || isset($data['trigger'])) {
            $updateData['trigger_type'] = $data['trigger_type'] ?? $data['trigger'] ?? 'login';
        }
        if (isset($data['start_time'])) {
            $updateData['start_time'] = $data['start_time'];
        }
        if (isset($data['end_time'])) {
            $updateData['end_time'] = $data['end_time'];
        }

        if (empty($updateData)) {
            return error('没有要更新的数据');
        }

        Db::name('popup')->where('id', $id)->update($updateData);

        return success('更新成功');
    }

    /**
     * 删除弹窗
     * DELETE /providence/popups/:id
     */
    public function deletePopup($id)
    {
        $popup = Db::name('popup')->where('id', $id)->find();
        if (!$popup) {
            return error('弹窗不存在');
        }

        Db::name('popup')->where('id', $id)->delete();

        return success('删除成功');
    }

    /**
     * 获取活动列表
     * GET /providence/activities
     */
    public function activities()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $status = input('get.status', '', 'trim');
        $type = input('get.type', '', 'trim');

        $where = [];

        if (!empty($keyword)) {
            $where[] = ['title', 'like', "%{$keyword}%"];
        }

        if ($status !== '') {
            $where[] = ['status', '=', $status ? 1 : 0];
        }

        if (!empty($type)) {
            $where[] = ['type', '=', $type];
        }

        $list = Db::name('activity')
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        foreach ($list as &$item) {
            $item['status'] = (bool)($item['status'] ?? 0);
            $item['show_popup'] = (bool)($item['show_popup'] ?? 0);
            $item['participants'] = (int)($item['participants'] ?? 0);
        }

        $total = Db::name('activity')->where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 创建活动
     * POST /providence/activities
     */
    public function createActivity()
    {
        $data = input('post.');

        // 验证必填字段
        if (empty($data['title'])) {
            return error('活动标题不能为空');
        }

        try {
            $insertData = [
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'type' => $data['type'] ?? 'general',
                'start_time' => $data['start_time'] ?? date('Y-m-d H:i:s'),
                'end_time' => $data['end_time'] ?? date('Y-m-d H:i:s', strtotime('+30 days')),
                'image' => $data['image'] ?? '',
                'reward_type' => $data['reward_type'] ?? 'points',
                'reward_amount' => $data['reward_amount'] ?? 0,
                'condition' => $data['condition'] ?? '',
                'show_popup' => isset($data['show_popup']) ? ($data['show_popup'] ? 1 : 0) : 0,
                'status' => isset($data['status']) ? ($data['status'] ? 1 : 0) : 1,
                'participants' => 0,
                'total_reward' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = Db::name('activity')->insertGetId($insertData);

            return success('创建成功', ['id' => $id]);
        } catch (\Exception $e) {
            return error('创建失败：' . $e->getMessage());
        }
    }

    /**
     * 更新活动
     * PUT /providence/activities/:id
     */
    public function updateActivity($id)
    {
        $data = input('post.');

        $activity = Db::name('activity')->where('id', $id)->find();
        if (!$activity) {
            return error('活动不存在');
        }

        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['type'])) {
            $updateData['type'] = $data['type'];
        }
        if (isset($data['start_time'])) {
            $updateData['start_time'] = $data['start_time'];
        }
        if (isset($data['end_time'])) {
            $updateData['end_time'] = $data['end_time'];
        }
        if (isset($data['image'])) {
            $updateData['image'] = $data['image'];
        }
        if (isset($data['reward_type'])) {
            $updateData['reward_type'] = $data['reward_type'];
        }
        if (isset($data['reward_amount'])) {
            $updateData['reward_amount'] = $data['reward_amount'];
        }
        if (isset($data['condition'])) {
            $updateData['condition'] = $data['condition'];
        }
        if (isset($data['show_popup'])) {
            $updateData['show_popup'] = $data['show_popup'] ? 1 : 0;
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'] ? 1 : 0;
        }

        if (empty($updateData)) {
            return error('没有要更新的数据');
        }

        Db::name('activity')->where('id', $id)->update($updateData);

        return success('更新成功');
    }

    /**
     * 删除活动
     * DELETE /providence/activities/:id
     */
    public function deleteActivity($id)
    {
        $activity = Db::name('activity')->where('id', $id)->find();
        if (!$activity) {
            return error('活动不存在');
        }

        Db::name('activity')->where('id', $id)->delete();

        return success('删除成功');
    }

    /**
     * 切换活动状态
     * POST /providence/activities/:id/status
     */
    public function toggleActivityStatus($id)
    {
        $status = input('post.status');

        if ($status === null || $status === '') {
            return error('状态参数不能为空');
        }

        $activity = Db::name('activity')->where('id', $id)->find();
        if (!$activity) {
            return error('活动不存在');
        }

        $newStatus = $status ? 1 : 0;
        Db::name('activity')->where('id', $id)->update([
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return success('状态更新成功', ['status' => (bool)$newStatus]);
    }

    /**
     * 获取活动统计
     * GET /providence/activities/:id/statistics
     */
    public function getActivityStatistics($id)
    {
        $activity = Db::name('activity')->where('id', $id)->find();
        if (!$activity) {
            return error('活动不存在');
        }

        // 获取参与人数（根据活动类型统计）
        $participants = (int)($activity['participants'] ?? 0);

        // 获取总奖励金额
        $totalReward = (float)($activity['total_reward'] ?? 0);

        // 如果数据库中没有统计，尝试从相关表计算
        if ($participants == 0) {
            // 这里可以根据实际业务逻辑统计参与人数
            // 例如：从活动参与记录表统计
            // $participants = Db::name('activity_participants')->where('activity_id', $id)->count();
        }

        return success('获取成功', [
            'participants' => $participants,
            'totalReward' => $totalReward
        ]);
    }
}
