<?php
namespace app\common\service;

use think\facade\Log;

/**
 * Telegram Bot通知服务
 */
class TelegramBotService
{
    private static $botToken = ''; // 从.env读取
    private static $chatId = '';   // 从.env读取

    /**
     * 初始化配置
     */
    private static function init()
    {
        // 如果已经初始化过，直接返回
        if (self::$botToken && self::$chatId) {
            return;
        }

        // 优先从.env文件直接读取（更可靠）
        // 获取项目根目录（兼容命令行和Web环境）
        $rootPath = '';
        if (function_exists('app') && method_exists(app(), 'getRootPath')) {
            $rootPath = app()->getRootPath();
        } else {
            // 从当前文件位置推断根目录
            $rootPath = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR;
        }
        $envFile = $rootPath . '.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                // 跳过注释和空行
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, 'TELEGRAM_BOT_TOKEN=') === 0) {
                    self::$botToken = trim(substr($line, strlen('TELEGRAM_BOT_TOKEN=')));
                } elseif (strpos($line, 'TELEGRAM_CHAT_ID=') === 0) {
                    self::$chatId = trim(substr($line, strlen('TELEGRAM_CHAT_ID=')));
                }
            }
        }

        // 如果.env文件读取失败，尝试从env()函数读取（ThinkPHP 6）
        if ((!self::$botToken || !self::$chatId) && function_exists('env')) {
            if (!self::$botToken) {
                self::$botToken = env('TELEGRAM_BOT_TOKEN', '');
            }
            if (!self::$chatId) {
                self::$chatId = env('TELEGRAM_CHAT_ID', '');
            }
        }
    }

    /**
     * 发送消息（支持按钮）
     */
    public static function sendMessage($message, $parseMode = 'HTML', $buttons = null)
    {
        self::init();

        if (!self::$botToken || !self::$chatId) {
            Log::warning('Telegram Bot未配置', [
                'has_token' => !empty(self::$botToken),
                'has_chat_id' => !empty(self::$chatId)
            ]);
            return false;
        }

        $url = "https://api.telegram.org/bot" . self::$botToken . "/sendMessage";

        $data = [
            'chat_id' => self::$chatId,
            'text' => $message,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true
        ];

        // 添加inline keyboard按钮
        if ($buttons) {
            $data['reply_markup'] = [
                'inline_keyboard' => $buttons
            ];
        }

        $result = self::httpPost($url, $data);

        if ($result && isset($result['ok']) && $result['ok']) {
            Log::info('Telegram消息发送成功', ['message' => substr($message, 0, 100)]);
            return true;
        } else {
            // 记录详细错误信息
            $errorMsg = '未知错误';
            if ($result && isset($result['description'])) {
                $errorMsg = $result['description'];
            } elseif ($result && isset($result['error_code'])) {
                $errorMsg = '错误代码: ' . $result['error_code'];
            } elseif (!$result) {
                $errorMsg = 'HTTP请求失败或返回为空';
            }
            Log::error('Telegram消息发送失败', [
                'error' => $errorMsg,
                'result' => $result,
                'url' => str_replace(self::$botToken, '***', $url),
                'chat_id' => self::$chatId ? '已配置' : '未配置',
                'token' => self::$botToken ? '已配置' : '未配置'
            ]);
            return false;
        }
    }

    /**
     * 用户注册通知
     */
    public static function notifyUserRegister($user)
    {
        $uid = $user['uid'] ?? 'N/A';
        $message = "👤 用户名: <code>{$user['username']}</code>\n";
        $message .= "🆔 UID: <code>{$uid}</code>\n";
        $message .= "🎫 邀请码: <code>{$user['invite_code']}</code>\n";

        if ($user['parent_id']) {
            $message .= "👥 推荐人ID: <code>{$user['parent_id']}</code>\n";
        }

        // IP地址和位置
        $ipInfo = self::getIpInfo($user['ip'] ?? '');
        if ($ipInfo) {
            $message .= "🌐 IP: <code>{$ipInfo['ip']}</code>\n";
            $message .= "📍 位置: {$ipInfo['location']}\n";
        }

        $message .= "⏰ 时间: " . date('Y-m-d H:i:s');

        // 按钮
        $buttons = [
            [
                ['text' => '🎉 新用户注册', 'callback_data' => 'register_' . ($user['uid'] ?? $user['id'])]
            ]
        ];

        return self::sendMessage($message, 'HTML', $buttons);
    }

    /**
     * KYC实名认证通知（含人脸比对结果）
     */
    public static function notifyKYC($user, $kycData)
    {
        $uid = $user['uid'] ?? 'N/A';
        $status = $kycData['status'] ?? '⏳ 待审核';
        $similarity = $kycData['similarity'] ?? '';

        // 根据状态选择标题
        $title = strpos($status, '自动通过') !== false ? "✅ <b>实名认证自动通过</b>\n\n" : "🪪 <b>实名认证提交</b>\n\n";

        $message = $title;
        $message .= "👤 用户: <code>{$user['username']}</code> (UID: {$uid})\n";
        $message .= "🪪 姓名: <code>{$kycData['real_name']}</code>\n";
        $message .= "🆔 身份证: <code>{$kycData['id_card']}</code>\n";

        // 人脸比对结果
        if (!empty($similarity)) {
            $message .= "🤖 人脸相似度: <b>{$similarity}</b>\n";
        }
        $message .= "📋 状态: {$status}\n";

        // IP地址和位置
        $ipInfo = self::getIpInfo($kycData['ip'] ?? '');
        if ($ipInfo) {
            $message .= "🌐 IP: <code>{$ipInfo['ip']}</code>\n";
            $message .= "📍 位置: {$ipInfo['location']}\n";
        }

        $message .= "⏰ 时间: " . date('Y-m-d H:i:s');

        // 按钮文字根据状态变化
        $buttonText = strpos($status, '自动通过') !== false ? '✅ 已自动通过' : '📝 待人工审核';
        $buttons = [
            [
                ['text' => $buttonText, 'callback_data' => 'kyc_' . $user['uid']]
            ]
        ];

        return self::sendMessage($message, 'HTML', $buttons);
    }

    /**
     * 充值通知（兼容旧版本，根据status自动选择）
     */
    public static function notifyRecharge($user, $recharge)
    {
        if (($recharge['status'] ?? 0) == 1) {
            return self::notifyRechargeSuccess($user, $recharge);
        } else {
            return self::notifyRechargeCreated($user, $recharge);
        }
    }

    /**
     * 创建充值订单通知
     */
    public static function notifyRechargeCreated($user, $recharge)
    {
        $uid = $user['uid'] ?? 'N/A';
        $method = strtolower($recharge['payment_method'] ?? '');

        // 根据支付方式设置标题和emoji
        $methodEmoji = '💳';
        $methodName = '银行卡';
        $buttonText = '📝 创建充值订单';

        if ($method === 'wechat' || strpos($method, 'wechat') !== false) {
            $methodEmoji = '💚';
            $methodName = '微信支付';
            $buttonText = '💚 微信充值订单';
        } elseif ($method === 'alipay' || strpos($method, 'alipay') !== false) {
            $methodEmoji = '🔵';
            $methodName = '支付宝';
            $buttonText = '🔵 支付宝充值订单';
        } elseif ($method === 'usdt' || strpos($method, 'usdt') !== false) {
            $methodEmoji = '💎';
            $methodName = 'USDT';
            $buttonText = '💎 USDT充值订单';
        }

        $message = "{$methodEmoji} <b>创建充值订单</b>\n\n";
        $message .= "👤 用户: <code>{$user['username']}</code> (UID: {$uid})\n";
        $message .= "💵 金额: <b>{$recharge['amount']} {$recharge['currency']}</b>\n";
        $message .= "💳 方式: {$methodName}\n";
        $message .= "📋 订单号: <code>{$recharge['order_no']}</code>\n";
        $message .= "⏳ 状态: <b>待支付</b>\n";

        // IP地址和位置
        $ipInfo = self::getIpInfo($recharge['ip'] ?? '');
        if ($ipInfo) {
            $message .= "🌐 IP: <code>{$ipInfo['ip']}</code>\n";
            $message .= "📍 位置: {$ipInfo['location']}\n";
        }

        $message .= "⏰ 时间: " . date('Y-m-d H:i:s');

        $buttons = [
            [
                ['text' => $buttonText, 'callback_data' => 'recharge_created_' . $recharge['order_no']]
            ]
        ];

        return self::sendMessage($message, 'HTML', $buttons);
    }

    /**
     * 充值成功通知
     */
    public static function notifyRechargeSuccess($user, $recharge)
    {
        $uid = $user['uid'] ?? 'N/A';
        $method = strtolower($recharge['payment_method'] ?? '');

        // 根据支付方式设置emoji
        $methodEmoji = '💳';
        $methodName = '银行卡';

        if ($method === 'wechat' || strpos($method, 'wechat') !== false) {
            $methodEmoji = '💚';
            $methodName = '微信支付';
        } elseif ($method === 'alipay' || strpos($method, 'alipay') !== false) {
            $methodEmoji = '🔵';
            $methodName = '支付宝';
        } elseif ($method === 'usdt' || strpos($method, 'usdt') !== false) {
            $methodEmoji = '💎';
            $methodName = 'USDT';
        }

        $message = "🌟 <b>充值成功</b> 🌟\n\n";
        $message .= "👤 用户: <code>{$user['username']}</code> (UID: {$uid})\n";
        $message .= "💵 金额: <b>{$recharge['amount']} {$recharge['currency']}</b>\n";
        $message .= "{$methodEmoji} 方式: {$methodName}\n";
        $message .= "📋 订单号: <code>{$recharge['order_no']}</code>\n";
        $message .= "✅ 状态: <b>已到账</b>\n";

        // IP地址和位置
        $ipInfo = self::getIpInfo($recharge['ip'] ?? '');
        if ($ipInfo) {
            $message .= "🌐 IP: <code>{$ipInfo['ip']}</code>\n";
            $message .= "📍 位置: {$ipInfo['location']}\n";
        }

        $message .= "⏰ 时间: " . date('Y-m-d H:i:s');

        $buttons = [
            [
                ['text' => '🌟充值成功🌟', 'callback_data' => 'recharge_success_' . $recharge['order_no']]
            ]
        ];

        return self::sendMessage($message, 'HTML', $buttons);
    }

    /**
     * 投资下单通知
     */
    public static function notifyInvest($user, $invest, $project)
    {
        $uid = $user['uid'] ?? 'N/A';
        $message = "👤 用户: <code>{$user['username']}</code> (UID: {$uid})\n";
        $message .= "🎯 项目: <b>{$project['name']}</b>\n";
        $message .= "💰 投资金额: <b>{$invest['amount']} {$invest['currency']}</b>\n";
        $message .= "📊 预期收益率: <b>{$project['return_rate']}%</b>\n";
        $message .= "⏱ 周期: {$project['duration']}天\n";
        $message .= "💵 预期收益: <b>" . ($invest['amount'] * $project['return_rate'] / 100) . " {$invest['currency']}</b>\n";

        // IP地址和位置
        $ipInfo = self::getIpInfo($invest['ip'] ?? '');
        if ($ipInfo) {
            $message .= "🌐 IP: <code>{$ipInfo['ip']}</code>\n";
            $message .= "📍 位置: {$ipInfo['location']}\n";
        }

        $message .= "⏰ 时间: " . date('Y-m-d H:i:s');

        $buttons = [
            [
                ['text' => '📈 新投资订单', 'callback_data' => 'invest_' . $invest['amount']]
            ]
        ];

        return self::sendMessage($message, 'HTML', $buttons);
    }

    /**
     * 提现申请通知
     */
    public static function notifyWithdraw($user, $withdraw)
    {
        $uid = $user['uid'] ?? 'N/A';
        $message = "👤 用户: <code>{$user['username']}</code> (UID: {$uid})\n";
        $message .= "💰 金额: <b>{$withdraw['amount']} {$withdraw['currency']}</b>\n";
        $message .= "🏦 地址: <code>{$withdraw['withdraw_address']}</code>\n";
        $message .= "📋 订单号: <code>{$withdraw['order_no']}</code>\n";

        // IP地址和位置
        $ipInfo = self::getIpInfo($withdraw['ip'] ?? '');
        if ($ipInfo) {
            $message .= "🌐 IP: <code>{$ipInfo['ip']}</code>\n";
            $message .= "📍 位置: {$ipInfo['location']}\n";
        }

        $message .= "⏰ 时间: " . date('Y-m-d H:i:s') . "\n";
        $message .= "⚠️ <b>需要审核处理</b>";

        $buttons = [
            [
                ['text' => '🏧 提现申请', 'callback_data' => 'withdraw_' . $withdraw['order_no']]
            ]
        ];

        return self::sendMessage($message, 'HTML', $buttons);
    }

    /**
     * 团队奖励通知
     */
    public static function notifyTeamReward($user, $reward)
    {
        $message = "🎁 <b>团队奖励</b>\n\n";
        $message .= "👤 用户: <code>{$user['username']}</code> (UID: {$user['uid']})\n";
        $message .= "💰 奖励金额: <b>{$reward['amount']} {$reward['currency']}</b>\n";
        $message .= "👥 来源: {$reward['source']}\n";
        $message .= "⏰ 时间: " . date('Y-m-d H:i:s') . "\n";

        return self::sendMessage($message);
    }

    /**
     * 系统异常通知
     */
    public static function notifyError($title, $error)
    {
        $message = "⚠️ <b>系统异常</b>\n\n";
        $message .= "📌 标题: {$title}\n";
        $message .= "❌ 错误: <code>{$error}</code>\n";
        $message .= "⏰ 时间: " . date('Y-m-d H:i:s') . "\n";

        return self::sendMessage($message);
    }

    /**
     * 每日统计通知
     */
    public static function notifyDailyStats($stats)
    {
        $message = "📊 <b>今日数据统计</b>\n\n";
        $message .= "👥 新注册: <b>{$stats['new_users']}</b> 人\n";
        $message .= "💰 总充值: <b>{$stats['total_recharge_cny']} CNY</b>\n";
        $message .= "💵 总充值: <b>{$stats['total_recharge_usdt']} USDT</b>\n";
        $message .= "📈 新投资: <b>{$stats['new_invests']}</b> 笔\n";
        $message .= "💼 投资金额: <b>{$stats['invest_amount']} CNY</b>\n";
        $message .= "🏧 提现申请: <b>{$stats['withdraws']}</b> 笔\n";
        $message .= "⏰ 统计时间: " . date('Y-m-d H:i:s') . "\n";

        return self::sendMessage($message);
    }

    /**
     * 获取用户真实IP（处理CloudFlare代理）
     */
    public static function getRealIp()
    {
        // 优先从 CloudFlare 头获取
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        // 其次从 X-Forwarded-For 获取（第一个IP是真实IP）
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        // 再其次从 X-Real-IP 获取
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        // 最后使用 REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? (function_exists('request') ? request()->ip() : '');
    }

    /**
     * 获取IP地址信息
     */
    private static function getIpInfo($ip)
    {
        // 如果传入的IP无效，尝试获取真实IP
        if (!$ip || $ip == '127.0.0.1' || $ip == '::1' || strpos($ip, '172.') === 0 || strpos($ip, '10.') === 0) {
            $ip = self::getRealIp();
        }

        if (!$ip || $ip == '127.0.0.1' || $ip == '::1') {
            return null;
        }

        // 格式化IPv6地址（缩短显示）
        $displayIp = $ip;
        if (strpos($ip, ':') !== false) {
            // IPv6地址，只显示前3段和后3段
            $parts = explode(':', $ip);
            if (count($parts) > 6) {
                $displayIp = implode(':', array_slice($parts, 0, 3)) . '...' . implode(':', array_slice($parts, -3));
            }
        }

        // 尝试获取IP地理位置
        $location = self::queryIpLocation($ip);

        return [
            'ip' => $displayIp,
            'location' => $location
        ];
    }

    /**
     * 查询IP地理位置
     */
    private static function queryIpLocation($ip)
    {
        try {
            // 使用ip-api.com免费API（无需key，每分钟45次）
            $url = "http://ip-api.com/json/{$ip}?lang=zh-CN&fields=status,country,regionName,city,isp";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

            $result = curl_exec($ch);
            curl_close($ch);

            if ($result) {
                $data = json_decode($result, true);
                if ($data && $data['status'] == 'success') {
                    // 记录完整信息到日志（包含ISP）
                    Log::info('IP地理位置查询', [
                        'ip' => $ip,
                        'country' => $data['country'] ?? '',
                        'region' => $data['regionName'] ?? '',
                        'city' => $data['city'] ?? '',
                        'isp' => $data['isp'] ?? ''
                    ]);

                    // 通知中只显示：国家 省份 城市（去掉ISP）
                    $location = [];
                    if (!empty($data['country'])) $location[] = $data['country'];
                    if (!empty($data['regionName'])) $location[] = $data['regionName'];
                    if (!empty($data['city'])) $location[] = $data['city'];

                    return implode(' ', $location) ?: '未知';
                }
            }
        } catch (\Exception $e) {
            Log::warning('IP查询失败', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return '未知';
    }

    /**
     * HTTP POST请求
     */
    private static function httpPost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 && $result) {
            return json_decode($result, true);
        }

        return null;
    }
}
