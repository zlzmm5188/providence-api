<?php
/**
 * Providence 系统辅助函数（最简化版本）
 */

if (!function_exists('bcmath_add')) {
    /**
     * 精确加法（8位小数）
     */
    function bcmath_add($a, $b, $scale = 8) {
        return bcadd((string)$a, (string)$b, $scale);
    }
}

if (!function_exists('bcmath_sub')) {
    /**
     * 精确减法（8位小数）
     */
    function bcmath_sub($a, $b, $scale = 8) {
        return bcsub((string)$a, (string)$b, $scale);
    }
}

if (!function_exists('bcmath_mul')) {
    /**
     * 精确乘法（8位小数）
     */
    function bcmath_mul($a, $b, $scale = 8) {
        return bcmul((string)$a, (string)$b, $scale);
    }
}

if (!function_exists('bcmath_div')) {
    /**
     * 精确除法（8位小数）
     */
    function bcmath_div($a, $b, $scale = 8) {
        if (bccomp((string)$b, '0', $scale) === 0) {
            throw new \Exception('除数不能为0');
        }
        return bcdiv((string)$a, (string)$b, $scale);
    }
}

if (!function_exists('bcmath_comp')) {
    /**
     * 精确比较
     * @return int -1:小于, 0:等于, 1:大于
     */
    function bcmath_comp($a, $b, $scale = 8) {
        return bccomp((string)$a, (string)$b, $scale);
    }
}

if (!function_exists('success')) {
    /**
     * 成功响应
     */
    function success($msg = '操作成功', $data = []) {
        return json([
            'code' => 1,
            'msg' => $msg,
            'data' => $data
        ]);
    }
}

if (!function_exists('error')) {
    /**
     * 失败响应
     * @param string $msg 错误消息
     * @param array|int $dataOrCode 数据或错误码（兼容两种调用方式）
     * @param int $code 错误码
     */
    function error($msg = '操作失败', $dataOrCode = [], $code = -1) {
        // 兼容 error('msg', [], 401) 和 error('msg', -1) 两种调用方式
        if (is_int($dataOrCode)) {
            // error('msg', -1) 格式
            return json([
                'code' => $dataOrCode,
                'msg' => $msg,
                'data' => []
            ]);
        } elseif (is_array($dataOrCode) && is_int($code)) {
            // error('msg', [], 401) 格式
            return json([
                'code' => $code,
                'msg' => $msg,
                'data' => $dataOrCode
            ]);
        } else {
            // 默认格式
            return json([
                'code' => -1,
                'msg' => $msg,
                'data' => is_array($dataOrCode) ? $dataOrCode : []
            ]);
        }
    }
}

if (!function_exists('generate_order_no')) {
    /**
     * 生成订单号
     * @param string $prefix R=充值, W=提现, I=投资
     */
    function generate_order_no($prefix = 'O') {
        $timestamp = date('YmdHis') . substr(microtime(), 2, 6);
        $random = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        return $prefix . $timestamp . $random;
    }
}

if (!function_exists('generate_invite_code')) {
    /**
     * 生成8位随机邀请码（包含字母和数字）
     */
    function generate_invite_code() {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 排除容易混淆的字符：0,1,I,O
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $code;
    }
}

if (!function_exists('generate_unique_uid')) {
    /**
     * 生成唯一UID（8位数字）
     */
    function generate_unique_uid() {
        do {
            $uid = str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            $exists = \app\common\model\User::where('uid', $uid)->count();
        } while ($exists > 0);
        return $uid;
    }
}

if (!function_exists('generate_unique_invite_code')) {
    /**
     * 生成唯一邀请码（8位字母+数字）
     */
    function generate_unique_invite_code() {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 排除容易混淆的字符：0,1,I,O
        $maxAttempts = 100; // 防止无限循环
        $attempts = 0;

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
            $exists = \app\common\model\User::where('invite_code', $code)->count();
            $attempts++;

            if ($attempts >= $maxAttempts) {
                // 如果尝试100次还是重复，添加时间戳后缀
                $code = substr($code, 0, 6) . substr(strtoupper(md5(time() . mt_rand())), 0, 2);
                break;
            }
        } while ($exists > 0);

        return $code;
    }
}

if (!function_exists('check_username')) {
    /**
     * 验证用户名（4-50位字母数字下划线，支持长账号）
     */
    function check_username($username) {
        return preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username);
    }
}

if (!function_exists('check_id_card')) {
    /**
     * 验证身份证号
     */
    function check_id_card($idCard) {
        return preg_match('/^[1-9]\d{5}(18|19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[\dXx]$/', $idCard);
    }
}

if (!function_exists('get_user_id')) {
    /**
     * 从Token中获取用户ID（统一前后台）
     * 兼容：id -> user_id -> admin_id
     */
    function get_user_id() {
        // 优先从request中获取（如果中间件已设置）
        if (request()->userId) {
            return request()->userId;
        }

        $token = request()->header('Authorization') ?: request()->header('token');
        if (empty($token)) {
            return 0;
        }

        // 移除Bearer前缀
        $token = str_replace('Bearer ', '', $token);

        try {
            $payload = jwt_decode($token, config('jwt.secret'));
            if (!$payload) {
                return 0;
            }

            // 统一解析：id -> user_id -> admin_id（与AuthMiddleware保持一致）
            return $payload['id'] ?? $payload['user_id'] ?? $payload['admin_id'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('jwt_decode')) {
    /**
     * JWT解码
     */
    function jwt_decode($token, $secret) {
        try {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));
            return json_decode(json_encode($decoded), true);
        } catch (\Exception $e) {
            return null;
        }
    }
}
