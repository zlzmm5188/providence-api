<?php
namespace app\providence\controller;

use app\common\model\User;
use app\common\model\RechargeOrder;
use app\common\model\WalletLog;
use app\common\model\AuditLog;
use think\facade\Db;

class Recharge
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

        $list = RechargeOrder::where($where)
            ->page($page, $pageSize)
            ->order('id', 'desc')
            ->select();

        // 关联用户信息
        foreach ($list as &$item) {
            $user = User::find($item->user_id);  // user_id 存储的是 users.id，正确
            $item->username = $user ? $user->username : '';
            $item->uid = $user ? ($user->uid ?? $user->id) : $item->user_id;
        }

        $total = RechargeOrder::where($where)->count();

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
            $recharge = RechargeOrder::where('id', $id)->lock(true)->find();

            if (!$recharge || $recharge->status != 0) {
                throw new \Exception('订单状态异常');
            }

            $user = User::where('id', $recharge->user_id)->lock(true)->find();

            $field = ($recharge->currency === 'CNY') ? 'balance_cny' : 'balance_usdt';
            $oldBalance = $user->$field;
            $newBalance = bcmath_add($oldBalance, $recharge->amount);

            $user->$field = $newBalance;
            $user->save();

            $recharge->status = 1;
            $recharge->reviewed_by = $adminId; // 文档中字段名是reviewed_by
            $recharge->reviewed_at = date('Y-m-d H:i:s');
            if ($remark) {
                $recharge->remark = $remark;
            }
            $recharge->save();

            WalletLog::create([
                'user_id' => $user->id,
                'currency' => $recharge->currency,
                'type' => 'recharge',
                'amount' => $recharge->amount,
                'balance_before' => $oldBalance,
                'balance_after' => $newBalance,
                'related_id' => $recharge->id,
                'remark' => "充值审核通过：{$recharge->order_no}"
            ]);

            AuditLog::create([
                'admin_id' => $adminId,
                'action' => 'approve_recharge',
                'module' => 'finance',
                'related_id' => $recharge->id,
                'content' => json_encode(['order_no' => $recharge->order_no, 'amount' => $recharge->amount]),
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

        $recharge = RechargeOrder::find($id);

        if (!$recharge || $recharge->status != 0) {
            return error('订单状态异常');
        }

        $recharge->status = -1; // 文档中要求：-1=已拒绝
        $recharge->reviewed_by = $adminId; // 文档中字段名是reviewed_by
        $recharge->reviewed_at = date('Y-m-d H:i:s');
        $recharge->reject_reason = $reason; // 文档中字段名是reject_reason
        $recharge->save();

        return success('已拒绝');
    }
}
