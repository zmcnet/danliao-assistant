# 单聊辅助 v1.0 · 学员信息管理 & AI 辅助系统

学员信息管理系统，配合 AI 学情分析与话术建议，帮助教务/辅导老师高效完成
学员跟进与续班沟通。

本仓库包含**两个功能完全一致的实现版本**：

| 目录 | 技术栈 | 说明 |
| ---- | ---- | ---- |
| [`python-flask/`](python-flask/) | Python 3.8+ · Flask 3 · SQLite | 原始版本 |
| [`php-native/`](php-native/) | PHP 8.4+ · MySQL 5.7 · Nginx | 由 Python 版 1:1 重写 |

---

## 功能简介

- **学员分析** — 学员信息管理、AI 学情分析、数据统计
- **单聊模式** — 学员搜索、信息编辑、话术一键复制、AI 沟通建议
- **设置** — AI 接口配置、数据导入导出、话术管理、账户管理

---

## 两版本差异速览

| 模块 | Python 版 | PHP 版 |
| ---- | --------- | ------ |
| Web 框架 | Flask 3.0 + Jinja2 | PHP 8.5 原生（自实现路由） |
| 数据库 | SQLite3 | MySQL 5.7+ |
| ORM | 原生 SQL + pandas | 原生 SQL + PDO |
| HTTP 客户端 | requests | cURL |
| Excel 读写 | openpyxl + pandas | PhpSpreadsheet |
| Session | Flask session（文件） | PHP Session |
| 密码算法 | `sha256(pwd + salt)` | `sha256(pwd + salt)`（**兼容原数据**） |

> **数据兼容**：两版用户密码均使用 `sha256(password + "danyun_assistant_salt_2024")`，
> 可直接导出 `users` 表在两版之间迁移。

---

## 版本一：Python / Flask

### 安装

```bash
cd python-flask
pip install -r requirements.txt
```

### 运行

```bash
python app.py
# 浏览器打开 http://127.0.0.1:5000
```

Windows 用户可直接运行 `start.bat`（自动检查环境、安装依赖、复制模板）。

### 打包为 exe

```bash
pip install pyinstaller
pyinstaller --onefile --windowed --add-data "templates;templates" --add-data "static;static" --name "单聊辅助" app.py
```

安装包使用 Inno Setup 编译 `build/setup.iss`。

### 配置 AI 接口

在系统「设置 → AI 接口配置」中填入：

| 项 | 说明 |
| -- | ---- |
| API URL | OpenAI 兼容接口地址，如 `https://api.openai.com/v1` |
| API Key | 你的密钥 |
| 模型 | 如 `gpt-3.5-turbo` |

---

## 版本二：PHP 原生

### 环境要求

| 组件 | 版本 |
| ---- | ---- |
| PHP | **8.4+**（需 `pdo_mysql` `json` `mbstring` `curl` `zip` `openssl` 扩展） |
| MySQL | **5.7+** |
| Nginx | 1.31+ |

### 安装

```bash
cd php-native
composer install
```

### 部署

```bash
# 1. 创建数据库
mysql -u root -p -e "CREATE DATABASE danchat DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p danchat < install.sql

# 2. 配置环境变量（config/config.php 默认全部走 getenv）
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=danchat
export DB_USER=root
export DB_PASS=你的密码

# 3. 指向 web 根目录
#    Nginx root 必须指向 public/ 目录
```

Nginx 片段见 [`php-native/nginx.conf`](php-native/nginx.conf)，PHP-FPM 配置见
[`php-native/php.ini.example`](php-native/php.ini.example)。

### 运行

```bash
# Linux / macOS
./start.sh

# Windows
start.bat
```

访问 `http://your-domain/`。

---

## 目录结构

```
danliao-assistant/
├── python-flask/
│   ├── app.py            # Flask 主入口
│   ├── ai_service.py     # AI 调用封装
│   ├── auth.py           # 认证
│   ├── config.py         # 全局配置
│   ├── data_clean.py     # 数据清洗
│   ├── database.py       # 数据访问
│   ├── requirements.txt
│   ├── students.db       # SQLite 数据库（gitignored）
│   ├── moban.xlsx        # 导入模板
│   ├── templates/        # Jinja2 模板
│   ├── static/           # 静态资源
│   └── build/            # PyInstaller / Inno Setup 打包
│
└── php-native/
    ├── composer.json
    ├── install.php / install.sql   # 安装向导与表结构
    ├── src/              # DanChat\ 命名空间核心类
    │   ├── App.php  Auth.php  Database.php
    │   ├── AiService.php  AiConfig.php
    │   ├── Student.php  ClassManager.php
    │   ├── Script.php  Importer.php  DataClean.php
    │   └── helpers.php
    ├── public/           # ★ Nginx 网站根目录
    ├── templates/
    ├── config/config.php # 全部配置项走 getenv()
    ├── nginx.conf
    └── start.bat / start.sh
```

---

## ⚠️ 数据安全

本系统处理**真实学员个人信息**（姓名、联系方式、学情记录等）。

`.gitignore` 已排除 `*.db`、`students.db` 等运行期数据文件，
**提交前请务必确认 `python-flask/students.db` 未被纳入版本库**。

如需分发演示数据，请先生成一份脱敏样本（如 `students.sample.db`）再提交。

---

## 许可

本项目源码采用 [MIT License](LICENSE) — © 2026 ZMCNET。

> ⚠️ **数据责任自负**：本系统用于处理学员个人信息。使用者须自行确保
> 数据采集合法合规、存储安全，并遵守所在地的个人信息保护法规。