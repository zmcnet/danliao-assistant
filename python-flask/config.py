"""全局配置 - 单聊辅助"""

import os

# 应用基础路径
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# 数据库
DB_PATH = os.path.join(BASE_DIR, 'students.db')

# 模板文件
MOBAN_PATH = os.path.join(BASE_DIR, 'moban.xlsx')

# Flask配置
SECRET_KEY = 'danyun_assistant_secret_key_2024'
SESSION_TYPE = 'filesystem'

# 默认AI配置
DEFAULT_AI_CONFIG = {
    'api_url': 'https://api.openai.com/v1',
    'api_key': '',
    'model': 'gpt-3.5-turbo',
    'system_prompt': '你是一位经验丰富的教育辅导老师，请根据学生数据进行分析并提供建议。',
    'temperature': 0.7,
    'max_tokens': 2000
}

# 续班状态选项
CONTINUATION_STATUS = {
    'none': {'label': '未续班', 'color': ''},
    'continued': {'label': '已续班', 'color': '#0078D4'},
    'no_condition': {'label': '无条件续班', 'color': '#D13438'}
}

# 话术默认分类
DEFAULT_SCRIPT_CATEGORIES = ['开场白', '课程跟进', '续班沟通', '家长沟通', '学习反馈', '通用']