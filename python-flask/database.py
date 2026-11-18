"""
数据库操作层 - 单聊辅助
SQLite3 数据库管理，包含学生信息、话术、AI配置等所有数据操作
"""

import sqlite3
import os
import pandas as pd
from datetime import datetime

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'students.db')


def get_db():
    """获取数据库连接"""
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA foreign_keys=ON")
    return conn


def init_db():
    """初始化数据库，创建所有表"""
    conn = get_db()
    cursor = conn.cursor()

    # 用户表
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            display_name TEXT DEFAULT '',
            role TEXT DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')

    # 学生信息表
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            middle_school TEXT DEFAULT '',
            high_school TEXT DEFAULT '',
            zhongkao_score TEXT DEFAULT '',
            science_score TEXT DEFAULT '',
            middle_school_type TEXT DEFAULT '',
            subject_preference TEXT DEFAULT '',
            wrong_questions TEXT DEFAULT '',
            l1_status TEXT DEFAULT '',
            feedback TEXT DEFAULT '',
            l2_status TEXT DEFAULT '',
            l3_status TEXT DEFAULT '',
            l4_status TEXT DEFAULT '',
            l5_status TEXT DEFAULT '',
            continuation_intent TEXT DEFAULT '',
            continuation_status TEXT DEFAULT 'none',
            grade TEXT DEFAULT '',
            note TEXT DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')

    # 话术表
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS scripts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            category TEXT DEFAULT '通用',
            sort_order INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')

    # AI配置表
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS ai_config (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            api_url TEXT DEFAULT 'https://api.openai.com/v1',
            api_key TEXT DEFAULT '',
            model TEXT DEFAULT 'gpt-3.5-turbo',
            system_prompt TEXT DEFAULT '你是一位经验丰富的教育辅导老师，请根据学生数据进行分析并提供建议。',
            temperature REAL DEFAULT 0.7,
            max_tokens INTEGER DEFAULT 2000
        )
    ''')

    # 班级表
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS classes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            class_number TEXT DEFAULT '',
            class_type TEXT DEFAULT 'A1',
            lesson_count INTEGER DEFAULT 5,
            grade TEXT DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')

    # 迁移：给students表添加class_id字段
    try:
        cursor.execute('ALTER TABLE students ADD COLUMN class_id INTEGER DEFAULT 0')
    except sqlite3.OperationalError:
        pass  # 字段已存在

    # 自动迁移：为已有grade创建班级记录
    cursor.execute('SELECT DISTINCT grade FROM students WHERE grade != ""')
    existing_grades = [row['grade'] for row in cursor.fetchall()]
    for g in existing_grades:
        cursor.execute('SELECT id FROM classes WHERE grade=?', (g,))
        if not cursor.fetchone():
            cursor.execute(
                'INSERT INTO classes (name, class_number, class_type, lesson_count, grade) VALUES (?, ?, ?, ?, ?)',
                (f'{g}班', g, 'A1', 5, g)
            )
            class_id = cursor.lastrowid
            cursor.execute('UPDATE students SET class_id=? WHERE grade=?', (class_id, g))

    conn.commit()
    conn.close()


# ==================== 学生信息操作 ====================

def import_students_from_df(df, grade=''):
    """从DataFrame导入学生数据，返回导入数量"""
    conn = get_db()
    cursor = conn.cursor()
    count = 0

    # 字段映射（中文列名 → 数据库字段）
    field_map = {
        '姓名': 'name', '初中学校': 'middle_school', '高中学校': 'high_school',
        '中考': 'zhongkao_score', '科学': 'science_score',
        '初中文理': 'middle_school_type', '选科倾向': 'subject_preference',
        '错题': 'wrong_questions', 'L1': 'l1_status', '反馈': 'feedback',
        'L2': 'l2_status', 'L3': 'l3_status', 'L4': 'l4_status', 'L5': 'l5_status',
        '续班意向': 'continuation_intent'
    }

    # 找到实际列映射
    actual_map = {}
    for col in df.columns:
        col_clean = col.strip()
        if col_clean in field_map:
            actual_map[col] = field_map[col_clean]

    for _, row in df.iterrows():
        name_val = str(row.get('姓名', '')).strip()
        if not name_val or name_val == 'None':
            continue

        # 检查是否已存在（姓名+年级去重）
        cursor.execute(
            'SELECT id FROM students WHERE name = ? AND grade = ?',
            (name_val, grade)
        )
        existing = cursor.fetchone()

        data = {'grade': grade, 'updated_at': datetime.now().isoformat()}
        for src_col, db_col in actual_map.items():
            val = row.get(src_col)
            if val is not None and str(val).strip() and str(val) != 'nan':
                data[db_col] = str(val).strip()

        if existing:
            # 更新
            set_clause = ', '.join([f'{k}=?' for k in data.keys()])
            values = list(data.values()) + [existing['id']]
            cursor.execute(f'UPDATE students SET {set_clause} WHERE id=?', values)
        else:
            # 插入
            data['name'] = name_val
            columns = ', '.join(data.keys())
            placeholders = ', '.join(['?'] * len(data))
            cursor.execute(
                f'INSERT INTO students ({columns}) VALUES ({placeholders})',
                list(data.values())
            )
            count += 1

    conn.commit()
    conn.close()
    return count


def get_all_students(grade='', sort_by='name', sort_order='asc'):
    """获取学生列表，支持排序
    sort_by: name(姓名), continuation(续班状态)
    sort_order: asc(升序/A-Z/未续班在前), desc(降序/Z-A/已续班在前)
    """
    conn = get_db()
    cursor = conn.cursor()

    base_query = 'SELECT s.*, c.name as class_name, c.class_number, c.class_type, c.lesson_count FROM students s LEFT JOIN classes c ON s.class_id=c.id'

    if sort_by == 'name':
        order = f'ORDER BY s.name {"ASC" if sort_order == "asc" else "DESC"}'
    elif sort_by == 'continuation':
        # 续班排序: none=0, continued=1, no_condition=2
        order = f'ORDER BY CASE s.continuation_status WHEN "none" THEN 0 WHEN "continued" THEN 1 WHEN "no_condition" THEN 2 ELSE 0 END {"ASC" if sort_order == "asc" else "DESC"}, s.name'
    else:
        order = 'ORDER BY s.name ASC'

    if grade:
        cursor.execute(f'{base_query} WHERE s.grade=? {order}', (grade,))
    else:
        cursor.execute(f'{base_query} {order}')
    students = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return students


def search_students(keyword='', grade='', sort_by='name', sort_order='asc'):
    """搜索学生，优先显示姓名匹配，再显示学校匹配"""
    conn = get_db()
    cursor = conn.cursor()
    # 姓名完全匹配优先，姓名模糊匹配次之，学校匹配最后
    query = '''
        SELECT s.*, c.name as class_name, c.class_number, c.class_type, c.lesson_count,
        CASE
            WHEN s.name = ? THEN 0
            WHEN s.name LIKE ? THEN 1
            ELSE 2
        END as match_priority
        FROM students s LEFT JOIN classes c ON s.class_id=c.id
        WHERE (s.name LIKE ? OR s.middle_school LIKE ? OR s.high_school LIKE ?)
    '''
    name_exact = keyword
    name_like = f'{keyword}%'
    name_any = f'%{keyword}%'
    school_like = f'%{keyword}%'
    params = [name_exact, name_like, name_any, school_like, school_like]

    if grade:
        query += ' AND s.grade=?'
        params.append(grade)

    query += ' ORDER BY match_priority ASC, s.name ASC'

    cursor.execute(query, params)
    students = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return students


def get_student(student_id):
    """获取单个学生详情（含班级信息）"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('''
        SELECT s.*, c.name as class_name, c.class_number, c.class_type, c.lesson_count
        FROM students s LEFT JOIN classes c ON s.class_id=c.id
        WHERE s.id=?
    ''', (student_id,))
    student = cursor.fetchone()
    conn.close()
    return dict(student) if student else None


def update_student(student_id, data):
    """更新学生信息"""
    conn = get_db()
    cursor = conn.cursor()
    data['updated_at'] = datetime.now().isoformat()
    set_clause = ', '.join([f'{k}=?' for k in data.keys()])
    values = list(data.values()) + [student_id]
    cursor.execute(f'UPDATE students SET {set_clause} WHERE id=?', values)
    conn.commit()
    conn.close()


def delete_student(student_id):
    """删除学生"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('DELETE FROM students WHERE id=?', (student_id,))
    conn.commit()
    conn.close()


def get_student_stats():
    """获取统计数据"""
    conn = get_db()
    cursor = conn.cursor()

    stats = {}

    # 总人数
    cursor.execute('SELECT COUNT(*) as count FROM students')
    stats['total'] = cursor.fetchone()['count']

    # 续班状态统计
    cursor.execute('''
        SELECT continuation_status, COUNT(*) as count
        FROM students GROUP BY continuation_status
    ''')
    stats['continuation'] = {row['continuation_status']: row['count'] for row in cursor.fetchall()}

    # 班级分布 (使用班级表)
    cursor.execute('''
        SELECT c.id, c.name, c.class_number, c.class_type, c.lesson_count, c.grade, COUNT(s.id) as student_count
        FROM classes c LEFT JOIN students s ON s.class_id=c.id
        GROUP BY c.id ORDER BY c.grade
    ''')
    stats['classes'] = [dict(row) for row in cursor.fetchall()]

    # 选科倾向统计
    cursor.execute('SELECT subject_preference FROM students WHERE subject_preference != ""')
    prefs = []
    for row in cursor.fetchall():
        prefs.append(row['subject_preference'])
    stats['subject_preferences'] = prefs

    # 作业提交率统计 (动态获取最大课次)
    cursor.execute('SELECT MAX(lesson_count) as max_lessons FROM classes')
    max_lessons = cursor.fetchone()['max_lessons'] or 5
    for i in range(1, max_lessons + 1):
        col = f'l{i}_status'
        cursor.execute(f'SELECT COUNT(*) as count FROM students WHERE {col}="1"')
        submitted = cursor.fetchone()['count']
        cursor.execute(f'SELECT COUNT(*) as total FROM students WHERE {col}!=""')
        total = cursor.fetchone()['total']
        stats[f'{col}_submitted'] = submitted
        stats[f'{col}_total'] = total

    conn.close()
    return stats


def export_students(grade='', format='csv'):
    """导出学生数据到文件"""
    conn = get_db()
    if grade:
        df = pd.read_sql_query(
            'SELECT * FROM students WHERE grade=? ORDER BY name',
            conn, params=(grade,)
        )
    else:
        df = pd.read_sql_query(
            'SELECT * FROM students ORDER BY grade, name', conn
        )
    conn.close()

    # 删除不需要导出的字段
    df = df.drop(columns=['id', 'created_at', 'updated_at', 'note'], errors='ignore')

    # 中文字段映射
    cn_headers = {
        'name': '姓名', 'middle_school': '初中学校', 'high_school': '高中学校',
        'zhongkao_score': '中考', 'science_score': '科学',
        'middle_school_type': '初中文理', 'subject_preference': '选科倾向',
        'wrong_questions': '错题', 'l1_status': 'L1', 'feedback': '反馈',
        'l2_status': 'L2', 'l3_status': 'L3', 'l4_status': 'L4', 'l5_status': 'L5',
        'continuation_intent': '续班意向', 'continuation_status': '续班状态',
        'grade': '年级'
    }
    df = df.rename(columns=cn_headers)

    if format == 'csv':
        path = f'学生数据导出_{datetime.now().strftime("%Y%m%d_%H%M%S")}.csv'
        df.to_csv(path, index=False, encoding='utf-8-sig')
    else:
        path = f'学生数据导出_{datetime.now().strftime("%Y%m%d_%H%M%S")}.xlsx'
        df.to_excel(path, index=False, engine='openpyxl')

    return path


# ==================== 话术管理 ====================

def get_all_scripts():
    """获取所有话术"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('SELECT * FROM scripts ORDER BY sort_order, id')
    scripts = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return scripts


def get_scripts_by_category(category=''):
    """按分类获取话术"""
    conn = get_db()
    cursor = conn.cursor()
    if category:
        cursor.execute(
            'SELECT * FROM scripts WHERE category=? ORDER BY sort_order, id',
            (category,)
        )
    else:
        cursor.execute('SELECT * FROM scripts ORDER BY category, sort_order, id')
    scripts = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return scripts


def get_script_categories():
    """获取所有话术分类"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('SELECT DISTINCT category FROM scripts ORDER BY category')
    categories = [row['category'] for row in cursor.fetchall()]
    conn.close()
    return categories


def add_script(title, content, category='通用'):
    """添加话术"""
    conn = get_db()
    cursor = conn.cursor()
    # 获取最大排序号
    cursor.execute('SELECT MAX(sort_order) as max_order FROM scripts')
    max_order = cursor.fetchone()['max_order'] or 0
    cursor.execute(
        'INSERT INTO scripts (title, content, category, sort_order) VALUES (?, ?, ?, ?)',
        (title, content, category, max_order + 1)
    )
    conn.commit()
    script_id = cursor.lastrowid
    conn.close()
    return script_id


def update_script(script_id, data):
    """更新话术"""
    conn = get_db()
    cursor = conn.cursor()
    data['updated_at'] = datetime.now().isoformat()
    set_clause = ', '.join([f'{k}=?' for k in data.keys()])
    values = list(data.values()) + [script_id]
    cursor.execute(f'UPDATE scripts SET {set_clause} WHERE id=?', values)
    conn.commit()
    conn.close()


def delete_script(script_id):
    """删除话术"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('DELETE FROM scripts WHERE id=?', (script_id,))
    conn.commit()
    conn.close()


def move_script(script_id, direction):
    """移动话术位置（up/down）"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('SELECT id, sort_order FROM scripts WHERE id=?', (script_id,))
    current = cursor.fetchone()
    if not current:
        conn.close()
        return False

    current_order = current['sort_order']

    if direction == 'up':
        cursor.execute(
            'SELECT id, sort_order FROM scripts WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1',
            (current_order,)
        )
    else:
        cursor.execute(
            'SELECT id, sort_order FROM scripts WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1',
            (current_order,)
        )

    neighbor = cursor.fetchone()
    if neighbor:
        cursor.execute(
            'UPDATE scripts SET sort_order=? WHERE id=?',
            (neighbor['sort_order'], current['id'])
        )
        cursor.execute(
            'UPDATE scripts SET sort_order=? WHERE id=?',
            (current_order, neighbor['id'])
        )
        conn.commit()
        conn.close()
        return True

    conn.close()
    return False


# ==================== AI配置 ====================

def get_ai_config():
    """获取AI配置"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('SELECT * FROM ai_config WHERE id=1')
    config = cursor.fetchone()
    conn.close()
    if config:
        return dict(config)
    return None


def save_ai_config(data):
    """保存AI配置"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('SELECT id FROM ai_config WHERE id=1')
    existing = cursor.fetchone()

    if existing:
        set_clause = ', '.join([f'{k}=?' for k in data.keys()])
        values = list(data.values())
        cursor.execute(f'UPDATE ai_config SET {set_clause} WHERE id=1', values)
    else:
        columns = ', '.join(data.keys())
        placeholders = ', '.join(['?'] * len(data))
        cursor.execute(f'INSERT INTO ai_config ({columns}) VALUES ({placeholders})', list(data.values()))
    conn.commit()
    conn.close()


# ==================== 数据导入辅助 ====================

def parse_import_file(filepath):
    """解析导入文件，返回DataFrame"""
    ext = os.path.splitext(filepath)[1].lower()
    if ext in ['.xlsx', '.xls']:
        df = pd.read_excel(filepath, engine='openpyxl')
    elif ext == '.csv':
        df = pd.read_csv(filepath, encoding='utf-8-sig')
    else:
        raise ValueError(f'不支持的文件格式: {ext}')
    return df


# ==================== 班级管理 ====================

def get_all_classes():
    """获取所有班级（含学生数）"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('''
        SELECT c.*, COUNT(s.id) as student_count
        FROM classes c LEFT JOIN students s ON s.class_id=c.id
        GROUP BY c.id ORDER BY c.grade
    ''')
    classes = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return classes


def get_class(class_id):
    """获取单个班级"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('''
        SELECT c.*, COUNT(s.id) as student_count
        FROM classes c LEFT JOIN students s ON s.class_id=c.id
        WHERE c.id=? GROUP BY c.id
    ''', (class_id,))
    cls = cursor.fetchone()
    conn.close()
    return dict(cls) if cls else None


def add_class(name, class_number='', class_type='A1', lesson_count=5, grade=''):
    """添加班级"""
    conn = get_db()
    cursor = conn.cursor()
    # 自动生成grade
    if not grade:
        grade = class_number if class_number else name
    cursor.execute(
        'INSERT INTO classes (name, class_number, class_type, lesson_count, grade) VALUES (?, ?, ?, ?, ?)',
        (name, class_number, class_type, lesson_count, grade)
    )
    conn.commit()
    class_id = cursor.lastrowid
    conn.close()
    return class_id


def update_class(class_id, data):
    """更新班级"""
    conn = get_db()
    cursor = conn.cursor()
    set_clause = ', '.join([f'{k}=?' for k in data.keys()])
    values = list(data.values()) + [class_id]
    cursor.execute(f'UPDATE classes SET {set_clause} WHERE id=?', values)
    conn.commit()
    conn.close()


def delete_class(class_id):
    """删除班级（同时将学生class_id置0）"""
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('UPDATE students SET class_id=0, grade="" WHERE class_id=?', (class_id,))
    cursor.execute('DELETE FROM classes WHERE id=?', (class_id,))
    conn.commit()
    conn.close()


def get_students_by_class(class_id, sort_by='name', sort_order='asc'):
    """获取班级学生列表，支持排序"""
    conn = get_db()
    cursor = conn.cursor()

    if sort_by == 'name':
        order = f'ORDER BY name {"ASC" if sort_order == "asc" else "DESC"}'
    elif sort_by == 'continuation':
        order = f'ORDER BY CASE continuation_status WHEN "none" THEN 0 WHEN "continued" THEN 1 WHEN "no_condition" THEN 2 ELSE 0 END {"ASC" if sort_order == "asc" else "DESC"}, name'
    else:
        order = 'ORDER BY name ASC'

    cursor.execute(f'SELECT * FROM students WHERE class_id=? {order}', (class_id,))
    students = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return students