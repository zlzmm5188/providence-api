<?php
/**
 * Hyperf Migration: 创建积分兑换订单表
 * 生成时间: 2025-11-23
 */

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;

class CreatePointsExchangeOrderTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('points_exchange_order', function (Blueprint $table) {
            $table->increments('id')->comment('订单ID');
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('goods_id')->comment('商品ID');
            $table->integer('quantity')->default(1)->comment('兑换数量');
            $table->decimal('points_cost', 20, 8)->default(0)->comment('消耗积分');
            $table->string('status', 20)->default('pending')->comment('状态：pending-待处理，completed-已完成，cancelled-已取消');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->dateTime('created_at')->nullable()->comment('创建时间');
            $table->dateTime('updated_at')->nullable()->comment('更新时间');

            $table->index('user_id', 'idx_user_id');
            $table->index('goods_id', 'idx_goods_id');
            $table->index('status', 'idx_status');
            $table->index('created_at', 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_exchange_order');
    }
}
