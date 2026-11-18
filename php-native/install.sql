-- ==========================================================
-- 单聊辅助 v1.0 (PHP 8.5 + MySQL 5.7)
-- 数据库结构脚本
-- 字符集: utf8mb4 / 排序: utf8mb4_unicode_ci
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 用户表
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(128) NOT NULL,
  `display_name` VARCHAR(128) NOT NULL DEFAULT '',
  `role` VARCHAR(32) NOT NULL DEFAULT 'teacher',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ----------------------------
-- 班级表
-- ----------------------------
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `class_number` VARCHAR(64) NOT NULL DEFAULT '',
  `class_type` VARCHAR(16) NOT NULL DEFAULT 'A1',
  `lesson_count` INT NOT NULL DEFAULT 5,
  `grade` VARCHAR(32) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_classes_grade` (`grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='班级表';

-- ----------------------------
-- 学生表
-- ----------------------------
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL,
  `middle_school` VARCHAR(255) NOT NULL DEFAULT '',
  `high_school` VARCHAR(255) NOT NULL DEFAULT '',
  `zhongkao_score` VARCHAR(64) NOT NULL DEFAULT '',
  `science_score` VARCHAR(64) NOT NULL DEFAULT '',
  `middle_school_type` VARCHAR(64) NOT NULL DEFAULT '',
  `subject_preference` VARCHAR(64) NOT NULL DEFAULT '',
  `wrong_questions` VARCHAR(128) NOT NULL DEFAULT '',
  `l1_status` VARCHAR(64) NOT NULL DEFAULT '',
  `feedback` TEXT,
  `l2_status` VARCHAR(64) NOT NULL DEFAULT '',
  `l3_status` VARCHAR(64) NOT NULL DEFAULT '',
  `l4_status` VARCHAR(64) NOT NULL DEFAULT '',
  `l5_status` VARCHAR(64) NOT NULL DEFAULT '',
  `continuation_intent` VARCHAR(255) NOT NULL DEFAULT '',
  `continuation_status` VARCHAR(32) NOT NULL DEFAULT 'none',
  `grade` VARCHAR(32) NOT NULL DEFAULT '',
  `note` MEDIUMTEXT,
  `class_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_students_grade` (`grade`),
  KEY `idx_students_class_id` (`class_id`),
  KEY `idx_students_continuation` (`continuation_status`),
  KEY `idx_students_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='学生表';

-- ----------------------------
-- 话术表
-- ----------------------------
DROP TABLE IF EXISTS `scripts`;
CREATE TABLE `scripts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `content` MEDIUMTEXT NOT NULL,
  `category` VARCHAR(64) NOT NULL DEFAULT '通用',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scripts_category` (`category`),
  KEY `idx_scripts_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='话术表';

-- ----------------------------
-- AI配置表 (单行配置)
-- ----------------------------
DROP TABLE IF EXISTS `ai_config`;
CREATE TABLE `ai_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `api_url` VARCHAR(512) NOT NULL DEFAULT 'https://api.openai.com/v1',
  `api_key` VARCHAR(512) NOT NULL DEFAULT '',
  `model` VARCHAR(128) NOT NULL DEFAULT 'gpt-3.5-turbo',
  `system_prompt` TEXT,
  `temperature` DECIMAL(3,2) NOT NULL DEFAULT 0.70,
  `max_tokens` INT NOT NULL DEFAULT 2000,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI配置表';

-- ----------------------------
-- 初始化 AI 配置 (单行)
-- ----------------------------
INSERT INTO `ai_config` (`id`, `api_url`, `api_key`, `model`, `system_prompt`, `temperature`, `max_tokens`)
VALUES (1, 'https://api.openai.com/v1', '', 'gpt-3.5-turbo',
        '你是一位经验丰富的教育辅导老师，请根据学生数据进行分析并提供建议。',
        0.70, 2000);

SET FOREIGN_KEY_CHECKS = 1;
