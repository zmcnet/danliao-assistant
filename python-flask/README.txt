============================================
  单聊辅助 v1.0
  学生信息管理 & AI辅助系统
============================================

【功能简介】
- 学员分析：学生信息管理、AI学情分析、数据统计
- 单聊模式：学生搜索、信息编辑、话术一键复制、AI沟通建议
- 设置：AI接口配置、数据导入导出、话术管理、账户管理

【运行环境】
- Python 3.8+
- 依赖安装：pip install -r requirements.txt

【启动方式】
方法一：直接运行
  python app.py
  浏览器打开 http://127.0.0.1:5000

方法二：打包为exe
  pip install pyinstaller
  pyinstaller --onefile --windowed --add-data "templates;templates" --add-data "static;static" --name "单聊辅助" app.py

方法三：安装包
  使用 Inno Setup 编译 build/setup.iss

【首次使用】
1. 启动程序
2. 浏览器打开 http://127.0.0.1:5000
3. 注册管理员账号
4. 在"设置"中配置AI API
5. 导入学生数据（支持 moban.xlsx 格式）

【数据文件】
- students.db：SQLite数据库（自动生成）
- moban.xlsx：导入模板

【注意事项】
- AI功能需要配置有效的API密钥
- 支持任何兼容OpenAI API格式的服务
- 数据存储在本地，安全可靠

【联系方式】
- 如有问题请联系技术支持