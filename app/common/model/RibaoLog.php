<?php
namespace app\common\model;

use think\Model;

/**
 * 日利宝操作日志模型
 */
class RibaoLog extends Model
{
    // 设置表名
    protected $name = 'ribao_log';

    // 设置主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = false;

    // 字段类型转换
    protected $type = [
        'amount' => 'string',
        'before_balance' => 'string',
        'after_balance' => 'string',
        'before_ribao' => 'string',
        'after_ribao' => 'string',
        'create_time' => 'integer',
    ];

    // 类型文本映射
    public static $typeText = [
        'transfer_in' => '转入',
        'transfer_out' => '转出',
        'profit' => '收益',
    ];

    // 币种文本映射
    public static $currencyText = [
        'CNY' => '人民币',
        'USDT' => 'USDT',
    ];

    /**
     * 获取类型文本
     */
    public function getTypeTextAttr($value, $data)
    {
        return self::$typeText[$data['type']] ?? $data['type'];
    }

    /**
     * 获取币种文本
     */
    public function getCurrencyTextAttr($value, $data)
    {
        return self::$currencyText[$data['currency']] ?? $data['currency'];
    }

    /**
     * 获取创建时间文本
     */
    public function getCreateTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['create_time']);
    }
}
