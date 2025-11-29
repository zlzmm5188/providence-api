<?php
/**
 * 人脸识别服务
 * 支持腾讯云、阿里云、百度AI等第三方API
 */

namespace app\common\service;

use app\common\model\User;
use think\facade\Db;
use think\facade\Cache;

class FaceRecognitionService
{
    /**
     * 人脸识别提供商
     */
    const PROVIDER_TENCENT = 'tencent';  // 腾讯云
    const PROVIDER_ALIYUN = 'aliyun';    // 阿里云
    const PROVIDER_BAIDU = 'baidu';      // 百度AI
    const PROVIDER_MOCK = 'mock';        // 模拟（开发测试）

    /**
     * 获取配置
     */
    private function getConfig()
    {
        $config = Db::name('system_config')
            ->where('key', 'face_verification_settings')
            ->value('value');

        if ($config) {
            $config = json_decode($config, true);
        } else {
            $config = [
                'provider' => self::PROVIDER_MOCK,
                'apiKey' => '',
                'secretKey' => '',
                'autoPassThreshold' => 0.85,
                'manualReviewThreshold' => 0.70,
                'enableLiveness' => true,
                'enablePasswordResetVerify' => true,
                'maxFailCount' => 3
            ];
        }

        return $config;
    }

    /**
     * 人脸比对
     * @param string $faceImage1 base64编码的人脸照片1（用户上传的）
     * @param string $faceImage2 base64编码的人脸照片2（数据库中保存的）
     * @param string $type 类型：kyc-实名认证, password_reset-找回密码
     * @param int $userId 用户ID
     * @return array ['success' => bool, 'similarity' => float, 'message' => string, 'liveness' => bool]
     */
    public function compareFaces($faceImage1, $faceImage2, $type = 'kyc', $userId = 0)
    {
        $config = $this->getConfig();
        $provider = $config['provider'] ?? self::PROVIDER_MOCK;

        // 根据提供商调用不同的API
        switch ($provider) {
            case self::PROVIDER_TENCENT:
                return $this->compareWithTencent($faceImage1, $faceImage2, $config);
            case self::PROVIDER_ALIYUN:
                return $this->compareWithAliyun($faceImage1, $faceImage2, $config);
            case self::PROVIDER_BAIDU:
                return $this->compareWithBaidu($faceImage1, $faceImage2, $config);
            case self::PROVIDER_MOCK:
            default:
                return $this->compareWithMock($faceImage1, $faceImage2, $config);
        }
    }

    /**
     * 活体检测
     * @param string $faceImage base64编码的人脸照片
     * @return array ['is_alive' => bool, 'confidence' => float]
     */
    public function detectLiveness($faceImage)
    {
        $config = $this->getConfig();
        $provider = $config['provider'] ?? self::PROVIDER_MOCK;

        if (!$config['enableLiveness']) {
            return ['is_alive' => true, 'confidence' => 1.0];
        }

        switch ($provider) {
            case self::PROVIDER_TENCENT:
                return $this->detectLivenessWithTencent($faceImage, $config);
            case self::PROVIDER_ALIYUN:
                return $this->detectLivenessWithAliyun($faceImage, $config);
            case self::PROVIDER_BAIDU:
                return $this->detectLivenessWithBaidu($faceImage, $config);
            default:
                return ['is_alive' => true, 'confidence' => 0.95];
        }
    }

    /**
     * 记录人脸识别结果
     * @param int $userId 用户ID
     * @param string $type 类型：kyc, password_reset
     * @param array $result 识别结果
     * @return int 记录ID
     */
    public function logVerification($userId, $type, $result)
    {
        $data = [
            'user_id' => $userId,
            'type' => $type,
            'face_image' => $result['face_image'] ?? '',
            'similarity' => $result['similarity'] ?? 0,
            'liveness_score' => $result['liveness_score'] ?? 0,
            'result' => $result['success'] ? 1 : 2, // 1-通过, 2-失败, 0-待审核
            'provider' => $result['provider'] ?? 'mock',
            'remark' => $result['message'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // 如果相似度在阈值之间，需要人工审核
        $config = $this->getConfig();
        if ($result['similarity'] >= $config['manualReviewThreshold'] &&
            $result['similarity'] < $config['autoPassThreshold']) {
            $data['result'] = 0; // 待审核
        }

        return Db::name('face_verification')->insertGetId($data);
    }

    /**
     * 腾讯云人脸比对
     */
    private function compareWithTencent($faceImage1, $faceImage2, $config)
    {
        // TODO: 集成腾讯云人脸识别API
        // 参考文档：https://cloud.tencent.com/document/product/867/32802

        $apiKey = $config['apiKey'] ?? '';
        $secretKey = $config['secretKey'] ?? '';

        if (empty($apiKey) || empty($secretKey)) {
            return $this->compareWithMock($faceImage1, $faceImage2, $config);
        }

        // 调用腾讯云API
        // $result = $this->callTencentAPI($faceImage1, $faceImage2, $apiKey, $secretKey);

        // 临时返回模拟结果
        return [
            'success' => true,
            'similarity' => 0.92,
            'message' => '人脸比对成功',
            'liveness' => true,
            'provider' => self::PROVIDER_TENCENT
        ];
    }

    /**
     * 阿里云人脸比对
     */
    private function compareWithAliyun($faceImage1, $faceImage2, $config)
    {
        // TODO: 集成阿里云人脸识别API
        // 参考文档：https://help.aliyun.com/document_detail/155003.html

        $apiKey = $config['apiKey'] ?? '';
        $secretKey = $config['secretKey'] ?? '';

        if (empty($apiKey) || empty($secretKey)) {
            return $this->compareWithMock($faceImage1, $faceImage2, $config);
        }

        // 临时返回模拟结果
        return [
            'success' => true,
            'similarity' => 0.91,
            'message' => '人脸比对成功',
            'liveness' => true,
            'provider' => self::PROVIDER_ALIYUN
        ];
    }

    /**
     * 百度AI人脸比对
     */
    private function compareWithBaidu($faceImage1, $faceImage2, $config)
    {
        // TODO: 集成百度AI人脸识别API
        // 参考文档：https://ai.baidu.com/ai-doc/FACE/yk37c1u4t

        $apiKey = $config['apiKey'] ?? '';
        $secretKey = $config['secretKey'] ?? '';

        if (empty($apiKey) || empty($secretKey)) {
            return $this->compareWithMock($faceImage1, $faceImage2, $config);
        }

        // 临时返回模拟结果
        return [
            'success' => true,
            'similarity' => 0.93,
            'message' => '人脸比对成功',
            'liveness' => true,
            'provider' => self::PROVIDER_BAIDU
        ];
    }

    /**
     * 模拟人脸比对（开发测试用）
     */
    private function compareWithMock($faceImage1, $faceImage2, $config)
    {
        // 简单的模拟逻辑：检查图片是否有效
        if (empty($faceImage1) || empty($faceImage2)) {
            return [
                'success' => false,
                'similarity' => 0,
                'message' => '人脸照片不能为空',
                'liveness' => false,
                'provider' => self::PROVIDER_MOCK
            ];
        }

        // 模拟相似度（实际应该调用真实API）
        $similarity = 0.90 + (rand(0, 10) / 100); // 90%-100%之间随机

        $threshold = $config['autoPassThreshold'] ?? 0.85;

        return [
            'success' => $similarity >= $threshold,
            'similarity' => $similarity,
            'message' => $similarity >= $threshold ? '人脸比对成功' : '人脸比对失败，相似度不足',
            'liveness' => true,
            'provider' => self::PROVIDER_MOCK
        ];
    }

    /**
     * 腾讯云活体检测
     */
    private function detectLivenessWithTencent($faceImage, $config)
    {
        // TODO: 实现腾讯云活体检测
        return ['is_alive' => true, 'confidence' => 0.95];
    }

    /**
     * 阿里云活体检测
     */
    private function detectLivenessWithAliyun($faceImage, $config)
    {
        // TODO: 实现阿里云活体检测
        return ['is_alive' => true, 'confidence' => 0.94];
    }

    /**
     * 百度AI活体检测
     */
    private function detectLivenessWithBaidu($faceImage, $config)
    {
        // TODO: 实现百度AI活体检测
        return ['is_alive' => true, 'confidence' => 0.96];
    }

    /**
     * 检查用户人脸识别失败次数
     * @param int $userId 用户ID
     * @param string $type 类型
     * @return int 失败次数
     */
    public function getFailCount($userId, $type = 'kyc')
    {
        $config = $this->getConfig();
        $maxFailCount = $config['maxFailCount'] ?? 3;

        // 获取最近24小时内的失败次数
        $failCount = Db::name('face_verification')
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('result', 2) // 失败
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->count();

        return $failCount;
    }

    /**
     * 检查是否超过最大失败次数
     */
    public function isExceedMaxFailCount($userId, $type = 'kyc')
    {
        $config = $this->getConfig();
        $maxFailCount = $config['maxFailCount'] ?? 3;
        $failCount = $this->getFailCount($userId, $type);

        return $failCount >= $maxFailCount;
    }
}
