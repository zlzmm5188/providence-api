<?php
/**
 * 数据库检查和迁移脚本
 * 自动检查表是否存在，不存在则创建
 */

// 直接读取数据库配置（不使用ThinkPHP的env函数）
$dbConfig = [
    'hostname' => '127.0.0.1',
    'hostport' => '3306',
    'database' => 'providence',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

// 尝试从.env文件读取配置
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    if (isset($env['database.hostname'])) $dbConfig['hostname'] = $env['database.hostname'];
    if (isset($env['database.hostport'])) $dbConfig['hostport'] = $env['database.hostport'];
    if (isset($env['database.database'])) $dbConfig['database'] = $env['database.database'];
    if (isset($env['database.username'])) $dbConfig['username'] = $env['database.username'];
    if (isset($env['database.password'])) $dbConfig['password'] = $env['database.password'];
    if (isset($env['database.charset'])) $dbConfig['charset'] = $env['database.charset'];
}

// 连接数据库
try {
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['hostname'],
        $dbConfig['hostport'],
        $dbConfig['database'],
        $dbConfig['charset']
    );

    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✅ 数据库连接成功\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // 检查表是否存在
    $tablesToCheck = ['points_goods', 'points_exchange_order', 'usdt_rate'];
    $missingTables = [];

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

    $stmt = $pdo->query("SHOW COLUMNS FROM `users`");
    $existingFields = array_column($stmt->fetchAll(), 'Field');

    foreach ($fieldsToCheck as $field) {
        if (!in_array($field, $existingFields)) {
            $missingFields[] = $field;
            echo "❌ users表字段 {$field} 不存在\n";
        } else {
            echo "✅ users表字段 {$field} 已存在\n";
        }
    }

    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // 如果有缺失，执行迁移
    if (!empty($missingTables) || !empty($missingFields)) {
        echo "🔧 开始执行迁移...\n\n";

        // 读取SQL文件
        $sqlFile = __DIR__ . '/migrations/20251123_add_new_tables.sql';
        if (!file_exists($sqlFile)) {
            die("❌ 迁移文件不存在: {$sqlFile}\n");
        }

        $sql = file_get_contents($sqlFile);

        // 分割SQL语句（按分号分割，但要注意存储过程中的分号）
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^--/', $stmt);
            }
        );

        foreach ($statements as $statement) {
            if (empty(trim($statement))) continue;

            try {
                $pdo->exec($statement);
                echo "✅ 执行成功\n";
            } catch (PDOException $e) {
                // 如果是"已存在"的错误，忽略
                if (strpos($e->getMessage(), 'already exists') !== false ||
                    strpos($e->getMessage(), 'Duplicate column') !== false) {
                    echo "⚠️  已存在，跳过\n";
                } else {
                    echo "❌ 执行失败: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }

    // 验证结果
    echo "🔍 验证迁移结果...\n\n";

    // 再次检查表
    foreach ($tablesToCheck as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "✅ 表 {$table} 已创建\n";

            // 显示表结构
            $stmt = $pdo->query("DESCRIBE `{$table}`");
            $columns = $stmt->fetchAll();
            echo "   字段列表:\n";
            foreach ($columns as $col) {
                echo "     - {$col['Field']} ({$col['Type']})\n";
            }
            echo "\n";
        } else {
            echo "❌ 表 {$table} 创建失败\n";
        }
    }

    // 再次检查字段
    $stmt = $pdo->query("SHOW COLUMNS FROM `users`");
    $existingFields = array_column($stmt->fetchAll(), 'Field');

    foreach ($fieldsToCheck as $field) {
        if (in_array($field, $existingFields)) {
            echo "✅ users表字段 {$field} 已添加\n";

            // 显示字段详情
            $stmt = $pdo->query("SHOW COLUMNS FROM `users` WHERE Field = '{$field}'");
            $fieldInfo = $stmt->fetch();
            if ($fieldInfo) {
                echo "   类型: {$fieldInfo['Type']}, 默认值: {$fieldInfo['Default']}, 注释: {$fieldInfo['Comment']}\n";
            }
        } else {
            echo "❌ users表字段 {$field} 添加失败\n";
        }
    }

    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ 迁移完成！\n";

} catch (PDOException $e) {
    die("❌ 数据库错误: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("❌ 错误: " . $e->getMessage() . "\n");
}
