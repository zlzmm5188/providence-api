<?php
/**
 * 初始化示例项目脚本
 * 用于快速创建VIP项目和USDT项目
 */

require_once __DIR__ . '/../vendor/autoload.php';

use think\facade\Db;

// 初始化ThinkPHP
$app = new think\App();
$app->initialize();

try {
    echo "🚀 开始初始化示例项目...\n\n";

    // 项目数据
    $projects = [
        // VIP项目（CNY）
        [
            'name' => 'VIP1专享-稳健收益',
            'subtitle' => '30天稳健理财',
            'description' => '专为VIP1用户打造的稳健收益项目，风险可控，收益稳定。',
            'daily_rate' => 0.15,
            'min_amount' => 1000.00,
            'max_amount' => 50000.00,
            'total_amount' => 1000000.00,
            'period' => 30,
            'currency' => 'CNY',
            'status' => 1,
            'category' => 'VIP专区',
            'usdt_bonus' => 0,
            'vip_level' => 1,
            'is_hot' => 1,
            'is_vip' => 1,
            'is_recommend' => 0,
            'sold_amount' => 0,
        ],
        [
            'name' => 'VIP2专享-高收益',
            'subtitle' => '60天高收益理财',
            'description' => 'VIP2用户专享高收益项目，投资周期适中，收益可观。',
            'daily_rate' => 0.20,
            'min_amount' => 5000.00,
            'max_amount' => 100000.00,
            'total_amount' => 2000000.00,
            'period' => 60,
            'currency' => 'CNY',
            'status' => 1,
            'category' => 'VIP专区',
            'usdt_bonus' => 0,
            'vip_level' => 2,
            'is_hot' => 1,
            'is_vip' => 1,
            'is_recommend' => 0,
            'sold_amount' => 0,
        ],
        [
            'name' => 'VIP3专享-尊享理财',
            'subtitle' => '90天尊享理财',
            'description' => 'VIP3用户尊享项目，长期投资，丰厚回报。',
            'daily_rate' => 0.25,
            'min_amount' => 10000.00,
            'max_amount' => 200000.00,
            'total_amount' => 5000000.00,
            'period' => 90,
            'currency' => 'CNY',
            'status' => 1,
            'category' => 'VIP专区',
            'usdt_bonus' => 0,
            'vip_level' => 3,
            'is_hot' => 1,
            'is_vip' => 1,
            'is_recommend' => 0,
            'sold_amount' => 0,
        ],
        [
            'name' => 'VIP4专享-至尊理财',
            'subtitle' => '120天至尊理财',
            'description' => 'VIP4用户至尊项目，超长周期，超高收益。',
            'daily_rate' => 0.30,
            'min_amount' => 20000.00,
            'max_amount' => 500000.00,
            'total_amount' => 10000000.00,
            'period' => 120,
            'currency' => 'CNY',
            'status' => 1,
            'category' => 'VIP专区',
            'usdt_bonus' => 0,
            'vip_level' => 4,
            'is_hot' => 1,
            'is_vip' => 1,
            'is_recommend' => 0,
            'sold_amount' => 0,
        ],
        [
            'name' => 'VIP5专享-顶级理财',
            'subtitle' => '180天顶级理财',
            'description' => 'VIP5用户顶级项目，长期持有，财富增值。',
            'daily_rate' => 0.35,
            'min_amount' => 50000.00,
            'max_amount' => 1000000.00,
            'total_amount' => 20000000.00,
            'period' => 180,
            'currency' => 'CNY',
            'status' => 1,
            'category' => 'VIP专区',
            'usdt_bonus' => 0,
            'vip_level' => 5,
            'is_hot' => 1,
            'is_vip' => 1,
            'is_recommend' => 0,
            'sold_amount' => 0,
        ],
        // USDT项目
        [
            'name' => 'USDT稳健收益',
            'subtitle' => '30天USDT理财',
            'description' => 'USDT稳定币理财项目，收益稳定，风险可控。',
            'daily_rate' => 0.18,
            'min_amount' => 100.00,
            'max_amount' => 10000.00,
            'total_amount' => 500000.00,
            'period' => 30,
            'currency' => 'USDT',
            'status' => 1,
            'category' => 'USDT专区',
            'usdt_bonus' => 0,
            'vip_level' => 0,
            'is_hot' => 1,
            'is_vip' => 0,
            'is_recommend' => 1,
            'sold_amount' => 0,
        ],
        [
            'name' => 'USDT高收益',
            'subtitle' => '60天USDT理财',
            'description' => 'USDT高收益理财项目，投资周期适中，收益可观。',
            'daily_rate' => 0.22,
            'min_amount' => 500.00,
            'max_amount' => 20000.00,
            'total_amount' => 1000000.00,
            'period' => 60,
            'currency' => 'USDT',
            'status' => 1,
            'category' => 'USDT专区',
            'usdt_bonus' => 0,
            'vip_level' => 0,
            'is_hot' => 1,
            'is_vip' => 0,
            'is_recommend' => 1,
            'sold_amount' => 0,
        ],
        [
            'name' => 'USDT尊享理财',
            'subtitle' => '90天USDT理财',
            'description' => 'USDT尊享理财项目，长期投资，丰厚回报。',
            'daily_rate' => 0.28,
            'min_amount' => 1000.00,
            'max_amount' => 50000.00,
            'total_amount' => 2000000.00,
            'period' => 90,
            'currency' => 'USDT',
            'status' => 1,
            'category' => 'USDT专区',
            'usdt_bonus' => 0,
            'vip_level' => 0,
            'is_hot' => 1,
            'is_vip' => 0,
            'is_recommend' => 1,
            'sold_amount' => 0,
        ],
        [
            'name' => 'USDT VIP专享',
            'subtitle' => '120天USDT VIP',
            'description' => 'USDT VIP专享项目，VIP3及以上用户可投。',
            'daily_rate' => 0.32,
            'min_amount' => 2000.00,
            'max_amount' => 100000.00,
            'total_amount' => 5000000.00,
            'period' => 120,
            'currency' => 'USDT',
            'status' => 1,
            'category' => 'USDT专区',
            'usdt_bonus' => 0,
            'vip_level' => 3,
            'is_hot' => 1,
            'is_vip' => 1,
            'is_recommend' => 1,
            'sold_amount' => 0,
        ],
        // 普通CNY项目
        [
            'name' => '稳健收益30天',
            'subtitle' => '30天稳健理财',
            'description' => '适合新手的稳健收益项目，风险低，收益稳定。',
            'daily_rate' => 0.12,
            'min_amount' => 500.00,
            'max_amount' => 20000.00,
            'total_amount' => 500000.00,
            'period' => 30,
            'currency' => 'CNY',
            'status' => 1,
            'category' => '固收优选',
            'usdt_bonus' => 0,
            'vip_level' => 0,
            'is_hot' => 1,
            'is_vip' => 0,
            'is_recommend' => 1,
            'sold_amount' => 0,
        ],
        [
            'name' => '高收益60天',
            'subtitle' => '60天高收益理财',
            'description' => '高收益理财项目，投资周期适中，收益可观。',
            'daily_rate' => 0.18,
            'min_amount' => 1000.00,
            'max_amount' => 50000.00,
            'total_amount' => 1000000.00,
            'period' => 60,
            'currency' => 'CNY',
            'status' => 1,
            'category' => '固收优选',
            'usdt_bonus' => 0,
            'vip_level' => 0,
            'is_hot' => 1,
            'is_vip' => 0,
            'is_recommend' => 1,
            'sold_amount' => 0,
        ],
    ];

    $successCount = 0;
    $failCount = 0;

    foreach ($projects as $project) {
        try {
            $project['created_at'] = date('Y-m-d H:i:s');
            $project['updated_at'] = date('Y-m-d H:i:s');

            $projectId = Db::name('invest_project')->insertGetId($project);

            echo "✅ 创建成功: {$project['name']} (ID: {$projectId})\n";
            echo "   - 币种: {$project['currency']}\n";
            echo "   - 日收益率: {$project['daily_rate']}%\n";
            echo "   - 周期: {$project['period']}天\n";
            echo "   - VIP等级: " . ($project['vip_level'] > 0 ? "VIP{$project['vip_level']}" : "不限") . "\n";
            echo "   - 总额: " . number_format($project['total_amount'], 2) . "\n\n";

            $successCount++;
        } catch (\Exception $e) {
            echo "❌ 创建失败: {$project['name']} - {$e->getMessage()}\n\n";
            $failCount++;
        }
    }

    echo "════════════════════════════════════════════════════════════\n";
    echo "📊 初始化完成统计：\n";
    echo "   ✅ 成功: {$successCount} 个\n";
    echo "   ❌ 失败: {$failCount} 个\n";
    echo "   📋 总计: " . count($projects) . " 个\n";
    echo "════════════════════════════════════════════════════════════\n";

} catch (\Exception $e) {
    echo "❌ 错误: {$e->getMessage()}\n";
    exit(1);
}

