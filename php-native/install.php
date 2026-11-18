<?php
/**
 * 单聊辅助 - 安装脚本
 *
 * 使用方法:
 *   浏览器访问:  http://your-domain/install.php
 *   或命令行:    php install.php
 *
 * 功能:
 *   1. 读取 install.sql 并创建/更新表结构
 *   2. 初始化 AI 配置默认值
 *   3. 验证目录权限
 *   4. 删除自身 (安装完成后建议)
 *
 * 安全:
 *   - 安装完成后请删除本文件
 *   - 生产环境建议通过命令行运行，避免暴露在Web
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$isCli = (php_sapi_name() === 'cli');

header('Content-Type: text/html; charset=utf-8');

function println(string $msg, string $color = 'black'): void
{
    global $isCli;
    if ($isCli) {
        $colors = [
            'red'    => "\033[31m",
            'green'  => "\033[32m",
            'yellow' => "\033[33m",
            'blue'   => "\033[34m",
            'cyan'   => "\033[36m",
            'gray'   => "\033[90m",
            'reset'  => "\033[0m",
        ];
        $c = $colors[$color] ?? '';
        echo $c . $msg . $colors['reset'] . PHP_EOL;
    } else {
        $colorMap = [
            'red'    => '#d13438',
            'green'  => '#107c10',
            'yellow' => '#ff8c00',
            'blue'   => '#0078d4',
            'cyan'   => '#1988e0',
            'gray'   => '#888',
        ];
        $c = $colorMap[$color] ?? '#333';
        echo "<div style='color:{$c};margin:4px 0'>{$msg}</div>";
    }
}

function println_h1(string $msg): void
{
    global $isCli;
    if ($isCli) {
        echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
        echo "  " . $msg . PHP_EOL;
        echo str_repeat('=', 60) . PHP_EOL;
    } else {
        echo "<h1 style='color:#0078d4;border-bottom:2px solid #0078d4;padding-bottom:8px'>{$msg}</h1>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>单聊辅助 - 安装</title>";
    echo "<style>body{font-family:'Microsoft YaHei',sans-serif;max-width:780px;margin:30px auto;padding:20px;background:#f5f5f5;line-height:1.7}";
    echo ".card{background:#fff;border-radius:8px;padding:20px;margin:10px 0;box-shadow:0 1px 3px rgba(0,0,0,.05)}";
    echo ".btn{display:inline-block;padding:10px 24px;background:#0078d4;color:#fff;border-radius:6px;text-decoration:none;cursor:pointer;border:none;font-size:14px}";
    echo ".btn:hover{background:#1988e0}</style></head><body>";
}

println_h1('单聊辅助 v1.0 安装程序');

// ====================================================
// 步骤 1: 检查 PHP 版本
// ====================================================
println('步骤 1/5: 检查 PHP 版本', 'blue');
$phpVer = PHP_VERSION;
$verOk = version_compare($phpVer, '8.4.0', '>=');
println("当前 PHP 版本: {$phpVer} " . ($verOk ? '✅' : '❌ (需要 8.4+)'), $verOk ? 'green' : 'red');
if (!$verOk) {
    println('PHP 版本过低，请升级到 8.4+', 'red');
    exit(1);
}

// ====================================================
// 步骤 2: 检查扩展
// ====================================================
println('', '');
println('步骤 2/5: 检查 PHP 扩展', 'blue');
$required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'zip', 'openssl', 'fileinfo'];
$allOk = true;
foreach ($required as $ext) {
    $loaded = extension_loaded($ext);
    if (!$loaded) $allOk = false;
    println("  - {$ext}: " . ($loaded ? '✅ 已安装' : '❌ 未安装'), $loaded ? 'green' : 'red');
}
if (!$allOk) {
    println('请安装缺失的 PHP 扩展后重试', 'red');
    exit(1);
}

// ====================================================
// 步骤 3: 检查目录权限
// ====================================================
println('', '');
println('步骤 3/5: 检查目录权限', 'blue');
$dirs = [
    'public/uploads'   => __DIR__ . '/public/uploads',
    'public/exports'   => __DIR__ . '/public/exports',
    'templates'        => __DIR__ . '/templates',
];
foreach ($dirs as $name => $path) {
    if (!is_dir($path)) mkdir($path, 0755, true);
    $writable = is_writable($path);
    println("  - {$name}: " . ($writable ? '✅ 可写' : '❌ 不可写'), $writable ? 'green' : 'red');
}

// ====================================================
// 步骤 4: 读取配置并测试数据库连接
// ====================================================
println('', '');
println('步骤 4/5: 测试数据库连接', 'blue');
$config = require __DIR__ . '/config/config.php';
$dbCfg  = $config['db'];

println("  - Host:     {$dbCfg['host']}:{$dbCfg['port']}");
println("  - Database: {$dbCfg['database']}");
println("  - User:     {$dbCfg['username']}");

try {
    // 先尝试无数据库名连接 (用于创建数据库)
    $dsn0 = "mysql:host={$dbCfg['host']};port={$dbCfg['port']};charset=utf8mb4";
    $pdo0 = new PDO($dsn0, $dbCfg['username'], $dbCfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $exists = (int)$pdo0->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = " . $pdo0->quote($dbCfg['database']))->fetchColumn();
    if (!$exists) {
        println("  - 数据库 {$dbCfg['database']} 不存在，正在创建...", 'yellow');
        $pdo0->exec("CREATE DATABASE `" . str_replace('`', '``', $dbCfg['database']) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        println("  ✅ 已创建数据库 {$dbCfg['database']}", 'green');
    } else {
        println("  ✅ 数据库 {$dbCfg['database']} 已存在", 'green');
    }
    $pdo0 = null;

    // 重新连接测试
    $pdo = new PDO(
        "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['database']};charset=utf8mb4",
        $dbCfg['username'],
        $dbCfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    println('  ✅ 数据库连接成功', 'green');
} catch (PDOException $e) {
    println('  ❌ 数据库连接失败: ' . $e->getMessage(), 'red');
    println('  请修改 config/config.php 中的数据库配置后重试', 'yellow');
    exit(1);
}

// ====================================================
// 步骤 5: 执行 SQL 脚本
// ====================================================
println('', '');
println('步骤 5/5: 导入数据库结构', 'blue');
$sqlFile = __DIR__ . '/install.sql';
if (!is_file($sqlFile)) {
    println("  ❌ 找不到 install.sql", 'red');
    exit(1);
}

$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
$ok = 0; $fail = 0;
foreach ($statements as $stmt) {
    if ($stmt === '' || str_starts_with($stmt, '--')) continue;
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (PDOException $e) {
        $fail++;
        $firstLine = strtok($stmt, "\n");
        println("  ⚠️ 失败: " . $firstLine, 'red');
        println("    " . $e->getMessage(), 'gray');
    }
}
println("  ✅ 执行 {$ok} 条 SQL，" . ($fail > 0 ? "❌ 失败 {$fail} 条" : "全部成功"), $fail > 0 ? 'yellow' : 'green');

// ====================================================
// 验证
// ====================================================
println('', '');
println('验证安装结果:', 'blue');
$tables = ['users', 'students', 'classes', 'scripts', 'ai_config'];
foreach ($tables as $t) {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    println("  - 表 {$t}: " . ($cnt >= 0 ? "✅ 存在 ({$cnt} 条记录)" : "❌ 不存在"), 'green');
}

// ====================================================
// 完成
// ====================================================
println('', '');
println_h1('✅ 安装完成！');
println('', '');
println('接下来请:', 'cyan');
println('  1. 浏览器访问首页:  http://your-host/');
println('  2. 首次访问会引导注册管理员账号');
println('  3. 在「设置 -> AI设置」中配置你的 AI API');
println('  4. 在「设置 -> 班级管理」中创建班级');
println('  5. 在「设置 -> 导入导出」中上传 moban.xlsx 导入学生数据');
println('', '');
println('⚠️ 安全提示: 安装完成后请删除 install.php', 'yellow');

if (!$isCli) {
    echo "<div class='card'>";
    echo "<a class='btn' href='/'>🚀 进入应用</a> ";
    echo "<a class='btn' style='background:#d13438' href='?delete=1'>🗑️ 删除安装脚本</a>";
    echo "</div>";
    echo "</body></html>";

    if (isset($_GET['delete']) && $_GET['delete'] === '1') {
        @unlink(__FILE__);
        echo "<script>alert('安装脚本已删除');location.href='/'</script>";
    }
}
