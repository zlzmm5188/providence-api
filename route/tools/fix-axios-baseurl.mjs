import fs from "fs";
import path from "path";

const rootDir = "/www/wwwroot/4kp3l0iq.top";
const correctBase = "https://api.4kp3l0iq.top/api";

// 匹配任意 axios baseURL 写法
const axiosPatterns = [
    // axios.create
    /axios\.create\s*\(\s*\{\s*baseURL\s*:\s*['"`](.+?)['"`]/gi,

    // service.defaults.baseURL =
    /defaults\.baseURL\s*=\s*['"`](.+?)['"`]/gi,

    // 自定义 http = axios.create()
    /http\.defaults\.baseURL\s*=\s*['"`](.+?)['"`]/gi,

    // new Axios({ baseURL: ... })
    /new\s+Axios\s*\(\s*\{\s*baseURL\s*:\s*['"`](.+?)['"`]/gi,

    // baseURL: `${API_BASE}` 或字符串模板
    /baseURL\s*:\s*`(.+?)`/gi,
    /baseURL\s*:\s*['"`](.+?)['"`]/gi,
];

// 旧域名、错误结构、大小写问题
const rewriteTable = [
    [/https:\/\/apis\.frevix\.top\/api/gi, correctBase],
    [/https:\/\/api\.frevix\.top\/api/gi, correctBase],
    [/https:\/\/api\.4kp3l0iq\.top\/index\.php\/api/gi, correctBase],
    [/HTTPS:\/\/API\.4KP3L0IQ\.TOP\/API/gi, correctBase],
    [/https:\/\/api\.4kp3l0iq\.top\/api\/api/gi, correctBase], // 双API
    [/\/index\.php\/api/gi, "/api"],
    [/http:\/\/localhost:8888\/api/gi, correctBase],
    [/http:\/\/localhost:8888/gi, correctBase],
    [/\/api\/\/+/gi, "/api/"],
];

function fixAxios(file) {
    let content = fs.readFileSync(file, "utf8");
    let original = content;
    let changed = false;

    // 统一修复 axios baseURL
    axiosPatterns.forEach(pattern => {
        if (pattern.test(content)) {
            content = content.replace(pattern, match => {
                console.log(`  ✔ 修复 axios baseURL → ${file}`);
                return match.replace(/baseURL\s*:\s*(['"`]).+?\1/g, `baseURL: "${correctBase}"`);
            });
            changed = true;
        }
    });

    // 修复旧域名和奇怪路径
    rewriteTable.forEach(([pattern, replacement]) => {
        if (pattern.test(content)) {
            console.log(`  ✔ 修复路径 ${pattern} → ${replacement}`);
            console.log(`    文件: ${file}`);
            content = content.replace(pattern, replacement);
            changed = true;
        }
    });

    // 写入
    if (changed && content !== original) {
        fs.writeFileSync(file, content);
    }
}

function walk(dir) {
    try {
        fs.readdirSync(dir).forEach(name => {
            if (name.startsWith(".")) return;

            // 跳过某些目录
            if (['node_modules', '.git', 'dist', 'backup', 'vendor'].includes(name)) return;

            const file = path.join(dir, name);

            let stat;
            try {
                stat = fs.lstatSync(file); // 使用lstatSync避免跟随符号链接
            } catch (err) {
                return; // 跳过无法访问的文件
            }

            if (stat.isDirectory()) {
                walk(file);
            } else if (stat.isFile() && (file.endsWith(".js") || file.endsWith(".vue") || file.endsWith(".ts") || file.endsWith(".html"))) {
                fixAxios(file);
            }
        });
    } catch (err) {
        console.log(`⚠️  跳过目录: ${dir}`);
    }
}

console.log("🚀 开始自动修复 axios baseURL...");
console.log(`📂 扫描目录: ${rootDir}\n`);
walk(rootDir);
console.log("\n✅ 完成：所有 axios baseURL 已更新到：", correctBase);
