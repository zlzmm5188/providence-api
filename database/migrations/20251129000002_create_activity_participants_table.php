<?php
/**
 * 创建活动参与记录表
 * 用于记录用户参与活动的信息
 */

use think\migration\Migrator;
use think\migration\db\Column;

class CreateActivityParticipantsTable extends Migrator
{
    public function up()
    {
        $table = $this->table('activity_participants', [
            'engine' => 'InnoDB',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '活动参与记录表'
        ]);

        $table->addColumn('id', 'integer', [
            'identity' => true,
            'signed' => false,
            'null' => false,
            'comment' => '主键ID'
        ])
        ->addColumn('activity_id', 'integer', [
            'signed' => false,
            'null' => false,
            'default' => 0,
            'comment' => '活动ID'
        ])
        ->addColumn('user_id', 'integer', [
            'signed' => false,
            'null' => false,
            'default' => 0,
            'comment' => '用户ID'
        ])
        ->addColumn('participated_at', 'datetime', [
            'null' => true,
            'comment' => '参与时间'
        ])
        ->addColumn('reward_received', 'decimal', [
            'precision' => 20,
            'scale' => 8,
            'default' => '0.00000000',
            'comment' => '已领取奖励金额'
        ])
        ->addColumn('created_at', 'datetime', [
            'null' => true,
            'comment' => '创建时间'
        ])
        ->addIndex(['activity_id', 'user_id'], [
            'unique' => true,
            'name' => 'uk_activity_user'
        ])
        ->addIndex('activity_id', ['name' => 'idx_activity_id'])
        ->addIndex('user_id', ['name' => 'idx_user_id'])
        ->addIndex('participated_at', ['name' => 'idx_participated_at'])
        ->create();
    }

    public function down()
    {
        $this->dropTable('activity_participants');
    }
}
