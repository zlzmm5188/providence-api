import axios from "axios";

const BASE_URL = "https://api.4kp3l0iq.top/api";
const TOKEN = "";  // 登录后填

const apis = [
    ["POST", "/auth/login", { username: "test", password: "123456" }],
    ["GET", "/user/index"],
    ["GET", "/project/index"],
    ["POST", "/recharge/add", { amount: 100 }],
    ["GET", "/recharge/list"],
    ["GET", "/withdraw/list"],
    ["POST", "/invest/create", { amount: 100 }],
];

async function runTest() {
    console.log("\n==== Providence API 自动测试 ====\n");

    for (const [method, path, body] of apis) {
        try {
            const res = await axios({
                method,
                url: BASE_URL + path,
                data: body,
                headers: TOKEN
                    ? { Token: TOKEN }
                    : {}
            });

            console.log(`🟢 ${method} ${path} → ${res.status}`);
        } catch (err) {
            const status = err.response?.status ?? "NO_RESPONSE";
            console.log(`🔴 ${method} ${path} → ERROR: ${status}`);
        }
    }
}

runTest();
