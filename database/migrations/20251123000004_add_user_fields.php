<?php
/**
 * Hyperf Migration: 添加users表字段
 * 生成时间: 2025-11-23
 */

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;

class AddUserFields extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 检查字段是否存在，不存在则添加
            if (!Schema::hasColumn('users', 'team_reward_level')) {
                $table->integer('team_reward_level')->default(0)->after('vip_level')->comment('团队奖励级别（0-9）');
            }

            if (!Schema::hasColumn('users', 'real_name')) {
                $table->string('real_name', 50)->nullable()->after('username')->comment('真实姓名');
            }

            if (!Schema::hasColumn('users', 'id_card')) {
                $table->string('id_card', 18)->nullable()->after('real_name')->comment('身份证号');
            }

            if (!Schema::hasColumn('users', 'face_photo')) {
                $table->text('face_photo')->nullable()->after('id_card')->comment('人脸照片（base64或文件路径）');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'team_reward_level')) {
                $table->dropColumn('team_reward_level');
            }

            if (Schema::hasColumn('users', 'real_name')) {
                $table->dropColumn('real_name');
            }

            if (Schema::hasColumn('users', 'id_card')) {
                $table->dropColumn('id_card');
            }

            if (Schema::hasColumn('users', 'face_photo')) {
                $table->dropColumn('face_photo');
            }
        });
    }
}
