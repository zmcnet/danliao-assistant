@echo off
chcp 65001 >nul
echo ============================================
echo  单聊辅助 - 快速启动脚本
echo ============================================
echo.

:: 检查Python
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 未找到Python，请先安装Python 3.8+
    pause
    exit /b
)

:: 安装依赖
echo [1/3] 检查依赖...
pip install -r requirements.txt -q

:: 复制模板
echo [2/3] 检查模板文件...
if not exist moban.xlsx (
    echo [提示] 未找到moban.xlsx模板文件，请确保文件存在
)

:: 启动
echo [3/3] 启动应用...
echo.
echo --------------------------------------------
echo  应用启动中...
echo  请打开浏览器访问: http://127.0.0.1:5000
echo --------------------------------------------
echo.
python app.py

pause