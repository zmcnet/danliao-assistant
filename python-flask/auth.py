"""
用户认证模块 - 单聊辅助
处理用户注册、登录、会话管理
"""

import hashlib
import secrets
import sqlite3
import os
from datetime import datetime, timedelta

DB_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'students.db')


def _get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn


def _hash_password(password):
    """SHA256哈希密码"""
    salt = 'danyun_assistant_salt_2024'
    return hashlib.sha256((password + salt).encode()).hexdigest()


def register_user(username, password, display_name='', role='teacher'):
    """注册新用户，支持角色: admin/teacher/assistant"""
    conn = _get_db()
    cursor = conn.cursor()
    try:
        # 如果是第一个用户，自动设为管理员
        if role == 'is_first':
            cursor.execute('SELECT COUNT(*) as count FROM users')
            count = cursor.fetchone()['count']
            role = 'admin' if count == 0 else 'teacher'
        
        # 防止重复插入
        cursor.execute('SELECT id FROM users WHERE username=?', (username,))
        if cursor.fetchone():
            conn.close()
            return {'success': False, 'error': '用户名已存在'}
        
        cursor.execute(
            'INSERT INTO users (username, password_hash, display_name, role) VALUES (?, ?, ?, ?)',
            (username, _hash_password(password), display_name or username, role)
        )
        conn.commit()
        user_id = cursor.lastrowid
        conn.close()
        return {'success': True, 'user_id': user_id}
    except sqlite3.IntegrityError:
        conn.close()
        return {'success': False, 'error': '用户名已存在'}


def login_user(username, password):
    """用户登录验证"""
    conn = _get_db()
    cursor = conn.cursor()
    cursor.execute(
        'SELECT * FROM users WHERE username=?',
        (username,)
    )
    user = cursor.fetchone()
    conn.close()

    if user and user['password_hash'] == _hash_password(password):
        # 生成简单会话令牌
        token = secrets.token_hex(16)
        return {
            'success': True,
            'user': {
                'id': user['id'],
                'username': user['username'],
                'display_name': user['display_name'],
                'role': user['role']
            },
            'token': token
        }
    return {'success': False, 'error': '用户名或密码错误'}


def get_user(user_id):
    """获取用户信息"""
    conn = _get_db()
    cursor = conn.cursor()
    cursor.execute('SELECT id, username, display_name, role FROM users WHERE id=?', (user_id,))
    user = cursor.fetchone()
    conn.close()
    return dict(user) if user else None


def change_password(user_id, old_password, new_password):
    """修改密码"""
    conn = _get_db()
    cursor = conn.cursor()
    cursor.execute('SELECT password_hash FROM users WHERE id=?', (user_id,))
    user = cursor.fetchone()

    if not user or user['password_hash'] != _hash_password(old_password):
        conn.close()
        return {'success': False, 'error': '原密码错误'}

    cursor.execute(
        'UPDATE users SET password_hash=? WHERE id=?',
        (_hash_password(new_password), user_id)
    )
    conn.commit()
    conn.close()
    return {'success': True}


def check_first_user():
    """检查是否有用户（用于首次注册引导）"""
    conn = _get_db()
    cursor = conn.cursor()
    cursor.execute('SELECT COUNT(*) as count FROM users')
    count = cursor.fetchone()['count']
    conn.close()
    return count == 0


def get_all_users():
    """获取所有用户"""
    conn = _get_db()
    cursor = conn.cursor()
    cursor.execute('SELECT id, username, display_name, role, created_at FROM users ORDER BY id')
    users = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return users


def delete_user(user_id):
    """删除用户"""
    conn = _get_db()
    cursor = conn.cursor()
    cursor.execute('DELETE FROM users WHERE id=?', (user_id,))
    conn.commit()
    conn.close()


def update_user_role(user_id, role):
    """更新用户角色"""
    conn = _get_db()
    cursor = conn.cursor()
    cursor.execute('UPDATE users SET role=? WHERE id=?', (role, user_id))
    conn.commit()
    conn.close()