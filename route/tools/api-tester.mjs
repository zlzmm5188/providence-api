import https from "https";

const BASE_URL = "https://api.4kp3l0iq.top/api";
const TOKEN = process.env.TOKEN || "";

const apis = [
    ["GET", "/project/index", null, false],
    ["POST", "/auth/login", { username: "G138688", password: "G138688" }, false],
    ["GET", "/user/index", null, true],
    ["GET", "/user/vip-progress", null, true],
    ["GET", "/user/ribao/head", null, true],
    ["GET", "/recharge/list", null, true],
    ["GET", "/withdraw/list", null, true],
];

function makeRequest(method, path, body, needAuth) {
    return new Promise((resolve, reject) => {
        const url = new URL(BASE_URL + path);

        const headers = {
            'Content-Type': 'application/json'
        };

        if (needAuth && TOKEN) {
            headers['Token'] = TOKEN;
        }

        const options = {
            hostname: url.hostname,
            path: url.pathname + url.search,
            method: method,
            headers: headers
        };

        const req = https.request(options, (res) => {
            let data = '';

            res.on('data', (chunk) => {
                data += chunk;
            });

            res.on('end', () => {
                resolve({
                    status: res.statusCode,
                    data: data
                });
            });
        });

        req.on('error', (e) => {
            reject(e);
        });

        if (body) {
            req.write(JSON.stringify(body));
        }

        req.end();
    });
}

async function runTest() {
    console.log("\n==== Providence API 自动测试 ====\n");
    console.log(`🔗 Base URL: ${BASE_URL}`);
    console.log(`🔑 Token: ${TOKEN ? TOKEN.substring(0, 20) + '...' : '未提供（仅测试公开接口）'}`);
    console.log("\n开始测试...\n");

    let passed = 0;
    let failed = 0;

    for (const [method, path, body, needAuth] of apis) {
        const authFlag = needAuth ? "🔒" : "🌐";

        try {
            const result = await makeRequest(method, path, body, needAuth);

            const json = JSON.parse(result.data);

            if (result.status === 200 && json.code === 1) {
                console.log(`🟢 ${authFlag} ${method.padEnd(6)} ${path.padEnd(30)} → 成功`);
                passed++;
            } else if (result.status === 401) {
                console.log(`🔴 ${authFlag} ${method.padEnd(6)} ${path.padEnd(30)} → 未授权 (需要Token)`);
                failed++;
            } else {
                console.log(`🟡 ${authFlag} ${method.padEnd(6)} ${path.padEnd(30)} → ${json.msg || '未知错误'}`);
                failed++;
            }
        } catch (err) {
            console.log(`🔴 ${authFlag} ${method.padEnd(6)} ${path.padEnd(30)} → 请求失败: ${err.message}`);
            failed++;
        }
    }

    console.log("\n" + "=".repeat(50));
    console.log(`📊 测试结果: ${passed} 成功 / ${failed} 失败 / ${passed + failed} 总计`);
    console.log("=".repeat(50) + "\n");
}

runTest();
