<?php
namespace app\common\model;

use think\Model;

class User extends Model
{
    protected $pk = 'id';  // 主键是id，不是user_id
    protected $table = 'pd_user';

    // 隐藏字段
    protected $hidden = ['password', 'pay_password'];

    // 类型转换
    protected $type = [
        'user_id' => 'integer',
        'vip_level' => 'integer',
        'is_kyc' => 'integer',
        'status' => 'integer',
        'points' => 'integer',
        'is_internal' => 'integer',
    ];

    // 自动完成
    protected $auto = [];
    // 移除自动插入invite_code，因为我们在注册时手动设置
    // protected $insert = ['invite_code'];

    // 生成邀请码规则：邀请码 = 个人账号（username）
    // 规则：code = 个人账号（系统规则）
    // 所有邀请码都是账号本身，不再使用随机生成的邀请码
    protected function setInviteCodeAttr($value, $data)
    {
        // 如果已经设置了邀请码，直接使用
        if (!empty($value)) {
            return strtoupper($value); // 转换为大写，统一格式
        }

        // 如果提供了username，使用username作为邀请码（必须）
        if (!empty($data['username'])) {
            $inviteCode = strtoupper($data['username']);
            // 检查是否已存在（理论上不会，因为username唯一）
            $exists = self::where('invite_code', $inviteCode)->count();
            if ($exists > 0) {
                // 如果冲突，抛出异常而不是生成随机邀请码
                throw new \Exception('邀请码冲突：账号 ' . $data['username'] . ' 的邀请码已存在');
            }
            return $inviteCode;
        }

        // 如果没有username，无法生成邀请码（因为规则是：邀请码 = 账号）
        throw new \Exception('无法生成邀请码：缺少账号信息');
    }

    // 获取推荐人
    public function parent()
    {
        return $this->hasOne(User::class, 'user_id', 'parent_id');
    }

    // 获取一级下级
    public function children()
    {
        return $this->hasMany(User::class, 'parent_id', 'user_id');
    }
}
