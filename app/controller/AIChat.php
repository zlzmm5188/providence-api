<?php
/**
 * AI客服控制器
 * 功能：发布项目、分析项目、根据剩余资金建议购买产品
 */

namespace app\api\controller;

use app\common\model\User;
use app\common\model\InvestProject;
use app\common\model\InvestOrder;
use think\facade\Db;

class AIChat
{
    /**
     * AI客服对话
     * POST /api/ai/chat
     */
    public function chat()
    {
        $userId = request()->userId;
        $message = input('post.message', '', 'trim');
        $context = input('post.context', [], 'array'); // 对话上下文

        if (empty($message)) {
            return json(['code' => -1, 'msg' => '消息不能为空', 'data' => null]);
        }

        try {
            // 获取用户信息
            $user = User::find($userId);
            if (!$user) {
                return json(['code' => -1, 'msg' => '用户不存在', 'data' => null]);
            }

            // 分析用户意图
            $intent = $this->analyzeIntent($message);

            $response = null;

            switch ($intent['type']) {
                case 'publish_project':
                    // 发布项目
                    $response = $this->handlePublishProject($message, $user);
                    break;

                case 'analyze_project':
                    // 分析项目
                    $projectId = $intent['project_id'] ?? 0;
                    $response = $this->handleAnalyzeProject($projectId, $user);
                    break;

                case 'suggest_invest':
                    // 建议购买产品
                    $response = $this->handleSuggestInvest($user);
                    break;

                case 'general_query':
                default:
                    // 通用查询
                    $response = $this->handleGeneralQuery($message, $user, $context);
                    break;
            }

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'response' => $response['text'],
                    'intent' => $intent,
                    'suggestions' => $response['suggestions'] ?? [],
                    'data' => $response['data'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => -1, 'msg' => 'AI服务错误：' . $e->getMessage(), 'data' => null]);
        }
    }

    /**
     * 分析用户意图
     * @param string $message
     * @return array
     */
    private function analyzeIntent($message)
    {
        $message = strtolower($message);

        // 发布项目相关关键词
        if (preg_match('/发布|创建|新增.*项目/', $message)) {
            return ['type' => 'publish_project'];
        }

        // 分析项目相关关键词
        if (preg_match('/分析|评估|查看.*项目|项目.*详情/', $message)) {
            preg_match('/项目[：:：]?(\d+)/', $message, $matches);
            $projectId = $matches[1] ?? 0;
            return ['type' => 'analyze_project', 'project_id' => $projectId];
        }

        // 建议购买相关关键词
        if (preg_match('/建议|推荐|买什么|投资什么|剩余资金|余额/', $message)) {
            return ['type' => 'suggest_invest'];
        }

        return ['type' => 'general_query'];
    }

    /**
     * 处理发布项目请求
     * @param string $message
     * @param User $user
     * @return array
     */
    private function handlePublishProject($message, $user)
    {
        // 提取项目信息（这里简化处理，实际应该用NLP提取）
        // 示例：从消息中提取项目名称、收益率、周期等

        return [
            'text' => '我可以帮您发布项目。请提供以下信息：\n1. 项目名称\n2. 预期收益率\n3. 投资周期（天）\n4. 最低投资金额\n5. 币种（CNY/USDT）\n\n或者您可以直接说："发布一个30天，收益率1.5%的CNY项目"',
            'suggestions' => [
                '发布30天CNY项目',
                '发布60天USDT项目',
                '查看项目发布模板'
            ]
        ];
    }

    /**
     * 处理分析项目请求
     * @param int $projectId
     * @param User $user
     * @return array
     */
    private function handleAnalyzeProject($projectId, $user)
    {
        if ($projectId <= 0) {
            return [
                'text' => '请提供项目ID，例如："分析项目1"或"项目1的详情"',
                'suggestions' => []
            ];
        }

        $project = InvestProject::find($projectId);
        if (!$project) {
            return [
                'text' => "项目ID {$projectId} 不存在",
                'suggestions' => []
            ];
        }

        // 分析项目
        $analysis = $this->analyzeProject($project);

        return [
            'text' => $analysis['text'],
            'data' => [
                'project' => $project,
                'analysis' => $analysis
            ],
            'suggestions' => [
                '查看类似项目',
                '计算投资收益',
                '查看项目风险'
            ]
        ];
    }

    /**
     * 处理建议购买请求
     * @param User $user
     * @return array
     */
    private function handleSuggestInvest($user)
    {
        $balanceCny = $user->balance_cny ?? 0;
        $balanceUsdt = $user->balance_usdt ?? 0;
        $totalBalance = bcadd($balanceCny, $balanceUsdt, 8);

        if (bccomp($totalBalance, '0', 8) <= 0) {
            return [
                'text' => '您的账户余额为0，请先充值后再进行投资。',
                'suggestions' => ['去充值', '查看充值方式']
            ];
        }

        // 获取可投资项目
        $projects = InvestProject::where('status', 1)
            ->order('daily_rate', 'desc')
            ->limit(10)
            ->select();

        $suggestions = [];
        foreach ($projects as $project) {
            $currency = $project->currency ?? 'CNY';
            $balance = $currency === 'CNY' ? $balanceCny : $balanceUsdt;
            $minAmount = $project->min_amount ?? 0;
            $maxAmount = $project->max_amount ?? 0;
            $dailyRate = $project->daily_rate ?? 0;
            $period = $project->period ?? $project->cycle ?? 0;

            if (bccomp($balance, $minAmount, 8) >= 0) {
                $recommendedAmount = bccomp($balance, $maxAmount, 8) > 0 ? $maxAmount : $balance;
                $expectedProfit = $this->calculateProfit($recommendedAmount, $dailyRate, $period);

                $suggestions[] = [
                    'project_id' => $project->project_id ?? $project->id,
                    'name' => $project->name,
                    'daily_rate' => $dailyRate,
                    'period' => $period,
                    'currency' => $currency,
                    'min_amount' => $minAmount,
                    'max_amount' => $maxAmount,
                    'recommended_amount' => $recommendedAmount,
                    'expected_profit' => $expectedProfit,
                    'annual_rate' => $period > 0 ? bcmul(bcmul($dailyRate, '365', 8), bcdiv('100', '1', 8), 8) : 0
                ];
            }
        }

        // 按预期收益排序
        usort($suggestions, function($a, $b) {
            return bccomp($b['expected_profit'], $a['expected_profit'], 8);
        });

        // 只返回前5个
        $suggestions = array_slice($suggestions, 0, 5);

        if (empty($suggestions)) {
            return [
                'text' => "根据您的余额（CNY: ¥{$balanceCny}, USDT: {$balanceUsdt}），暂无合适的投资项目。建议充值后再投资。",
                'suggestions' => ['去充值', '查看所有项目']
            ];
        }

        $text = "💡 根据您的余额，我为您推荐以下项目：\n\n";
        foreach ($suggestions as $idx => $suggestion) {
            $text .= ($idx + 1) . ". 📊 {$suggestion['name']}\n";
            $text .= "   • 日收益率：{$suggestion['daily_rate']}%\n";
            $text .= "   • 投资周期：{$suggestion['period']} 天\n";
            $text .= "   • 年化收益：" . number_format((float)$suggestion['annual_rate'], 2) . "%\n";
            $text .= "   • 建议投资：¥" . number_format((float)$suggestion['recommended_amount'], 2) . "\n";
            $text .= "   • 预期收益：¥" . number_format((float)$suggestion['expected_profit'], 2) . "\n\n";
        }

        return [
            'text' => $text,
            'data' => [
                'balance_cny' => $balanceCny,
                'balance_usdt' => $balanceUsdt,
                'suggestions' => $suggestions
            ],
            'suggestions' => array_map(function($s) {
                return "投资{$s['name']}";
            }, $suggestions)
        ];
    }

    /**
     * 处理通用查询
     * @param string $message
     * @param User $user
     * @param array $context
     * @return array
     */
    private function handleGeneralQuery($message, $user, $context)
    {
        $messageLower = strtolower($message);

        // 余额查询
        if (strpos($messageLower, '余额') !== false || strpos($messageLower, '多少钱') !== false) {
            $balanceCny = $user->balance_cny ?? '0.00000000';
            $balanceUsdt = $user->balance_usdt ?? '0.00000000';
            $ribaoCny = $user->ribao_cny ?? '0.00000000';
            $ribaoUsdt = $user->ribao_usdt ?? '0.00000000';
            $total = bcadd(bcadd($balanceCny, $ribaoCny, 8), bcadd($balanceUsdt, $ribaoUsdt, 8), 8);

            return [
                'text' => "💰 您的账户资产：\n\n" .
                         "💵 CNY余额：¥" . number_format((float)$balanceCny, 2) . "\n" .
                         "💎 USDT余额：" . number_format((float)$balanceUsdt, 2) . "\n" .
                         "📊 日利宝CNY：¥" . number_format((float)$ribaoCny, 2) . "\n" .
                         "📊 日利宝USDT：" . number_format((float)$ribaoUsdt, 2) . "\n" .
                         "✨ 总资产：¥" . number_format((float)$total, 2),
                'suggestions' => ['查看收益', '查看投资', '去充值']
            ];
        }

        // 积分查询
        if (strpos($messageLower, '积分') !== false) {
            $points = $user->points ?? 0;
            return [
                'text' => "🎁 您的积分余额：" . number_format((float)$points, 0) . " 分\n\n" .
                         "积分可用于兑换商品，查看积分商城了解更多。",
                'suggestions' => ['查看积分商城', '查看积分记录']
            ];
        }

        // VIP查询
        if (strpos($messageLower, 'vip') !== false || strpos($messageLower, '等级') !== false) {
            $vipLevel = $user->vip_level ?? 0;
            $totalInvest = $user->total_invest ?? '0.00000000';

            // 获取下一级VIP要求
            $nextVip = Db::name('vip_level')
                ->where('level', $vipLevel + 1)
                ->find();

            $nextRequirement = $nextVip ? $nextVip['required_amount'] : '已达最高等级';

            return [
                'text' => "👑 您的VIP等级：VIP{$vipLevel}\n\n" .
                         "📈 累计投资：¥" . number_format((float)$totalInvest, 2) . "\n" .
                         "🎯 下一级要求：{$nextRequirement}",
                'suggestions' => ['查看VIP权益', '查看投资项目']
            ];
        }

        // 收益查询
        if (strpos($messageLower, '收益') !== false || strpos($messageLower, '利润') !== false) {
            $profitCny = $user->total_profit_cny ?? '0.00000000';
            $profitUsdt = $user->total_profit_usdt ?? '0.00000000';

            // 获取投资订单收益
            $orders = InvestOrder::where('user_id', $user->user_id)
                ->where('status', 'running')
                ->select();

            $activeInvestments = count($orders);

            return [
                'text' => "📊 您的收益情况：\n\n" .
                         "💵 CNY累计收益：¥" . number_format((float)$profitCny, 2) . "\n" .
                         "💎 USDT累计收益：" . number_format((float)$profitUsdt, 2) . "\n" .
                         "📈 进行中投资：{$activeInvestments} 笔",
                'suggestions' => ['查看收益记录', '查看投资项目']
            ];
        }

        // 项目查询
        if (strpos($messageLower, '项目') !== false || strpos($messageLower, '产品') !== false) {
            $projectCount = InvestProject::where('status', 1)->count();
            $hotProjects = InvestProject::where('status', 1)
                ->where('is_hot', 1)
                ->limit(3)
                ->select();

            $text = "📋 当前有 {$projectCount} 个可投资项目\n\n";
            if (!empty($hotProjects)) {
                $text .= "🔥 热门项目：\n";
                foreach ($hotProjects as $idx => $project) {
                    $text .= ($idx + 1) . ". {$project->name} - 收益率{$project->daily_rate}%\n";
                }
            }

            return [
                'text' => $text,
                'suggestions' => ['查看所有项目', '推荐投资']
            ];
        }

        // 团队查询
        if (strpos($messageLower, '团队') !== false || strpos($messageLower, '下级') !== false) {
            $teamCount1 = Db::name('user_relation')
                ->where('parent_id', $user->user_id)
                ->where('level', 1)
                ->count();
            $teamCount2 = Db::name('user_relation')
                ->where('parent_id', $user->user_id)
                ->where('level', 2)
                ->count();

            return [
                'text' => "👥 您的团队情况：\n\n" .
                         "一级成员：{$teamCount1} 人\n" .
                         "二级成员：{$teamCount2} 人\n" .
                         "总计：{$teamCount1 + $teamCount2} 人",
                'suggestions' => ['查看团队详情', '查看团队奖励']
            ];
        }

        // 默认回复
        return [
            'text' => "🤖 我是Providence AI智能客服，可以帮您：\n\n" .
                     "1. 📊 查询账户信息（余额、积分、VIP等级）\n" .
                     "2. 📈 分析投资项目\n" .
                     "3. 💡 根据余额推荐投资\n" .
                     "4. 📋 查看项目列表\n" .
                     "5. 👥 查询团队情况\n" .
                     "6. 💰 查看收益情况\n\n" .
                     "请告诉我您需要什么帮助？",
            'suggestions' => ['查看余额', '分析项目', '推荐投资', '查看收益', '查看团队']
        ];
    }

    /**
     * 分析项目
     * @param InvestProject $project
     * @return array
     */
    private function analyzeProject($project)
    {
        $dailyRate = $project->daily_rate ?? $project->rate ?? 0;
        $period = $project->period ?? $project->cycle ?? 0;
        $currency = $project->currency ?? 'CNY';
        $minAmount = $project->min_amount ?? 0;
        $maxAmount = $project->max_amount ?? 0;
        $totalLimit = $project->total_limit ?? 0;

        // 计算已售出金额
        $soldAmount = InvestOrder::where('project_id', $project->project_id ?? $project->id)
            ->where('status', 'in', ['running', 'completed'])
            ->sum('amount');
        $soldAmount = $soldAmount ?? 0;
        $remainAmount = bcsub($totalLimit, $soldAmount, 8);
        $soldRate = $totalLimit > 0 ? bcmul(bcdiv($soldAmount, $totalLimit, 4), '100', 2) : 0;

        $text = "📊 项目深度分析：{$project->name}\n\n";
        $text .= "📋 基本信息：\n";
        $text .= "• 日收益率：{$dailyRate}%\n";
        $text .= "• 投资周期：{$period} 天\n";
        $text .= "• 币种：{$currency}\n";
        $text .= "• 起投金额：¥" . number_format((float)$minAmount, 2) . "\n";
        $text .= "• 单笔限额：¥" . number_format((float)$maxAmount, 2) . "\n";
        $text .= "• 项目总额：¥" . number_format((float)$totalLimit, 2) . "\n";
        $text .= "• 已售出：¥" . number_format((float)$soldAmount, 2) . " ({$soldRate}%)\n";
        $text .= "• 剩余额度：¥" . number_format((float)$remainAmount, 2) . "\n\n";

        // 计算年化收益率
        $annualRate = $period > 0 ? bcmul(bcmul($dailyRate, '365', 8), bcdiv('100', '1', 8), 8) : 0;
        $text .= "📈 收益分析：\n";
        $text .= "• 年化收益率：" . number_format((float)$annualRate, 2) . "%\n";

        // 计算不同投资金额的收益
        $exampleAmounts = [1000, 5000, 10000, 50000];
        $text .= "• 收益示例：\n";
        foreach ($exampleAmounts as $amount) {
            if ($amount >= $minAmount && $amount <= $maxAmount) {
                $profit = bcmul(bcmul((string)$amount, $dailyRate, 8), bcdiv((string)$period, '100', 8), 8);
                $text .= "  - 投资¥" . number_format($amount, 0) . "，{$period}天收益：¥" . number_format((float)$profit, 2) . "\n";
            }
        }
        $text .= "\n";

        // 风险评估
        $riskLevel = $this->assessRisk($project);
        $riskDesc = $this->getRiskDescription($riskLevel, $dailyRate, $period);
        $text .= "⚠️ 风险评估：\n";
        $text .= "• 风险等级：{$riskLevel}\n";
        $text .= "• 风险说明：{$riskDesc}\n\n";

        // 投资建议
        $suggestion = $this->getInvestmentSuggestion($project, $soldRate);
        $text .= "💡 投资建议：\n";
        $text .= "{$suggestion}\n";

        return [
            'text' => $text,
            'annual_rate' => $annualRate,
            'risk_level' => $riskLevel,
            'sold_rate' => $soldRate,
            'remain_amount' => $remainAmount
        ];
    }

    /**
     * 获取风险描述
     */
    private function getRiskDescription($riskLevel, $dailyRate, $period)
    {
        if ($riskLevel === '低风险') {
            return "收益率稳定，适合稳健型投资者，风险可控。";
        } elseif ($riskLevel === '中低风险') {
            return "收益率适中，风险较低，适合大多数投资者。";
        } elseif ($riskLevel === '中风险') {
            return "收益率较高，需要一定的风险承受能力。";
        } else {
            return "高收益伴随高风险，建议谨慎投资，分散风险。";
        }
    }

    /**
     * 获取投资建议
     */
    private function getInvestmentSuggestion($project, $soldRate)
    {
        if ($soldRate >= 90) {
            return "项目即将售罄，建议尽快投资。";
        } elseif ($soldRate >= 70) {
            return "项目销售良好，剩余额度有限，建议考虑投资。";
        } elseif ($soldRate >= 50) {
            return "项目销售正常，可以适当投资。";
        } else {
            return "项目刚上线，可以关注后续表现。";
        }
    }

    /**
     * 评估项目风险
     * @param InvestProject $project
     * @return string
     */
    private function assessRisk($project)
    {
        $dailyRate = floatval($project->daily_rate ?? $project->rate ?? 0);
        $period = intval($project->period ?? $project->cycle ?? 0);

        // 综合评估：收益率和周期
        if ($dailyRate < 0.1) {
            return '低风险';
        } elseif ($dailyRate < 0.2) {
            return '中低风险';
        } elseif ($dailyRate < 0.3) {
            return '中风险';
        } elseif ($dailyRate < 0.5) {
            return '中高风险';
        } else {
            return '高风险';
        }
    }

    /**
     * 计算预期收益
     * @param string $amount 投资金额
     * @param string $rate 日收益率（百分比，如1.5表示1.5%）
     * @param int $cycle 周期（天）
     * @return string
     */
    private function calculateProfit($amount, $rate, $cycle)
    {
        // 日收益率转换为小数：1.5% = 0.015
        $rateDecimal = bcdiv($rate, '100', 8);
        // 收益 = 本金 × 日收益率 × 天数
        return bcmul(bcmul($amount, $rateDecimal, 8), (string)$cycle, 8);
    }
}
