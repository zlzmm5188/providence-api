<?php
namespace app\api\controller;

use app\common\model\UserBankCard;
use think\facade\Db;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class BankCard
{
    /**
     * 从Token获取用户ID
     */
    private function getUserId()
    {
        // 优先从中间件获取
        $userId = request()->userId ?? 0;
        if ($userId) {
            return $userId;
        }

        // 手动解析Token
        $token = request()->header('authorization') ?: request()->header('token');
        if (!$token) {
            return 0;
        }

        if (stripos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        try {
            $secret = config('jwt.secret');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return $decoded->user_id ?? $decoded->id ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 获取用户银行卡列表
     * GET /api/user/bank/list
     */
    public function list()
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return json([
                'code' => -1,
                'msg' => '请先登录',
                'data' => []
            ]);
        }

        try {
            $cards = UserBankCard::where('user_id', $userId)
                ->where('status', 1)
                ->order('created_at', 'desc')
                ->select();

            return json([
                'code' => 0,
                'msg' => '获取成功',
                'data' => [
                    'cards' => $cards,
                    'count' => count($cards)
                ]
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('获取银行卡列表失败: ' . $e->getMessage());
            return json([
                'code' => -1,
                'msg' => '获取失败',
                'data' => []
            ]);
        }
    }

    /**
     * 添加银行卡
     * POST /api/user/bank/add
     */
    public function add()
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return json([
                'code' => -1,
                'msg' => '请先登录',
                'data' => []
            ]);
        }

        $cardNumber = input('card_number', '');
        $bankName = input('bank_name', '');
        $accountName = input('account_name', '');
        $accountBranch = input('account_branch', '');
        $cardType = input('card_type', 'bank');  // bank 或 usdt
        $usdtAddress = input('address', '');
        $chainType = input('chain_type', 'TRC20');

        // USDT地址绑定
        if ($cardType === 'usdt' || !empty($usdtAddress)) {
            if (empty($usdtAddress)) {
                return json([
                    'code' => -1,
                    'msg' => '请输入USDT地址',
                    'data' => []
                ]);
            }

            // 获取用户信息，验证实名认证状态
            $user = Db::name('users')->where('id', $userId)->find();
            $isVerified = ($user['realname_status'] ?? 0) == 2;

            if (!$isVerified) {
                $statusMsg = '';
                switch ($user['realname_status'] ?? 0) {
                    case 0: $statusMsg = '请先完成实名认证后再绑定USDT地址'; break;
                    case 1: $statusMsg = '您的实名认证正在审核中，请耐心等待'; break;
                    case 3: $statusMsg = '您的实名认证被拒绝，请重新提交'; break;
                    default: $statusMsg = '请先完成实名认证后再绑定USDT地址';
                }
                return json([
                    'code' => -1,
                    'msg' => $statusMsg,
                    'data' => ['need_kyc' => true, 'kyc_status' => $user['realname_status'] ?? 0]
                ]);
            }

            // 检查是否已绑定过
            $existing = Db::name('user_usdt_addresses')
                ->where('user_id', $userId)
                ->where('address', $usdtAddress)
                ->find();

            if ($existing) {
                return json([
                    'code' => -1,
                    'msg' => '该USDT地址已绑定过',
                    'data' => []
                ]);
            }

            try {
                Db::name('user_usdt_addresses')->insert([
                    'user_id' => $userId,
                    'address' => $usdtAddress,
                    'chain_type' => $chainType,
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                return json([
                    'code' => 0,
                    'msg' => 'USDT地址绑定成功',
                    'data' => [
                        'address' => $usdtAddress,
                        'chain_type' => $chainType
                    ]
                ]);
            } catch (\Exception $e) {
                \think\facade\Log::error('绑定USDT地址失败: ' . $e->getMessage());
                return json([
                    'code' => -1,
                    'msg' => '绑定失败，请稍后重试',
                    'data' => []
                ]);
            }
        }

        // 银行卡绑定 - 参数验证
        if (!$cardNumber || !$bankName) {
            return json([
                'code' => -1,
                'msg' => '请填写银行名称和卡号',
                'data' => []
            ]);
        }

        // 获取用户信息，验证实名认证状态
        $user = Db::name('users')->where('id', $userId)->find();

        if (!$user) {
            return json([
                'code' => -1,
                'msg' => '用户不存在',
                'data' => []
            ]);
        }

        // 检查是否已完成实名认证
        // realname_status: 0=未认证, 1=审核中, 2=已认证, 3=拒绝
        $realName = $user['realname'] ?? '';
        $isVerified = ($user['realname_status'] ?? 0) == 2;

        if (!$isVerified || empty($realName)) {
            $statusMsg = '';
            switch ($user['realname_status'] ?? 0) {
                case 0: $statusMsg = '请先完成实名认证后再绑定银行卡'; break;
                case 1: $statusMsg = '您的实名认证正在审核中，请耐心等待'; break;
                case 3: $statusMsg = '您的实名认证被拒绝，请重新提交'; break;
                default: $statusMsg = '请先完成实名认证后再绑定银行卡';
            }
            return json([
                'code' => -1,
                'msg' => $statusMsg,
                'data' => ['need_kyc' => true, 'kyc_status' => $user['realname_status'] ?? 0]
            ]);
        }

        // 验证持卡人姓名是否填写
        if (empty($accountName)) {
            return json([
                'code' => -1,
                'msg' => '请填写持卡人姓名',
                'data' => []
            ]);
        }

        // 验证持卡人姓名与实名信息是否一致
        if (trim($accountName) !== trim($realName)) {
            return json([
                'code' => -1,
                'msg' => '持卡人姓名与实名信息不一致，请核对后重新填写',
                'data' => ['realname_hint' => mb_substr($realName, 0, 1) . '**']  // 只显示姓的第一个字
            ]);
        }

        // 验证卡号格式（简单验证）
        if (strlen($cardNumber) < 10 || strlen($cardNumber) > 19) {
            return json([
                'code' => -1,
                'msg' => '银行卡号格式错误',
                'data' => []
            ]);
        }

        // 检查是否已添加过该卡
        $existing = UserBankCard::where('user_id', $userId)
            ->where('card_number', $cardNumber)
            ->find();

        if ($existing) {
            return json([
                'code' => -1,
                'msg' => '该银行卡已添加过',
                'data' => []
            ]);
        }

        try {
            $cardData = [
                'user_id' => $userId,
                'card_number' => $cardNumber,
                'bank_name' => $bankName,
                'account_name' => $accountName,
                'account_branch' => $accountBranch ?: '',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $card = UserBankCard::create($cardData);

            return json([
                'code' => 0,
                'msg' => '添加成功',
                'data' => [
                    'card_id' => $card->id,
                    'card_number' => $cardNumber,
                    'bank_name' => $bankName,
                    'account_name' => $accountName
                ]
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('添加银行卡失败: ' . $e->getMessage());
            return json([
                'code' => -1,
                'msg' => '添加失败，请稍后重试',
                'data' => []
            ]);
        }
    }

    /**
     * 删除银行卡
     * POST /api/user/bank/del
     */
    public function del()
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return json([
                'code' => -1,
                'msg' => '请先登录',
                'data' => []
            ]);
        }

        $cardId = input('card_id', 0);

        if (!$cardId) {
            return json([
                'code' => -1,
                'msg' => '缺少card_id参数',
                'data' => []
            ]);
        }

        try {
            // 检查银行卡是否属于当前用户
            $card = UserBankCard::find($cardId);

            if (!$card) {
                return json([
                    'code' => -1,
                    'msg' => '银行卡不存在',
                    'data' => []
                ]);
            }

            if ($card->user_id != $userId) {
                return json([
                    'code' => -1,
                    'msg' => '无权删除此银行卡',
                    'data' => []
                ]);
            }

            // 逻辑删除（软删除）
            $card->status = 0;
            $card->deleted_at = date('Y-m-d H:i:s');
            $card->save();

            return json([
                'code' => 0,
                'msg' => '删除成功',
                'data' => []
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('删除银行卡失败: ' . $e->getMessage());
            return json([
                'code' => -1,
                'msg' => '删除失败，请稍后重试',
                'data' => []
            ]);
        }
    }
}
