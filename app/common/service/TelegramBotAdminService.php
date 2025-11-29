<?php
namespace app\common\service;

use think\facade\Db;
use think\facade\Log;

/**
 * Telegram Bot 管理员命令服务
 */
class TelegramBotAdminService
{
    private static $botToken = '';
    private static $adminIds = [6159132946, 7289705725]; // 管理员ID

    /**
     * 初始化配置
     */
    private static function init()
    {
        self::$botToken = env('TELEGRAM_BOT_TOKEN', '');

        // 从.env文件读取（备用方案）
        if (!self::$botToken) {
            $envFile = app()->getRootPath() . '.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, 'TELEGRAM_BOT_TOKEN=') === 0) {
                        self::$botToken = trim(substr($line, strlen('TELEGRAM_BOT_TOKEN=')));
                        break;
                    }
                }
            }
        }
    }

    /**
     * 处理Webhook消息
     */
    public static function handleWebhook($update)
    {
        self::init();

        // 处理普通消息
        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $userId = $message['from']['id'];
            $text = $message['text'] ?? '';

            // 检查是否是管理员
            if (!in_array($userId, self::$adminIds)) {
                // 非管理员不响应
                return false;
            }

            // 处理命令
            if ($text == '/start' || $text == '/menu' || $text == '菜单') {
                return self::sendMainMenu($chatId);
            }

            // 处理统计报告命令
            if ($text == '/stats' || $text == '/report' || $text == '统计' || $text == '报告') {
                return self::sendFullReport($chatId);
            }

            // 处理到期产品查询（纯数字）
            if (is_numeric(trim($text)) && intval($text) > 0) {
                $days = intval($text);
                if ($days >= 1 && $days <= 365) {
                    return self::queryExpiringProductsByDays($days, $chatId);
                }
            }

            return true;
        }

        // 处理按钮回调
        if (isset($update['callback_query'])) {
            $callbackQuery = $update['callback_query'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $userId = $callbackQuery['from']['id'];
            $data = $callbackQuery['data'];
            $messageId = $callbackQuery['message']['message_id'];

            // 检查是否是管理员
            if (!in_array($userId, self::$adminIds)) {
                return false;
            }

            // 应答回调查询
            self::answerCallbackQuery($callbackQuery['id']);

            // 处理不同的按钮回调（仅保留新功能）
            switch ($data) {
                case 'user_data':
                    return self::sendUserData($chatId, $messageId);
                case 'recharge_data':
                    return self::sendRechargeData($chatId, $messageId);
                case 'project_data':
                    return self::sendProjectData($chatId, $messageId);
                case 'full_report':
                    return self::sendFullReport($chatId, $messageId);
                case 'query_expiring':
                    return self::sendExpiringQueryForm($chatId, $messageId);
                case 'back_menu':
                    return self::sendMainMenu($chatId, $messageId);
                default:
                    break;
            }
        }

        return false;
    }

    /**
     * 发送主菜单 - 纯净版（仅保留4个新按钮）
     */
    private static function sendMainMenu($chatId, $messageId = null)
    {
        $message = "🤖 <b>Providence 管理面板</b>\n\n";
        $message .= "📊 数据统计与查询系统\n\n";
        $message .= "选择你要查看的内容：";

        $buttons = [
            [
                ['text' => '👥 用户数据', 'callback_data' => 'user_data'],
                ['text' => '💳 充值数据', 'callback_data' => 'recharge_data']
            ],
            [
                ['text' => '📦 项目数据', 'callback_data' => 'project_data'],
                ['text' => '📊 统计报告', 'callback_data' => 'full_report']
            ],
            [
                ['text' => '⏰ 查询到期产品', 'callback_data' => 'query_expiring']
            ]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 发送今日统计数据
     */
    private static function sendTodayStats($chatId, $messageId)
    {
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        // 今日注册
        $newUsers = Db::name('users')
            ->where('created_at', 'between', [$todayStart, $todayEnd])
            ->count();

        // 今日实名
        $newKyc = Db::name('users')
            ->where('is_kyc', 1)
            ->where('updated_at', 'between', [$todayStart, $todayEnd])
            ->count();

        // 今日充值
        $todayRecharge = Db::name('recharge_records')
            ->where('status', 1)
            ->where('created_at', 'between', [$todayStart, $todayEnd])
            ->select();

        $rechargeCny = 0;
        $rechargeUsdt = 0;
        foreach ($todayRecharge as $r) {
            if ($r['currency'] == 'CNY') {
                $rechargeCny += $r['amount'];
            } else {
                $rechargeUsdt += $r['amount'];
            }
        }

        // 今日提现
        $todayWithdraw = Db::name('withdraw_records')
            ->where('created_at', 'between', [$todayStart, $todayEnd])
            ->select();

        $withdrawCny = 0;
        $withdrawUsdt = 0;
        $withdrawPending = 0;
        foreach ($todayWithdraw as $w) {
            if ($w['status'] == 0) {
                $withdrawPending++;
            }
            if ($w['currency'] == 'CNY') {
                $withdrawCny += $w['amount'];
            } else {
                $withdrawUsdt += $w['amount'];
            }
        }

        $message = "📊 <b>今日数据统计</b>\n\n";
        $message .= "📅 日期: " . $today . "\n\n";
        $message .= "👥 新注册: <b>{$newUsers}</b> 人\n";
        $message .= "🪪 新实名: <b>{$newKyc}</b> 人\n\n";
        $message .= "💰 充值总额:\n";
        $message .= "  ├─ CNY: <b>" . number_format($rechargeCny, 2) . "</b>\n";
        $message .= "  └─ USDT: <b>" . number_format($rechargeUsdt, 2) . "</b>\n\n";
        $message .= "🏧 提现总额:\n";
        $message .= "  ├─ CNY: <b>" . number_format($withdrawCny, 2) . "</b>\n";
        $message .= "  ├─ USDT: <b>" . number_format($withdrawUsdt, 2) . "</b>\n";
        $message .= "  └─ ⚠️ 待审核: <b>{$withdrawPending}</b> 笔\n";

        $buttons = [
            [['text' => '🔙 返回主菜单', 'callback_data' => 'back_menu']]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 发送总体统计数据
     */
    private static function sendTotalStats($chatId, $messageId)
    {
        // 总注册
        $totalUsers = Db::name('users')->count();

        // 总实名
        $totalKyc = Db::name('users')->where('is_kyc', 1)->count();

        // 总充值
        $totalRecharge = Db::name('recharge_records')
            ->where('status', 1)
            ->select();

        $rechargeCny = 0;
        $rechargeUsdt = 0;
        foreach ($totalRecharge as $r) {
            if ($r['currency'] == 'CNY') {
                $rechargeCny += $r['amount'];
            } else {
                $rechargeUsdt += $r['amount'];
            }
        }

        // 总提现
        $totalWithdraw = Db::name('withdraw_records')->select();

        $withdrawCny = 0;
        $withdrawUsdt = 0;
        foreach ($totalWithdraw as $w) {
            if ($w['currency'] == 'CNY') {
                $withdrawCny += $w['amount'];
            } else {
                $withdrawUsdt += $w['amount'];
            }
        }

        $message = "📈 <b>总体数据统计</b>\n\n";
        $message .= "👥 总用户: <b>{$totalUsers}</b> 人\n";
        $message .= "🪪 总实名: <b>{$totalKyc}</b> 人\n\n";
        $message .= "💰 累计充值:\n";
        $message .= "  ├─ CNY: <b>" . number_format($rechargeCny, 2) . "</b>\n";
        $message .= "  └─ USDT: <b>" . number_format($rechargeUsdt, 2) . "</b>\n\n";
        $message .= "🏧 累计提现:\n";
        $message .= "  ├─ CNY: <b>" . number_format($withdrawCny, 2) . "</b>\n";
        $message .= "  └─ USDT: <b>" . number_format($withdrawUsdt, 2) . "</b>\n";

        $buttons = [
            [['text' => '🔙 返回主菜单', 'callback_data' => 'back_menu']]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 发送用户数据统计
     */
    private static function sendUserData($chatId, $messageId)
    {
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        // 今日新增用户
        $newUsers = Db::name('users')
            ->where('id', '!=', 1)
            ->where('created_at', 'between', [$todayStart, $todayEnd])
            ->select();

        $totalNewUsers = count($newUsers);

        // 按地理位置统计
        $locationStats = [];
        $cityProvinceMap = [
            '安徽' => 'AH',
            '河南' => 'HA',
            '北京' => 'BJ',
            '上海' => 'SH',
            '浙江' => 'ZJ',
            '江苏' => 'JS',
            '广东' => 'GD',
            '四川' => 'SC',
            '山东' => 'SD',
        ];

        // 从IP或用户信息获取位置（假设有region字段）
        foreach ($newUsers as $user) {
            $region = $user['region'] ?? '未知';
            $locationStats[$region] = ($locationStats[$region] ?? 0) + 1;
        }

        $message = "👥 <b>今日用户数据统计</b>\n\n";
        $message .= "📅 统计日期: " . $today . "\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 <b>新增用户总数</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "今日新增用户: <b>{$totalNewUsers}</b> 人\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🌍 <b>按地区分布</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

        if (count($locationStats) > 0) {
            arsort($locationStats);
            $rank = 1;
            foreach ($locationStats as $location => $count) {
                $message .= "{$rank}. {$location}: <b>{$count}</b> 人\n";
                $rank++;
            }
        } else {
            $message .= "暂无数据\n";
        }

        // 高亮显示安徽和河南
        $message .= "\n━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⭐ <b>关键地区</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "安徽地区: <b>" . ($locationStats['安徽'] ?? 0) . "</b> 人\n";
        $message .= "河南地区: <b>" . ($locationStats['河南'] ?? 0) . "</b> 人\n";

        $buttons = [
            [['text' => '🔙 返回菜单', 'callback_data' => 'back_menu']]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 发送充值数据统计
     */
    private static function sendRechargeData($chatId, $messageId)
    {
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        // 充值数据
        $rechargeRecords = Db::name('recharge_records')
            ->alias('rr')
            ->join('users u', 'rr.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('rr.status', 1)
            ->where('rr.created_at', 'between', [$todayStart, $todayEnd])
            ->select();

        $cnyRecharge = 0;
        $usdtRecharge = 0;
        foreach ($rechargeRecords as $r) {
            if ($r['currency'] == 'CNY') {
                $cnyRecharge += $r['amount'];
            } else {
                $usdtRecharge += $r['amount'];
            }
        }

        // 提现数据
        $withdrawRecords = Db::name('withdraw_records')
            ->alias('wo')
            ->join('users u', 'wo.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('wo.created_at', 'between', [$todayStart, $todayEnd])
            ->select();

        $cnyWithdraw = 0;
        $usdtWithdraw = 0;
        foreach ($withdrawRecords as $w) {
            if ($w['currency'] == 'CNY') {
                $cnyWithdraw += $w['amount'];
            } else {
                $usdtWithdraw += $w['amount'];
            }
        }

        $message = "💳 <b>今日充提数据统计</b>\n\n";
        $message .= "📅 统计日期: " . $today . "\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💰 <b>充值统计</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "RMB充值: ¥<b>" . number_format($cnyRecharge, 2) . "</b>\n";
        $message .= "USDT充值: <b>" . number_format($usdtRecharge, 2) . "</b> USDT\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🏧 <b>提现统计</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "RMB提款: ¥<b>" . number_format($cnyWithdraw, 2) . "</b>\n";
        $message .= "USDT提款: <b>" . number_format($usdtWithdraw, 2) . "</b> USDT\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 <b>统计总览</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "今日总充值: ¥<b>" . number_format($cnyRecharge, 2) . "</b> + <b>" . number_format($usdtRecharge, 2) . "</b> USDT\n";
        $message .= "今日总提款: ¥<b>" . number_format($cnyWithdraw, 2) . "</b> + <b>" . number_format($usdtWithdraw, 2) . "</b> USDT\n";
        $message .= "资金流向: <b>" . ($cnyRecharge - $cnyWithdraw >= 0 ? '+' : '') . number_format($cnyRecharge - $cnyWithdraw, 2) . "</b> CNY\n";

        $buttons = [
            [['text' => '🔙 返回菜单', 'callback_data' => 'back_menu']]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 发送项目数据统计
     */
    private static function sendProjectData($chatId, $messageId)
    {
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        // 今日购买项目统计
        $investOrders = Db::name('invest_orders')
            ->alias('io')
            ->join('users u', 'io.user_id = u.user_id')
            ->join('projects p', 'io.project_id = p.id')
            ->where('u.id', '!=', 1)
            ->where('io.created_at', 'between', [$todayStart, $todayEnd])
            ->field('io.*, p.name as project_name')
            ->select();

        $totalInvestors = count($investOrders);
        $totalInvestAmount = 0;
        $projectStats = [];

        foreach ($investOrders as $order) {
            $totalInvestAmount += $order['amount'];
            $projectName = $order['project_name'];
            if (!isset($projectStats[$projectName])) {
                $projectStats[$projectName] = ['count' => 0, 'amount' => 0];
            }
            $projectStats[$projectName]['count']++;
            $projectStats[$projectName]['amount'] += $order['amount'];
        }

        $message = "📦 <b>今日项目数据统计</b>\n\n";
        $message .= "📅 统计日期: " . $today . "\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 <b>购买统计</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "今日建仓人数: <b>{$totalInvestors}</b> 人\n";
        $message .= "今日建仓金额: ¥<b>" . number_format($totalInvestAmount, 2) . "</b>\n\n";

        if (count($projectStats) > 0) {
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "🎯 <b>产品排名</b>\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

            // 按金额排序
            uasort($projectStats, function ($a, $b) {
                return $b['amount'] - $a['amount'];
            });

            $rank = 1;
            foreach ($projectStats as $projectName => $stats) {
                $message .= "{$rank}. <b>{$projectName}</b>\n";
                $message .= "   └─ 人数: <b>" . $stats['count'] . "</b> 人 | 金额: ¥<b>" . number_format($stats['amount'], 2) . "</b>\n\n";
                $rank++;
            }
        } else {
            $message .= "暂无建仓数据\n";
        }

        $buttons = [
            [['text' => '🔙 返回菜单', 'callback_data' => 'back_menu']]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 发送到期产品查询表单
     */
    private static function sendExpiringQueryForm($chatId, $messageId)
    {
        $message = "⏰ <b>查询到期产品</b>\n\n";
        $message .= "请发送要查询的天数(数字)，例如：\n\n";
        $message .= "<code>7</code> - 查询7天后到期的产品\n";
        $message .= "<code>8</code> - 查询8天后到期的产品\n";
        $message .= "<code>15</code> - 查询15天后到期的产品\n\n";
        $message .= "系统将显示：\n";
        $message .= "• 该天到期的产品名称\n";
        $message .= "• 需要返款的人数\n";
        $message .= "• 共计返款金额\n";

        $buttons = [
            [['text' => '🔙 返回菜单', 'callback_data' => 'back_menu']]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 查询指定天数后的到期产品
     */
    private static function queryExpiringProductsByDays($days, $chatId)
    {
        $targetDate = date('Y-m-d', strtotime("+{$days} days"));
        $targetStart = $targetDate . ' 00:00:00';
        $targetEnd = $targetDate . ' 23:59:59';

        $expiringOrders = Db::name('invest_orders')
            ->alias('io')
            ->join('users u', 'io.user_id = u.user_id')
            ->join('projects p', 'io.project_id = p.id')
            ->where('u.id', '!=', 1)
            ->where('io.status', 1)
            ->where('io.end_time', 'between', [$targetStart, $targetEnd])
            ->field('io.*, u.username, u.uid, p.name as project_name')
            ->select();

        $message = "⏰ <b>{$days}天后到期产品统计</b>\n\n";
        $message .= "📅 目标日期: " . $targetDate . "\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📊 <b>返款统计</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

        if (count($expiringOrders) == 0) {
            $message .= "该日期暂无到期产品\n";
        } else {
            $totalAmount = 0;
            $projectStats = [];

            foreach ($expiringOrders as $order) {
                $totalAmount += $order['amount'];
                $projectName = $order['project_name'];
                if (!isset($projectStats[$projectName])) {
                    $projectStats[$projectName] = ['count' => 0, 'amount' => 0];
                }
                $projectStats[$projectName]['count']++;
                $projectStats[$projectName]['amount'] += $order['amount'];
            }

            $message .= "到期订单数: <b>" . count($expiringOrders) . "</b> 笔\n";
            $message .= "需返款人数: <b>" . count(array_unique(array_map(function($o) { return $o['user_id']; }, $expiringOrders))) . "</b> 人\n";
            $message .= "共计返款: ¥<b>" . number_format($totalAmount, 2) . "</b>\n\n";

            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📦 <b>产品分布</b>\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

            foreach ($projectStats as $projectName => $stats) {
                $message .= "🎯 <b>{$projectName}</b>\n";
                $message .= "   └─ 人数: <b>" . $stats['count'] . "</b> 人 | 金额: ¥<b>" . number_format($stats['amount'], 2) . "</b>\n\n";
            }
        }

        $buttons = [
            [['text' => '🔙 返回菜单', 'callback_data' => 'back_menu']]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, null);
    }

    /**
     * 发送完整统计报告
     */
    private static function sendFullReport($chatId, $messageId = null)
    {
        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';

        // ======== 总体统计 ========
        // 排除内部账号(ID为1的创始人账号)
        $totalUsers = Db::name('users')->where('id', '!=', 1)->count();
        $totalKyc = Db::name('users')->where('id', '!=', 1)->where('is_kyc', 1)->count();

        // 总充值人数
        $rechargedUsers = Db::name('recharge_records')
            ->alias('rr')
            ->join('users u', 'rr.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('rr.status', 1)
            ->distinct(true)
            ->field('rr.user_id')
            ->count();

        // 当前在仓(有进行中的投资)
        $activeInvestors = Db::name('invest_orders')
            ->alias('io')
            ->join('users u', 'io.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('io.status', 1)
            ->distinct(true)
            ->field('io.user_id')
            ->count();

        // 总充值金额
        $totalRecharge = Db::name('recharge_records')
            ->alias('rr')
            ->join('users u', 'rr.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('rr.status', 1)
            ->select();

        $totalRechargeCny = 0;
        $totalRechargeUsdt = 0;
        foreach ($totalRecharge as $r) {
            if ($r['currency'] == 'CNY') {
                $totalRechargeCny += $r['amount'];
            } else {
                $totalRechargeUsdt += $r['amount'];
            }
        }

        // 总提现金额
        $totalWithdraw = Db::name('withdraw_records')
            ->alias('wo')
            ->join('users u', 'wo.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->select();

        $totalWithdrawCny = 0;
        $totalWithdrawUsdt = 0;
        foreach ($totalWithdraw as $w) {
            if ($w['currency'] == 'CNY') {
                $totalWithdrawCny += $w['amount'];
            } else {
                $totalWithdrawUsdt += $w['amount'];
            }
        }

        // 总返利
        $totalReferral = Db::name('referral_rewards')
            ->alias('rw')
            ->join('users u', 'rw.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->sum('amount');
        $totalReferral = $totalReferral ?? 0;

        // 日利宝余额统计（所有用户的日利宝余额总和）
        $ribaoStats = Db::name('users')
            ->where('id', '!=', 1)
            ->field('SUM(ribao_cny) as total_ribao_cny, SUM(ribao_usdt) as total_ribao_usdt')
            ->find();
        $totalRibaoCny = $ribaoStats['total_ribao_cny'] ?? 0;
        $totalRibaoUsdt = $ribaoStats['total_ribao_usdt'] ?? 0;

        // ======== 今日统计 ========
        // 今日注册
        $todayNewUsers = Db::name('users')
            ->where('id', '!=', 1)
            ->where('created_at', 'between', [$todayStart, $todayEnd])
            ->count();

        // 今日实名
        $todayNewKyc = Db::name('users')
            ->where('id', '!=', 1)
            ->where('is_kyc', 1)
            ->where('updated_at', 'between', [$todayStart, $todayEnd])
            ->count();

        // 今日首次充值人数
        $todayFirstRecharge = Db::name('recharge_records')
            ->alias('rr')
            ->join('users u', 'rr.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('rr.status', 1)
            ->where('rr.created_at', 'between', [$todayStart, $todayEnd])
            ->distinct(true)
            ->field('rr.user_id')
            ->count();

        // 今日充值
        $todayRecharge = Db::name('recharge_records')
            ->alias('rr')
            ->join('users u', 'rr.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('rr.status', 1)
            ->where('rr.created_at', 'between', [$todayStart, $todayEnd])
            ->select();

        $todayRechargeCny = 0;
        $todayRechargeUsdt = 0;
        $todayRechargeCount = 0;
        foreach ($todayRecharge as $r) {
            $todayRechargeCount++;
            if ($r['currency'] == 'CNY') {
                $todayRechargeCny += $r['amount'];
            } else {
                $todayRechargeUsdt += $r['amount'];
            }
        }

        // 今日提现
        $todayWithdraw = Db::name('withdraw_records')
            ->alias('wo')
            ->join('users u', 'wo.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('wo.created_at', 'between', [$todayStart, $todayEnd])
            ->select();

        $todayWithdrawCny = 0;
        $todayWithdrawUsdt = 0;
        $todayWithdrawCount = 0;
        foreach ($todayWithdraw as $w) {
            $todayWithdrawCount++;
            if ($w['currency'] == 'CNY') {
                $todayWithdrawCny += $w['amount'];
            } else {
                $todayWithdrawUsdt += $w['amount'];
            }
        }

        // 今日建仓(新订单)
        $todayNewOrders = Db::name('invest_orders')
            ->alias('io')
            ->join('users u', 'io.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('io.created_at', 'between', [$todayStart, $todayEnd])
            ->select();

        $todayNewOrderAmount = 0;
        $todayNewOrderCount = 0;
        foreach ($todayNewOrders as $order) {
            $todayNewOrderCount++;
            $todayNewOrderAmount += $order['amount'];
        }

        // 今日平仓(结束的订单)
        $todayEndOrders = Db::name('invest_orders')
            ->alias('io')
            ->join('users u', 'io.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('io.status', 2)  // 已完成状态
            ->where('io.end_time', 'between', [$todayStart, $todayEnd])
            ->select();

        $todayEndOrderAmount = 0;
        $todayEndOrderCount = 0;
        foreach ($todayEndOrders as $order) {
            $todayEndOrderCount++;
            $todayEndOrderAmount += $order['amount'];
        }

        // 明日到期
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $tomorrowStart = $tomorrow . ' 00:00:00';
        $tomorrowEnd = $tomorrow . ' 23:59:59';

        $tomorrowExpiring = Db::name('invest_orders')
            ->alias('io')
            ->join('users u', 'io.user_id = u.user_id')
            ->where('u.id', '!=', 1)
            ->where('io.status', 1)
            ->where('io.end_time', 'between', [$tomorrowStart, $tomorrowEnd])
            ->select();

        $tomorrowExpiringAmount = 0;
        $tomorrowExpiringUsers = count($tomorrowExpiring);
        foreach ($tomorrowExpiring as $order) {
            $tomorrowExpiringAmount += $order['amount'];
        }

        // 构建美化的报告消息
        $message = "📊 <b>Providence 统计报告</b>\n\n";
        $message .= "📅 统计日期: " . $today . "\n";
        $message .= "ℹ️ 已排除内部账号数据\n\n";

        // 总体统计
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📈 <b>总体统计</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "注册总人数: <b>{$totalUsers}</b> 人\n";
        $message .= "入过金总人数: <b>{$rechargedUsers}</b> 人\n";
        $message .= "当前在仓总人数: <b>{$activeInvestors}</b> 人\n\n";
        $message .= "共计充值: ¥<b>" . number_format($totalRechargeCny, 2) . "</b>\n";
        $message .= "共计提款: ¥<b>" . number_format($totalWithdrawCny, 2) . "</b>\n";
        $message .= "共计返利: ¥<b>" . number_format($totalReferral, 2) . "</b>\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💰 <b>平台余额统计</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "📊 日利宝余额:\n";
        $message .= "  ├─ 人民币: ¥<b>" . number_format($totalRibaoCny, 2) . "</b>\n";
        $message .= "  └─ USDT: <b>" . number_format($totalRibaoUsdt, 2) . "</b> USDT\n\n";

        // 今日统计
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📅 <b>今日统计</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

        $message .= "<b>👥 用户统计</b>\n";
        $message .= "  今日注册人数: <b>{$todayNewUsers}</b> 人\n";
        $message .= "  今日实名人数: <b>{$todayNewKyc}</b> 人\n";
        $message .= "  今日首次充值人数: <b>{$todayFirstRecharge}</b> 人\n\n";

        $message .= "<b>💵 充值统计</b>\n";
        $message .= "  今日总充值金额: ¥<b>" . number_format($todayRechargeCny, 2) . "</b>\n";
        $message .= "  今日总充值人数: <b>{$todayRechargeCount}</b> 人\n\n";

        $message .= "<b>💸 提现统计</b>\n";
        $message .= "  今日总提现金额: ¥<b>" . number_format($todayWithdrawCny, 2) . "</b>\n";
        $message .= "  今日总提现人数: <b>{$todayWithdrawCount}</b> 人\n\n";

        $message .= "<b>📦 建仓统计（购买项目）</b>\n";
        $message .= "  今日总建仓金额: ¥<b>" . number_format($todayNewOrderAmount, 2) . "</b>\n";
        $message .= "  今日总建仓人数: <b>{$todayNewOrderCount}</b> 人\n\n";

        $message .= "<b>💰 平仓统计（到期返款）</b>\n";
        $message .= "  今日总平仓金额: ¥<b>" . number_format($todayEndOrderAmount, 2) . "</b>\n";
        $message .= "  今日总平仓人数: <b>{$todayEndOrderCount}</b> 人\n\n";

        $message .= "<b>📅 明日到期产品（需返款）</b>\n";
        $message .= "  明日到期金额: ¥<b>" . number_format($tomorrowExpiringAmount, 2) . "</b>\n";
        $message .= "  明日到期用户数: <b>{$tomorrowExpiringUsers}</b> 人\n";
        $message .= "  明日到期项目数: <b>" . (count(array_unique(array_map(function($o) { return $o['project_id']; }, $tomorrowExpiring)))) . "</b> 个\n";

        // 使用按钮样式显示余额（人民币和USDT）
        $buttons = [
            [
                ['text' => '💵 人民币余额: ¥' . number_format($totalRibaoCny, 2), 'callback_data' => 'balance_cny'],
                ['text' => '💎 USDT余额: ' . number_format($totalRibaoUsdt, 2), 'callback_data' => 'balance_usdt']
            ],
            [
                ['text' => '📊 日利宝CNY: ¥' . number_format($totalRibaoCny, 2), 'callback_data' => 'ribao_cny'],
                ['text' => '📊 日利宝USDT: ' . number_format($totalRibaoUsdt, 2), 'callback_data' => 'ribao_usdt']
            ],
            [
                ['text' => '🔙 返回主菜单', 'callback_data' => 'back_menu']
            ]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 发送明日到期项目
     */
    private static function sendExpiringProjects($chatId, $messageId)
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $tomorrowStart = $tomorrow . ' 00:00:00';
        $tomorrowEnd = $tomorrow . ' 23:59:59';

        $expiringOrders = Db::name('invest_orders')
            ->alias('io')
            ->join('users u', 'io.user_id = u.user_id')
            ->join('projects p', 'io.project_id = p.id')
            ->where('io.status', 1)
            ->where('io.end_time', 'between', [$tomorrowStart, $tomorrowEnd])
            ->field('io.*, u.username, u.uid, p.name as project_name')
            ->select();

        $count = count($expiringOrders);
        $totalAmount = 0;
        foreach ($expiringOrders as $order) {
            $totalAmount += $order['amount'];
        }

        $message = "⏰ <b>明日到期项目</b>\n\n";
        $message .= "📅 到期日期: {$tomorrow}\n";
        $message .= "📊 到期订单: <b>{$count}</b> 笔\n";
        $message .= "💰 到期金额: <b>" . number_format($totalAmount, 2) . "</b> CNY\n\n";

        if ($count > 0) {
            $message .= "详细列表:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";

            $limit = min($count, 10); // 最多显示10条
            for ($i = 0; $i < $limit; $i++) {
                $order = $expiringOrders[$i];
                $message .= "\n" . ($i + 1) . ". <b>{$order['project_name']}</b>\n";
                $message .= "   用户: {$order['username']} ({$order['uid']})\n";
                $message .= "   金额: {$order['amount']} {$order['currency']}\n";
            }

            if ($count > 10) {
                $message .= "\n... 还有 " . ($count - 10) . " 笔订单\n";
            }
        }

        $buttons = [
            [['text' => '🔙 返回主菜单', 'callback_data' => 'back_menu']]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 发送项目列表
     */
    private static function sendProjectList($chatId, $messageId)
    {
        $projects = Db::name('projects')
            ->where('status', 1)
            ->order('id', 'desc')
            ->select();

        $message = "📋 <b>当前项目列表</b>\n\n";

        if (count($projects) == 0) {
            $message .= "暂无项目\n";
        } else {
            foreach ($projects as $project) {
                $message .= "🎯 <b>{$project['name']}</b>\n";
                $message .= "   收益率: {$project['return_rate']}%\n";
                $message .= "   周期: {$project['duration']}天\n";
                $message .= "   最低: {$project['min_amount']} | 最高: {$project['max_amount']}\n";
                $message .= "\n";
            }
        }

        // 生成按钮
        $buttons = [];
        foreach ($projects as $project) {
            $buttons[] = [
                ['text' => "❌ 下架 [{$project['name']}]", 'callback_data' => 'project_offline_' . $project['id']]
            ];
        }
        $buttons[] = [['text' => '🔙 返回主菜单', 'callback_data' => 'back_menu']];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 下架项目
     */
    private static function offlineProject($chatId, $messageId, $projectId)
    {
        $project = Db::name('projects')->where('id', $projectId)->find();

        if (!$project) {
            $message = "❌ 项目不存在";
        } else {
            Db::name('projects')->where('id', $projectId)->update(['status' => 0]);
            $message = "✅ 项目已下架\n\n";
            $message .= "项目名称: <b>{$project['name']}</b>\n";
            $message .= "收益率: {$project['return_rate']}%\n";
        }

        $buttons = [
            [
                ['text' => '📋 项目列表', 'callback_data' => 'project_list'],
                ['text' => '🔙 主菜单', 'callback_data' => 'back_menu']
            ]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 发送项目添加表单说明
     */
    private static function sendProjectAddForm($chatId, $messageId)
    {
        $message = "➕ <b>发布新项目</b>\n\n";
        $message .= "⚠️ 请使用后台管理面板发布项目\n\n";
        $message .= "或发送以下格式的消息：\n";
        $message .= "<code>/addproject\n";
        $message .= "项目名称\n";
        $message .= "收益率(%) 周期(天)\n";
        $message .= "最低金额 最高金额</code>\n\n";
        $message .= "示例:\n";
        $message .= "<code>/addproject\n";
        $message .= "稳健理财A\n";
        $message .= "2.6 30\n";
        $message .= "1000 50000</code>";

        $buttons = [
            [['text' => '🔙 返回主菜单', 'callback_data' => 'back_menu']]
        ];

        return self::sendOrEditMessage($chatId, $message, $buttons, $messageId);
    }

    /**
     * 发送或编辑消息
     */
    private static function sendOrEditMessage($chatId, $message, $buttons, $messageId = null)
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'inline_keyboard' => $buttons
            ]
        ];

        if ($messageId) {
            // 编辑现有消息
            $data['message_id'] = $messageId;
            $url = "https://api.telegram.org/bot" . self::$botToken . "/editMessageText";
        } else {
            // 发送新消息
            $url = "https://api.telegram.org/bot" . self::$botToken . "/sendMessage";
        }

        return self::httpPost($url, $data);
    }

    /**
     * 应答回调查询
     */
    private static function answerCallbackQuery($callbackQueryId)
    {
        $url = "https://api.telegram.org/bot" . self::$botToken . "/answerCallbackQuery";
        $data = ['callback_query_id' => $callbackQueryId];
        return self::httpPost($url, $data);
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
