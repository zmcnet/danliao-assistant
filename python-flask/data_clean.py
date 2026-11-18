"""
数据整理脚本 - 将学生原始数据格式化为标准格式
反馈 -> 标准化描述
L1-L5状态 -> 已提交/未提交
错题类型 -> 统一为：手抄/打印/无
"""

from database import get_all_students, update_student, init_db

init_db()

# ==================== 反馈标准化映射 ====================
FEEDBACK_MAP = {
    # 听懂类
    '能听懂': '上课接受度尚可，知识点基本能够理解',
    '听得懂': '课堂知识吸收能力良好',
    '能听懂的': '上课内容理解程度较好',
    '听懂了': '课堂知识掌握情况良好',
    '可以听懂的！': '听课接受度尚可，知识点基本能够跟上',
    '可以': '课堂表现正常，学习状态稳定',
    '行': '整体学习状态尚可',
    '好': '学习态度积极，课堂表现良好',
    
    # OK/态度类
    'OK': '上课接受度尚可，知识点基本能够理解',
    '学习态度OK': '学习态度端正，课堂表现稳定',
    '学习态度可以': '学习态度较为端正，需要继续保持',
    '还行': '整体学习状态一般，有提升空间',
    'EMMMM勉强': '学习状态一般，部分知识点理解存在困难',
    '还算好学': '学习态度尚可，课堂专注度一般',
    '好学': '学习态度积极，课堂表现良好',
    '好学？': '学习态度有待观察，需要进一步关注',
    '认真': '课堂学习专注度较高，学习态度端正',
    '较好的老师': '对教师教学认可度较高',
    '好的老师': '对教师教学认可度较高',
    '谢谢老师': '礼貌配合，沟通顺畅',
    '开智了': '学习认知有显著提升',
    
    # 没回/未联系
    '没回': '暂未收到学生回复，需进一步跟进沟通',
    
    # 其他
    '先学一年': '建议暂缓正式课程，先进行基础学习',
    '偏科': '学科发展不均衡，需重点关注弱势科目',
    '想选理科': '倾向于理科方向，后续选科可考虑理科组合',
    '没想好，但是听课OK': '选科意向不明确，但课堂学习状态良好',
    '美术班，能跟': '美术班学生，课堂学习进度基本能够跟上',
    '都听得懂，可能物化': '课堂知识理解良好，选科倾向物化方向',
    '选物化，上课可以听懂': '选科意向明确（物化），课堂知识吸收能力良好',
    '不错，对理科兴趣更大': '整体表现良好，对理科方向兴趣浓厚',
    '挺好的，相对理科好': '整体学习情况较好，理科方面表现突出',
    '课程非常好': '对课程内容认可度高，学习体验良好',
    '哈哈哈清朝网课': '课堂氛围轻松活跃，学习态度积极',
    '可以，好学': '学习积极性较高，课堂表现良好',
    '选理科，听得懂，活泼': '性格活泼开朗，课堂知识理解良好，选科倾向理科',
    '能听懂不一定选': '课堂知识理解良好，但选科意向尚不确定',
    '听懂了，可能选理': '课堂知识掌握情况良好，选科倾向可能为理科',
    '上课没听明白': '课堂知识理解存在困难，需要课后辅导加强',
    '不知道，上课听懂了': '课堂知识吸收良好，但对某些细节了解不够深入',
    '老师教的能听得懂': '对教师教学方法认可度高，课堂知识理解良好',
    '目前还行，跟得上': '当前学习进度基本能够跟上，状态稳定',
    '讲的挺好的，都能听懂': '教师授课效果良好，学生课堂知识理解充分',
    '没想好': '选科意向不明确，需要进一步引导和沟通',
    '孩子听的懂的': '家长反馈孩子课堂知识理解良好',
    '较为自信，比较简单': '学习自信心较强，知识点掌握较为扎实',
    '听得懂、马虎': '课堂知识理解良好，但需注意粗心大意的问题',
}

# ==================== L1-L5 状态标准化 ====================
L_STATUS_MAP = {
    '1': '1',
    '已联系': '1',
    '已联系本人': '1',
    '找不到': '',
    '': '',
}

# ==================== 错题类型标准化 ====================
WRONG_Q_MAP = {
    '无': '无',
    '印': '打印',
    '手': '手抄',
    '没': '无',
    '打印': '打印',
    '手抄': '手抄',
    '没选': '无',
    '手+印': '手抄+打印',
    '手，印': '手抄+打印',
}


def format_feedback(raw):
    """格式化反馈为标准描述"""
    return format_feedback_v2(raw)


def format_feedback_v2(raw):
    """格式化反馈为标准描述（v2: 避免重复前缀）"""
    if not raw or str(raw).strip() == '' or str(raw).strip() == 'None':
        return ''
    raw = str(raw).strip()
    # 去掉所有可能的重复前缀
    while '学生反馈：学生反馈' in raw:
        raw = raw.replace('学生反馈：学生反馈', '学生反馈')
    clean = raw.replace('学生反馈：', '')
    
    # 如果已经是标准格式，直接返回
    if raw.startswith('上课') or raw.startswith('课堂') or raw.startswith('学习态度') or raw.startswith('建议'):
        return raw
    
    # 先精确匹配清理后的值
    if clean in FEEDBACK_MAP:
        return FEEDBACK_MAP[clean]
    # 再精确匹配原值
    if raw in FEEDBACK_MAP:
        return FEEDBACK_MAP[raw]
    # 模糊匹配
    for key, val in FEEDBACK_MAP.items():
        if key in raw or raw in key:
            return val
    # 兜底 - 保留原始意思但格式化
    return f'{clean}'


def format_l_status(raw):
    """格式化L1-L5状态"""
    if not raw or str(raw).strip() == '' or str(raw).strip() == 'None':
        return ''
    raw = str(raw).strip()
    if raw in L_STATUS_MAP:
        return L_STATUS_MAP[raw]
    if raw == '1':
        return '1'
    return raw


def format_wrong_q(raw):
    """格式化错题类型"""
    if not raw or str(raw).strip() == '' or str(raw).strip() == 'None':
        return ''
    raw = str(raw).strip()
    if raw in WRONG_Q_MAP:
        return WRONG_Q_MAP[raw]
    return raw


def main():
    students = get_all_students()
    print(f'共 {len(students)} 名学生需要整理')
    
    updated = 0
    for s in students:
        data = {}
        
        # 格式化反馈
        fb = s.get('feedback', '')
        new_fb = format_feedback(fb)
        if new_fb != fb:
            data['feedback'] = new_fb
        
        # 格式化L1-L5
        for i in range(1, 6):
            col = f'l{i}_status'
            old_val = s.get(col, '')
            new_val = format_l_status(old_val)
            if new_val != old_val:
                data[col] = new_val
        
        # 格式化错题类型
        wq = s.get('wrong_questions', '')
        new_wq = format_wrong_q(wq)
        if new_wq != wq:
            data['wrong_questions'] = new_wq
        
        if data:
            from database import update_student
            update_student(s['id'], data)
            updated += 1
            print(f'  更新 {s["name"]}: {data}')
    
    print(f'✅ 已更新 {updated} 名学生')
    
    # 验证结果
    students = get_all_students()
    from collections import Counter
    feedbacks = Counter()
    l1_vals = Counter()
    wrong_q = Counter()
    for s in students:
        fb = s.get('feedback', '') or ''
        if fb: feedbacks[fb[:20]] += 1
        l1_vals[str(s.get('l1_status','')).strip()] += 1
        wq = s.get('wrong_questions', '') or ''
        if wq: wrong_q[wq.strip()] += 1
    
    print()
    print('=== 更新后反馈统计 ===')
    for k, v in feedbacks.most_common(15):
        print(f'  "{k}..." -> {v}人')
    
    print()
    print('=== L1状态统计 ===')
    for k, v in l1_vals.most_common():
        print(f'  "{k}" -> {v}人')
    
    print()
    print('=== 错题类型统计 ===')
    for k, v in wrong_q.most_common():
        print(f'  "{k}" -> {v}人')


if __name__ == '__main__':
    main()
