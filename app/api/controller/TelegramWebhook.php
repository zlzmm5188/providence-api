<?php
namespace app\api\controller;

use app\common\service\TelegramBotAdminService;
use think\facade\Log;

/**
 * Telegram Bot Webhook 接收器
 */
class TelegramWebhook
{
    /**
     * 接收Telegram Webhook
     * POST /api/telegram/webhook
     */
    public function index()
    {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);

        // 记录日志
        Log::info('Telegram Webhook接收', ['update' => $update]);

        if (!$update) {
            return json(['ok' => false, 'error' => 'Invalid input']);
        }

        try {
            // 处理消息
            TelegramBotAdminService::handleWebhook($update);

            return json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Telegram Webhook处理失败', ['error' => $e->getMessage()]);
            return json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
}
