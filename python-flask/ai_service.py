"""
AI服务模块 - 单聊辅助
封装OpenAI兼容API调用，支持学生分析、沟通建议等功能
"""

import requests
import json


def call_ai_api(api_url, api_key, model, messages, temperature=0.7, max_tokens=2000, stream=False):
    """
    调用AI API (兼容OpenAI格式)
    返回: 响应文本或流式响应
    """
    # 确保url正确
    url = api_url.rstrip('/')
    if not url.endswith('/chat/completions'):
        if not url.endswith('/v1'):
            url += '/v1'
        url += '/chat/completions'

    headers = {
        'Content-Type': 'application/json',
        'Authorization': f'Bearer {api_key}'
    }

    data = {
        'model': model,
        'messages': messages,
        'temperature': temperature,
        'max_tokens': max_tokens,
        'stream': stream
    }

    try:
        response = requests.post(
            url, headers=headers, json=data,
            stream=stream, timeout=60
        )

        if response.status_code != 200:
            error_msg = f'API错误 ({response.status_code}): '
            try:
                error_detail = response.json()
                error_msg += error_detail.get('error', {}).get('message', str(response.text))
            except:
                error_msg += str(response.text)
            return {'success': False, 'error': error_msg}

        if stream:
            return {'success': True, 'stream': response.iter_lines()}

        result = response.json()
        content = result['choices'][0]['message']['content']
        return {'success': True, 'content': content}

    except requests.exceptions.Timeout:
        return {'success': False, 'error': '请求超时，请检查网络连接'}
    except requests.exceptions.ConnectionError:
        return {'success': False, 'error': '连接失败，请检查API地址'}
    except Exception as e:
        return {'success': False, 'error': f'请求异常: {str(e)}'}


def build_student_analysis_prompt(student, system_prompt=None):
    """构建学生分析提示词"""
    if not system_prompt:
        system_prompt = '你是一位经验丰富的教育辅导老师，擅长分析学生学情并给出针对性建议。'

    # 构建学生信息文本
    info_parts = []
    fields = [
        ('姓名', 'name'), ('初中学校', 'middle_school'), ('高中学校', 'high_school'),
        ('中考成绩', 'zhongkao_score'), ('科学成绩', 'science_score'),
        ('初中文理倾向', 'middle_school_type'), ('选科倾向', 'subject_preference'),
        ('错题类型', 'wrong_questions'), ('L1(第1次课作业)', 'l1_status'),
        ('学生反馈', 'feedback'), ('L2(第2次课作业)', 'l2_status'),
        ('L3(第3次课作业)', 'l3_status'), ('L4(第4次课作业)', 'l4_status'),
        ('L5(第5次课作业)', 'l5_status'), ('续班意向', 'continuation_intent'),
        ('续班状态', 'continuation_status'), ('年级', 'grade')
    ]

    for label, key in fields:
        val = student.get(key, '')
        if val and str(val).strip():
            info_parts.append(f'{label}: {val}')

    student_info = '\n'.join(info_parts)

    user_prompt = f"""请根据以下学生信息进行全面分析，给出你的专业建议：

## 学生信息
{student_info}

## 请分析以下内容
1. **学情概况**: 该学生的整体学习情况如何？
2. **优势与不足**: 有哪些优势和需要改进的地方？
3. **选科建议**: 根据成绩和倾向，适合什么选科方向？
4. **沟通策略**: 如何与该学生/家长进行有效沟通？
5. **续班建议**: 是否有续班潜力？建议采取什么策略？
6. **重点关注**: 有什么需要特别关注的地方？

请用专业、友善的语气输出分析报告。"""

    return [
        {'role': 'system', 'content': system_prompt},
        {'role': 'user', 'content': user_prompt}
    ]


def build_chat_suggestion_prompt(student, system_prompt=None):
    """构建单聊沟通建议提示词"""
    if not system_prompt:
        system_prompt = '你是一位经验丰富的教育辅导老师，擅长与学生和家长沟通。'

    info_parts = []
    fields = [
        ('姓名', 'name'), ('高中学校', 'high_school'), ('中考成绩', 'zhongkao_score'),
        ('科学成绩', 'science_score'), ('选科倾向', 'subject_preference'),
        ('学生反馈', 'feedback'), ('续班状态', 'continuation_status')
    ]

    for label, key in fields:
        val = student.get(key, '')
        if val and str(val).strip():
            info_parts.append(f'{label}: {val}')

    student_info = '\n'.join(info_parts)

    user_prompt = f"""我正在准备与以下学生进行一对一沟通，请帮我准备沟通要点：

## 学生信息
{student_info}

## 请提供
1. **开场白建议**: 如何自然开启对话
2. **沟通要点**: 需要重点沟通的内容
3. **话术建议**: 针对该学生的具体话术建议
4. **注意事项**: 沟通中需要注意什么

请提供简洁实用的沟通建议。"""

    return [
        {'role': 'system', 'content': system_prompt},
        {'role': 'user', 'content': user_prompt}
    ]


def build_stats_analysis_prompt(stats, system_prompt=None):
    """构建统计数据分析提示词"""
    if not system_prompt:
        system_prompt = '你是一位经验丰富的教育数据分析师，擅长从数据中发现问题和机会。'

    stats_text = json.dumps(stats, ensure_ascii=False, indent=2)

    user_prompt = f"""请根据以下班级统计数据进行分析：

## 统计数据
{stats_text}

## 请分析
1. **整体概况**: 班级整体情况如何？
2. **数据发现**: 有哪些值得关注的数据趋势或问题？
3. **行动建议**: 建议采取哪些行动来提升续班率和教学质量？

请输出简洁的分析报告。"""

    return [
        {'role': 'system', 'content': system_prompt},
        {'role': 'user', 'content': user_prompt}
    ]


def test_ai_connection(api_url, api_key, model):
    """测试AI连接"""
    messages = [
        {'role': 'user', 'content': '你好，请回复"连接成功"（仅回复这四个字）'}
    ]
    result = call_ai_api(api_url, api_key, model, messages, max_tokens=20)
    return result