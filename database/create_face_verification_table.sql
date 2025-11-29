-- ============================================
-- 创建人脸识别记录表
-- ============================================

CREATE TABLE IF NOT EXISTS `face_verification` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `type` varchar(20) NOT NULL DEFAULT 'kyc' COMMENT '类型：kyc-实名认证, password_reset-找回密码',
  `face_image` text COMMENT '人脸照片（base64或文件路径）',
  `similarity` decimal(5,4) DEFAULT '0.0000' COMMENT '相似度（0-1）',
  `liveness_score` decimal(5,4) DEFAULT '0.0000' COMMENT '活体检测分数（0-1）',
  `result` tinyint(1) DEFAULT 0 COMMENT '结果：0-待审核，1-通过，2-失败',
  `provider` varchar(20) DEFAULT 'mock' COMMENT '识别提供商：tencent-腾讯云, aliyun-阿里云, baidu-百度AI, mock-模拟',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_result` (`result`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='人脸识别记录表';
