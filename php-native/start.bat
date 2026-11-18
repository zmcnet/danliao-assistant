@echo off
chcp 65001 >nul
echo ============================================
echo  单聊辅助 v1.0 - PHP版本 启动脚本
echo  PHP 8.5 + MySQL 5.7 + Nginx 1.31.1
echo ============================================
echo.

:: ---- 1. 检查 PHP ----
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 未找到 PHP，请先安装 PHP 8.4+
    pause
    exit /b
)
echo [1/4] PHP:
php --version | findstr /C:"PHP"
echo.

:: ---- 2. 检查 Composer ----
where composer >nul 2>&1
if %errorlevel% neq 0 (
    echo [警告] 未找到 Composer，请先安装 Composer
    echo        下载: https://getcomposer.org/Composer-Setup.exe
    echo.
) else (
    echo [2/4] Composer:
    composer --version | findstr /C:"Composer"
    echo.
    if not exist vendor (
        echo       正在安装依赖...
        composer install --no-dev --optimize-autoloader
    ) else (
        echo       vendor/ 已存在，跳过安装
    )
)
echo.

:: ---- 3. 检查 MySQL ----
where mysql >nul 2>&1
if %errorlevel% neq 0 (
    echo [警告] 未找到 MySQL 命令行工具
    echo        请确保 MySQL 5.7+ 已运行并已创建数据库
    echo.
) else (
    echo [3/4] MySQL:
    mysql --version
    echo.
)

:: ---- 4. 检查 Nginx ----
where nginx >nul 2>&1
if %errorlevel% neq 0 (
    echo [警告] 未找到 nginx，请先安装并启动 Nginx 1.31.1+
    echo.
) else (
    echo [4/4] Nginx:
    nginx -v 2>&1
    echo.
)

:: ---- 检查是否需要安装 ----
echo ============================================
echo  启动前检查
echo ============================================
echo.
echo 项目目录: %CD%
echo.

if not exist config\config.php (
    echo [错误] 缺少 config\config.php
    pause
    exit /b
)

:: 询问是否运行安装脚本
set /p RUN_INSTALL="是否运行安装脚本? (y/N): "
if /i "%RUN_INSTALL%"=="y" (
    echo.
    php install.php
    echo.
)

:: ---- 显示访问信息 ----
echo ============================================
echo  启动信息
echo ============================================
echo.
echo 项目已就绪，请配置 Nginx 后访问:
echo.
echo   - 默认URL:  http://127.0.0.1/
echo   - 配置请编辑:  nginx.conf
echo.
echo 如果尚未配置 Nginx，可使用 PHP 内置服务器快速测试:
echo   php -S 127.0.0.1:8000 -t public
echo.
echo (注意: PHP 内置服务器不支持 .xlsx 等大文件上传, 生产请用 Nginx)
echo.
pause
