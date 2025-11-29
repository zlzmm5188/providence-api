<?php
return [
    // 路由中间件
    "middleware" => [],

    // 域名路由
    "domain" => [],

    // URL后缀
    "url_html_suffix" => false,

    // 是否开启路由缓存
    "route_cache" => false,

    // 是否开启路由延迟解析
    "url_lazy_route" => true,

    // 默认控制器
    "default_controller" => "Index",

    // 默认操作
    "default_action" => "index",

    // 【新增】路由文件自动加载
    "route_file" => [
        __DIR__ . '/../route/api.php',
        __DIR__ . '/../route/providence.php',
    ],
];
