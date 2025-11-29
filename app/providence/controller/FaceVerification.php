<?php
/**
 * 人脸识别管理控制器
 */

namespace app\providence\controller;

use app\common\model\User;
use think\facade\Db;

class FaceVerification
{
    /**
     * 获取人脸识别记录列表
     * GET /providence/face-verification
     */
    public function index()
    {
        $keyword = input('get.keyword', '', 'trim');
        $page = input('get.page', 1, 'intval');
        $pageSize = input('get.pageSize', 20, 'intval');
        $type = input('get.type', '', 'trim');
        $result = input('get.result', '', 'trim');
        $startTime = input('get.start_time', '', 'trim');
        $endTime = input('get.end_time', '', 'trim');

        $where = [];

        // 搜索关键词
        if (!empty($keyword)) {
            $userIds = User::where('username', 'like', "%{$keyword}%")->column('id');
            if (!empty($userIds)) {
                $where[] = ['user_id', 'in', $userIds];
            } else {
                return success('获取成功', ['list' => [], 'total' => 0]);
            }
        }

        // 类型筛选
        if (!empty($type)) {
            $where[] = ['type', '=', $type];
        }

        // 结果筛选
        if ($result !== '') {
            $where[] = ['result', '=', intval($result)];
        }

        // 时间筛选
        if (!empty($startTime)) {
            $where[] = ['created_at', '>=', $startTime];
        }
        if (!empty($endTime)) {
            $where[] = ['created_at', '<=', $endTime];
        }

        $list = Db::name('face_verification')
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select();

        // 关联用户信息
        foreach ($list as &$item) {
            $user = User::find($item['user_id']);
            $item['username'] = $user ? $user->username : '';
        }

        $total = Db::name('face_verification')->where($where)->count();

        return success('获取成功', [
            'list' => $list,
            'total' => $total
        ]);
    }

    /**
     * 通过人脸识别审核
     * POST /providence/face-verification/:id/approve
     */
    public function approve()
    {
        $id = input('param.id', 0, 'intval');

        try {
            $verification = Db::name('face_verification')->where('id', $id)->find();
            if (!$verification) {
                return error('审核记录不存在');
            }

            Db::startTrans();

            // 更新审核状态
            Db::name('face_verification')
                ->where('id', $id)
                ->update([
                    'result' => 1, // 通过
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            // 如果是KYC实名认证，更新用户状态
            if ($verification['type'] == 'kyc') {
                Db::name('users')
                    ->where('user_id', $verification['user_id'])
                    ->update(['is_kyc' => 1]);
            }

            // 如果是找回密码，生成重置token（可选，也可以让用户自己查询状态）
            if ($verification['type'] == 'password_reset') {
                // 这里可以发送通知或生成token，暂时不处理，让前端通过check-status接口查询
            }

            Db::commit();

            return success('审核通过');
        } catch (\Exception $e) {
            Db::rollback();
            return error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 拒绝人脸识别审核
     * POST /providence/face-verification/:id/reject
     */
    public function reject()
    {
        $id = input('param.id', 0, 'intval');

        try {
            Db::name('face_verification')
                ->where('id', $id)
                ->update(['result' => 2, 'updated_at' => date('Y-m-d H:i:s')]);

            return success('审核已拒绝');
        } catch (\Exception $e) {
            return error('操作失败：' . $e->getMessage());
        }
    }

    /**
     * 获取人脸识别统计
     * GET /providence/face-verification/statistics
     */
    public function statistics()
    {
        $total = Db::name('face_verification')->count();
        $successCount = Db::name('face_verification')->where('result', 1)->count();
        $failCount = Db::name('face_verification')->where('result', 2)->count();
        $pending = Db::name('face_verification')->where('result', 0)->count();
        $kycCount = Db::name('face_verification')->where('type', 'kyc')->count();
        $passwordResetCount = Db::name('face_verification')->where('type', 'password_reset')->count();

        $avgSimilarity = Db::name('face_verification')
            ->where('result', 1)
            ->avg('similarity');

        $statistics = [
            'total' => $total,
            'successCount' => $successCount,
            'failCount' => $failCount,
            'pending' => $pending,
            'kycCount' => $kycCount,
            'passwordResetCount' => $passwordResetCount,
            'successRate' => $total > 0 ? round(($successCount / $total) * 100, 2) : 0,
            'avgSimilarity' => round((float)$avgSimilarity, 2)
        ];

        return success('获取成功', $statistics);
    }

    /**
     * 获取人脸识别设置
     * GET /providence/face-verification/settings
     */
    public function getSettings()
    {
        $settings = Db::name('system_config')
            ->where('key', 'face_verification_settings')
            ->value('value');

        if ($settings) {
            $settings = json_decode($settings, true);
        } else {
            $settings = [
                'provider' => 'tencent',
                'apiKey' => '',
                'secretKey' => '',
                'autoPassThreshold' => 0.85,
                'manualReviewThreshold' => 0.70,
                'enableLiveness' => true,
                'enablePasswordResetVerify' => true,
                'maxFailCount' => 3
            ];
        }

        return success('获取成功', $settings);
    }

    /**
     * 更新人脸识别设置
     * POST /providence/face-verification/settings
     */
    public function updateSettings()
    {
        $settings = input('post.');

        try {
            Db::name('system_config')
                ->where('key', 'face_verification_settings')
                ->updateOrInsert(
                    ['key' => 'face_verification_settings'],
                    ['value' => json_encode($settings), 'updated_at' => date('Y-m-d H:i:s')]
                );

            return success('更新成功');
        } catch (\Exception $e) {
            return error('更新失败：' . $e->getMessage());
        }
    }
}
