<?php
/**
 * Hyperf Migration: 创建USDT汇率表
 * 生成时间: 2025-11-23
 */

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;

class CreateUsdtRateTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usdt_rate', function (Blueprint $table) {
            $table->increments('id')->comment('ID');
            $table->decimal('price', 20, 8)->comment('汇率（1 USDT = X CNY）');
            $table->string('source', 50)->default('manual')->comment('来源：manual-手动，api-API接口');
            $table->dateTime('created_at')->nullable()->comment('创建时间');

            $table->index('created_at', 'idx_created_at');
        });

        // 插入初始汇率
        \Hyperf\DbConnection\Db::table('usdt_rate')->insert([
            'price' => 7.2,
            'source' => 'manual',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usdt_rate');
    }
}
