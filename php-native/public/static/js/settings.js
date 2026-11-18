/* ============================================
   单聊辅助 - 设置模式
   ============================================ */

// ========== 设置Tab切换 ==========
function switchSettingsTab(tab) {
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.settings-tab[data-stab="${tab}"]`).classList.add('active');
    document.querySelectorAll('.settings-section').forEach(s => s.style.display = 'none');
    document.getElementById(`stab-${tab}`).style.display = 'block';

    if (tab === 'scripts') {
        loadScriptsForManagement();
        loadScriptCategoryOptions();
    } else if (tab === 'classes') {
        loadClassManagement();
    }
}

// ========== AI配置 ==========
function loadAiConfig() {
    fetch('/api/ai/config')
        .then(r => r.json())
        .then(config => {
            if (config && config.id) {
                document.getElementById('aiApiUrl').value = config.api_url || '';
                document.getElementById('aiApiKey').value = config.api_key || '';
                document.getElementById('aiModel').value = config.model || '';
                document.getElementById('aiSystemPrompt').value = config.system_prompt || '';
            }
        });
}

function saveAiConfig() {
    const data = {
        api_url: document.getElementById('aiApiUrl').value.trim(),
        api_key: document.getElementById('aiApiKey').value.trim(),
        model: document.getElementById('aiModel').value.trim(),
        system_prompt: document.getElementById('aiSystemPrompt').value.trim(),
        temperature: 0.7,
        max_tokens: 4000
    };

    fetch('/api/ai/config', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) showToast('AI配置已保存', 'success');
    });
}

function testAiConnection() {
    const data = {
        api_url: document.getElementById('aiApiUrl').value.trim(),
        api_key: document.getElementById('aiApiKey').value.trim(),
        model: document.getElementById('aiModel').value.trim()
    };
    const resultEl = document.getElementById('aiTestResult');
    resultEl.innerHTML = '<div class="spinner"></div> 测试中...';

    fetch('/api/ai/test', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        resultEl.innerHTML = result.success
            ? '<span style="color:var(--success)">✅ 连接成功！</span>'
            : `<span style="color:var(--danger)">❌ ${result.error}</span>`;
    });
}

// ========== 导入导出 ==========
function previewImport() {
    const fileInput = document.getElementById('importFile');
    const file = fileInput.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('grade', document.getElementById('importGrade').value);

    const previewArea = document.getElementById('importPreviewArea');
    previewArea.innerHTML = '<div style="padding:20px;text-align:center"><div class="spinner"></div> 解析中...</div>';

    fetch('/api/students/import/preview', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            let html = `<div style="margin:12px 0;font-size:14px;color:var(--text-secondary)">共 ${result.total_rows} 行数据，预览前10行：</div>`;
            html += '<table class="preview-table"><thead><tr>';
            result.columns.forEach(c => { html += `<th>${c}</th>`; });
            html += '</tr></thead><tbody>';
            result.preview.forEach(row => {
                html += '<tr>';
                result.columns.forEach(c => { html += `<td>${row[c] || ''}</td>`; });
                html += '</tr>';
            });
            html += '</tbody></table>';
            previewArea.innerHTML = html;
            document.getElementById('confirmImportBtn').style.display = 'inline-flex';
        } else {
            previewArea.innerHTML = `<div style="color:var(--danger)">❌ ${result.error}</div>`;
        }
    });
}

function confirmImport() {
    const fileInput = document.getElementById('importFile');
    const file = fileInput.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('grade', document.getElementById('importGrade').value);

    const btn = document.getElementById('confirmImportBtn');
    btn.disabled = true;
    btn.textContent = '⏳ 导入中...';

    fetch('/api/students/import', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(result => {
        btn.disabled = false;
        btn.textContent = '✅ 确认导入';
        if (result.success) {
            showToast(result.message, 'success');
            document.getElementById('importPreviewArea').innerHTML = '';
            document.getElementById('confirmImportBtn').style.display = 'none';
            fileInput.value = '';
            loadGradeCards();
        } else {
            showToast(result.error, 'error');
        }
    });
}

function exportStudents(format) {
    const grade = document.getElementById('exportGrade').value;

    fetch('/api/students/export', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({grade, format})
    })
    .then(r => {
        if (r.headers.get('content-type')?.includes('application/json')) {
            return r.json().then(d => { throw new Error(d.error); });
        }
        return r.blob();
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `学生数据_${new Date().toISOString().slice(0,10)}.${format === 'csv' ? 'csv' : 'xlsx'}`;
        a.click();
        window.URL.revokeObjectURL(url);
        showToast('导出成功', 'success');
    })
    .catch(err => showToast('导出失败: ' + err.message, 'error'));
}

// ========== 话术管理 ==========
function loadScriptCategoryOptions() {
    fetch('/api/scripts/categories')
        .then(r => r.json())
        .then(categories => {
            const select = document.getElementById('scriptFilterCategory');
            const currentVal = select.value;
            select.innerHTML = '<option value="">全部分类</option>' +
                categories.map(c => `<option value="${c}">${c}</option>`).join('');
            select.value = currentVal;

            const datalist = document.getElementById('scriptCategoryList');
            datalist.innerHTML = categories.map(c => `<option value="${c}">`).join('');
        });
}

function loadScriptsForManagement() {
    const category = document.getElementById('scriptFilterCategory').value;
    let url = '/api/scripts';
    if (category) url += `?category=${encodeURIComponent(category)}`;

    fetch(url)
        .then(r => r.json())
        .then(scripts => {
            const container = document.getElementById('scriptManagementList');
            if (scripts.length === 0) {
                container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted)">暂无话术，点击"新增话术"添加</div>';
                return;
            }
            container.innerHTML = `<div class="script-management-list">${scripts.map((s, idx) => `
                <div class="script-mgmt-item">
                    <div>
                        <div class="title">#${s.sort_order} ${s.title}</div>
                        <div class="meta">${s.category} · ${s.content.substring(0, 50)}${s.content.length > 50 ? '...' : ''}</div>
                    </div>
                    <div class="actions">
                        <button class="fluent-btn small" onclick="moveScript(${s.id}, 'up')" ${idx === 0 ? 'disabled' : ''}>↑</button>
                        <button class="fluent-btn small" onclick="moveScript(${s.id}, 'down')" ${idx === scripts.length - 1 ? 'disabled' : ''}>↓</button>
                        <button class="fluent-btn small" onclick="editScript(${s.id})">✏️</button>
                        <button class="fluent-btn small danger" onclick="deleteScript(${s.id})">🗑️</button>
                    </div>
                </div>
            `).join('')}</div>`;
        });
}

let editingScriptId = null;

function moveScript(scriptId, direction) {
    fetch(`/api/scripts/${scriptId}/move`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({direction})
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            loadScriptsForManagement();
            loadChatScripts && loadChatScripts();
        }
    });
}

function showScriptEditor() {
    editingScriptId = null;
    document.getElementById('scriptEditorTitle').textContent = '新增话术';
    document.getElementById('scriptEditTitle').value = '';
    document.getElementById('scriptEditCategory').value = '通用';
    document.getElementById('scriptEditContent').value = '';
    document.getElementById('scriptEditorModal').classList.add('active');
}

function editScript(scriptId) {
    fetch('/api/scripts')
        .then(r => r.json())
        .then(scripts => {
            const script = scripts.find(s => s.id === scriptId);
            if (!script) return;
            editingScriptId = scriptId;
            document.getElementById('scriptEditorTitle').textContent = '编辑话术';
            document.getElementById('scriptEditTitle').value = script.title;
            document.getElementById('scriptEditCategory').value = script.category;
            document.getElementById('scriptEditContent').value = script.content;
            document.getElementById('scriptEditorModal').classList.add('active');
        });
}

function closeScriptEditor() {
    document.getElementById('scriptEditorModal').classList.remove('active');
}

function saveScriptEditor() {
    const title = document.getElementById('scriptEditTitle').value.trim();
    const category = document.getElementById('scriptEditCategory').value.trim() || '通用';
    const content = document.getElementById('scriptEditContent').value.trim();

    if (!title || !content) {
        showToast('请填写标题和内容', 'error');
        return;
    }

    const url = editingScriptId ? `/api/scripts/${editingScriptId}` : '/api/scripts';
    const method = editingScriptId ? 'PUT' : 'POST';
    const body = {title, category, content};

    fetch(url, {
        method,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast(editingScriptId ? '话术已更新' : '话术已添加', 'success');
            closeScriptEditor();
            loadScriptsForManagement();
            loadScriptCategoryOptions();
            // 刷新聊天侧边栏
            if (typeof loadChatScriptCategories === 'function') loadChatScriptCategories();
            if (typeof loadChatScripts === 'function') loadChatScripts();
        }
    });
}

function deleteScript(scriptId) {
    if (!confirm('确定删除此话术吗？')) return;
    fetch(`/api/scripts/${scriptId}`, {method: 'DELETE'})
        .then(r => r.json())
        .then(() => {
            showToast('话术已删除', 'success');
            loadScriptsForManagement();
            loadScriptCategoryOptions();
            if (typeof loadChatScriptCategories === 'function') loadChatScriptCategories();
            if (typeof loadChatScripts === 'function') loadChatScripts();
        });
}

// ========== 全局搜索 ==========
let globalSearchGrade = '';

function filterGlobalSearchGrade(grade) {
    globalSearchGrade = grade;
    document.querySelectorAll('[data-gsgrade]').forEach(b => {
        b.classList.toggle('active', b.dataset.gsgrade === grade);
    });
    globalSearch();
}

function globalSearch() {
    const keyword = document.getElementById('globalSearchKeyword').value.trim();
    let url = '/api/students?';
    if (keyword) url += `keyword=${encodeURIComponent(keyword)}&`;
    if (globalSearchGrade) url += `grade=${globalSearchGrade}&`;

    fetch(url)
        .then(r => r.json())
        .then(students => {
            const container = document.getElementById('globalSearchResults');
            if (students.length === 0) {
                container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted)">未找到匹配的学生</div>';
                return;
            }
            container.innerHTML = `<div style="font-size:13px;color:var(--text-secondary);margin-bottom:8px">找到 ${students.length} 名学生</div>` +
                '<div class="student-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">' +
                students.map(s => {
                    let statusLabel = '未续班', statusColor = '';
                    if (s.continuation_status === 'continued') { statusLabel = '已续班'; statusColor = '#0078D4'; }
                    else if (s.continuation_status === 'no_condition') { statusLabel = '无条件'; statusColor = '#D13438'; }
                    return `<div class="student-card" onclick="switchMode('analysis');setTimeout(()=>enterGrade('${s.grade}'),100)">
                        <div class="student-card-name" style="color:${statusColor}">${s.name}</div>
                        <div class="student-card-info"><span>${s.grade || '?'}</span><span style="color:${statusColor}">${statusLabel}</span></div>
                    </div>`;
                }).join('') + '</div>';
        });
}

// ========== 修改密码 ==========
function changePassword() {
    const oldPwd = document.getElementById('oldPassword').value;
    const newPwd = document.getElementById('newPassword').value;
    const confirmPwd = document.getElementById('confirmPassword').value;
    const resultEl = document.getElementById('passwordResult');

    if (!oldPwd || !newPwd) { resultEl.textContent = '请填写密码'; resultEl.style.color = 'var(--danger)'; return; }
    if (newPwd !== confirmPwd) { resultEl.textContent = '两次密码不一致'; resultEl.style.color = 'var(--danger)'; return; }
    if (newPwd.length < 4) { resultEl.textContent = '密码至少4位'; resultEl.style.color = 'var(--danger)'; return; }

    fetch('/api/auth/change_password', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({old_password: oldPwd, new_password: newPwd})
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            resultEl.textContent = '✅ 密码修改成功';
            resultEl.style.color = 'var(--success)';
            document.getElementById('oldPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        } else {
            resultEl.textContent = '❌ ' + result.error;
            resultEl.style.color = 'var(--danger)';
        }
    });
}

// ========== 班级管理 ==========
let editingClassId = null;

function loadClassManagement() {
    fetch('/api/classes')
        .then(r => r.json())
        .then(classes => {
            const container = document.getElementById('classManagementList');
            if (classes.length === 0) {
                container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted)">暂无班级，点击"新建班级"创建</div>';
                return;
            }
            container.innerHTML = classes.map(c => `
                <div class="script-mgmt-item">
                    <div>
                        <div class="title">${c.name}</div>
                        <div class="meta">班号: ${c.class_number||'-'} · 班型: ${c.class_type} · ${c.lesson_count}次课 · ${c.student_count||0}名学生</div>
                    </div>
                    <div class="actions">
                        <button class="fluent-btn small" onclick="editClass(${c.id})">✏️</button>
                        <button class="fluent-btn small danger" onclick="deleteClass(${c.id})">🗑️</button>
                    </div>
                </div>
            `).join('');
        });
}

function showClassEditor() {
    editingClassId = null;
    document.getElementById('classEditorTitle').textContent = '新建班级';
    document.getElementById('classEditName').value = '';
    document.getElementById('classEditNumber').value = '';
    document.getElementById('classEditType').value = 'A1';
    document.getElementById('classEditLessons').value = '5';
    document.getElementById('classEditorModal').classList.add('active');
}

function editClass(classId) {
    fetch('/api/classes')
        .then(r => r.json())
        .then(classes => {
            const cls = classes.find(c => c.id === classId);
            if (!cls) return;
            editingClassId = classId;
            document.getElementById('classEditorTitle').textContent = '编辑班级';
            document.getElementById('classEditName').value = cls.name;
            document.getElementById('classEditNumber').value = cls.class_number || '';
            document.getElementById('classEditType').value = cls.class_type;
            document.getElementById('classEditLessons').value = cls.lesson_count;
            document.getElementById('classEditorModal').classList.add('active');
        });
}

function closeClassEditor() {
    document.getElementById('classEditorModal').classList.remove('active');
    editingClassId = null;
    window._editingClassId = null;
}

function saveClassEditor() {
    const name = document.getElementById('classEditName').value.trim();
    const class_number = document.getElementById('classEditNumber').value.trim();
    const class_type = document.getElementById('classEditType').value;
    const lesson_count = parseInt(document.getElementById('classEditLessons').value) || 5;

    if (!name) { showToast('请输入班级名称', 'error'); return; }

    // 支持从设置和学员分析两个入口编辑
    const targetId = editingClassId || window._editingClassId || null;
    const url = targetId ? `/api/classes/${targetId}` : '/api/classes';
    const method = targetId ? 'PUT' : 'POST';
    const body = {name, class_number, class_type, lesson_count};

    fetch(url, {method, headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)})
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                showToast(targetId ? '班级已更新' : '班级已创建', 'success');
                closeClassEditor();
                editingClassId = null;
                window._editingClassId = null;
                loadClassManagement();
                if (typeof loadGradeCards === 'function') loadGradeCards();
                // 如果在学员分析中，刷新当前班级视图
                if (currentClassId && typeof enterClass === 'function') {
                    enterClass(currentClassId);
                }
            }
        });
}

function deleteClass(classId) {
    if (!confirm('确定删除此班级？班级内的学生将变为未分配状态。')) return;
    fetch(`/api/classes/${classId}`, {method: 'DELETE'})
        .then(r => r.json())
        .then(() => {
            showToast('班级已删除', 'success');
            loadClassManagement();
            if (typeof loadGradeCards === 'function') loadGradeCards();
        });
}

// ========== 设置区AI对话 ==========
function sendSettingsAiMsg() {
    const input = document.getElementById('settingsAiInput');
    const text = input.value.trim();
    if (!text) return;

    const container = document.getElementById('settingsAiMsgs');
    const userDiv = document.createElement('div');
    userDiv.className = 'chat-msg user-msg';
    userDiv.innerHTML = `<div class="msg-avatar">👤</div><div class="msg-bubble user">${text.replace(/\n/g, '<br>')}</div>`;
    container.appendChild(userDiv);
    container.scrollTop = container.scrollHeight;
    input.value = '';

    // 构建上下文，包含数据库信息
    fetch('/api/students/stats')
        .then(r => r.json())
        .then(stats => {
            fetch('/api/classes')
                .then(r => r.json())
                .then(classes => {
                    const classInfo = classes.map(c => `${c.name}(${c.class_type}) ${c.student_count}人 ${c.lesson_count}次课`).join('；');
                    const contextInfo = `当前数据库统计：
- 总学生数：${stats.total}
- 已续班：${stats.continuation?.continued || 0}人
- 无条件续班：${stats.continuation?.no_condition || 0}人
- 未续班：${stats.continuation?.none || 0}人
- 班级：${classInfo || '无'}

系统指令：你是数据管理助手，可以查询、统计、分析学生数据。当用户要求编辑数据时，给出明确的编辑建议（字段名和值）。请用Markdown格式输出。`;

                    const messages = [
                        {role: 'system', content: contextInfo}
                    ];

                    // 取最近对话
                    const msgs = container.querySelectorAll('.chat-msg');
                    Array.from(msgs).slice(-8).forEach(msg => {
                        const isUser = msg.classList.contains('user-msg');
                        const bubble = msg.querySelector('.msg-bubble');
                        if (bubble) {
                            const avatar = msg.querySelector('.msg-avatar');
                            if (avatar && avatar.textContent === '🔔') return;
                            messages.push({
                                role: isUser ? 'user' : 'assistant',
                                content: bubble.textContent.replace(/<br>/g, '\n').replace(/<[^>]*>/g, '')
                            });
                        }
                    });
                    messages.push({role: 'user', content: text});

                    const loading = document.createElement('div');
                    loading.className = 'chat-msg'; loading.id = 'settingsAiLoading';
                    loading.innerHTML = `<div class="msg-avatar">🤖</div><div class="msg-bubble"><div class="spinner"></div> 思考中...</div>`;
                    container.appendChild(loading);
                    container.scrollTop = container.scrollHeight;

                    fetch('/api/ai/chat', {
                        method: 'POST', headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({messages})
                    }).then(r => r.json()).then(result => {
                        document.getElementById('settingsAiLoading')?.remove();
                        const div = document.createElement('div');
                        div.className = 'chat-msg';
                        const content = result.success ? (typeof renderMarkdown === 'function' ? renderMarkdown(result.content) : result.content.replace(/\n/g, '<br>')) : '❌ ' + result.error;
                        div.innerHTML = `<div class="msg-avatar">🤖</div><div class="msg-bubble">${content}</div>`;
                        container.appendChild(div);
                        container.scrollTop = container.scrollHeight;
                    });
                });
        });
}

// ========== 用户管理 ==========
function loadUserManagement() {
    fetch('/api/users')
        .then(r => r.json())
        .then(users => {
            const container = document.getElementById('userManagementList');
            if (!users || users.length === 0) {
                container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted)">暂无用户</div>';
                return;
            }
            const roleMap = {'admin': '👑管理员', 'teacher': '👩‍🏫教师', 'assistant': '🤝助教'};
            container.innerHTML = users.map(u => `
                <div class="script-mgmt-item">
                    <div>
                        <div class="title">${u.display_name || u.username}</div>
                        <div class="meta">@${u.username} · ${roleMap[u.role] || u.role} · 创建于 ${u.created_at ? new Date(u.created_at).toLocaleDateString() : '-'}</div>
                    </div>
                    <div class="actions">
                        <select class="user-role-select" onchange="changeUserRole(${u.id}, this.value)">
                            <option value="admin" ${u.role==='admin'?'selected':''}>👑管理员</option>
                            <option value="teacher" ${u.role==='teacher'?'selected':''}>👩‍🏫教师</option>
                            <option value="assistant" ${u.role==='assistant'?'selected':''}>🤝助教</option>
                        </select>
                        <button class="fluent-btn small danger" onclick="deleteUser(${u.id})">🗑️</button>
                    </div>
                </div>
            `).join('');
        });
}

function showUserEditor() {
    document.getElementById('userEditorTitle').textContent = '添加用户';
    document.getElementById('userEditUsername').value = '';
    document.getElementById('userEditDisplay').value = '';
    document.getElementById('userEditPassword').value = '';
    document.getElementById('userEditRole').value = 'teacher';
    document.getElementById('userEditorModal').classList.add('active');
}

function closeUserEditor() {
    document.getElementById('userEditorModal').classList.remove('active');
}

function saveUserEditor() {
    const username = document.getElementById('userEditUsername').value.trim();
    const password = document.getElementById('userEditPassword').value;
    const display_name = document.getElementById('userEditDisplay').value.trim();
    const role = document.getElementById('userEditRole').value;

    if (!username || !password) { showToast('请填写用户名和密码', 'error'); return; }
    if (password.length < 4) { showToast('密码至少4位', 'error'); return; }

    fetch('/api/users', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({username, password, display_name: display_name || username, role})
    }).then(r => r.json()).then(result => {
        if (result.success) {
            showToast('用户已添加', 'success');
            closeUserEditor();
            loadUserManagement();
        } else {
            showToast(result.error, 'error');
        }
    });
}

function changeUserRole(userId, role) {
    fetch(`/api/users/${userId}/role`, {
        method: 'PUT', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({role})
    }).then(r => r.json()).then(result => {
        if (result.success) showToast('角色已更新', 'success');
        else showToast(result.error, 'error');
    });
}

function deleteUser(userId) {
    if (!confirm('确定删除此用户？')) return;
    fetch(`/api/users/${userId}`, {method: 'DELETE'})
        .then(r => r.json()).then(result => {
            if (result.success) { showToast('用户已删除', 'success'); loadUserManagement(); }
            else showToast(result.error, 'error');
        });
}