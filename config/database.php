<?php
return [
    'default' => env('database.driver', 'mysql'),
    'connections' => [
        'mysql' => [
            'type' => env('database.type', 'mysql'),
            'hostname' => env('database.hostname', '127.0.0.1'),
            'database' => env('database.database', 'providence'),
            'username' => env('database.username', 'root'),
            'password' => env('database.password', ''),
            'hostport' => env('database.hostport', '3306'),
            'charset' => env('database.charset', 'utf8mb4'),
            'prefix' => env('database.prefix', ''),
            'deploy' => 0,
            'rw_separate' => false,
            'master_num' => 1,
            'slave_no' => '',
            'fields_strict' => true,
            'break_reconnect' => false,
            'trigger_sql' => env('app_debug', true),
            'fields_cache' => false,
        ],
    ],
];
