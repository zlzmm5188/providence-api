import fs from "fs";
import path from "path";

const targetDir = "/Users/lulu/zijinzuixin/qianduan";
const correct = "https://api.4kp3l0iq.top/api";

function fixFile(file) {
    let content = fs.readFileSync(file, "utf8");

    const patterns = [
        /https:\/\/api\.4kp3l0iq\.top\/api\/api/g,
        /https:\/\/api\.4kp3l0iq\.top\/index\.php\/api/g,
        /http:\/\/localhost:8888\/api/g
    ];

    patterns.forEach(p => {
        content = content.replace(p, correct);
    });

    fs.writeFileSync(file, content);
    console.log("✔ 修复：" + file);
}

function walk(dir) {
    fs.readdirSync(dir).forEach(name => {
        const file = path.join(dir, name);
        if (fs.lstatSync(file).isDirectory()) {
            walk(file);
        } else if (file.endsWith(".js") || file.endsWith(".html")) {
            fixFile(file);
        }
    });
}

console.log("开始修复...");
walk(targetDir);
console.log("完成！");
