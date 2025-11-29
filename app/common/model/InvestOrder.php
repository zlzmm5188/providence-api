<?php
namespace app\common\model;
use think\Model;

class InvestOrder extends Model
{
    protected $table = 'pd_investment';
    protected $pk = 'id';  // 数据库主键是id，不是order_id
}
