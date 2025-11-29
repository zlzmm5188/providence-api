<?php

// 日志配置
return [
    // 默认日志记录通道
    'default' => 'file',

    // 日志通道列表
    'channels' => [
        'file' => [
            // 日志记录方式
            'type'           => 'File',
            // 日志保存目录
            'path'           => runtime_path() . 'log/',
            // 单文件日志写入
            'single'         => false,
            // 独立记录error和sql
            'apart_level'    => ['error', 'sql'],
            // 最大日志文件数量
            'max_files'      => 30,
            // 使用JSON格式记录
            'json'           => false,
            // 日志处理
            'realtime_write' => false,
        ],
    ],
];
