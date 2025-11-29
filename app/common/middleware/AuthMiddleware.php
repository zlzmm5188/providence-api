<?php
namespace app\common\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class AuthMiddleware
{
    /**
     * 处理请求
     */
    public function handle($request, \Closure $next)
    {
        $logFile = '/www/wwwroot/api.4kp3l0iq.top/runtime/debug_auth.log';
        file_put_contents($logFile, "\n[" . date('Y-m-d H:i:s') . "] ========== AuthMiddleware 开始 ==========\n", FILE_APPEND);
        file_put_contents($logFile, "Request URI: " . $request->url(true) . "\n", FILE_APPEND);

        // 1. 获取Token
        $token = $request->header('authorization');
        file_put_contents($logFile, "Authorization header: " . ($token ?: 'null') . "\n", FILE_APPEND);

        if (!$token) {
            $token = $request->header('token');
            file_put_contents($logFile, "Token header: " . ($token ?: 'null') . "\n", FILE_APPEND);
        }

        if ($token && stripos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
            file_put_contents($logFile, "Bearer token extracted: " . substr($token, 0, 30) . "...\n", FILE_APPEND);
        }

        if (!$token) {
            file_put_contents($logFile, "❌ Token不存在，返回401\n", FILE_APPEND);
            return json([
                'code' => 401,
                'msg' => '请先登录',
                'data' => []
            ]);
        }

        // 2. 验证Token
        try {
            // 确保使用统一密钥 config('jwt.secret')
            $secret = config('jwt.secret');
            if (empty($secret)) {
                file_put_contents($logFile, "❌ JWT密钥未配置\n", FILE_APPEND);
                return json([
                    'code' => 401,
                    'msg' => '系统配置错误',
                    'data' => []
                ]);
            }
            file_put_contents($logFile, "JWT secret: " . substr($secret, 0, 10) . "...\n", FILE_APPEND);

            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            file_put_contents($logFile, "JWT decoded: " . json_encode($decoded) . "\n", FILE_APPEND);

            // 3. 将用户ID存入请求
            // 优先解析：id -> user_id -> admin_id（兼容前台与后台的 token）
            $uid = null;
            if (isset($decoded->id)) {
                $uid = $decoded->id;
                file_put_contents($logFile, "✅ 从 payload['id'] 获取: " . $uid . "\n", FILE_APPEND);
            } elseif (isset($decoded->user_id)) {
                $uid = $decoded->user_id;
                file_put_contents($logFile, "✅ 从 payload['user_id'] 获取: " . $uid . "\n", FILE_APPEND);
            } elseif (isset($decoded->admin_id)) {
                $uid = $decoded->admin_id;
                file_put_contents($logFile, "⚠️  从 payload['admin_id'] 获取: " . $uid . "\n", FILE_APPEND);
            }

            if (!$uid) {
                file_put_contents($logFile, "❌ Token中未找到 id、user_id 或 admin_id\n", FILE_APPEND);
                return json([
                    'code' => 401,
                    'msg' => 'Token无效：缺少用户ID',
                    'data' => []
                ]);
            }

            $request->userId = $uid;
            file_put_contents($logFile, "✅ Request userId已设置为: " . $request->userId . "\n", FILE_APPEND);

            return $next($request);

        } catch (ExpiredException $e) {
            file_put_contents($logFile, "❌ Token过期: " . $e->getMessage() . "\n", FILE_APPEND);
            return json([
                'code' => 401,
                'msg' => 'Token已过期，请重新登录',
                'data' => []
            ]);
        } catch (\Exception $e) {
            file_put_contents($logFile, "❌ Token验证失败: " . $e->getMessage() . "\n", FILE_APPEND);
            return json([
                'code' => 401,
                'msg' => 'Token验证失败: ' . $e->getMessage(),
                'data' => []
            ]);
        }
    }
}
