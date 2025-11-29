#!/usr/bin/env node
/**
 * Providence 路由检查工具
 * 功能：
 * 1. 解析 ThinkPHP route/api.php 文件
 * 2. 扫描前端所有 API 调用
 * 3. 对比前后端路由一致性
 * 4. 生成详细报告
 */

const fs = require('fs');
const path = require('path');

// 配置
const CONFIG = {
    backendRouteFile: '/www/wwwroot/api.4kp3l0iq.top/route/api.php',
    frontendDir: '/www/wwwroot/4kp3l0iq.top',
    excludeDirs: ['node_modules', '.git', 'dist', 'backup'],
};

// 存储结果
const results = {
    backendRoutes: {
        public: [],      // 无需认证的路由
        protected: [],   // 需要认证的路由
    },
    frontendAPIs: [],    // 前端调用的API
    mismatches: [],      // 不匹配的API
    unused: [],          // 后端定义但前端未使用
};

// 颜色输出
const colors = {
    reset: '\x1b[0m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    cyan: '\x1b[36m',
    gray: '\x1b[90m',
};

function log(msg, color = 'reset') {
    console.log(colors[color] + msg + colors.reset);
}

// ==================== 步骤1: 解析后端路由 ====================

function parseBackendRoutes() {
    log('\n📂 步骤1: 解析后端路由配置\n', 'cyan');

    if (!fs.existsSync(CONFIG.backendRouteFile)) {
        log('❌ 路由文件不存在: ' + CONFIG.backendRouteFile, 'red');
        return false;
    }

    const content = fs.readFileSync(CONFIG.backendRouteFile, 'utf8');

    // 正则提取路由定义
    const routePattern = /Route::(get|post|put|delete|patch)\s*\(\s*['"]([^'"]+)['"]\s*,\s*['"]([^'"]+)['"]/gi;

    let match;
    let inProtectedGroup = false;
    const lines = content.split('\n');

    lines.forEach((line, index) => {
        // 检测是否进入需要认证的路由组
        if (line.includes('->middleware') && line.includes('AuthMiddleware')) {
            inProtectedGroup = true;
        }

        // 检测路由组结束
        if (line.trim() === '});' && inProtectedGroup) {
            inProtectedGroup = false;
        }

        // 提取路由
        const routeMatch = line.match(/Route::(get|post|put|delete|patch)\s*\(\s*['"]([^'"]+)['"]/i);
        if (routeMatch) {
            const method = routeMatch[1].toUpperCase();
            const path = routeMatch[2];

            const route = {
                method,
                path: '/' + path.replace(/^\//, ''),
                fullPath: '/api/' + path.replace(/^\//, ''),
                line: index + 1,
                needsAuth: inProtectedGroup,
            };

            if (inProtectedGroup) {
                results.backendRoutes.protected.push(route);
            } else {
                results.backendRoutes.public.push(route);
            }
        }
    });

    const totalRoutes = results.backendRoutes.public.length + results.backendRoutes.protected.length;
    log(`✅ 解析完成: 共 ${totalRoutes} 个路由`, 'green');
    log(`   🌐 公开路由: ${results.backendRoutes.public.length} 个`, 'gray');
    log(`   🔒 认证路由: ${results.backendRoutes.protected.length} 个`, 'gray');

    return true;
}

// ==================== 步骤2: 扫描前端API调用 ====================

function scanFrontendAPIs() {
    log('\n📱 步骤2: 扫描前端 API 调用\n', 'cyan');

    const apiCalls = new Map(); // 使用Map避免重复

    function scanFile(filePath) {
        const content = fs.readFileSync(filePath, 'utf8');
        const fileName = path.basename(filePath);

        // 匹配各种API调用模式
        const patterns = [
            // http.get('/api/user/index')
            /http\.(get|post|put|delete|patch)\s*\(\s*['"`]([^'"`]+)['"`]/gi,
            // fetch('https://api.4kp3l0iq.top/api/auth/login')
            /fetch\s*\(\s*['"`]([^'"`]*\/api\/[^'"`]+)['"`]/gi,
            // ApiService.user.getInfo() -> 需要从config.js中提取
            /ApiService\.([a-zA-Z]+)\.([a-zA-Z]+)\s*\(/gi,
            // axios.get('/api/...')
            /axios\.(get|post|put|delete|patch)\s*\(\s*['"`]([^'"`]+)['"`]/gi,
        ];

        patterns.forEach(pattern => {
            let match;
            while ((match = pattern.exec(content)) !== null) {
                let apiPath = match[2] || match[1];

                // 清理路径
                if (apiPath) {
                    // 移除域名部分
                    apiPath = apiPath.replace(/https?:\/\/[^\/]+/, '');

                    // 确保以/api开头
                    if (!apiPath.startsWith('/api')) {
                        apiPath = '/api' + (apiPath.startsWith('/') ? '' : '/') + apiPath;
                    }

                    // 移除查询参数
                    apiPath = apiPath.split('?')[0];

                    const method = match[1] ? match[1].toUpperCase() : 'GET';
                    const key = `${method} ${apiPath}`;

                    if (!apiCalls.has(key)) {
                        apiCalls.set(key, {
                            method,
                            path: apiPath,
                            files: [fileName],
                        });
                    } else {
                        const existing = apiCalls.get(key);
                        if (!existing.files.includes(fileName)) {
                            existing.files.push(fileName);
                        }
                    }
                }
            }
        });
    }

    function walkDir(dir) {
        try {
            const files = fs.readdirSync(dir);

            files.forEach(file => {
                if (file.startsWith('.')) return;
                if (CONFIG.excludeDirs.includes(file)) return;

                const fullPath = path.join(dir, file);
                const stat = fs.lstatSync(fullPath);

                if (stat.isDirectory()) {
                    walkDir(fullPath);
                } else if (file.endsWith('.js') || file.endsWith('.html')) {
                    scanFile(fullPath);
                }
            });
        } catch (err) {
            // 忽略权限错误
        }
    }

    walkDir(CONFIG.frontendDir);

    results.frontendAPIs = Array.from(apiCalls.values());

    log(`✅ 扫描完成: 发现 ${results.frontendAPIs.length} 个 API 调用`, 'green');

    return true;
}

// ==================== 步骤3: 对比前后端路由 ====================

function compareRoutes() {
    log('\n🔍 步骤3: 对比前后端路由一致性\n', 'cyan');

    const allBackendRoutes = [
        ...results.backendRoutes.public,
        ...results.backendRoutes.protected,
    ];

    // 检查前端调用的API是否在后端定义
    results.frontendAPIs.forEach(frontendAPI => {
        const matched = allBackendRoutes.find(backendRoute => {
            return backendRoute.method === frontendAPI.method &&
                   backendRoute.fullPath === frontendAPI.path;
        });

        if (!matched) {
            results.mismatches.push({
                type: 'frontend_not_in_backend',
                api: frontendAPI,
            });
        }
    });

    // 检查后端定义但前端未使用的路由（可能正常）
    allBackendRoutes.forEach(backendRoute => {
        const matched = results.frontendAPIs.find(frontendAPI => {
            return frontendAPI.method === backendRoute.method &&
                   frontendAPI.path === backendRoute.fullPath;
        });

        if (!matched) {
            results.unused.push(backendRoute);
        }
    });

    log(`✅ 对比完成`, 'green');
    log(`   ⚠️  前端调用但后端未定义: ${results.mismatches.length} 个`, 'yellow');
    log(`   📊 后端定义但前端未使用: ${results.unused.length} 个`, 'gray');

    return true;
}

// ==================== 步骤4: 生成报告 ====================

function generateReport() {
    log('\n' + '='.repeat(60), 'blue');
    log('📋 Providence 路由检查报告', 'blue');
    log('='.repeat(60) + '\n', 'blue');

    // 1. 后端路由清单
    log('【后端路由清单】\n', 'cyan');

    log('🌐 公开路由 (无需认证):', 'green');
    results.backendRoutes.public.forEach(route => {
        log(`   ${route.method.padEnd(6)} ${route.fullPath}`, 'gray');
    });

    log('\n🔒 认证路由 (需要Token):', 'yellow');
    results.backendRoutes.protected.forEach(route => {
        log(`   ${route.method.padEnd(6)} ${route.fullPath}`, 'gray');
    });

    // 2. 前端API调用清单
    log('\n【前端 API 调用清单】\n', 'cyan');
    results.frontendAPIs.slice(0, 20).forEach(api => {
        const files = api.files.slice(0, 3).join(', ');
        log(`   ${api.method.padEnd(6)} ${api.path}`, 'gray');
        log(`          调用文件: ${files}`, 'gray');
    });

    if (results.frontendAPIs.length > 20) {
        log(`   ... 还有 ${results.frontendAPIs.length - 20} 个API调用`, 'gray');
    }

    // 3. 问题报告
    if (results.mismatches.length > 0) {
        log('\n【⚠️  问题清单】\n', 'yellow');
        log('前端调用但后端未定义的API:', 'red');
        results.mismatches.forEach(mismatch => {
            const api = mismatch.api;
            log(`   🔴 ${api.method.padEnd(6)} ${api.path}`, 'red');
            log(`          调用文件: ${api.files.join(', ')}`, 'gray');
        });
    }

    // 4. 未使用的后端路由
    if (results.unused.length > 0 && results.unused.length < 10) {
        log('\n【📊 后端定义但前端未使用的路由】\n', 'gray');
        results.unused.forEach(route => {
            log(`   ${route.method.padEnd(6)} ${route.fullPath}`, 'gray');
        });
    }

    // 5. 总结
    log('\n' + '='.repeat(60), 'blue');
    log('总结:', 'cyan');
    log(`   后端路由总数: ${results.backendRoutes.public.length + results.backendRoutes.protected.length}`, 'gray');
    log(`   前端API调用: ${results.frontendAPIs.length}`, 'gray');
    log(`   ⚠️  不匹配问题: ${results.mismatches.length}`, results.mismatches.length > 0 ? 'red' : 'green');
    log(`   📊 未使用路由: ${results.unused.length}`, 'gray');
    log('='.repeat(60) + '\n', 'blue');

    // 保存JSON报告
    const reportPath = '/tmp/providence_route_check_report.json';
    fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
    log(`📄 详细报告已保存: ${reportPath}\n`, 'green');
}

// ==================== 主函数 ====================

function main() {
    log('\n🚀 Providence 路由检查工具', 'blue');
    log('正在分析项目路由配置...\n', 'gray');

    try {
        // 步骤1: 解析后端路由
        if (!parseBackendRoutes()) {
            process.exit(1);
        }

        // 步骤2: 扫描前端API
        if (!scanFrontendAPIs()) {
            process.exit(1);
        }

        // 步骤3: 对比路由
        compareRoutes();

        // 步骤4: 生成报告
        generateReport();

        // 返回状态码
        process.exit(results.mismatches.length > 0 ? 1 : 0);

    } catch (err) {
        log('\n❌ 错误: ' + err.message, 'red');
        console.error(err.stack);
        process.exit(1);
    }
}

// 运行
main();
