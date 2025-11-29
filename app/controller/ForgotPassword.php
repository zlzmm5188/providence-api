<?php
/**
 * 找回密码控制器（含人证识别）
 */

namespace app\api\controller;

use app\common\model\User;
use app\common\service\FileUploadService;
use think\facade\Db;

class ForgotPassword
{
    /**
     * 第一步：输入账号，定位用户并返回人证识别要求
     * POST /api/auth/forgot-password/step1
     */
    public function step1()
    {
        $username = input('post.username', '', 'trim');

        if (empty($username)) {
            return json(['code' => -1, 'msg' => '用户名不能为空', 'data' => null]);
        }

        // 查找用户
        $user = User::where('username', $username)->find();

        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        // 检查是否已实名认证
        if (empty($user->real_name) || empty($user->id_card) || empty($user->face_photo)) {
            return json([
                'code' => -1,
                'msg' => '该账号未完成实名认证，无法通过人脸识别找回密码',
                'data' => null
            ]);
        }

        // 返回人证识别要求
        return json([
            'code' => 1,
            'msg' => '请进行人脸识别验证',
            'data' => [
                'user_id' => $user->id,
                'username' => $user->username,
                'has_real_name' => !empty($user->real_name),
                'has_id_card' => !empty($user->id_card),
                'has_face_photo' => !empty($user->face_photo),
                'verify_token' => $this->generateVerifyToken($user->id) // 临时验证token
            ]
        ]);
    }

    /**
     * 第二步：上传人脸照片，进行人证识别
     * POST /api/auth/forgot-password/step2
     */
    public function step2()
    {
        $userId = input('post.user_id', 0, 'intval');
        $verifyToken = input('post.verify_token', '', 'trim');
        $facePhoto = input('post.face_photo', '', 'trim'); // base64编码的人脸照片

        if ($userId <= 0) {
            return json(['code' => -1, 'msg' => '用户ID不能为空', 'data' => null]);
        }

        if (empty($facePhoto)) {
            return json(['code' => -1, 'msg' => '请上传人脸照片', 'data' => null]);
        }

        // 验证token
        if (!$this->verifyToken($userId, $verifyToken)) {
            return json(['code' => -1, 'msg' => '验证token无效或已过期', 'data' => null]);
        }

        $user = User::find($userId);
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        // 使用安全的上传服务保存人脸照片
        $faceUploadResult = FileUploadService::saveBase64Image($facePhoto, 'password_reset', 'uploads/face');
        if (!$faceUploadResult['success']) {
            return json([
                'code' => -1,
                'msg' => $faceUploadResult['message'],
                'data' => null
            ]);
        }
        $facePhotoPath = $faceUploadResult['path'];

        // 记录到人脸识别审核表（待审核）
        $verificationId = Db::name('face_verification')->insertGetId([
            'user_id' => $userId,
            'type' => 'password_reset',
            'face_image' => $facePhotoPath,
            'similarity' => 0,
            'liveness_score' => 0,
            'result' => 0, // 0-待审核
            'provider' => 'manual', // 人工审核
            'remark' => '找回密码待审核',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // 生成临时token，用于关联审核记录
        $verifyToken = $this->generateVerifyToken($userId, $verificationId);

        return json([
            'code' => 1,
            'msg' => '人脸照片已提交，等待人工审核',
            'data' => [
                'verify_token' => $verifyToken,
                'verification_id' => $verificationId,
                'status' => 'pending', // 待审核
                'message' => '我们将在24小时内完成审核，审核通过后您将收到通知'
            ]
        ]);
    }

    /**
     * 第三步：重置密码
     * POST /api/auth/forgot-password/step3
     */
    public function step3()
    {
        $resetToken = input('post.reset_token', '', 'trim');
        $newPassword = input('post.new_password', '', 'trim');

        if (empty($resetToken)) {
            return json(['code' => -1, 'msg' => '重置token不能为空', 'data' => null]);
        }

        if (empty($newPassword) || strlen($newPassword) < 6) {
            return json(['code' => -1, 'msg' => '新密码长度不能少于6位', 'data' => null]);
        }

        // 验证并获取用户ID
        $userId = $this->verifyResetToken($resetToken);
        if (!$userId) {
            return json(['code' => -1, 'msg' => '重置token无效或已过期', 'data' => null]);
        }

        $user = User::find($userId);
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        // 更新密码
        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->save();

        // 清除重置token
        cache('reset_token_' . $resetToken, null);

        return json([
            'code' => 1,
            'msg' => '密码重置成功',
            'data' => null
        ]);
    }

    /**
     * 检查审核状态（用于找回密码流程）
     * POST /api/auth/forgot-password/check-status
     */
    public function checkStatus()
    {
        $verifyToken = input('post.verify_token', '', 'trim');

        if (empty($verifyToken)) {
            return json(['code' => -1, 'msg' => '验证token不能为空', 'data' => null]);
        }

        // 从缓存获取审核记录ID
        $cacheData = cache('verify_token_' . $verifyToken);
        if (!$cacheData || !isset($cacheData['verification_id'])) {
            return json(['code' => -1, 'msg' => '验证token无效或已过期', 'data' => null]);
        }

        $verificationId = $cacheData['verification_id'];
        $userId = $cacheData['user_id'];

        // 查询审核状态
        $verification = Db::name('face_verification')
            ->where('id', $verificationId)
            ->where('user_id', $userId)
            ->where('type', 'password_reset')
            ->find();

        if (!$verification) {
            return json(['code' => -1, 'msg' => '审核记录不存在', 'data' => null]);
        }

        // result: 0-待审核, 1-通过, 2-拒绝
        if ($verification['result'] == 0) {
            return json([
                'code' => 1,
                'msg' => '审核中',
                'data' => [
                    'status' => 'pending',
                    'message' => '正在审核中，请稍候...'
                ]
            ]);
        } elseif ($verification['result'] == 1) {
            // 审核通过，生成重置密码token
            $resetToken = $this->generateResetToken($userId);
            return json([
                'code' => 1,
                'msg' => '审核通过',
                'data' => [
                    'status' => 'approved',
                    'reset_token' => $resetToken,
                    'expires_in' => 3600
                ]
            ]);
        } else {
            return json([
                'code' => -1,
                'msg' => '审核未通过',
                'data' => [
                    'status' => 'rejected',
                    'message' => $verification['remark'] ?? '人脸识别审核未通过，请重新提交'
                ]
            ]);
        }
    }

    /**
     * 生成验证token
     * @param int $userId
     * @param int $verificationId 审核记录ID
     * @return string
     */
    private function generateVerifyToken($userId, $verificationId)
    {
        $token = md5($userId . $verificationId . time() . rand(1000, 9999));
        cache('verify_token_' . $token, [
            'user_id' => $userId,
            'verification_id' => $verificationId
        ], 86400); // 24小时有效期（等待审核）
        return $token;
    }

    /**
     * 验证token
     * @param int $userId
     * @param string $token
     * @return bool
     */
    private function verifyToken($userId, $token)
    {
        $cacheData = cache('verify_token_' . $token);
        if (!$cacheData) {
            return false;
        }
        // 兼容旧格式（直接存储userId）和新格式（存储数组）
        if (is_array($cacheData)) {
            return $cacheData['user_id'] == $userId;
        }
        return $cacheData == $userId;
    }

    /**
     * 生成重置密码token
     * @param int $userId
     * @return string
     */
    private function generateResetToken($userId)
    {
        $token = md5($userId . time() . rand(10000, 99999));
        cache('reset_token_' . $token, $userId, 3600); // 1小时有效期
        return $token;
    }

    /**
     * 验证重置token
     * @param string $token
     * @return int|false 用户ID或false
     */
    private function verifyResetToken($token)
    {
        return cache('reset_token_' . $token);
    }
}
