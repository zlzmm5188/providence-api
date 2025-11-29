<?php
/**
 * 后台用户管理控制器
 */

namespace app\providence\controller;

use app\common\model\User as UserModel;
use app\common\model\WalletLog;
use think\facade\Db;

class User
{
    /**
     * 获取用户列表
     * GET /providence/users
     */
    public function index()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $status = input('get.status', '', 'trim');
        $vipLevel = input('get.vip_level', '', 'trim');

        $where = [];

        // 搜索关键词
        if (!empty($keyword)) {
            $where[] = ['username', 'like', "%{$keyword}%"];
        }

        // 状态筛选
        if ($status !== '') {
            $where[] = ['status', '=', intval($status)];
        }

        // VIP等级筛选
        if ($vipLevel !== '') {
            $where[] = ['vip_level', '=', intval($vipLevel)];
        }

        $list = UserModel::where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        $total = UserModel::where($where)->count();

        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => [
                'list' => $list,
                'total' => $total
            ]
        ]);
    }

    /**
     * 获取用户详情
     * GET /providence/users/:id
     */
    public function detail()
    {
        $id = input('param.id', 0, 'intval');

        $user = UserModel::where('id', $id)->find();
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => $user
        ]);
    }

    /**
     * 调整用户VIP等级
     * POST /providence/users/:id/vip
     */
    public function updateVip()
    {
        $id = input('param.id', 0, 'intval');
        $vipLevel = input('post.vip_level', 0, 'intval');
        $remark = input('post.remark', '', 'trim');

        if ($vipLevel < 0 || $vipLevel > 8) {
            return json(['code' => -1, 'msg' => 'VIP等级必须在0-8之间', 'data' => null]);
        }

        $user = UserModel::where('id', $id)->find();
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        try {
            Db::startTrans();

            $oldLevel = $user->vip_level;
            $user->vip_level = $vipLevel;
            $user->save();

            // 记录操作日志
            WalletLog::create([
                'user_id' => $user->id,
                'type' => 'admin_vip_adjust',
                'amount' => 0,
                'currency' => 'system',
                'balance_before' => $oldLevel,
                'balance_after' => $vipLevel,
                'remark' => "管理员调整VIP等级：{$oldLevel} → {$vipLevel}" . ($remark ? "（{$remark}）" : ''),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();

            return json([
                'code' => 1,
                'msg' => 'VIP等级调整成功',
                'data' => null
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => -1, 'msg' => '调整失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 调整用户余额
     * POST /providence/users/:id/balance
     */
    public function updateBalance()
    {
        $id = input('param.id', 0, 'intval');
        $type = input('post.type', '', 'trim'); // add/sub
        $amount = input('post.amount', '0', 'trim');
        $currency = input('post.currency', 'CNY', 'trim'); // CNY/USDT
        $remark = input('post.remark', '', 'trim');

        if (!in_array($type, ['add', 'sub'])) {
            return json(['code' => -1, 'msg' => '操作类型错误，必须是add或sub', 'data' => null]);
        }

        if (!in_array($currency, ['CNY', 'USDT'])) {
            return json(['code' => -1, 'msg' => '币种类型错误，必须是CNY或USDT', 'data' => null]);
        }

        if (bccomp($amount, '0', 8) <= 0) {
            return json(['code' => -1, 'msg' => '调整金额必须大于0', 'data' => null]);
        }

        if (empty($remark)) {
            return json(['code' => -1, 'msg' => '必须填写调整原因', 'data' => null]);
        }

        $user = UserModel::where('id', $id)->find();
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        try {
            Db::startTrans();

            $field = $currency === 'CNY' ? 'balance_cny' : 'balance_usdt';
            $balanceBefore = $user->$field ?? 0;

            if ($type === 'add') {
                $user->$field = bcadd($balanceBefore, $amount, 8);
            } else {
                if (bccomp($balanceBefore, $amount, 8) < 0) {
                    throw new \Exception('余额不足');
                }
                $user->$field = bcsub($balanceBefore, $amount, 8);
            }

            $user->save();

            // 记录操作日志
            WalletLog::create([
                'user_id' => $user->id,
                'type' => 'admin_balance_adjust',
                'amount' => $type === 'add' ? $amount : '-' . $amount,
                'currency' => $currency,
                'balance_before' => $balanceBefore,
                'balance_after' => $user->$field,
                'remark' => "管理员{$type === 'add' ? '增加' : '减少'}余额：{$amount} {$currency}（{$remark}）",
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();

            return json([
                'code' => 1,
                'msg' => '余额调整成功',
                'data' => null
            ]);
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => -1, 'msg' => '调整失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 冻结/解冻用户
     * POST /providence/users/:id/status
     */
    public function toggleStatus()
    {
        $id = input('param.id', 0, 'intval');
        $status = input('post.status', 1, 'intval');

        if (!in_array($status, [0, 1])) {
            return json(['code' => -1, 'msg' => '状态值错误', 'data' => null]);
        }

        $user = UserModel::where('id', $id)->find();
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        try {
            $user->status = $status;
            $user->save();

            return json([
                'code' => 1,
                'msg' => ($status === 1 ? '解冻' : '冻结') . '成功',
                'data' => null
            ]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '操作失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 设置/取消内部账号
     * POST /providence/users/:id/internal
     */
    public function toggleInternal()
    {
        $id = input('param.id', 0, 'intval');
        $isInternal = input('post.is_internal', false);

        $user = UserModel::where('id', $id)->find();
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        try {
            // 检查users表是否有is_internal字段
            $columns = Db::query("SHOW COLUMNS FROM `users` LIKE 'is_internal'");
            if (empty($columns)) {
                // 如果字段不存在，先添加字段
                Db::execute("ALTER TABLE `users` ADD COLUMN `is_internal` tinyint(1) DEFAULT 0 COMMENT '是否内部账号：1-是，0-否' AFTER `status`");
            }

            $user->is_internal = $isInternal ? 1 : 0;
            $user->save();

            return json([
                'code' => 1,
                'msg' => ($isInternal ? '设为内部账号' : '设为正常用户') . '成功',
                'data' => null
            ]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '操作失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 重置用户密码
     * POST /providence/users/:id/reset-password
     */
    public function resetPassword()
    {
        $id = input('param.id', 0, 'intval');
        $newPassword = input('post.new_password', '', 'trim'); // 文档中要求new_password字段

        if (empty($newPassword)) {
            return json(['code' => -1, 'msg' => '新密码不能为空', 'data' => null]);
        }

        $user = UserModel::where('id', $id)->find();
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        try {
            $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
            $user->save();

            return json([
                'code' => 1,
                'msg' => '密码重置成功',
                'data' => null
            ]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '重置失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * KYC审核通过
     * POST /providence/users/:id/kyc/approve
     */
    public function approveKyc()
    {
        $id = input('param.id', 0, 'intval');

        $user = UserModel::where('id', $id)->find();
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        try {
            $user->is_kyc = 1;
            $user->save();

            return json([
                'code' => 1,
                'msg' => 'KYC审核通过',
                'data' => null
            ]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '操作失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * KYC审核拒绝
     * POST /providence/users/:id/kyc/reject
     */
    public function rejectKyc()
    {
        $id = input('param.id', 0, 'intval');
        $reason = input('post.reason', '', 'trim');

        $user = UserModel::where('id', $id)->find();
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        try {
            $user->is_kyc = 2; // 2表示审核失败
            $user->save();

            return json([
                'code' => 1,
                'msg' => 'KYC审核已拒绝',
                'data' => null
            ]);
        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '操作失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 获取内部用户列表
     * GET /providence/users/internal
     */
    public function internal()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');

        $where = [];
        $where[] = ['is_internal', '=', 1];

        // 搜索关键词
        if (!empty($keyword)) {
            $where[] = ['username', 'like', "%{$keyword}%"];
        }

        $list = UserModel::where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        $total = UserModel::where($where)->count();

        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => [
                'list' => $list,
                'total' => $total
            ]
        ]);
    }
}
