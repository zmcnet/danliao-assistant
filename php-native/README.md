# 单聊辅助 v1.0 (PHP 8.5 + MySQL 5.7 + Nginx 1.31.1)

> 从 Python Flask 版本完整重写为 PHP 版本，所有功能 1:1 复刻。
> 原 Python 项目位于 `../单聊辅助/`。

## 0. 与原版差异速览

| 模块 | Python 版 | PHP 版 |
| --- | --- | --- |
| Web 框架 | Flask 3.0 + Jinja2 | PHP 8.5 原生 (自实现路由) |
| 数据库 | SQLite3 | MySQL 5.7+ |
| ORM | 原生 SQL + pandas | 原生 SQL + PDO |
| HTTP 客户端 | requests | cURL |
| Excel 读写 | openpyxl + pandas | PhpSpreadsheet |
| Session | Flask session (文件) | PHP Session |
| 密码算法 | `sha256(pwd + salt)` | `sha256(pwd + salt)` (兼容原数据) |
| 静态文件 | Flask static | Nginx 直接服务 |

> **数据兼容**：用户密码仍使用 `sha256(password + "danyun_assistant_salt_2024")` 算法，
> 与 Python 版本完全一致。如果原系统数据需要迁移，可直接导出 users 表导入。

## 1. 环境要求

| 软件 | 版本 | 说明 |
| --- | --- | --- |
| PHP | 8.4+ (推荐 8.5) | 需开启 pdo_mysql, curl, mbstring, zip, openssl, fileinfo 扩展 |
| MySQL | 5.7+ (推荐 5.7 或 8.0) | 字符集 utf8mb4 |
| Nginx | 1.31.1+ | 用于代理 PHP-FPM + 静态资源 |
| PHP-FPM | 与 PHP 同版本 | 监听 unix socket 或 9000 端口 |
| Composer | 2.x | 依赖管理 (安装 PhpSpreadsheet) |

### 必需的 PHP 扩展

```
pdo  pdo_mysql  json  mbstring  curl  zip  openssl  fileinfo
```

## 2. 部署步骤

### 2.1 克隆/上传项目

将本目录上传到服务器，例如 `/var/www/danchat` 或 `D:\PHP\单聊辅助_php`。

### 2.2 安装 Composer 依赖

```bash
cd /var/www/danchat        # 或 D:\PHP\单聊辅助_php
composer install --no-dev --optimize-autoloader
```

> 如果服务器没有 composer，可从 https://getcomposer.org/download/ 安装。

### 2.3 配置数据库

创建 MySQL 数据库：

```sql
CREATE DATABASE danchat DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'danchat'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON danchat.* TO 'danchat'@'localhost';
FLUSH PRIVILEGES;
```

修改 [config/config.php](config/config.php) 中的数据库配置（也可用环境变量覆盖）：

```php
'db' => [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'danchat',
    'username' => 'danchat',
    'password' => 'your_password',
    'charset'  => 'utf8mb4',
],
```

或者使用环境变量（推荐生产环境）：

```bash
export DB_HOST=127.0.0.1
export DB_NAME=danchat
export DB_USER=danchat
export DB_PASS=your_password
```

### 2.4 运行安装脚本

```bash
# 命令行方式 (推荐)
php install.php

# 或浏览器方式
http://your-host/install.php
```

安装脚本会：
- 检查 PHP 版本和扩展
- 创建目录并设置权限
- 测试数据库连接 (不存在则自动创建)
- 导入 `install.sql` 创建表结构
- 插入 AI 配置默认值

### 2.5 配置 Nginx

复制 [nginx.conf](nginx.conf) 到 Nginx 配置目录：

```bash
# 修改 nginx.conf 中的 root 路径
sudo nano nginx.conf
# 将 root D:/PHP/单聊辅助_php/public 改为实际路径
# 将 fastcgi_pass unix:/run/php/php8.5-fpm.sock 改为实际 socket 路径

sudo cp nginx.conf /etc/nginx/conf.d/danchat.conf
sudo nginx -t
sudo nginx -s reload
```

参考 [php.ini.example](php.ini.example) 配置 PHP：
```bash
sudo cp php.ini.example /etc/php/8.5/fpm/conf.d/99-danchat.ini
sudo systemctl restart php8.5-fpm
```

### 2.6 设置目录权限

```bash
sudo chown -R www-data:www-data /var/www/danchat
sudo chmod -R 755 /var/www/danchat
sudo chmod -R 775 /var/www/danchat/public/uploads
sudo chmod -R 775 /var/www/danchat/public/exports
```

### 2.7 启动并访问

```
http://your-host/  →  进入登录页
首次访问会引导注册管理员账号
```

## 3. 目录结构

```
单聊辅助_php/
├── public/                    # Web 根目录 (Nginx root 指向此)
│   ├── index.php              # 前端控制器 (路由入口)
│   ├── static/                # 静态资源 (Nginx 直接服务)
│   │   ├── css/
│   │   └── js/
│   ├── uploads/               # 用户上传的临时文件
│   └── exports/               # 导出文件临时目录
├── src/                       # 业务逻辑 (PSR-4: DanChat\)
│   ├── helpers.php            # 通用助手函数
│   ├── Database.php           # PDO 单例
│   ├── Auth.php               # 用户认证
│   ├── Student.php            # 学生管理
│   ├── ClassManager.php       # 班级管理
│   ├── Script.php             # 话术管理
│   ├── AiConfig.php           # AI 配置
│   ├── AiService.php          # AI API 调用
│   ├── Importer.php           # Excel/CSV 导入导出
│   ├── DataClean.php          # 数据标准化
│   └── App.php                # API 控制器
├── templates/                 # PHP 视图模板
│   ├── index.php              # 主页面
│   └── login.php              # 登录页
├── config/
│   └── config.php             # 全局配置
├── vendor/                    # Composer 依赖 (含 PhpSpreadsheet)
├── moban.xlsx                 # 导入模板 (与原版同源)
├── install.sql                # MySQL 建表脚本
├── install.php                # Web/CLI 安装脚本
├── nginx.conf                 # Nginx 配置
├── php.ini.example            # PHP 配置示例
├── composer.json              # 依赖清单
└── README.md                  # 本文件
```

## 4. API 端点速查

所有 API 路径与原 Python 版完全一致，前端 JS 不需任何修改。

### 认证

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| POST | `/api/auth/register` | 注册 (首注册自动管理员) |
| POST | `/api/auth/login` | 登录 |
| GET  | `/api/auth/check` | 检查登录状态 |
| GET  | `/api/auth/check_first` | 是否需要首次注册引导 |
| POST | `/api/auth/change_password` | 修改密码 |

### 用户管理 (admin)

| 方法 | 路径 |
| --- | --- |
| GET    | `/api/users` |
| POST   | `/api/users` |
| PUT    | `/api/users/{id}/role` |
| DELETE | `/api/users/{id}` |

### 学生

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| GET    | `/api/students?grade=&keyword=&sort_by=&sort_order=&class_id=` | 列表/搜索 |
| GET    | `/api/students/{id}` | 详情 |
| PUT    | `/api/students/{id}` | 更新 |
| DELETE | `/api/students/{id}` | 删除 |
| GET    | `/api/students/stats` | 统计数据 |
| POST   | `/api/students/import` | 导入 (multipart) |
| POST   | `/api/students/import/preview` | 导入预览 |
| POST   | `/api/students/export` | 导出 (json body {grade, format}) |

### 话术

| 方法 | 路径 |
| --- | --- |
| GET    | `/api/scripts?category=` |
| GET    | `/api/scripts/categories` |
| POST   | `/api/scripts` |
| PUT    | `/api/scripts/{id}` |
| DELETE | `/api/scripts/{id}` |
| POST   | `/api/scripts/{id}/move` |

### 班级

| 方法 | 路径 |
| --- | --- |
| GET    | `/api/classes` |
| POST   | `/api/classes` |
| PUT    | `/api/classes/{id}` |
| DELETE | `/api/classes/{id}` |

### AI

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| GET  | `/api/ai/config` | 获取配置 (含脱敏 api_key_masked) |
| POST | `/api/ai/config` | 保存配置 |
| POST | `/api/ai/test`   | 测试连接 |
| POST | `/api/ai/analyze`        | 学生学情分析 (body: student_id) |
| POST | `/api/ai/chat_suggestion` | 沟通建议 (body: student_id) |
| POST | `/api/ai/analyze_stats`   | 班级统计 AI 分析 |
| POST | `/api/ai/chat`            | 自由对话 (body: messages) |

## 5. 功能对照表

| 功能 | 原 Python 版 | 本 PHP 版 | 状态 |
| --- | --- | --- | --- |
| 登录/注册/首次引导 | ✅ | ✅ | 完成 |
| 多用户 + 角色 (admin/teacher/assistant) | ✅ | ✅ | 完成 |
| 修改密码 | ✅ | ✅ | 完成 |
| 用户管理 (CRUD + 角色变更) | ✅ | ✅ | 完成 |
| 班级管理 (CRUD + 课次配置) | ✅ | ✅ | 完成 |
| 学生 CRUD + 续班状态 | ✅ | ✅ | 完成 |
| 学员分析模式 (70% 列表 + 30% AI) | ✅ | ✅ | 完成 |
| 单聊模式 (三栏 50:30:20) | ✅ | ✅ | 完成 |
| 设置面板 (8 个子页签) | ✅ | ✅ | 完成 |
| 作业提交管理弹窗 | ✅ | ✅ | 完成 |
| Excel 导入 (xlsx/csv) | ✅ | ✅ | 完成 |
| Excel/CSV 导出 | ✅ | ✅ | 完成 |
| 话术管理 (CRUD + 上下移动) | ✅ | ✅ | 完成 |
| 话术分类 + 复制 | ✅ | ✅ | 完成 |
| AI 配置 (api_url/key/model/system_prompt) | ✅ | ✅ | 完成 |
| AI 测试连接 | ✅ | ✅ | 完成 |
| AI 单生学情分析 | ✅ | ✅ | 完成 |
| AI 沟通建议 | ✅ | ✅ | 完成 |
| AI 班级统计 | ✅ | ✅ | 完成 |
| AI 自由对话 (学员分析/单聊/设置) | ✅ | ✅ | 完成 |
| AI 流式响应 (stream) | ✅ | ⚠️ | 非流式 (可扩展) |
| 数据整理 (data_clean.py) | ✅ | ✅ | 完成 |
| 移动端响应式 | ✅ | ✅ | 完成 (CSS 完整保留) |
| 备注自动保存 | ✅ | ✅ | 完成 |
| 启动横幅 (本机 IP) | ✅ | ⚠️ | 仅命令行模式 |

## 6. 性能调优建议

```ini
# php.ini
memory_limit = 256M
opcache.jit = tracing
opcache.jit_buffer_size = 128M
```

```nginx
# nginx.conf
gzip on;
fastcgi_buffer_size 32k;
fastcgi_buffers 16 16k;
```

## 7. 常见问题

### Q1: 访问首页提示"数据库连接失败"
A: 检查 [config/config.php](config/config.php) 中 `db` 配置。`host`/`port`/`username`/`password`/`database` 是否正确。

### Q2: 上传 xlsx 文件报错
A: 确认 PHP 扩展 `zip` 和 `fileinfo` 已安装，并已运行 `composer install`。

### Q3: 静态资源 404
A: 检查 Nginx 配置中 `root` 指向 `public/`，且 `location /static/` 已配置 `try_files` 或 alias。

### Q4: 启用 HTTPS
A: 参考 [nginx.conf](nginx.conf) 末尾示例，使用 Let's Encrypt：
```bash
sudo certbot --nginx -d your-domain.com
```

### Q5: 从原 Python 数据库迁移用户
A:
```sql
-- 导出 Python SQLite 的 users 表
.mode csv
.output users.csv
SELECT id, username, password_hash, display_name, role, created_at FROM users;

-- 在 MySQL 中导入 (注意: password_hash 算法相同, 可直接使用)
LOAD DATA INFILE '/tmp/users.csv' INTO TABLE users
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n' (id, username, password_hash, display_name, role, created_at);
```

## 8. 升级到 PHP 8.5+ 特性

本项目已使用以下现代 PHP 特性：
- `readonly` 类
- 构造函数属性提升
- 命名参数
- `match` 表达式
- 类型联合 (`int|string`)
- 严格类型 (`declare(strict_types=1)`)
- 空安全操作符 (`?->`)
- `str_contains()` / `str_starts_with()` 等新函数

## 9. 许可

原项目 © 单聊辅助团队。PHP 重写版遵循相同使用范围。
