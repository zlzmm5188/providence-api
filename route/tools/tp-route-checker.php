#!/usr/bin/env php
<?php
/**
 * ThinkPHP 路由检查工具
 * 检查所有路由配置、中间件应用、控制器存在性
 */

define('APP_PATH', dirname(__DIR__, 2) . '/');
require APP_PATH . 'vendor/autoload.php';

echo "\n==== ThinkPHP 路由检查工具 ====\n\n";

// 1. 检查路由文件
echo "📂 1. 检查路由文件\n";
$routeFile = APP_PATH . 'route/api.php';
if (file_exists($routeFile)) {
    echo "   ✅ route/api.php 存在\n";
    $routeContent = file_get_contents($routeFile);

    // 检查中间件配置
    if (strpos($routeContent, 'AuthMiddleware::class') !== false) {
        echo "   ✅ AuthMiddleware 已配置\n";
    } else {
        echo "   ❌ AuthMiddleware 未配置\n";
    }

    // 统计路由数量
    preg_match_all('/Route::(get|post|put|delete|patch)/i', $routeContent, $matches);
    $routeCount = count($matches[0]);
    echo "   📊 共定义 {$routeCount} 个路由\n";
} else {
    echo "   ❌ route/api.php 不存在\n";
}

// 2. 检查中间件文件
echo "\n🔒 2. 检查中间件文件\n";
$middlewareFile = APP_PATH . 'app/common/middleware/AuthMiddleware.php';
if (file_exists($middlewareFile)) {
    echo "   ✅ AuthMiddleware.php 存在\n";
    $middlewareContent = file_get_contents($middlewareFile);

    if (strpos($middlewareContent, 'request()->userId') !== false) {
        echo "   ✅ 中间件设置 request()->userId\n";
    } else {
        echo "   ⚠️  中间件可能未设置 request()->userId\n";
    }
} else {
    echo "   ❌ AuthMiddleware.php 不存在\n";
}

// 3. 检查控制器文件
echo "\n🎮 3. 检查关键控制器\n";
$controllers = [
    'Auth' => 'app/api/controller/Auth.php',
    'User' => 'app/api/controller/User.php',
    'Project' => 'app/api/controller/Project.php',
];

foreach ($controllers as $name => $path) {
    $fullPath = APP_PATH . $path;
    if (file_exists($fullPath)) {
        echo "   ✅ {$name} 控制器存在\n";
    } else {
        echo "   ❌ {$name} 控制器不存在: {$path}\n";
    }
}

// 4. 检查配置文件
echo "\n⚙️  4. 检查配置文件\n";
$configFiles = [
    'database.php' => 'config/database.php',
    'jwt.php' => 'config/jwt.php',
    'middleware.php' => 'config/middleware.php',
];

foreach ($configFiles as $name => $path) {
    $fullPath = APP_PATH . $path;
    if (file_exists($fullPath)) {
        echo "   ✅ {$name} 存在\n";
    } else {
        echo "   ⚠️  {$name} 不存在\n";
    }
}

// 5. 检查 JWT 配置
echo "\n🔐 5. 检查 JWT 配置\n";
$envFile = APP_PATH . '.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (strpos($envContent, 'JWT_SECRET') !== false) {
        echo "   ✅ JWT_SECRET 已配置\n";
    } else {
        echo "   ⚠️  .env 中未找到 JWT_SECRET\n";
    }
} else {
    echo "   ⚠️  .env 文件不存在\n";
}

// 6. 提取所有路由并检查
echo "\n📋 6. 路由清单\n";
if (isset($routeContent)) {
    preg_match_all("/Route::(get|post|put|delete|patch)\s*\(\s*['\"]([^'\"]+)['\"]/i", $routeContent, $routes);

    echo "\n   需要认证的路由:\n";
    $authSection = false;
    $lines = explode("\n", $routeContent);
    foreach ($lines as $line) {
        if (strpos($line, 'middleware') !== false && strpos($line, 'AuthMiddleware') !== false) {
            $authSection = true;
        }
        if (preg_match("/Route::(get|post|put|delete|patch)\s*\(\s*['\"]([^'\"]+)['\"]/i", $line, $match)) {
            if ($authSection) {
                echo "      🔒 {$match[1]} /{$match[2]}\n";
            }
        }
        if (strpos($line, '});') !== false && $authSection) {
            $authSection = false;
        }
    }
}

// 7. 检查数据库连接
echo "\n🗄️  7. 检查数据库连接\n";
try {
    $dbConfig = require APP_PATH . 'config/database.php';
    $mysql = $dbConfig['connections']['mysql'] ?? null;

    if ($mysql) {
        echo "   📊 数据库配置:\n";
        echo "      主机: {$mysql['hostname']}\n";
        echo "      库名: {$mysql['database']}\n";
        echo "      用户: {$mysql['username']}\n";

        // 尝试连接
        try {
            $dsn = "mysql:host={$mysql['hostname']};dbname={$mysql['database']};charset={$mysql['charset']}";
            $pdo = new PDO($dsn, $mysql['username'], $mysql['password']);
            echo "   ✅ 数据库连接成功\n";

            // 检查 users 表
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "   👥 用户表记录数: {$result['count']}\n";
        } catch (PDOException $e) {
            echo "   ❌ 数据库连接失败: {$e->getMessage()}\n";
        }
    }
} catch (Exception $e) {
    echo "   ⚠️  无法读取数据库配置\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "检查完成！\n\n";
