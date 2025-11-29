<?php
/**
 * 文件上传服务
 * 提供安全的文件上传功能，防止一句话木马等安全威胁
 */

namespace app\common\service;

class FileUploadService
{
    /**
     * 允许的图片MIME类型
     */
    private static $allowedImageMimes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    /**
     * 允许的图片扩展名
     */
    private static $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp'
    ];

    /**
     * 最大文件大小（字节），默认5MB
     */
    private static $maxFileSize = 5 * 1024 * 1024;

    /**
     * 验证并保存base64图片
     * @param string $base64Data base64编码的图片数据
     * @param string $prefix 文件前缀
     * @param string $subDir 子目录（如：face, idcard）
     * @return array ['success' => bool, 'path' => string, 'message' => string]
     */
    public static function saveBase64Image($base64Data, $prefix = 'image', $subDir = 'uploads')
    {
        try {
            // 1. 移除base64前缀
            $imageData = $base64Data;
            if (strpos($base64Data, ',') !== false) {
                $parts = explode(',', $base64Data);
                $mimeType = self::extractMimeType($parts[0]);
                $imageData = $parts[1];
            } else {
                $mimeType = 'image/jpeg'; // 默认
            }

            // 2. 解码base64
            $decodedData = base64_decode($imageData, true);
            if ($decodedData === false) {
                return ['success' => false, 'path' => '', 'message' => 'Base64解码失败'];
            }

            // 3. 验证文件大小
            if (strlen($decodedData) > self::$maxFileSize) {
                return ['success' => false, 'path' => '', 'message' => '文件大小超过限制（最大5MB）'];
            }

            // 4. 验证是否为真实图片（使用getimagesize检测）
            $imageInfo = @getimagesizefromstring($decodedData);
            if ($imageInfo === false) {
                return ['success' => false, 'path' => '', 'message' => '不是有效的图片文件'];
            }

            // 5. 验证MIME类型
            $detectedMime = $imageInfo['mime'];
            if (!in_array($detectedMime, self::$allowedImageMimes)) {
                return ['success' => false, 'path' => '', 'message' => '不支持的图片格式，仅支持JPG/PNG/GIF/WEBP'];
            }

            // 6. 检测一句话木马（检查文件内容）
            $securityCheck = self::checkSecurity($decodedData);
            if (!$securityCheck['safe']) {
                return ['success' => false, 'path' => '', 'message' => '文件安全检查失败：' . $securityCheck['reason']];
            }

            // 7. 生成安全的文件名
            $extension = self::getExtensionFromMime($detectedMime);
            $filename = self::generateSafeFilename($prefix, $extension);

            // 8. 创建上传目录
            $uploadDir = app()->getRootPath() . 'public/' . $subDir . '/' . date('Y/m/d/');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // 9. 保存文件
            $filepath = $uploadDir . $filename;
            $bytesWritten = file_put_contents($filepath, $decodedData);

            if ($bytesWritten === false || $bytesWritten === 0) {
                return ['success' => false, 'path' => '', 'message' => '文件保存失败'];
            }

            // 10. 再次验证保存的文件（二次安全检查）
            $savedImageInfo = @getimagesizefromstring(file_get_contents($filepath));
            if ($savedImageInfo === false) {
                @unlink($filepath); // 删除不安全的文件
                return ['success' => false, 'path' => '', 'message' => '文件保存后验证失败'];
            }

            // 11. 返回相对路径
            $relativePath = $subDir . '/' . date('Y/m/d/') . $filename;

            return [
                'success' => true,
                'path' => $relativePath,
                'full_path' => $filepath,
                'size' => $bytesWritten,
                'mime' => $detectedMime,
                'width' => $imageInfo[0],
                'height' => $imageInfo[1]
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'path' => '', 'message' => '上传失败：' . $e->getMessage()];
        }
    }

    /**
     * 安全检查：检测一句话木马和恶意代码
     * @param string $fileContent 文件内容
     * @return array ['safe' => bool, 'reason' => string]
     */
    private static function checkSecurity($fileContent)
    {
        // 1. 检测PHP标签
        if (preg_match('/<\?php/i', $fileContent) ||
            preg_match('/<\?=/i', $fileContent) ||
            preg_match('/<script/i', $fileContent)) {
            return ['safe' => false, 'reason' => '检测到可疑代码标签'];
        }

        // 2. 检测常见的一句话木马关键词
        $dangerousKeywords = [
            'eval(',
            'assert(',
            'system(',
            'exec(',
            'shell_exec(',
            'passthru(',
            'popen(',
            'proc_open(',
            'file_get_contents(',
            'file_put_contents(',
            'fwrite(',
            'fopen(',
            'base64_decode(',
            'gzinflate(',
            'str_rot13(',
            'create_function(',
            'call_user_func(',
            'preg_replace.*\/e',
            'include(',
            'require(',
            'include_once(',
            'require_once(',
        ];

        foreach ($dangerousKeywords as $keyword) {
            if (preg_match('/' . preg_quote($keyword, '/') . '/i', $fileContent)) {
                return ['safe' => false, 'reason' => '检测到危险函数：' . $keyword];
            }
        }

        // 3. 检测base64编码的可疑内容
        if (preg_match('/[a-zA-Z0-9+\/]{100,}/', $fileContent)) {
            // 如果包含长base64字符串，进一步检查
            if (preg_match('/eval.*base64/i', $fileContent)) {
                return ['safe' => false, 'reason' => '检测到base64编码的可执行代码'];
            }
        }

        // 4. 检测图片文件头（确保是真实图片）
        $imageHeaders = [
            "\xFF\xD8\xFF", // JPEG
            "\x89\x50\x4E\x47", // PNG
            "GIF87a", // GIF87a
            "GIF89a", // GIF89a
            "RIFF", // WEBP (需要进一步检查)
        ];

        $isValidImage = false;
        foreach ($imageHeaders as $header) {
            if (strpos($fileContent, $header) === 0) {
                $isValidImage = true;
                break;
            }
        }

        // WEBP特殊检查
        if (strpos($fileContent, "RIFF") === 0 && strpos($fileContent, "WEBP", 8) !== false) {
            $isValidImage = true;
        }

        if (!$isValidImage) {
            return ['safe' => false, 'reason' => '文件头验证失败，不是有效的图片文件'];
        }

        return ['safe' => true, 'reason' => ''];
    }

    /**
     * 从data URI提取MIME类型
     */
    private static function extractMimeType($dataUri)
    {
        if (preg_match('/data:([^;]+)/', $dataUri, $matches)) {
            return trim($matches[1]);
        }
        return 'image/jpeg';
    }

    /**
     * 从MIME类型获取扩展名
     */
    private static function getExtensionFromMime($mime)
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        return $mimeMap[$mime] ?? 'jpg';
    }

    /**
     * 生成安全的文件名
     */
    private static function generateSafeFilename($prefix, $extension)
    {
        // 使用时间戳 + 随机字符串，避免文件名冲突和可预测性
        $random = bin2hex(random_bytes(8));
        $timestamp = time();
        return $prefix . '_' . $timestamp . '_' . $random . '.' . $extension;
    }
}

