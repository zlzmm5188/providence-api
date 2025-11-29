<?php
namespace app\middleware;

/**
 * CORS跨域中间件
 */
class Cors
{
    public function handle($request, \Closure $next)
    {
        // 设置允许的源
        $origin = $request->header('Origin') ?: '*';

        // 设置CORS头
        $response = $next($request);

        $response->header([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age' => '86400'
        ]);

        // 处理OPTIONS预检请求
        if ($request->isOptions()) {
            return response('', 204)->header([
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, Origin',
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Max-Age' => '86400'
            ]);
        }

        return $response;
    }
}
