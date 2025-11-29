<?php
namespace app\common\service;

use app\common\model\User;
use app\common\model\VipLevel;

class VipService
{
    /**
     * 检查并升级VIP
     */
    public static function checkAndUpgrade($userId)
    {
        $user = User::find($userId);
        
        // 查找符合条件的最高VIP等级
        $newLevel = VipLevel::where('required_invest', '<=', $user->total_invest)
            ->orderBy('level', 'desc')
            ->find();
        
        if ($newLevel && $newLevel->level > $user->vip_level) {
            $user->vip_level = $newLevel->level;
            $user->save();
            
            return true;
        }
        
        return false;
    }
}
