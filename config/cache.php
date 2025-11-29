<?php

// 缓存配置
return [
    // 默认缓存驱动
    'default' => 'file',

    // 缓存连接配置
    'stores' => [
        'file' => [
            // 驱动方式
            'type' => 'File',
            // 缓存保存目录
            'path' => runtime_path() . 'cache/',
            // 缓存前缀
            'prefix' => '',
            // 缓存有效期 0表示永久缓存
            'expire' => 0,
        ],
        'redis' => [
            'type' => 'redis',
            'host' => env('redis.host', '127.0.0.1'),
            'port' => env('redis.port', 6379),
            'password' => env('redis.password', ''),
            'select' => env('redis.select', 0),
            'timeout' => 0,
            'expire' => 0,
            'persistent' => false,
            'prefix' => 'providence:',
        ],
    ],
];
