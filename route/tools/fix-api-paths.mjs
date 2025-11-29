import fs from "fs";
import path from "path";

const targetDir = "/www/wwwroot/4kp3l0iq.top";
const correct = "https://api.4kp3l0iq.top";

let totalFixed = 0;
let filesModified = 0;

function fixFile(file) {
    let content = fs.readFileSync(file, "utf8");
    let changed = false;
    let fixCount = 0;

    const patterns = [
        // 1. 旧域名
        { pattern: /https:\/\/apis\.frevix\.top/g, desc: "旧域名apis.frevix.top", replacement: correct },

        // 2. 双重/api
        { pattern: /https:\/\/api\.4kp3l0iq\.top\/api\/api/g, desc: "双重/api", replacement: correct + "/api" },

        // 3. index.php路径
        { pattern: /https:\/\/api\.4kp3l0iq\.top\/index\.php\/api/g, desc: "index.php路径", replacement: correct + "/api" },
        { pattern: /https:\/\/api\.4kp3l0iq\.top\/api\/index\.php/g, desc: "api/index.php路径", replacement: correct + "/api" },

        // 4. localhost
        { pattern: /http:\/\/localhost:8888\/api/g, desc: "localhost", replacement: correct + "/api" },
        { pattern: /http:\/\/localhost:8888/g, desc: "localhost无/api", replacement: correct },

        // 5. 错误的API路径 (user/user/xxx -> user/xxx)
        { pattern: /\/user\/user\/index/g, desc: "user/user/index", replacement: "/api/user/index" },
        { pattern: /\/user\/user\/invite/g, desc: "user/user/invite", replacement: "/api/user/invite" },
        { pattern: /\/user\/team\/team/g, desc: "user/team/team", replacement: "/api/team/members" },

        // 6. 大小写错误
        { pattern: /HTTPS:\/\/API\.4KP3L0IQ\.TOP/gi, desc: "大写URL", replacement: correct },

        // 7. 变量模板未替换 (需要手动检查)
        // ${API_BASE}/api/user/index 这种需要确保API_BASE正确
    ];

    patterns.forEach(({ pattern, desc, replacement }) => {
        const matches = content.match(pattern);
        if (matches && matches.length > 0) {
            content = content.replace(pattern, replacement);
            changed = true;
            fixCount += matches.length;
            console.log(`    ✔ 修复 ${desc}: ${matches.length} 处`);
        }
    });

    if (changed) {
        fs.writeFileSync(file, content);
        filesModified++;
        totalFixed += fixCount;
        console.log(`  ✅ ${path.basename(file)}: 修复 ${fixCount} 处问题\n`);
    }
}

function walk(dir) {
    try {
        const files = fs.readdirSync(dir);

        files.forEach(name => {
            if (name.startsWith('.')) return;

            const file = path.join(dir, name);
            const stat = fs.lstatSync(file);

            if (stat.isDirectory()) {
                // 跳过某些目录
                if (['node_modules', '.git', 'dist', 'backup'].includes(name)) {
                    return;
                }
                walk(file);
            } else if (file.endsWith(".js") || file.endsWith(".html")) {
                fixFile(file);
            }
        });
    } catch (err) {
        // 忽略权限错误
    }
}

console.log("🔧 开始扫描并修复前端API路径...");
console.log(`📂 目标目录: ${targetDir}\n`);
console.log("=" .repeat(60) + "\n");

walk(targetDir);

console.log("=" .repeat(60));
console.log(`\n✅ 修复完成!`);
console.log(`📊 统计:`);
console.log(`   - 修改文件数: ${filesModified} 个`);
console.log(`   - 修复问题数: ${totalFixed} 处\n`);
