<?php
/**
 * 单聊辅助 - 通用助手函数
 */

declare(strict_types=1);

if (!function_exists('config')) {
    /**
     * 读取配置项 (支持 . 路径)
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/../config/config.php';
        }
        $parts = explode('.', $key);
        $val = $config;
        foreach ($parts as $p) {
            if (is_array($val) && array_key_exists($p, $val)) {
                $val = $val[$p];
            } else {
                return $default;
            }
        }
        return $val;
    }
}

if (!function_exists('json_response')) {
    /**
     * 输出 JSON 响应
     */
    function json_response(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        // 防止浏览器缓存API响应
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('json_input')) {
    /**
     * 解析 JSON 请求体
     */
    function json_input(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('h')) {
    /**
     * HTML 转义
     */
    function h(?string $str): string
    {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('render')) {
    /**
     * 渲染 PHP 模板
     */
    function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $tplFile = config('app.template_dir') . '/' . $template . '.php';
        if (!is_file($tplFile)) {
            http_response_code(404);
            echo "Template not found: " . h($template);
            return;
        }
        require $tplFile;
    }
}

if (!function_exists('now')) {
    /**
     * 当前时间字符串
     */
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('client_ip')) {
    /**
     * 获取客户端IP (Nginx反向代理场景)
     */
    function client_ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', $_SERVER[$k])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}

if (!function_exists('local_ip')) {
    /**
     * 获取本机局域网IP
     */
    function local_ip(): string
    {
        try {
            $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            socket_connect($sock, '8.8.8.8', 80);
            socket_getsockname($sock, $addr);
            socket_close($sock);
            return $addr ?: '127.0.0.1';
        } catch (\Throwable $e) {
            return '127.0.0.1';
        }
    }
}
