<?php

// 应用配置
return [
    // 应用名称
    'app_name' => 'Providence API',

    // 应用调试模式
    'app_debug' => env('app.debug', false),

    // 应用Trace
    'app_trace' => env('app.trace', false),

    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 默认语言
    'default_lang' => 'zh-cn',

    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl' => app()->getThinkPath() . 'tpl/dispatch_jump.tpl',
    'dispatch_error_tmpl'   => app()->getThinkPath() . 'tpl/dispatch_jump.tpl',

    // 异常页面的模板文件
    'exception_tmpl' => app()->getThinkPath() . 'tpl/think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message' => '页面错误！请稍后再试～',

    // 显示错误信息
    'show_error_msg' => false,
];
