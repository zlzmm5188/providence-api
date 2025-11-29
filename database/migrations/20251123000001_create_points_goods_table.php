<?php
/**
 * Hyperf Migration: 创建积分商品表
 * 生成时间: 2025-11-23
 */

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;

class CreatePointsGoodsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('points_goods', function (Blueprint $table) {
            $table->increments('id')->comment('商品ID');
            $table->string('name', 255)->comment('商品名称');
            $table->text('description')->nullable()->comment('商品描述');
            $table->decimal('points_price', 20, 8)->default(0)->comment('积分价格');
            $table->string('image', 500)->nullable()->comment('商品图片');
            $table->integer('stock')->default(0)->comment('库存数量');
            $table->tinyInteger('status')->default(1)->comment('状态：1-上架，0-下架');
            $table->integer('sort')->default(0)->comment('排序');
            $table->dateTime('created_at')->nullable()->comment('创建时间');
            $table->dateTime('updated_at')->nullable()->comment('更新时间');

            $table->index('status', 'idx_status');
            $table->index('sort', 'idx_sort');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_goods');
    }
}
