<?php
/**
 * 单聊辅助 - 前端控制器
 * 1:1 复刻 Python: app.py 路由
 *
 * 部署方式：所有非静态资源请求都路由到本文件
 * Nginx 配置示例: try_files $uri $uri/ /index.php?$query_string;
 */

declare(strict_types=1);

// 显示错误（部署时可关闭）
ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use DanChat\App;
use DanChat\Database;

// 启动 Session
$sessionName = config('app.session_name');
session_name($sessionName);
session_set_cookie_params([
    'lifetime' => config('app.session_ttl'),
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// 初始化数据库
try {
    Database::getInstance();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error'   => '数据库连接失败',
        'message' => $e->getMessage(),
        'tip'     => '请先运行安装脚本 /install.php 或检查 config/config.php 中的数据库配置',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 解析请求路径
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$uri    = '/' . trim($uri, '/');

// 静态资源直接交给 Nginx (本文件不应处理 static 资源)
if (str_starts_with($uri, '/static/')) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$app = new App();

try {
    route($method, $uri, $app);
} catch (\Throwable $e) {
    if (config('app.debug')) {
        json_response([
            'error' => 'Server Error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
    json_response(['error' => 'Server Error: ' . $e->getMessage()], 500);
}

function route(string $method, string $uri, App $app): void
{
    // 页面路由
    if ($uri === '/' || $uri === '/index.php') {
        if ($method === 'GET') { $app->pageIndex(); return; }
    }
    if ($uri === '/login') {
        if ($method === 'GET') { $app->pageLogin(); return; }
    }
    if ($uri === '/logout') {
        $app->pageLogout();
        return;
    }

    // API: 认证
    if ($uri === '/api/auth/register' && $method === 'POST') { $app->apiRegister(); return; }
    if ($uri === '/api/auth/login'    && $method === 'POST') { $app->apiLogin();    return; }
    if ($uri === '/api/auth/check'    && $method === 'GET')  { $app->apiCheckAuth(); return; }
    if ($uri === '/api/auth/check_first' && $method === 'GET') { $app->apiCheckFirst(); return; }
    if ($uri === '/api/auth/change_password' && $method === 'POST') { $app->apiChangePassword(); return; }

    // API: 用户
    if ($uri === '/api/users' && $method === 'GET')  { $app->apiGetUsers(); return; }
    if ($uri === '/api/users' && $method === 'POST') { $app->apiAddUser();  return; }
    if (preg_match('#^/api/users/(\d+)/role$#', $uri, $m) && $method === 'PUT') {
        $app->apiUpdateUserRole((int)$m[1]); return;
    }
    if (preg_match('#^/api/users/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $app->apiDeleteUser((int)$m[1]); return;
    }

    // API: 学生
    if ($uri === '/api/students' && $method === 'GET')  { $app->apiGetStudents();  return; }
    if ($uri === '/api/students/stats' && $method === 'GET') { $app->apiStudentStats(); return; }
    if ($uri === '/api/students/import' && $method === 'POST') { $app->apiImportStudents(); return; }
    if ($uri === '/api/students/import/preview' && $method === 'POST') { $app->apiPreviewImport(); return; }
    if ($uri === '/api/students/export' && $method === 'POST') { $app->apiExportStudents(); return; }
    if (preg_match('#^/api/students/(\d+)$#', $uri, $m)) {
        if ($method === 'GET')    { $app->apiGetStudent((int)$m[1]);    return; }
        if ($method === 'PUT')    { $app->apiUpdateStudent((int)$m[1]); return; }
        if ($method === 'DELETE') { $app->apiDeleteStudent((int)$m[1]); return; }
    }

    // API: 话术
    if ($uri === '/api/scripts' && $method === 'GET')  { $app->apiGetScripts();  return; }
    if ($uri === '/api/scripts' && $method === 'POST') { $app->apiAddScript();   return; }
    if ($uri === '/api/scripts/categories' && $method === 'GET') { $app->apiGetScriptCategories(); return; }
    if (preg_match('#^/api/scripts/(\d+)/move$#', $uri, $m) && $method === 'POST') {
        $app->apiMoveScript((int)$m[1]); return;
    }
    if (preg_match('#^/api/scripts/(\d+)$#', $uri, $m)) {
        if ($method === 'PUT')    { $app->apiUpdateScript((int)$m[1]); return; }
        if ($method === 'DELETE') { $app->apiDeleteScript((int)$m[1]); return; }
    }

    // API: 班级
    if ($uri === '/api/classes' && $method === 'GET')  { $app->apiGetClasses(); return; }
    if ($uri === '/api/classes' && $method === 'POST') { $app->apiAddClass();   return; }
    if (preg_match('#^/api/classes/(\d+)$#', $uri, $m)) {
        if ($method === 'PUT')    { $app->apiUpdateClass((int)$m[1]); return; }
        if ($method === 'DELETE') { $app->apiDeleteClass((int)$m[1]); return; }
    }

    // API: AI
    if ($uri === '/api/ai/config' && $method === 'GET')  { $app->apiGetAiConfig(); return; }
    if ($uri === '/api/ai/config' && $method === 'POST') { $app->apiSaveAiConfig(); return; }
    if ($uri === '/api/ai/test' && $method === 'POST')   { $app->apiTestAi();      return; }
    if ($uri === '/api/ai/analyze' && $method === 'POST'){ $app->apiAnalyzeStudent(); return; }
    if ($uri === '/api/ai/chat_suggestion' && $method === 'POST') { $app->apiChatSuggestion(); return; }
    if ($uri === '/api/ai/analyze_stats' && $method === 'POST') { $app->apiAnalyzeStats(); return; }
    if ($uri === '/api/ai/chat' && $method === 'POST') { $app->apiAiChat(); return; }

    // 健康检查
    if ($uri === '/health' && $method === 'GET') {
        json_response(['status' => 'ok', 'time' => now()]);
    }

    // 未匹配
    http_response_code(404);
    if (str_starts_with($uri, '/api/')) {
        json_response(['error' => 'Not Found', 'path' => $uri], 404);
    }
    echo '404 Not Found';
}
