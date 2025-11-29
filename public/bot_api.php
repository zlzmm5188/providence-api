<?php
// Bot专用API - 财务和用户统计
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');

$adminToken = $_SERVER['HTTP_ADMIN_TOKEN'] ?? '';
if ($adminToken !== 'providence_admin_2025') {
    http_response_code(403);
    echo json_encode(['code' => 0, 'msg' => '无权限']);
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=providence;charset=utf8mb4', 'root', 'wdzEALSSyyNwGwdk');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['code' => 0, 'msg' => '数据库连接失败']);
    exit;
}

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// 财务统计（包含用户统计）
if (strpos($uri, '/finance/stats') !== false && $method === 'GET') {
    try {
        // === 财务数据 ===
        // 今日充值
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM recharge_records 
            WHERE status = 'completed' AND DATE(created_at) = CURDATE()
        ");
        $todayRecharge = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 昨日充值
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM recharge_records 
            WHERE status = 'completed' AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ");
        $yesterdayRecharge = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 今日提现
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM withdraw_records 
            WHERE status = 'completed' AND DATE(created_at) = CURDATE()
        ");
        $todayWithdraw = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // === 用户统计 ===
        // 今日注册用户
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM users 
            WHERE DATE(created_at) = CURDATE()
        ");
        $todayRegister = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 今日实名用户（realname_status = 1表示已实名）
        $stmt = $pdo->query("
            SELECT COUNT(*) as total 
            FROM users 
            WHERE realname_status = 1 
            AND DATE(updated_at) = CURDATE()
        ");
        $todayRealname = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 今日首充用户
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT user_id) as total 
            FROM recharge_records r1
            WHERE DATE(r1.created_at) = CURDATE() 
            AND r1.status = 'completed'
            AND NOT EXISTS (
                SELECT 1 FROM recharge_records r2 
                WHERE r2.user_id = r1.user_id 
                AND r2.status = 'completed'
                AND DATE(r2.created_at) < CURDATE()
            )
        ");
        $todayFirstRecharge = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 总用户数
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 热门项目
        $stmt = $pdo->query("
            SELECT * FROM projects 
            WHERE status = 1 
            ORDER BY sort DESC, id DESC 
            LIMIT 5
        ");
        $hotProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'code' => 1,
            'data' => [
                // 财务数据
                'yesterday_recharge' => floatval($yesterdayRecharge),
                'today_recharge' => floatval($todayRecharge),
                'today_withdraw' => floatval($todayWithdraw),
                'available_funds' => floatval($todayRecharge - $todayWithdraw),
                'net_cash_flow' => floatval($todayRecharge - $todayWithdraw),
                
                // 用户数据
                'today_register' => intval($todayRegister),
                'today_realname' => intval($todayRealname),
                'today_first_recharge' => intval($todayFirstRecharge),
                'total_users' => intval($totalUsers),
                
                // 项目数据
                'hot_projects' => $hotProjects
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['code' => 0, 'msg' => '查询失败: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(404);
echo json_encode(['code' => 0, 'msg' => '接口不存在']);
