<?php
/**
 * 添加签到积分字段到VIP等级表
 * 执行时间: 2025-11-29
 */

use think\facade\Db;

// 检查字段是否存在
$tableName = 'vip_levels';
$columnName = 'checkin_points';

try {
    // 检查表是否存在
    $tableExists = Db::query("SHOW TABLES LIKE '{$tableName}'");
    if (empty($tableExists)) {
        echo "表 {$tableName} 不存在，跳过迁移\n";
        exit(0);
    }

    // 检查字段是否已存在
    $columns = Db::query("SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnName}'");
    if (!empty($columns)) {
        echo "字段 {$columnName} 已存在，跳过迁移\n";
        exit(0);
    }

    // 添加字段
    $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `checkin_points` INT(11) DEFAULT 0 COMMENT '签到积分' AFTER `referral_rate_2`";
    Db::execute($sql);

    // 设置默认值
    $defaultValues = [
        0 => 6,   // VIP0
        1 => 10,  // VIP1
        2 => 16,  // VIP2
        3 => 20,  // VIP3
        4 => 30,  // VIP4
        5 => 40,  // VIP5
        6 => 50,  // VIP6
        7 => 60,  // VIP7
        8 => 70,  // VIP8
    ];

    foreach ($defaultValues as $level => $points) {
        Db::name('vip_levels')
            ->where('level', $level)
            ->update(['checkin_points' => $points]);
    }

    echo "✅ 成功添加字段 {$columnName} 到表 {$tableName}\n";
    echo "✅ 已设置默认签到积分值\n";

} catch (\Exception $e) {
    echo "❌ 迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}
