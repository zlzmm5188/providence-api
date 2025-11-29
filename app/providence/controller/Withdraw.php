<?php
namespace app\providence\controller;

use app\common\model\User;
use app\common\model\WithdrawOrder;
use app\common\model\AuditLog;
use think\facade\Db;

class Withdraw
{
    public function index()
    {
        $status = input('status', '');
        $page = input('page', 1);
        $pageSize = input('pageSize', 20);

        $where = [];
        if ($status !== '') {
            $where[] = ['status', '=', $status];
        }

        $list = WithdrawOrder::where($where)
            ->page($page, $pageSize)
            ->order('id', 'desc')
            ->select();

        foreach ($list as &$item) {
            $user = User::find($item->user_id);
            $item->username = $user ? $user->username : '';
            $item->uid = $user->uid ?? $user->id;
        }

        $total = WithdrawOrder::where($where)->count();

        return success('获取成功', [
            'total' => $total,
            'list' => $list
        ]);
    }

    public function approve()
    {
        $id = input('id'); // 文档中要求id字段
        $remark = input('remark', ''); // 文档中要求remark字段（可选）
        $adminId = request()->userId;

        Db::startTrans();
        try {
            $withdraw = WithdrawOrder::where('id', $id)->lock(true)->find();

            if (!$withdraw || $withdraw->status != 0) {
                throw new \Exception('订单状态异常');
            }

            $user = User::where('id', $withdraw->user_id)->lock(true)->find();

            $frozenField = ($withdraw->currency === 'CNY') ? 'frozen_cny' : 'frozen_usdt';

            if (bcmath_comp($user->$frozenField, $withdraw->amount) === -1) {
                throw new \Exception('冻结金额不足');
            }

            $user->$frozenField = bcmath_sub($user->$frozenField, $withdraw->amount);
            $user->save();

            $withdraw->status = 1; // 文档中要求：1=审核通过
            $withdraw->reviewed_by = $adminId; // 文档中字段名是reviewed_by
            $withdraw->reviewed_at = date('Y-m-d H:i:s');
            if ($remark) {
                $withdraw->remark = $remark;
            }
            $withdraw->save();

            AuditLog::create([
                'admin_id' => $adminId,
                'action' => 'approve_withdraw',
                'module' => 'finance',
                'related_id' => $withdraw->id,
                'content' => json_encode(['order_no' => $withdraw->order_no, 'amount' => $withdraw->amount]),
                'ip' => request()->ip()
            ]);

            Db::commit();
            return success('审核通过');

        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage());
        }
    }

    public function reject()
    {
        $id = input('id'); // 文档中要求id字段
        $reason = input('reason', ''); // 文档中要求reason字段
        $adminId = request()->userId;

        Db::startTrans();
        try {
            $withdraw = WithdrawOrder::where('id', $id)->lock(true)->find();

            if (!$withdraw || $withdraw->status != 0) {
                throw new \Exception('订单状态异常');
            }

            $user = User::where('id', $withdraw->user_id)->lock(true)->find();

            // 解冻金额
            $frozenField = ($withdraw->currency === 'CNY') ? 'frozen_cny' : 'frozen_usdt';
            $balanceField = ($withdraw->currency === 'CNY') ? 'balance_cny' : 'balance_usdt';

            $frozenAmount = $user->$frozenField;
            $newFrozen = bcmath_sub($frozenAmount, $withdraw->amount);
            $newBalance = bcmath_add($user->$balanceField, $withdraw->amount);

            $user->$frozenField = $newFrozen;
            $user->$balanceField = $newBalance;
            $user->save();

            $withdraw->status = -1; // 文档中要求：-1=已拒绝
            $withdraw->reviewed_by = $adminId; // 文档中字段名是reviewed_by
            $withdraw->reviewed_at = date('Y-m-d H:i:s');
            $withdraw->reject_reason = $reason; // 文档中字段名是reject_reason
            $withdraw->save();

            AuditLog::create([
                'admin_id' => $adminId,
                'action' => 'reject_withdraw',
                'module' => 'finance',
                'related_id' => $withdraw->id,
                'content' => json_encode(['order_no' => $withdraw->order_no, 'amount' => $withdraw->amount, 'reason' => $reason]),
                'ip' => request()->ip()
            ]);

            Db::commit();
            return success('已拒绝');

        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage());
        }
    }

    /**
     * 标记提现为处理中
     * POST /providence/withdraw/processing
     */
    public function processing()
    {
        $id = input('id');
        $remark = input('remark', '');
        $adminId = request()->userId;

        Db::startTrans();
        try {
            $withdraw = WithdrawOrder::where('id', $id)->lock(true)->find();

            if (!$withdraw) {
                throw new \Exception('订单不存在');
            }

            // 只能从审核通过状态(1)转为处理中(2)
            if ($withdraw->status != 1) {
                throw new \Exception('订单状态异常，只能将审核通过的订单标记为处理中');
            }

            $withdraw->status = 2; // 2=处理中
            $withdraw->reviewed_by = $adminId;
            $withdraw->reviewed_at = date('Y-m-d H:i:s');
            if ($remark) {
                $withdraw->remark = $remark;
            }
            $withdraw->save();

            AuditLog::create([
                'admin_id' => $adminId,
                'action' => 'mark_withdraw_processing',
                'module' => 'finance',
                'related_id' => $withdraw->id,
                'content' => json_encode(['order_no' => $withdraw->order_no, 'amount' => $withdraw->amount]),
                'ip' => request()->ip()
            ]);

            Db::commit();
            return success('已标记为处理中');

        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage());
        }
    }

    /**
     * 标记提现为已完成
     * POST /providence/withdraw/completed
     */
    public function completed()
    {
        $id = input('id');
        $remark = input('remark', '');
        $adminId = request()->userId;

        Db::startTrans();
        try {
            $withdraw = WithdrawOrder::where('id', $id)->lock(true)->find();

            if (!$withdraw) {
                throw new \Exception('订单不存在');
            }

            // 只能从处理中状态(2)转为已完成(3)
            if ($withdraw->status != 2) {
                throw new \Exception('订单状态异常，只能将处理中的订单标记为已完成');
            }

            // 扣除用户冻结金额（如果还未扣除）
            $user = User::where('id', $withdraw->user_id)->lock(true)->find();
            $frozenField = ($withdraw->currency === 'CNY') ? 'frozen_cny' : 'frozen_usdt';

            // 确保冻结金额足够
            if (bcmath_comp($user->$frozenField, $withdraw->amount) === -1) {
                throw new \Exception('冻结金额不足');
            }

            // 扣除冻结金额
            $user->$frozenField = bcmath_sub($user->$frozenField, $withdraw->amount);
            $user->save();

            $withdraw->status = 3; // 3=已完成
            $withdraw->reviewed_by = $adminId;
            $withdraw->reviewed_at = date('Y-m-d H:i:s');
            $withdraw->completed_at = date('Y-m-d H:i:s');
            if ($remark) {
                $withdraw->remark = $remark;
            }
            $withdraw->save();

            AuditLog::create([
                'admin_id' => $adminId,
                'action' => 'complete_withdraw',
                'module' => 'finance',
                'related_id' => $withdraw->id,
                'content' => json_encode(['order_no' => $withdraw->order_no, 'amount' => $withdraw->amount]),
                'ip' => request()->ip()
            ]);

            Db::commit();
            return success('已标记为已完成');

        } catch (\Exception $e) {
            Db::rollback();
            return error($e->getMessage());
        }
    }
}
