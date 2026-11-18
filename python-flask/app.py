"""
单聊辅助 - Flask主应用
提供所有API路由和前端页面服务
"""

import os
import json
import tempfile
from flask import Flask, render_template, request, jsonify, session, redirect, url_for, send_file

from database import (
    init_db, import_students_from_df, get_all_students, search_students,
    get_student, update_student, delete_student, get_student_stats,
    export_students, parse_import_file,
    get_all_scripts, get_scripts_by_category, get_script_categories,
    add_script, update_script, delete_script, move_script,
    get_ai_config, save_ai_config,
    get_all_classes, get_class, add_class, update_class, delete_class,
    get_students_by_class
)
from auth import register_user, login_user, get_user, change_password, check_first_user, get_all_users, delete_user, update_user_role
from ai_service import (
    call_ai_api, build_student_analysis_prompt, build_chat_suggestion_prompt,
    build_stats_analysis_prompt, test_ai_connection
)

app = Flask(__name__)
app.secret_key = 'danyun_assistant_secret_key_2024'
app.config['MAX_CONTENT_LENGTH'] = 50 * 1024 * 1024  # 50MB


@app.after_request
def add_header(response):
    """防止浏览器缓存旧版本，但保留Cookie"""
    if response.content_type and 'text/html' in response.content_type:
        response.headers['Cache-Control'] = 'no-cache, must-revalidate'
        response.headers['Pragma'] = 'no-cache'
    return response


def get_local_ip():
    """获取本机局域网IP地址"""
    import socket
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(('8.8.8.8', 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except:
        return '127.0.0.1'

# ==================== 页面路由 ====================

@app.route('/')
def index():
    """主页面"""
    if 'user_id' not in session:
        return redirect(url_for('login_page'))
    return render_template('index.html')


@app.route('/login')
def login_page():
    """登录页面"""
    if 'user_id' in session:
        return redirect(url_for('index'))
    is_first = check_first_user()
    return render_template('login.html', is_first=is_first)


@app.route('/logout')
def logout():
    """退出登录"""
    session.clear()
    return redirect(url_for('login_page'))


# ==================== 认证API ====================

@app.route('/api/auth/register', methods=['POST'])
def api_register():
    data = request.json
    result = register_user(
        data.get('username', ''),
        data.get('password', ''),
        data.get('display_name', '')
    )
    if result['success']:
        return jsonify({'success': True, 'message': '注册成功'})
    return jsonify({'success': False, 'error': result['error']}), 400


@app.route('/api/auth/login', methods=['POST'])
def api_login():
    data = request.json
    result = login_user(data.get('username', ''), data.get('password', ''))
    if result['success']:
        session['user_id'] = result['user']['id']
        session['username'] = result['user']['username']
        session['display_name'] = result['user']['display_name']
        return jsonify({'success': True, 'user': result['user']})
    return jsonify({'success': False, 'error': result['error']}), 401


@app.route('/api/auth/check')
def api_check_auth():
    if 'user_id' in session:
        user = get_user(session['user_id'])
        if user:
            return jsonify({'authenticated': True, 'user': user})
    return jsonify({'authenticated': False})


@app.route('/api/auth/change_password', methods=['POST'])
def api_change_password():
    if 'user_id' not in session:
        return jsonify({'success': False, 'error': '未登录'}), 401
    data = request.json
    result = change_password(
        session['user_id'],
        data.get('old_password', ''),
        data.get('new_password', '')
    )
    return jsonify(result)


@app.route('/api/auth/check_first')
def api_check_first():
    return jsonify({'is_first': check_first_user()})


# ==================== 用户管理API ====================

@app.route('/api/users', methods=['GET'])
def api_get_users():
    if 'user_id' not in session:
        return jsonify({'error': '未登录'}), 401
    user = get_user(session['user_id'])
    if not user or user['role'] != 'admin':
        return jsonify({'error': '无权限'}), 403
    users = get_all_users()
    for u in users:
        if u['created_at']:
            u['created_at'] = u['created_at'].split('T')[0]
    return jsonify(users)


@app.route('/api/users', methods=['POST'])
def api_add_user():
    if 'user_id' not in session:
        return jsonify({'error': '未登录'}), 401
    user = get_user(session['user_id'])
    if not user or user['role'] != 'admin':
        return jsonify({'error': '无权限'}), 403
    data = request.json
    result = register_user(
        data.get('username', ''),
        data.get('password', ''),
        data.get('display_name', ''),
        'admin' if data.get('is_first') else 'teacher'
    )
    if result['success']:
        return jsonify({'success': True, 'message': '用户已添加'})
    return jsonify(result), 400


@app.route('/api/users/<int:user_id>/role', methods=['PUT'])
def api_update_user_role(user_id):
    if 'user_id' not in session:
        return jsonify({'error': '未登录'}), 401
    user = get_user(session['user_id'])
    if not user or user['role'] != 'admin':
        return jsonify({'error': '无权限'}), 403
    data = request.json
    update_user_role(user_id, data.get('role', 'teacher'))
    return jsonify({'success': True})


@app.route('/api/users/<int:user_id>', methods=['DELETE'])
def api_delete_user(user_id):
    if 'user_id' not in session:
        return jsonify({'error': '未登录'}), 401
    user = get_user(session['user_id'])
    if not user or user['role'] != 'admin':
        return jsonify({'error': '无权限'}), 403
    if user_id == session['user_id']:
        return jsonify({'success': False, 'error': '不能删除自己'}), 400
    delete_user(user_id)
    return jsonify({'success': True})


# ==================== 学生API ====================

@app.route('/api/students', methods=['GET'])
def api_get_students():
    grade = request.args.get('grade', '')
    keyword = request.args.get('keyword', '')
    sort_by = request.args.get('sort_by', 'name')
    sort_order = request.args.get('sort_order', 'asc')
    class_id = request.args.get('class_id', '')

    if class_id:
        students = get_students_by_class(int(class_id), sort_by, sort_order)
    elif keyword:
        students = search_students(keyword, grade, sort_by, sort_order)
    else:
        students = get_all_students(grade, sort_by, sort_order)
    return jsonify(students)


@app.route('/api/students/<int:student_id>')
def api_get_student(student_id):
    student = get_student(student_id)
    if student:
        return jsonify(student)
    return jsonify({'error': '学生不存在'}), 404


@app.route('/api/students/<int:student_id>', methods=['PUT'])
def api_update_student(student_id):
    data = request.json
    update_student(student_id, data)
    return jsonify({'success': True})


@app.route('/api/students/<int:student_id>', methods=['DELETE'])
def api_delete_student(student_id):
    delete_student(student_id)
    return jsonify({'success': True})


@app.route('/api/students/stats')
def api_student_stats():
    stats = get_student_stats()
    return jsonify(stats)


@app.route('/api/students/import', methods=['POST'])
def api_import_students():
    if 'file' not in request.files:
        return jsonify({'success': False, 'error': '请选择文件'}), 400

    file = request.files['file']
    if file.filename == '':
        return jsonify({'success': False, 'error': '请选择文件'}), 400

    grade = request.form.get('grade', '')

    try:
        # 保存临时文件
        suffix = os.path.splitext(file.filename)[1]
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
            file.save(tmp.name)
            tmp_path = tmp.name

        df = parse_import_file(tmp_path)
        os.unlink(tmp_path)

        count = import_students_from_df(df, grade)
        return jsonify({'success': True, 'count': count, 'message': f'成功导入 {count} 名学生'})

    except Exception as e:
        return jsonify({'success': False, 'error': f'导入失败: {str(e)}'}), 400


@app.route('/api/students/export', methods=['POST'])
def api_export_students():
    data = request.json
    try:
        path = export_students(
            grade=data.get('grade', ''),
            format=data.get('format', 'csv')
        )
        return send_file(
            path,
            as_attachment=True,
            download_name=os.path.basename(path)
        )
    except Exception as e:
        return jsonify({'success': False, 'error': f'导出失败: {str(e)}'}), 400


@app.route('/api/students/import/preview', methods=['POST'])
def api_preview_import():
    """预览导入文件"""
    if 'file' not in request.files:
        return jsonify({'success': False, 'error': '请选择文件'}), 400

    file = request.files['file']
    try:
        suffix = os.path.splitext(file.filename)[1]
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
            file.save(tmp.name)
            tmp_path = tmp.name

        df = parse_import_file(tmp_path)
        os.unlink(tmp_path)

        # 返回前10行预览
        preview = df.head(10).fillna('').to_dict('records')
        columns = list(df.columns)

        return jsonify({
            'success': True,
            'columns': columns,
            'preview': preview,
            'total_rows': len(df)
        })
    except Exception as e:
        return jsonify({'success': False, 'error': f'读取文件失败: {str(e)}'}), 400


# ==================== 话术API ====================

@app.route('/api/scripts', methods=['GET'])
def api_get_scripts():
    category = request.args.get('category', '')
    if category:
        scripts = get_scripts_by_category(category)
    else:
        scripts = get_all_scripts()
    return jsonify(scripts)


@app.route('/api/scripts/categories')
def api_get_script_categories():
    categories = get_script_categories()
    return jsonify(categories)


@app.route('/api/scripts', methods=['POST'])
def api_add_script():
    data = request.json
    script_id = add_script(
        data.get('title', ''),
        data.get('content', ''),
        data.get('category', '通用')
    )
    return jsonify({'success': True, 'id': script_id})


@app.route('/api/scripts/<int:script_id>', methods=['PUT'])
def api_update_script(script_id):
    data = request.json
    update_script(script_id, data)
    return jsonify({'success': True})


@app.route('/api/scripts/<int:script_id>', methods=['DELETE'])
def api_delete_script(script_id):
    delete_script(script_id)
    return jsonify({'success': True})


@app.route('/api/scripts/<int:script_id>/move', methods=['POST'])
def api_move_script(script_id):
    data = request.json
    success = move_script(script_id, data.get('direction', 'up'))
    return jsonify({'success': success})


# ==================== 班级API ====================

@app.route('/api/classes', methods=['GET'])
def api_get_classes():
    classes = get_all_classes()
    return jsonify(classes)


@app.route('/api/classes', methods=['POST'])
def api_add_class():
    data = request.json
    class_id = add_class(
        data.get('name', ''),
        data.get('class_number', ''),
        data.get('class_type', 'A1'),
        data.get('lesson_count', 5),
        data.get('grade', '')
    )
    return jsonify({'success': True, 'id': class_id})


@app.route('/api/classes/<int:class_id>', methods=['PUT'])
def api_update_class(class_id):
    data = request.json
    update_class(class_id, data)
    return jsonify({'success': True})


@app.route('/api/classes/<int:class_id>', methods=['DELETE'])
def api_delete_class(class_id):
    delete_class(class_id)
    return jsonify({'success': True})


# ==================== AI配置API ====================

@app.route('/api/ai/config', methods=['GET'])
def api_get_ai_config():
    config = get_ai_config()
    if config:
        # 隐藏完整API密钥
        if config.get('api_key'):
            key = config['api_key']
            if len(key) > 8:
                config['api_key_masked'] = key[:4] + '****' + key[-4:]
            else:
                config['api_key_masked'] = '****'
        return jsonify(config)
    return jsonify({})


@app.route('/api/ai/config', methods=['POST'])
def api_save_ai_config():
    data = request.json
    save_ai_config(data)
    return jsonify({'success': True})


@app.route('/api/ai/test', methods=['POST'])
def api_test_ai():
    data = request.json
    result = test_ai_connection(
        data.get('api_url', ''),
        data.get('api_key', ''),
        data.get('model', 'gpt-3.5-turbo')
    )
    return jsonify(result)


@app.route('/api/ai/analyze', methods=['POST'])
def api_analyze_student():
    """AI分析学生"""
    data = request.json
    student_id = data.get('student_id')
    student = get_student(student_id)
    if not student:
        return jsonify({'success': False, 'error': '学生不存在'}), 404

    config = get_ai_config()
    if not config or not config.get('api_key'):
        return jsonify({'success': False, 'error': '请先在设置中配置AI API'}), 400

    messages = build_student_analysis_prompt(student, config.get('system_prompt'))
    result = call_ai_api(
        config['api_url'], config['api_key'], config['model'],
        messages, config.get('temperature', 0.7), config.get('max_tokens', 2000)
    )

    if result['success']:
        # 保存AI分析备注到学生
        update_student(student_id, {'note': result['content']})

    return jsonify(result)


@app.route('/api/ai/chat_suggestion', methods=['POST'])
def api_chat_suggestion():
    """AI生成沟通建议"""
    data = request.json
    student_id = data.get('student_id')
    student = get_student(student_id)
    if not student:
        return jsonify({'success': False, 'error': '学生不存在'}), 404

    config = get_ai_config()
    if not config or not config.get('api_key'):
        return jsonify({'success': False, 'error': '请先在设置中配置AI API'}), 400

    messages = build_chat_suggestion_prompt(student, config.get('system_prompt'))
    result = call_ai_api(
        config['api_url'], config['api_key'], config['model'],
        messages, config.get('temperature', 0.7), config.get('max_tokens', 2000)
    )

    return jsonify(result)


@app.route('/api/ai/analyze_stats', methods=['POST'])
def api_analyze_stats():
    """AI分析统计数据"""
    config = get_ai_config()
    if not config or not config.get('api_key'):
        return jsonify({'success': False, 'error': '请先在设置中配置AI API'}), 400

    stats = get_student_stats()
    messages = build_stats_analysis_prompt(stats, config.get('system_prompt'))
    result = call_ai_api(
        config['api_url'], config['api_key'], config['model'],
        messages, config.get('temperature', 0.7), config.get('max_tokens', 2000)
    )

    return jsonify(result)


@app.route('/api/ai/chat', methods=['POST'])
def api_ai_chat():
    """自由对话模式"""
    data = request.json
    config = get_ai_config()
    if not config or not config.get('api_key'):
        return jsonify({'success': False, 'error': '请先在设置中配置AI API'}), 400

    messages = data.get('messages', [])
    result = call_ai_api(
        config['api_url'], config['api_key'], config['model'],
        messages, config.get('temperature', 0.7), config.get('max_tokens', 2000)
    )

    return jsonify(result)


# ==================== 启动 ====================

if __name__ == '__main__':
    init_db()
    print('=' * 50)
    print('  学员信息管理系统 v1.0')
    print('=' * 50)
    port = 5000
    try:
        import socket
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(('8.8.8.8', 80))
        local_ip = s.getsockname()[0]
        s.close()
        print(f'  局域网访问: http://{local_ip}:{port}')
    except:
        pass
    print(f'  本机访问: http://127.0.0.1:{port}')
    print('=' * 50)
    app.run(host='0.0.0.0', port=port, debug=False, threaded=True)