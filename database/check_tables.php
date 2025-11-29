<?php
/**
 * 数据库表检查脚本（交互式）
 * 可以手动输入数据库信息或使用默认值
 */

echo "════════════════════════════════════════════════════════════\n";
echo "🔍 Providence 数据库表检查工具\n";
echo "════════════════════════════════════════════════════════════\n\n";

// 默认配置
$defaultConfig = [
    'hostname' => '127.0.0.1',
    'hostport' => '3306',
    'database' => 'providence',
    'username' => 'root',
    'password' => '',
];

// 尝试从命令行参数获取
$config = $defaultConfig;
if ($argc > 1) {
    parse_str(implode('&', array_slice($argv, 1)), $params);
    if (isset($params['host'])) $config['hostname'] = $params['host'];
    if (isset($params['port'])) $config['hostport'] = $params['port'];
    if (isset($params['db'])) $config['database'] = $params['db'];
    if (isset($params['user'])) $config['username'] = $params['user'];
    if (isset($params['pass'])) $config['password'] = $params['pass'];
}

echo "数据库配置:\n";
echo "  主机: {$config['hostname']}:{$config['hostport']}\n";
echo "  数据库: {$config['database']}\n";
echo "  用户名: {$config['username']}\n";
echo "\n";

// 连接数据库
try {
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
        $config['hostname'],
        $config['hostport'],
        $config['database']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✅ 数据库连接成功\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // 检查表
    $tablesToCheck = ['points_goods', 'points_exchange_order', 'usdt_rate'];
    $missingTables = [];

    echo "📋 检查表结构:\n\n";
    foreach ($tablesToCheck as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() == 0) {
            $missingTables[] = $table;
            echo "❌ 表 {$table} 不存在\n";
        } else {
            echo "✅ 表 {$table} 已存在\n";
        }
    }

    echo "\n";

    // 检查users表字段
    $fieldsToCheck = ['team_reward_level', 'real_name', 'id_card', 'face_photo'];
    $missingFields = [];

    echo "📋 检查users表字段:\n\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM `users`");
    $existingFields = array_column($stmt->fetchAll(), 'Field');

    foreach ($fieldsToCheck as $field) {
        if (!in_array($field, $existingFields)) {
            $missingFields[] = $field;
            echo "❌ users表字段 {$field} 不存在\n";
        } else {
            echo "✅ users表字段 {$field} 已存在\n";
            // 显示字段详情
            $stmt = $pdo->query("SHOW COLUMNS FROM `users` WHERE Field = '{$field}'");
            $fieldInfo = $stmt->fetch();
            if ($fieldInfo) {
                echo "   类型: {$fieldInfo['Type']}, 默认值: " . ($fieldInfo['Default'] ?? 'NULL') . "\n";
            }
        }
    }

    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // 总结
    if (empty($missingTables) && empty($missingFields)) {
        echo "✅ 所有表和字段都已存在，无需迁移！\n\n";

        // 显示表结构验证
        echo "🔍 表结构验证:\n\n";
        foreach ($tablesToCheck as $table) {
            echo "表: {$table}\n";
            $stmt = $pdo->query("DESCRIBE `{$table}`");
            $columns = $stmt->fetchAll();
            foreach ($columns as $col) {
                echo "  - {$col['Field']} ({$col['Type']})\n";
            }
            echo "\n";
        }
    } else {
        echo "⚠️  发现缺失项:\n";
        if (!empty($missingTables)) {
            echo "  缺失的表: " . implode(', ', $missingTables) . "\n";
        }
        if (!empty($missingFields)) {
            echo "  缺失的字段: " . implode(', ', $missingFields) . "\n";
        }
        echo "\n";
        echo "💡 请执行以下命令创建缺失的表和字段:\n";
        echo "   mysql -u{$config['username']} -p{$config['password']} {$config['database']} < database/migrate_direct.sql\n";
        echo "   或\n";
        echo "   mysql -u{$config['username']} -p {$config['database']} < database/migrate_direct.sql\n";
    }

} catch (PDOException $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n\n";
    echo "💡 请检查:\n";
    echo "   1. MySQL服务是否运行\n";
    echo "   2. 数据库配置是否正确\n";
    echo "   3. 数据库用户权限是否足够\n\n";
    echo "💡 或者直接执行SQL文件:\n";
    echo "   mysql -u用户名 -p数据库名 < database/migrate_direct.sql\n";
    exit(1);
}
