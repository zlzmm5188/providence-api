<?php
/**
 * 用户端人脸识别控制器
 * 提供独立的人脸上传和验证接口
 */

namespace app\api\controller;

use app\common\model\User;
use app\common\service\FaceRecognitionService;
use think\facade\Db;

class Face
{
    /**
     * 上传人脸照片
     * POST /api/user/face-upload
     */
    public function upload()
    {
        $userId = request()->userId;
        $faceImage = input('post.face_image', '', 'trim'); // base64编码的人脸照片

        if (empty($faceImage)) {
            return json(['code' => -1, 'msg' => '请上传人脸照片', 'data' => null]);
        }

        $user = User::find($userId);
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        try {
            // 保存人脸照片
            $facePhotoPath = $this->saveFacePhoto($userId, $faceImage);

            // 更新用户的人脸照片
            $user->face_photo = $facePhotoPath;
            $user->save();

            return json([
                'code' => 1,
                'msg' => '上传成功',
                'data' => [
                    'face_photo_url' => $facePhotoPath,
                    'has_face_photo' => true
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => '上传失败：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 人脸识别验证
     * POST /api/user/face-verify
     */
    public function verify()
    {
        $userId = request()->userId;
        $scene = input('post.scene', 'general', 'trim'); // 场景：general-通用, withdraw-提现, login-登录
        $faceImage = input('post.face_image', '', 'trim'); // base64编码的人脸照片

        if (empty($faceImage)) {
            return json(['code' => -1, 'msg' => '请上传人脸照片', 'data' => null]);
        }

        $user = User::find($userId);
        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        // 检查用户是否已上传人脸照片
        if (empty($user->face_photo)) {
            return json([
                'code' => -1,
                'msg' => '请先上传人脸照片',
                'data' => ['need_upload' => true]
            ]);
        }

        $faceService = new FaceRecognitionService();

        // 检查失败次数
        if ($faceService->isExceedMaxFailCount($userId, $scene)) {
            return json([
                'code' => -1,
                'msg' => '人脸识别失败次数过多，请24小时后再试',
                'data' => null
            ]);
        }

        // 获取用户已保存的人脸照片
        $savedFacePhoto = $user->face_photo;
        if (file_exists(app()->getRootPath() . 'public/' . $savedFacePhoto)) {
            $savedFacePhoto = base64_encode(file_get_contents(app()->getRootPath() . 'public/' . $savedFacePhoto));
        } elseif (strpos($savedFacePhoto, 'data:image') === 0) {
            // 如果已经是base64格式
            if (strpos($savedFacePhoto, ',') !== false) {
                $savedFacePhoto = explode(',', $savedFacePhoto)[1];
            }
        }

        // 移除上传照片的base64前缀
        $uploadFacePhoto = $faceImage;
        if (strpos($faceImage, ',') !== false) {
            $uploadFacePhoto = explode(',', $faceImage)[1];
        }

        // 调用人脸识别服务
        $result = $faceService->compareFaces($uploadFacePhoto, $savedFacePhoto, $scene, $userId);

        // 活体检测
        if ($result['success']) {
            $livenessResult = $faceService->detectLiveness($uploadFacePhoto);
            if (!$livenessResult['is_alive']) {
                $result['success'] = false;
                $result['message'] = '活体检测失败，请确保是真人操作';
            }
            $result['liveness_score'] = $livenessResult['confidence'];
        }

        // 记录识别结果
        $result['face_image'] = $uploadFacePhoto;
        $faceService->logVerification($userId, $scene, $result);

        if ($result['success']) {
            return json([
                'code' => 1,
                'msg' => '人脸识别验证成功',
                'data' => [
                    'verified' => true,
                    'similarity' => $result['similarity'],
                    'liveness_score' => $result['liveness_score'] ?? 0,
                    'message' => $result['message']
                ]
            ]);
        } else {
            return json([
                'code' => -1,
                'msg' => $result['message'],
                'data' => [
                    'verified' => false,
                    'similarity' => $result['similarity'] ?? 0,
                    'liveness_score' => $result['liveness_score'] ?? 0
                ]
            ]);
        }
    }

    /**
     * 获取人脸照片状态
     * GET /api/user/face/status
     */
    public function status()
    {
        $userId = request()->userId;
        $user = User::find($userId);

        if (!$user) {
            return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
        }

        return json([
            'code' => 1,
            'msg' => '获取成功',
            'data' => [
                'has_face_photo' => !empty($user->face_photo),
                'face_photo_url' => $user->face_photo ?? null
            ]
        ]);
    }

    /**
     * 保存人脸照片
     */
    private function saveFacePhoto($userId, $base64Image)
    {
        $uploadDir = app()->getRootPath() . 'public/uploads/face/' . date('Y/m/d/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'face_' . $userId . '_' . time() . '.jpg';
        $filepath = $uploadDir . $filename;

        // 移除base64前缀
        $imageData = $base64Image;
        if (strpos($base64Image, ',') !== false) {
            $imageData = explode(',', $base64Image)[1];
        }

        file_put_contents($filepath, base64_decode($imageData));

        // 返回相对路径
        return 'uploads/face/' . date('Y/m/d/') . $filename;
    }
}
