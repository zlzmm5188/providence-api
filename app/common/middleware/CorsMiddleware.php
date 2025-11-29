<?php
namespace app\common\middleware;

/**
 * CORS跨域中间件
 * 已禁用 - CORS 已在 public/index.php 统一处理，避免重复设置
 */
class CorsMiddleware
{
    public function handle($request, \Closure $next)
    {
        // CORS 已在 public/index.php 统一处理，此处不再设置
        // 避免重复的 Access-Control-Allow-Origin 头部
        return $next($request);
    }
}
