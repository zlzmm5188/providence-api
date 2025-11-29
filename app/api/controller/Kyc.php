<?php
/**
 * KYC实名认证控制器（含人脸识别比对）
 */

namespace app\api\controller;

use app\common\model\User;
use app\common\service\TelegramBotService;
use app\common\service\FaceRecognitionService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Db;
use think\facade\Log;

class Kyc
{
    /**
     * 获取当前用户ID（手动解析Token）
     */
    private function getUserId()
    {
        // 优先从中间件获取
        $userId = request()->userId;
        if ($userId) {
            return $userId;
        }

        // 手动解析Token
        $token = request()->header('authorization');
        if (!$token) {
            $token = request()->header('token');
        }

        if ($token && stripos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (!$token) {
            return null;
        }

        try {
            $secret = config('jwt.secret');
            if (empty($secret)) {
                return null;
            }

            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            if (isset($decoded->id)) {
                return $decoded->id;
            } elseif (isset($decoded->user_id)) {
                return $decoded->user_id;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('KYC Token解析失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 提交实名认证（含人脸比对）
     * POST /api/user/kyc/submit
     */
    public function submit()
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return json(['code' => -1, 'msg' => '请先登录']);
        }

        $realName = input('post.real_name', '', 'trim');
        $idCard = input('post.id_card', '', 'trim');
        $idCardFront = input('post.id_card_front', ''); // 身份证正面base64
        $idCardBack = input('post.id_card_back', ''); // 身份证反面base64
        $facePhoto = input('post.face_photo', ''); // 人脸照片base64

        Log::info('KYC提交', ['user_id' => $userId, 'real_name' => $realName, 'id_card' => substr($idCard, 0, 6) . '****']);

        // 验证必填字段
        if (empty($realName) || empty($idCard) || empty($facePhoto)) {
            return json(['code' => -1, 'msg' => '请填写完整信息并上传人脸照片']);
        }

        // 验证身份证照片
        if (empty($idCardFront)) {
            return json(['code' => -1, 'msg' => '请上传身份证正面照片']);
        }

        // 验证身份证号格式
        if (!preg_match('/^[1-9]\d{5}(18|19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/', $idCard)) {
            return json(['code' => -1, 'msg' => '身份证号格式不正确']);
        }

        // 检查身份证是否已被使用
        $existingUser = Db::name('users')
            ->where('id_card', $idCard)
            ->where('id', '<>', $userId)
            ->find();
        if ($existingUser) {
            return json(['code' => -1, 'msg' => '该身份证号已被其他账号使用']);
        }

        $user = Db::name('users')->where('id', $userId)->find();
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在']);
        }

        // 如果用户已实名认证，检查是否允许修改
        $realNameStatus = $user['realname_status'] ?? 0;
        if ($realNameStatus == 2 && !empty($user['id_card'])) {
            if ($user['id_card'] !== $idCard) {
                return json(['code' => -1, 'msg' => '已实名认证的账号不能修改身份证号']);
            }
        }

        // 检查是否超过最大失败次数
        $faceService = new FaceRecognitionService();
        if ($faceService->isExceedMaxFailCount($userId, 'kyc')) {
            return json(['code' => -1, 'msg' => '人脸验证失败次数过多，请24小时后再试或联系客服']);
        }

        Db::startTrans();
        try {
            // 保存人脸照片
            $facePhotoPath = $this->saveBase64Image($facePhoto, 'face');
            if (!$facePhotoPath) {
                Db::rollback();
                return json(['code' => -1, 'msg' => '人脸照片保存失败']);
            }

            // 保存身份证照片
            $idCardFrontPath = null;
            $idCardBackPath = null;
            if (!empty($idCardFront)) {
                $idCardFrontPath = $this->saveBase64Image($idCardFront, 'idcard_front');
            }
            if (!empty($idCardBack)) {
                $idCardBackPath = $this->saveBase64Image($idCardBack, 'idcard_back');
            }

            // ========== 人脸比对 ==========
            Log::info('KYC开始人脸比对', ['user_id' => $userId]);

            $faceResult = $faceService->compareFaces($facePhoto, $idCardFront, 'kyc', $userId);
            $similarity = $faceResult['similarity'] ?? 0;
            $faceSuccess = $faceResult['success'] ?? false;

            Log::info('KYC人脸比对结果', [
                'user_id' => $userId,
                'similarity' => $similarity,
                'success' => $faceSuccess,
                'provider' => $faceResult['provider'] ?? 'unknown'
            ]);

            // 记录人脸验证结果
            $faceService->logVerification($userId, 'kyc', [
                'face_image' => $facePhotoPath,
                'similarity' => $similarity,
                'liveness_score' => $faceResult['liveness'] ? 1 : 0,
                'success' => $faceSuccess,
                'provider' => $faceResult['provider'] ?? 'mock',
                'message' => $faceResult['message'] ?? ''
            ]);

            // 根据人脸比对结果确定认证状态
            // 相似度 >= 85%：自动通过
            // 相似度 70%-85%：待人工审核
            // 相似度 < 70%：比对失败
            $realNameStatus = 1;  // 默认待审核
            $resultMessage = '';

            if ($similarity >= 0.85) {
                // 自动通过
                $realNameStatus = 2;
                $resultMessage = '实名认证已通过';
            } elseif ($similarity >= 0.70) {
                // 待人工审核
                $realNameStatus = 1;
                $resultMessage = '实名认证已提交，等待人工审核';
            } else {
                // 比对失败，但仍保存记录待审核
                $realNameStatus = 1;
                $resultMessage = '人脸比对相似度较低，已提交人工审核';
            }

            // 更新用户信息
            $updateData = [
                'realname' => $realName,
                'id_card' => $idCard,
                'realname_status' => $realNameStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // 保存图片路径到对应字段
            if ($idCardFrontPath) {
                $updateData['id_card_front'] = $idCardFrontPath;
            }
            if ($idCardBackPath) {
                $updateData['id_card_back'] = $idCardBackPath;
            }
            if ($facePhotoPath) {
                $updateData['id_card_hand'] = $facePhotoPath;
            }

            Db::name('users')->where('id', $userId)->update($updateData);

            Db::commit();

            // 发送Telegram通知
            try {
                $statusText = $realNameStatus == 2 ? '✅ 自动通过' : '⏳ 待审核';
                TelegramBotService::notifyKYC([
                    'username' => $user['username'],
                    'uid' => $user['uid'] ?? $user['id']
                ], [
                    'real_name' => $realName,
                    'id_card' => substr($idCard, 0, 6) . '****' . substr($idCard, -4),
                    'ip' => TelegramBotService::getRealIp(),
                    'similarity' => round($similarity * 100, 1) . '%',
                    'status' => $statusText
                ]);
            } catch (\Exception $e) {
                Log::error('KYC Telegram通知失败', ['error' => $e->getMessage()]);
            }

            return json([
                'code' => 1,
                'msg' => $resultMessage,
                'data' => [
                    'realname_status' => $realNameStatus,
                    'similarity' => round($similarity * 100, 1),
                    'message' => $resultMessage,
                    'auto_pass' => $realNameStatus == 2
                ]
            ]);

        } catch (\Exception $e) {
            Db::rollback();
            Log::error('KYC提交失败', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return json(['code' => -1, 'msg' => '提交失败：' . $e->getMessage()]);
        }
    }

    /**
     * 保存 Base64 图片
     */
    private function saveBase64Image($base64, $prefix = 'img')
    {
        if (empty($base64)) return null;

        // 解析 base64
        if (strpos($base64, 'data:image') === 0) {
            $parts = explode(',', $base64, 2);
            if (count($parts) !== 2) return null;
            $base64 = $parts[1];
        }

        $imageData = base64_decode($base64);
        if (!$imageData) return null;

        // 生成文件名
        $filename = $prefix . '_' . date('Ymd_His') . '_' . uniqid() . '.jpg';
        $uploadDir = public_path() . 'uploads/kyc/';

        // 创建目录
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filepath = $uploadDir . $filename;

        if (file_put_contents($filepath, $imageData)) {
            return '/uploads/kyc/' . $filename;
        }

        return null;
    }


    /**
     * 获取实名认证状态
     * GET /api/user/kyc/status
     */
    public function status()
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return json(['code' => -1, 'msg' => '请先登录']);
        }

        $user = Db::name('users')->where('id', $userId)->find();

        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在']);
        }

        // realname_status: 0=未认证, 1=审核中, 2=已认证, 3=拒绝
        $status = $user['realname_status'] ?? 0;

        return json([
            'code' => 1,
            'msg' => 'success',
            'data' => [
                'is_kyc' => $status,
                'real_name' => $status == 2 ? ($user['realname'] ?? '') : '',
                'id_card' => $status == 2 && !empty($user['id_card'])
                    ? substr($user['id_card'], 0, 6) . '****' . substr($user['id_card'], -4)
                    : '',
                'has_face_photo' => !empty($user['id_card_hand']),
                'reject_reason' => $status == 3 ? ($user['realname_reject_reason'] ?? '') : ''
            ]
        ]);
    }
}
