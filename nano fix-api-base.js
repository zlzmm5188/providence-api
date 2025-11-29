import fs from "fs";
import path from "path";

const root = "/www/wwwroot/4kp3l0iq.top";
const correct = "https://api.4kp3l0iq.top/api";

function fix(file) {
    let txt = fs.readFileSync(file, "utf8");
    let changed = false;

    const replaceList = [
        { from: /https:\/\/api\.4kp3l0iq\.top\/api\/api/g, label: "双重 /api/api" },
        { from: /https:\/\/apis\.frevix\.top/g, label: "旧域名 apis.frevix.top" },
        { from: /http:\/\/localhost:8888\/api/g, label: "本地 localhost/api" },
        { from: /http:\/\/localhost:8888/g, label: "本地 localhost根路径" }
    ];

    for (let { from, label } of replaceList) {
        if (from.test(txt)) {
            console.log(`✔ 修复 ${label}: ${file}`);
            txt = txt.replace(from, correct);
            changed = true;
        }
    }

    if (changed) fs.writeFileSync(file, txt);
}

function walk(dir) {
    for (const name of fs.readdirSync(dir)) {
        const file = path.join(dir, name);
        const stat = fs.lstatSync(file);

        if (stat.isDirectory()) walk(file);
        else if (file.endsWith(".js") || file.endsWith(".html")) fix(file);
    }
}

console.log("🔧 开始修复所有前端 API 拼接…");
walk(root);
console.log("✅ 修复完成！");
