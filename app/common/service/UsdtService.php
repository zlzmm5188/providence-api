<?php
namespace app\common\service;

use think\facade\Db;
use think\facade\Log;
use think\facade\Cache;
use think\facade\Env;
use app\common\service\TelegramBotService;

/**
 * USDT充值服务（多地址池版本）
 *
 * 核心逻辑：
 * 1. 配置多个USDT收款地址（地址池）
 * 2. 每个订单分配一个空闲地址
 * 3. 订单完成或过期后，地址释放回地址池
 * 4. 监控时，只需检查订单对应的地址是否有收款
 *
 * 优势：
 * - 精确匹配：每个订单有独立地址，不会混淆
 * - 多人同时充值不会冲突
 * - 不需要靠金额和时间匹配
 */
class UsdtService
{
    // 订单有效期（秒）：30分钟
    const ORDER_EXPIRE_TIME = 30 * 60;

    /**
     * 获取地址池（所有配置的USDT地址）
     */
    public static function getAddressPool()
    {
        $addresses = [];
        $rootPath = defined('APP_PATH') ? dirname(APP_PATH) . '/' : (function_exists('app') ? app()->getRootPath() : __DIR__ . '/../../../');
        $envFile = $rootPath . '.env';

        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                // 匹配 USDT_ADDRESS_1, USDT_ADDRESS_2, ... 或 usdt_address
                if (preg_match('/^USDT_ADDRESS_(\d+)\s*=\s*(.+)$/i', $line, $matches)) {
                    $addr = trim($matches[2], ' "\'');
                    if ($addr && strlen($addr) > 20) {  // 有效地址长度检查
                        $addresses[(int)$matches[1]] = $addr;
                    }
                } elseif (preg_match('/^(USDT_ADDRESS|usdt_address)\s*=\s*(.+)$/i', $line, $matches)) {
                    $addr = trim($matches[2], ' "\'');
                    if ($addr && strlen($addr) > 20 && !in_array($addr, $addresses)) {
                        $addresses[0] = $addr;  // 主地址作为第0个
                    }
                }
            }
        }

        // 按索引排序
        ksort($addresses);
        return array_values($addresses);
    }

    /**
     * 分配一个空闲地址给订单
     *
     * @return string|null 分配的地址，如果没有空闲地址返回null
     */
    public static function allocateAddress()
    {
        $addresses = self::getAddressPool();
        if (empty($addresses)) {
            Log::warning('USDT地址池为空');
            return null;
        }

        // 获取当前正在使用的地址（有未完成订单的地址）
        $usedAddresses = Db::name('recharge_records')
            ->where('currency', 'USDT')
            ->where('status', 0)  // 待支付
            ->where('usdt_address', '<>', '')
            ->whereNotNull('usdt_address')
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - self::ORDER_EXPIRE_TIME))
            ->column('usdt_address');

        // 找一个空闲地址
        foreach ($addresses as $addr) {
            if (!in_array($addr, $usedAddresses)) {
                return $addr;
            }
        }

        // 没有空闲地址，返回第一个地址（允许共用，但会按金额匹配）
        Log::warning('所有USDT地址都在使用中，返回第一个地址');
        return $addresses[0];
    }

    /**
     * 获取USDT充值配置（兼容旧版本）
     */
    public static function getUsdtConfig()
    {
        $addresses = self::getAddressPool();
        $address = !empty($addresses) ? $addresses[0] : 'TYourUsdtAddressHere';

        return [
            'address' => $address,
            'addresses' => $addresses,  // 所有地址
            'network' => 'TRC20',
            'min_amount' => 10,
            'max_amount' => 50000,
            'rate' => 7.2,
            'tips' => [
                '请使用TRC20网络转账USDT',
                '最小充值金额：10 USDT',
                '到账时间：约3-5分钟',
                '转账完成后请耐心等待，系统会自动检测'
            ]
        ];
    }

    /**
     * 创建USDT充值订单（分配独立地址）
     */
    public static function createUsdtOrder($userId, $amount)
    {
        $config = self::getUsdtConfig();

        // 检查是否有未完成的USDT订单（30分钟内）
        $pendingOrder = Db::name('recharge_records')
            ->where('user_id', $userId)
            ->where('currency', 'USDT')
            ->where('status', 0)
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - self::ORDER_EXPIRE_TIME))
            ->find();

        if ($pendingOrder) {
            return [
                'code' => -1,
                'msg' => '您有未完成的充值订单，请先完成或取消',
                'data' => [
                    'pending_order' => [
                        'order_no' => $pendingOrder['order_no'],
                        'amount' => $pendingOrder['amount'],
                        'address' => $pendingOrder['usdt_address'],
                        'created_at' => $pendingOrder['created_at']
                    ]
                ]
            ];
        }

        // 分配一个空闲地址
        $allocatedAddress = self::allocateAddress();
        if (!$allocatedAddress) {
            return [
                'code' => -1,
                'msg' => '系统繁忙，请稍后重试'
            ];
        }

        // 验证金额
        if ($amount < $config['min_amount'] || $amount > $config['max_amount']) {
            return [
                'code' => -1,
                'msg' => "充值金额限制：{$config['min_amount']}-{$config['max_amount']} USDT"
            ];
        }

        // 生成订单号
        $orderNo = 'RUSDT' . date('YmdHis') . rand(1000, 9999);

        // 获取用户真实IP（处理CloudFlare代理）
        $userIp = TelegramBotService::getRealIp();

        // 创建充值记录（保存分配的地址和IP）
        $rechargeId = Db::name('recharge_records')->insertGetId([
            'user_id' => $userId,
            'user_ip' => $userIp,  // 保存用户IP
            'order_no' => $orderNo,
            'amount' => $amount,
            'currency' => 'USDT',
            'payment_method' => 'usdt',
            'usdt_address' => $allocatedAddress,  // 保存分配的地址
            'status' => 0, // 待支付
            'created_at' => date('Y-m-d H:i:s')
        ]);

        Log::info('USDT订单创建', [
            'user_id' => $userId,
            'order_no' => $orderNo,
            'amount' => $amount,
            'usdt_address' => $allocatedAddress
        ]);

        // 发送Telegram通知（创建订单时）
        try {
            $user = Db::name('users')->where('id', $userId)->find();
            if ($user) {
                TelegramBotService::notifyRecharge([
                    'username' => $user['username'],
                    'uid' => $user['uid']
                ], [
                    'amount' => $amount,
                    'currency' => 'USDT',
                    'payment_method' => 'USDT',
                    'order_no' => $orderNo,
                    'status' => 0, // 待支付
                    'ip' => request()->ip()
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('USDT订单创建通知失败', ['error' => $e->getMessage()]);
        }

        return [
            'code' => 1,
            'msg' => '订单创建成功',
            'data' => [
                'order_no' => $orderNo,
                'recharge_id' => $rechargeId,
                'address' => $allocatedAddress,  // 返回分配的地址
                'network' => $config['network'],
                'amount' => $amount,
                'rate' => $config['rate'],
                'cny_amount' => $amount * $config['rate'],
                'expire_time' => self::ORDER_EXPIRE_TIME,  // 订单有效期（秒）
                'tips' => $config['tips']
            ]
        ];
    }

    /**
     * 查询USDT订单状态
     */
    public static function checkOrder($orderNo)
    {
        $recharge = Db::name('recharge_records')
            ->where('order_no', $orderNo)
            ->find();

        if (!$recharge) {
            return ['code' => -1, 'msg' => '订单不存在'];
        }

        return [
            'code' => 1,
            'data' => [
                'order_no' => $recharge['order_no'],
                'amount' => $recharge['amount'],
                'status' => $recharge['status'], // 0=待支付 1=已支付
                'created_at' => $recharge['created_at'],
                'paid_at' => $recharge['paid_at'] ?? null
            ]
        ];
    }

    /**
     * 取消USDT订单
     */
    public static function cancelOrder($userId, $orderNo)
    {
        $recharge = Db::name('recharge_records')
            ->where('order_no', $orderNo)
            ->where('user_id', $userId)
            ->find();

        if (!$recharge) {
            return ['code' => -1, 'msg' => '订单不存在'];
        }

        if ($recharge['status'] == 1) {
            return ['code' => -1, 'msg' => '订单已支付，无法取消'];
        }

        if ($recharge['status'] == -1) {
            return ['code' => -1, 'msg' => '订单已取消'];
        }

        // 取消订单
        Db::name('recharge_records')
            ->where('id', $recharge['id'])
            ->update([
                'status' => -1,
                'remark' => '用户主动取消'
            ]);

        return [
            'code' => 1,
            'msg' => '订单已取消'
        ];
    }

    /**
     * 监控USDT到账（多地址池版本）
     * 定时任务调用：php think usdt:monitor
     *
     * 核心逻辑：
     * 1. 获取所有待支付的USDT订单
     * 2. 每个订单有独立的收款地址
     * 3. 只需检查订单对应的地址是否有收款
     * 4. 按地址精确匹配，不会混淆
     */
    public static function monitorTransactions()
    {
        // 1. 获取订单有效期内的待支付USDT订单
        $pendingOrders = Db::name('recharge_records')
            ->where('currency', 'USDT')
            ->where('status', 0)
            ->where('usdt_address', '<>', '')
            ->whereNotNull('usdt_address')
            ->where('created_at', '>=', date('Y-m-d H:i:s', time() - self::ORDER_EXPIRE_TIME))
            ->order('created_at asc')
            ->select();

        if (empty($pendingOrders)) {
            return true;  // 没有待处理订单
        }

        Log::info('USDT监控开始', ['pending_orders' => count($pendingOrders)]);

        $processedCount = 0;

        // 2. 遍历每个订单，检查其对应地址是否有收款
        foreach ($pendingOrders as $order) {
            $orderNo = $order['order_no'];
            $orderAmount = (float)$order['amount'];
            $orderAddress = $order['usdt_address'];  // 订单分配的地址
            $orderCreatedAt = strtotime($order['created_at']);
            $orderExpireAt = $orderCreatedAt + self::ORDER_EXPIRE_TIME;

            // 订单已过期，跳过
            if (time() > $orderExpireAt) {
                continue;
            }

            // 3. 只查询这个地址在订单时间范围内的交易
            $transactions = self::fetchTronTransactions(
                $orderAddress,
                $orderCreatedAt - 60,  // 往前1分钟
                time()
            );

            if (empty($transactions)) {
                continue;  // 该地址没有交易
            }

            // 4. 在交易中查找匹配的（精确匹配地址，只需验证金额和时间）
            $matchedTx = null;
            foreach ($transactions as $tx) {
                $txHash = $tx['hash'];
                $txAmount = (float)$tx['amount'];
                $txTime = isset($tx['block_timestamp']) ? (int)($tx['block_timestamp'] / 1000) : 0;

                // 检查交易是否已处理
            $exists = Db::name('recharge_records')
                    ->where('transaction_hash', $txHash)
                    ->where('status', 1)
                ->find();
            if ($exists) {
                continue;
            }

                // 匹配条件（地址已经精确匹配，只需验证金额和时间）：
                // 1. 金额匹配（允许0.01误差）
                // 2. 交易时间在订单时间范围内
                $amountMatch = abs($orderAmount - $txAmount) <= 0.01;
                $timeInRange = ($txTime >= $orderCreatedAt - 60) && ($txTime <= $orderExpireAt);

                if ($amountMatch && $timeInRange) {
                    $matchedTx = $tx;
                    break;
                }
            }

            if ($matchedTx) {
                Log::info('USDT订单匹配成功', [
                    'order_no' => $orderNo,
                    'amount' => $orderAmount,
                    'address' => $orderAddress,
                    'tx_hash' => $matchedTx['hash']
                ]);
                self::processArrival($order['id'], $matchedTx);
                $processedCount++;
            }
        }

        if ($processedCount > 0) {
        Log::info('USDT监控完成', ['processed' => $processedCount]);
        }

        return true;
    }

    /**
     * 处理USDT到账
     */
    private static function processArrival($rechargeId, $transaction)
    {
        Db::startTrans();
        try {
            $recharge = Db::name('recharge_records')->where('id', $rechargeId)->find();

            if ($recharge['status'] == 1) {
                Db::rollback();
                return false; // 已处理
            }

            // 更新订单状态
            $updateData = [
                'status' => 1,
                'transaction_hash' => $transaction['hash']
            ];

            // 检查 paid_at 字段是否存在
            try {
                $columns = Db::query("SHOW COLUMNS FROM `recharge_records` LIKE 'paid_at'");
                if (!empty($columns)) {
                    $updateData['paid_at'] = date('Y-m-d H:i:s');
                }
            } catch (\Exception $e) {
                // 字段不存在，不添加
            }

            Db::name('recharge_records')->where('id', $rechargeId)->update($updateData);

            // 更新用户余额
            $amount = (float)$recharge['amount'];

            // 获取更新前余额
            $balanceBefore = (float)Db::name('users')->where('id', $recharge['user_id'])->value('balance_usdt');

            Db::name('users')->where('id', $recharge['user_id'])->inc('balance_usdt', $amount)->update();
            Db::name('users')->where('id', $recharge['user_id'])->inc('total_recharge_usdt', $amount)->update();

            // 记录钱包日志
            $balanceAfter = $balanceBefore + $amount;
            Db::name('wallet_logs')->insert([
                'user_id' => $recharge['user_id'],
                'type' => 'recharge',
                'currency' => 'USDT',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'remark' => 'USDT充值到账',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            Db::commit();

            Log::info('USDT到账处理成功', [
                'recharge_id' => $rechargeId,
                'user_id' => $recharge['user_id'],
                'amount' => $amount,
                'hash' => $transaction['hash']
            ]);

            // 发送Telegram通知
            try {
                Log::info('准备发送USDT充值成功通知', [
                    'user_id' => $recharge['user_id'],
                    'order_no' => $recharge['order_no']
                ]);

            $user = Db::name('users')->where('id', $recharge['user_id'])->find();
                if ($user) {
                    Log::info('找到用户，准备发送USDT通知', [
                        'username' => $user['username'],
                        'uid' => $user['uid'] ?? 'N/A'
                    ]);

                    $result = TelegramBotService::notifyRecharge([
                'username' => $user['username'],
                        'uid' => $user['uid'] ?? $user['id']  // 如果uid不存在，使用id
            ], [
                'amount' => $amount,
                'currency' => 'USDT',
                'payment_method' => 'usdt',
                'order_no' => $recharge['order_no'],
                'status' => 1,
                        'ip' => $recharge['user_ip'] ?? request()->ip()
                    ]);

                    if ($result) {
                        Log::info('USDT充值成功通知发送成功', [
                            'user_id' => $recharge['user_id'],
                            'order_no' => $recharge['order_no']
                        ]);
                    } else {
                        Log::warning('USDT充值成功通知发送失败', [
                            'user_id' => $recharge['user_id'],
                            'order_no' => $recharge['order_no']
                        ]);
                    }
                } else {
                    Log::warning('USDT充值成功通知失败：用户不存在', ['user_id' => $recharge['user_id']]);
                }
            } catch (\Exception $e) {
                Log::error('USDT充值成功通知异常', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => $recharge['user_id'],
                    'order_no' => $recharge['order_no']
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Db::rollback();
            Log::error('USDT到账处理失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 查询Tron区块链交易（使用TronGrid API）
     *
     * @param string $address 收款地址
     * @param int $startTime 开始时间（秒级时间戳）
     * @param int $endTime 结束时间（秒级时间戳）
     * @return array 交易列表
     */
    private static function fetchTronTransactions($address, $startTime = null, $endTime = null)
    {
        // 读取 TRON API Key（支持轮换使用）
        $apiKeys = [];

        // 方法1: 使用 ThinkPHP Env Facade
        if (class_exists('\think\facade\Env')) {
            $key1 = \think\facade\Env::get('TRON_API_KEY', '');
            $key2 = \think\facade\Env::get('TRON_API_KEY_2', '');
            if ($key1) $apiKeys[] = $key1;
            if ($key2) $apiKeys[] = $key2;
        }

        // 方法2: 如果 Env Facade 不可用，直接读取 .env 文件
        if (empty($apiKeys)) {
            $rootPath = app()->getRootPath();
            $envFile = $rootPath . '.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || strpos($line, '#') === 0) {
                        continue;
                    }
                    if (preg_match('/^TRON_API_KEY(?:_2)?\s*=\s*(.+)$/i', $line, $matches)) {
                        $key = trim($matches[1], ' "\'');
                        if ($key && !in_array($key, $apiKeys)) {
                            $apiKeys[] = $key;
                        }
                    }
                }
            }
        }

        // 轮换使用 API Key
        $cacheKey = 'tron_api_key_index';
        $currentIndex = (int)Cache::get($cacheKey, 0);
        $apiKey = '';
        if (!empty($apiKeys)) {
            $keyCount = count($apiKeys);
            $apiKey = $apiKeys[$currentIndex % $keyCount];
            Cache::set($cacheKey, ($currentIndex + 1) % $keyCount, 3600);
        }

        // 构建API URL（带时间范围过滤）
        // TronGrid API 使用毫秒级时间戳
        $url = "https://api.trongrid.io/v1/accounts/{$address}/transactions/trc20?limit=50&only_confirmed=true";

        // 添加时间范围过滤（只扫描订单时间范围内的交易）
        if ($startTime) {
            $url .= "&min_timestamp=" . ($startTime * 1000);  // 转换为毫秒
        }
        if ($endTime) {
            $url .= "&max_timestamp=" . ($endTime * 1000);  // 转换为毫秒
        }

        $headers = [];
        if ($apiKey) {
            // 使用 TRON-PRO-API-KEY 请求头（TronGrid 官方要求）
            $headers[] = "TRON-PRO-API-KEY: {$apiKey}";
        } else {
            // 如果没有 API Key，记录警告（请求会被限制）
            Log::warning('TronGrid API请求未携带API Key，请求可能被限制');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('TronGrid API请求CURL错误', ['error' => $curlError, 'address' => $address]);
            return [];
        }

        if ($httpCode !== 200) {
            // 429 表示速率限制，403 表示 API Key 无效
            if ($httpCode == 429) {
                Log::warning('TronGrid API速率限制，建议使用多个API Key轮换', ['code' => $httpCode]);
            } elseif ($httpCode == 403) {
                Log::error('TronGrid API Key无效或被拒绝', ['code' => $httpCode, 'api_key_preview' => substr($apiKey, 0, 10) . '...']);
            } else {
                Log::error('TronGrid API请求失败', ['code' => $httpCode, 'response' => substr($response, 0, 200)]);
            }
            return [];
        }

        $data = json_decode($response, true);

        if (!isset($data['data']) || !is_array($data['data'])) {
            return [];
        }

        $transactions = [];

        foreach ($data['data'] as $tx) {
            // 只处理USDT交易（合约地址：TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t）
            if (!isset($tx['token_info']['symbol']) || $tx['token_info']['symbol'] !== 'USDT') {
                continue;
            }

            // 只处理转入交易（to 地址是我们的收款地址）
            // 注意：地址比较需要统一大小写
            $txTo = strtolower(trim($tx['to'] ?? ''));
            $addressLower = strtolower(trim($address));
            if ($txTo === $addressLower) {
            $transactions[] = [
                'hash' => $tx['transaction_id'],
                'from' => $tx['from'],
                'to' => $tx['to'],
                'amount' => (float)($tx['value'] / 1000000),
                'block_timestamp' => $tx['block_timestamp']  // 保持字段名一致
            ];
            }
        }

        // 不缓存结果，每次实时查询（因为需要快速检测到账）
        return $transactions;
    }
}
