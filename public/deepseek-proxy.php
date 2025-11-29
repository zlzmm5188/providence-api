<?php
/**
 * DeepSeek API 代理
 * 保护 API Key 不暴露在前端
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// DeepSeek API 配置
$DEEPSEEK_API_KEY = 'sk-d06267d6714a4621a656a47b5ae30bbd';
$DEEPSEEK_API_URL = 'https://api.deepseek.com/chat/completions';

// 获取请求数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// 系统提示词 - 专业金融客服
$systemPrompt = <<<EOT
你是 Providence 平台的专属智能客服顾问。请遵循以下准则：

## 身份定位
- 你是一位专业、友善、热情的金融服务顾问
- 你代表 Providence 理财平台为用户提供咨询服务
- 始终使用"您"称呼用户，保持礼貌专业

## 服务范围
1. **账户咨询**：账户注册、登录问题、密码找回、账户安全
2. **投资咨询**：产品介绍、收益说明、投资建议、风险提示
3. **VIP服务**：VIP等级说明、升级条件、专属权益
4. **充值提现**：操作指导、到账时间、手续费说明
5. **日利宝**：产品介绍、收益计算、转入转出
6. **团队奖励**：邀请规则、奖励说明、团队管理

## 回复要求
1. 回答简洁明了，重点突出
2. 对于具体数据查询（如余额、收益），告知用户需要登录后在相应页面查看
3. 敏感操作（如提现、密码修改）需提醒用户注意安全
4. 如遇无法回答的问题，建议联系人工客服
5. 适当使用 emoji 使回复更友好

## 安全提示
- 绝不向用户索要密码、验证码等敏感信息
- 提醒用户不要向任何人透露账户信息
- 遇到可疑情况建议用户联系官方客服

记住：你的目标是让每位用户都感受到专业、贴心的服务体验！
EOT;

// 构建请求消息
$messages = [
    ['role' => 'system', 'content' => $systemPrompt]
];

// 添加用户消息（最多保留最近10条）
$userMessages = array_slice($data['messages'], -10);
foreach ($userMessages as $msg) {
    if (isset($msg['role']) && isset($msg['content'])) {
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }
}

// 是否流式输出
$stream = isset($data['stream']) && $data['stream'] === true;

// 构建 DeepSeek API 请求
$requestData = [
    'model' => 'deepseek-chat',
    'messages' => $messages,
    'temperature' => 0.7,
    'max_tokens' => 1024,
    'stream' => $stream
];

// 发送请求到 DeepSeek API
$ch = curl_init($DEEPSEEK_API_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $DEEPSEEK_API_KEY
    ],
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 10
]);

if ($stream) {
    // 流式输出
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
        echo $data;
        ob_flush();
        flush();
        return strlen($data);
    });
    
    curl_exec($ch);
} else {
    // 普通输出
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode(['error' => 'API request failed: ' . curl_error($ch)]);
    } else if ($httpCode !== 200) {
        http_response_code($httpCode);
        echo $response;
    } else {
        echo $response;
    }
}

curl_close($ch);

