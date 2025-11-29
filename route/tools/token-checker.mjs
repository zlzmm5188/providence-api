import https from "https";

const URL = "https://api.4kp3l0iq.top/api/user/index";
const TOKEN = process.env.TOKEN || "";  // 从环境变量或命令行参数获取

function makeRequest(token) {
    return new Promise((resolve, reject) => {
        const url = new URL(URL);

        const options = {
            hostname: url.hostname,
            path: url.pathname + url.search,
            method: 'GET',
            headers: {
                'Token': token,
                'Content-Type': 'application/json'
            }
        };

        const req = https.request(options, (res) => {
            let data = '';

            res.on('data', (chunk) => {
                data += chunk;
            });

            res.on('end', () => {
                resolve({
                    status: res.statusCode,
                    headers: res.headers,
                    data: data
                });
            });
        });

        req.on('error', (e) => {
            reject(e);
        });

        req.end();
    });
}

async function checkToken() {
    console.log("\n==== Token 自检工具 ====\n");
    console.log(`📡 测试接口: ${URL}`);

    if (!TOKEN) {
        console.log("❌ 未提供 TOKEN");
        console.log("💡 使用方法: TOKEN=你的token node token-checker.mjs");
        console.log("💡 或者从浏览器 localStorage 复制 providence_token\n");

        // 尝试从文件读取
        try {
            const fs = await import('fs');
            const tokenFile = '/tmp/providence_test_token.txt';
            if (fs.existsSync(tokenFile)) {
                const fileToken = fs.readFileSync(tokenFile, 'utf8').trim();
                if (fileToken) {
                    console.log("✅ 从临时文件读取到 Token，开始测试...\n");
                    return checkWithToken(fileToken);
                }
            }
        } catch (e) { }

        return;
    }

    await checkWithToken(TOKEN);
}

async function checkWithToken(token) {
    console.log(`🔑 Token: ${token.substring(0, 20)}...`);
    console.log("📤 发送请求...\n");

    try {
        const result = await makeRequest(token);

        console.log(`📊 HTTP状态: ${result.status}`);

        try {
            const json = JSON.parse(result.data);

            if (result.status === 200 && json.code === 1) {
                console.log("🟢 Token 有效！\n");
                console.log("👤 用户信息:");
                console.log(JSON.stringify(json.data, null, 2));
            } else {
                console.log("🔴 Token 无效或后端验证失败\n");
                console.log("响应内容:");
                console.log(JSON.stringify(json, null, 2));
            }
        } catch (e) {
            console.log("⚠️  响应不是有效JSON:");
            console.log(result.data.substring(0, 500));
        }

    } catch (err) {
        console.log("🔴 请求失败:");
        console.log(err.message);
    }
}

checkToken();
