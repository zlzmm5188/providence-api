import axios from "axios";
import fs from "fs";

const BASE = "https://api.4kp3l0iq.top/api";

// 自己填 Token（从浏览器 F12 → Application → Local Storage 复制）
const TOKEN = process.env.TOKEN || "";

if (!TOKEN) {
    console.log("\n❌ 你还没有提供 Token！");
    console.log("请这样运行：");
    console.log("TOKEN=你的token node token-debug.js\n");
    process.exit(0);
}

const apis = [
    "/user/index",
    "/user/vip-progress",
    "/user/ribao/head",
    "/user/ribao/info",
    "/user/ribao/records",
    "/user/points/balance",
    "/recharge/list",
    "/withdraw/list",
    "/team/members",
    "/payment-v2/usdt-config",
];

async function testApi(url) {
    try {
        const res = await axios.get(BASE + url, {
            headers: { Authorization: TOKEN },
            timeout: 8000
        });
        return { url, ok: true, status: res.status, data: res.data };
    } catch (err) {
        return {
            url,
            ok: false,
            status: err.response?.status || "NO_RESPONSE",
            data: err.response?.data || err.message
        };
    }
}

(async () => {
    console.log("\n🚀 正在测试 Token 有效性...");
    console.log("======================================");

    const results = [];

    for (const url of apis) {
        const r = await testApi(url);
        results.push(r);
        if (r.ok) {
            console.log(`✔ ${url} → ${r.status}`);
        } else {
            console.log(`✖ ${url} → ${r.status}`);
        }
    }

    fs.writeFileSync("token_debug_result.json", JSON.stringify(results, null, 2));

    console.log("\n📄 测试结果已保存：token_debug_result.json");
    console.log("======================================\n");
})();
