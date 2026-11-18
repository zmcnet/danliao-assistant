#!/usr/bin/env bash
# 单聊辅助 v1.0 - Linux/macOS 启动脚本
set -e

cd "$(dirname "$0")"

echo "============================================"
echo " 单聊辅助 v1.0 - PHP版本 启动脚本"
echo " PHP 8.5 + MySQL 5.7 + Nginx 1.31.1"
echo "============================================"
echo

# ---- 1. 检查 PHP ----
if ! command -v php >/dev/null 2>&1; then
    echo "[错误] 未找到 PHP，请先安装 PHP 8.4+"
    exit 1
fi
echo "[1/4] PHP:"
php --version | head -n 1
echo

# ---- 2. 检查 Composer ----
if ! command -v composer >/dev/null 2>&1; then
    echo "[警告] 未找到 Composer"
    echo "       安装: https://getcomposer.org/download/"
    echo
else
    echo "[2/4] Composer:"
    composer --version | head -n 1
    echo
    if [ ! -d vendor ]; then
        echo "       正在安装依赖..."
        composer install --no-dev --optimize-autoloader
    else
        echo "       vendor/ 已存在"
    fi
    echo
fi

# ---- 3. 检查 MySQL ----
if ! command -v mysql >/dev/null 2>&1; then
    echo "[警告] 未找到 MySQL 命令行工具"
    echo
else
    echo "[3/4] MySQL:"
    mysql --version
    echo
fi

# ---- 4. 检查 Nginx ----
if ! command -v nginx >/dev/null 2>&1; then
    echo "[警告] 未找到 nginx"
    echo
else
    echo "[4/4] Nginx:"
    nginx -v 2>&1
    echo
fi

# ---- 检查配置 ----
if [ ! -f config/config.php ]; then
    echo "[错误] 缺少 config/config.php"
    exit 1
fi

# ---- 询问安装 ----
read -p "是否运行安装脚本? (y/N): " RUN_INSTALL
if [[ "$RUN_INSTALL" == "y" || "$RUN_INSTALL" == "Y" ]]; then
    echo
    php install.php
    echo
fi

echo "============================================"
echo " 启动信息"
echo "============================================"
echo
echo "项目已就绪，请配置 Nginx 后访问:"
echo
echo "  - 默认URL:  http://127.0.0.1/"
echo "  - 配置请编辑:  nginx.conf"
echo
echo "快速测试 (PHP 内置服务器):"
echo "  php -S 127.0.0.1:8000 -t public"
echo
echo "(生产环境请使用 Nginx + PHP-FPM)"
