#!/usr/bin/env node
/**
 * Providence Token 调试工具
 * 测试登录 -> 获取Token -> 验证Token -> 调用API
 */

const https = require('https');

const API_BASE = 'api.4kp3l0iq.top';
const TEST_USER = {
    username: 'G138688',
    password: 'G138688'
};

function makeRequest(method, path, data = null, token = null) {
    return new Promise((resolve, reject) => {
        const postData = data ? JSON.stringify(data) : null;

        const options = {
            hostname: API_BASE,
            path: path,
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (postData) {
            options.headers['Content-Length'] = Buffer.byteLength(postData);
        }

        if (token) {
            options.headers['Token'] = token;
        }

        const req = https.request(options, (res) => {
            let body = '';

            res.on('data', (chunk) => {
                body += chunk;
            });

            res.on('end', () => {
                try {
                    const json = JSON.parse(body);
                    resolve({ status: res.statusCode, data: json });
                } catch (e) {
                    resolve({ status: res.statusCode, data: body });
                }
            });
        });

        req.on('error', (e) => {
            reject(e);
        });

        if (postData) {
            req.write(postData);
        }

        req.end();
    });
}

function decodeJWT(token) {
    try {
        const parts = token.split('.');
        if (parts.length !== 3) {
            return null;
        }

        const payload = Buffer.from(parts[1], 'base64').toString('utf8');
        return JSON.parse(payload);
    } catch (e) {
        return null;
    }
}

async function main() {
    console.log('🔐 Providence Token 调试工具\n');
    console.log('='.repeat(60));

    try {
        // 1. 登录获取Token
        console.log('\n1️⃣ 测试登录...');
        console.log(`   用户: ${TEST_USER.username}`);

        const loginRes = await makeRequest('POST', '/api/auth/login', TEST_USER);

        if (loginRes.status !== 200 || loginRes.data.code !== 1) {
            console.log('   ❌ 登录失败:', loginRes.data.msg || loginRes.data);
            return;
        }

        const token = loginRes.data.data.token;
        console.log('   ✅ 登录成功!');
        console.log(`   Token: ${token.substring(0, 40)}...`);

        // 2. 解码Token
        console.log('\n2️⃣ 解码Token...');
        const decoded = decodeJWT(token);

        if (decoded) {
            console.log('   ✅ Token解码成功:');
            console.log('   ', JSON.stringify(decoded, null, 2).replace(/\n/g, '\n   '));

            if (decoded.user_id) {
                console.log(`   ✅ user_id: ${decoded.user_id}`);
            } else {
                console.log('   ⚠️  Token中没有user_id字段!');
            }
        } else {
            console.log('   ❌ Token解码失败');
        }

        // 3. 测试获取用户信息
        console.log('\n3️⃣ 测试获取用户信息...');
        const userRes = await makeRequest('GET', '/api/user/index', null, token);

        if (userRes.status === 200 && userRes.data.code === 1) {
            console.log('   ✅ 获取用户信息成功!');
            console.log('   用户ID:', userRes.data.data.id);
            console.log('   用户名:', userRes.data.data.username);
            console.log('   余额CNY:', userRes.data.data.balance_cny);
            console.log('   余额USDT:', userRes.data.data.balance_usdt);
        } else {
            console.log('   ❌ 获取用户信息失败:', userRes.data.msg || userRes.data);
        }

        // 4. 测试其他接口
        console.log('\n4️⃣ 测试其他接口...');

        const tests = [
            ['GET', '/api/user/vip-progress', '获取VIP进度'],
            ['GET', '/api/project/index', '获取项目列表'],
            ['GET', '/api/user/ribao/head', '获取日利宝头部'],
        ];

        for (const [method, path, desc] of tests) {
            try {
                const res = await makeRequest(method, path, null, token);
                if (res.status === 200 && res.data.code === 1) {
                    console.log(`   ✅ ${desc}`);
                } else {
                    console.log(`   ⚠️  ${desc} - ${res.data.msg || 'Unknown'}`);
                }
            } catch (e) {
                console.log(`   ❌ ${desc} - ${e.message}`);
            }
        }

        console.log('\n' + '='.repeat(60));
        console.log('✅ 调试完成!\n');

    } catch (err) {
        console.log('\n❌ 错误:', err.message);
        console.error(err);
    }
}

main();
