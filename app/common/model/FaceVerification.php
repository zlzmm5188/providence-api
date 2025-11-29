<?php
/**
 * 人脸识别记录模型
 */

namespace app\common\model;

use think\Model;

class FaceVerification extends Model
{
    protected $pk = 'id';
    protected $table = 'face_verification';

    // 类型转换
    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'similarity' => 'float',
        'liveness_score' => 'float',
        'result' => 'integer',
    ];

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
