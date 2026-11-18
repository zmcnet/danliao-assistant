<?php
/**
 * 单聊辅助 v1.0 - 全局配置
 * PHP 8.5 + MySQL 5.7 + Nginx 1.31.1
 *
 * 说明：本文件为「本地配置」文件，包含密钥，已被 .gitignore 排除，不会提交到仓库。
 *       全新部署请复制 config.example.php 为 config.php 并填写。
 *       所有配置项均支持用环境变量覆盖（推荐用于生产环境）。
 */

declare(strict_types=1);

return [

    // ====== 数据库配置 (MySQL 5.7) ======
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => (int)(getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'danchat',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
        // PDO options
        'options' => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ],
    ],

    // ====== 应用配置 ======
    'app' => [
        'name'         => '单聊辅助',
        'version'      => '1.0',
        'secret_key'   => getenv('APP_SECRET_KEY') ?: 'CHANGE_ME_请在生产环境设置随机密钥',
        'session_name' => 'DANCHAT_SID',
        'session_ttl'  => 86400 * 7,  // 7天
        'upload_max_size' => 50 * 1024 * 1024, // 50MB
        'upload_dir'   => __DIR__ . '/../public/uploads',
        'export_dir'   => __DIR__ . '/../public/exports',
        // 模板渲染目录
        'template_dir' => __DIR__ . '/../templates',
        'debug'        => false,
    ],

    // ====== 密码盐 (兼容原Python版本) ======
    // 原版本使用 SHA256(password + salt)，本项目沿用同样算法以便数据互通
    'password_salt' => getenv('PASSWORD_SALT') ?: 'danyun_assistant_salt_2024'  // 保持不变以兼容旧数据,

    // ====== 默认AI配置 ======
    'default_ai_config' => [
        'api_url'       => getenv('AI_API_URL') ?: 'https://api.openai.com/v1',
        'api_key'       => getenv('AI_API_KEY') ?: '',
        'model'         => getenv('AI_MODEL') ?: 'gpt-3.5-turbo',
        'system_prompt' => '你是一位经验丰富的教育辅导老师，请根据学生数据进行分析并提供建议。',
        'temperature'   => 0.7,
        'max_tokens'    => 2000,
    ],

    // ====== 续班状态枚举 ======
    'continuation_status' => [
        'none'         => ['label' => '未续班',     'color' => ''],
        'continued'    => ['label' => '已续班',     'color' => '#0078D4'],
        'no_condition' => ['label' => '无条件续班', 'color' => '#D13438'],
    ],

    // ====== 话术默认分类 ======
    'default_script_categories' => ['开场白', '课程跟进', '续班沟通', '家长沟通', '学习反馈', '通用'],
];