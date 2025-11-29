import axios from "axios";

const URL = "https://api.4kp3l0iq.top/api/user/index";
const TOKEN = "";  // 填

async function checkToken() {
    console.log("\n==== Token 自检 ====\n");

    if (!TOKEN) return console.log("❌ 没有填写 TOKEN");

    try {
        const res = await axios.get(URL, {
            headers: { Token: TOKEN }
        });

        console.log("🟢 Token 有效！");
        console.log("用户信息：", res.data);

    } catch (err) {
        const code = err.response?.status;
        console.log("🔴 Token 无效或后端未识别");
        console.log("Response:", err.response?.data);
    }
}

checkToken();
