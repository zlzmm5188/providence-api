<?php
require_once __DIR__ . '/../vendor/autoload.php';

$app = new think\App(__DIR__ . '/..');
$app->initialize();

// 获取数据库连接
$db = \think\facade\Db::connect();

// 处理操作
$action = $_GET['action'] ?? 'list';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'delete' && isset($_POST['id'])) {
        try {
            $db->name('projects')->where('id', $_POST['id'])->delete();
            $message = '<div class="alert success">项目删除成功！</div>';
        } catch (Exception $e) {
            $message = '<div class="alert error">删除失败：' . $e->getMessage() . '</div>';
        }
    } elseif ($action === 'toggle' && isset($_POST['id'])) {
        try {
            $project = $db->name('projects')->where('id', $_POST['id'])->find();
            $newStatus = $project['status'] == 1 ? 0 : 1;
            $db->name('projects')->where('id', $_POST['id'])->update(['status' => $newStatus]);
            $message = '<div class="alert success">状态更新成功！</div>';
        } catch (Exception $e) {
            $message = '<div class="alert error">更新失败：' . $e->getMessage() . '</div>';
        }
    }
}

// 获取项目列表
$projects = $db->name('projects')->order('id', 'desc')->select()->toArray();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>项目管理 - Providence Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 2px solid #4CAF50; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        tr:hover { background: #f8f9fa; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn-toggle { background: #007bff; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; }
        .stat-card h3 { font-size: 14px; opacity: 0.9; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 项目管理</h1>

        <?php echo $message; ?>

        <div class="stats">
            <div class="stat-card">
                <h3>总项目数</h3>
                <div class="number"><?php echo count($projects); ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3>上线项目</h3>
                <div class="number"><?php echo count(array_filter($projects, fn($p) => $p['status'] == 1)); ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h3>下线项目</h3>
                <div class="number"><?php echo count(array_filter($projects, fn($p) => $p['status'] == 0)); ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>项目名称</th>
                    <th>收益率</th>
                    <th>周期(天)</th>
                    <th>起投金额</th>
                    <th>项目总额</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td><?php echo $project['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($project['name']); ?></strong></td>
                    <td><?php echo number_format($project['rate'] * 100, 2); ?>%</td>
                    <td><?php echo $project['cycle']; ?>天</td>
                    <td>¥<?php echo number_format($project['min_amount'], 0); ?></td>
                    <td>¥<?php echo number_format($project['total_amount'], 0); ?></td>
                    <td>
                        <span class="status-badge <?php echo $project['status'] == 1 ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $project['status'] == 1 ? '上线' : '下线'; ?>
                        </span>
                    </td>
                    <td><?php echo substr($project['created_at'], 0, 10); ?></td>
                    <td>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('确定要<?php echo $project['status'] == 1 ? '下线' : '上线'; ?>此项目吗？');">
                            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                            <button type="submit" class="btn btn-toggle" name="action" value="toggle">
                                <?php echo $project['status'] == 1 ? '下线' : '上线'; ?>
                            </button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除此项目吗？此操作不可恢复！');">
                            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                            <button type="submit" class="btn btn-delete" name="action" value="delete">删除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
