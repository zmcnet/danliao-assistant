/* ============================================
   单聊辅助 - 学员分析模式 (70%列表 + 30%AI)
   ============================================ */

let currentClassId = null;
let modalStudentData = null;
let modalMode = 'view';
let analysisSelectedStudents = [];

// ========== Markdown渲染 ==========
function renderMarkdown(text) {
    if (!text) return '';
    let html = text
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/### (.+)/g, '<h3>$1</h3>')
        .replace(/## (.+)/g, '<h2>$1</h2>')
        .replace(/# (.+)/g, '<h1>$1</h1>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/```(\w*)\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>')
        .replace(/`(.+?)`/g, '<code>$1</code>')
        .replace(/^- (.+)/gm, '<li>$1</li>')
        .replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>')
        .replace(/\n/g, '<br>');
    return html;
}

// ========== 获取视图模式 ==========
function getClassViewMode() {
    return localStorage.getItem('classViewMode') || 'list';
}

function setClassViewMode(mode) {
    localStorage.setItem('classViewMode', mode);
}

// ========== 初始化 ==========
function loadGradeCards() {
    const viewMode = getClassViewMode();
    fetch('/api/classes')
        .then(r => r.json())
        .then(classes => {
            const container = document.getElementById('analysisGradeCards');
            if (!classes.length) {
                container.innerHTML = '<div class="empty-state" style="padding:40px"><div class="empty-icon">📭</div><div class="empty-text">暂无班级，请先在设置中创建</div></div>';
                return;
            }
            
            const toggleHtml = '<div class="view-toggle"><button class="vt-btn ' + (viewMode === 'list' ? 'active' : '') + '" onclick="switchClassView(\'list\')">📋 列表</button><button class="vt-btn ' + (viewMode === 'card' ? 'active' : '') + '" onclick="switchClassView(\'card\')">📐 卡片</button></div>';
            
            if (viewMode === 'card') {
                container.innerHTML = toggleHtml + classes.map(function(c) {
                    return '<div class="grade-card" onclick="enterClass(' + c.id + ')"><div class="grade-card-number">' + c.name + '</div><div class="grade-card-label">' + (c.class_number || '') + ' · ' + c.class_type + '</div><div class="grade-card-count">' + (c.student_count || 0) + '人 · ' + c.lesson_count + '次课</div></div>';
                }).join('');
            } else {
                container.innerHTML = toggleHtml + '<div class="class-list">' + classes.map(function(c) {
                    return '<div class="class-list-item" onclick="enterClass(' + c.id + ')"><div class="cli-icon">📚</div><div class="cli-info"><div class="cli-name">' + c.name + '</div><div class="cli-meta">' + (c.class_number || '-') + ' · ' + c.class_type + ' · ' + (c.student_count || 0) + '人 · ' + c.lesson_count + '次课</div></div><span class="cli-arrow">→</span></div>';
                }).join('') + '</div>';
            }
        });
}

function switchClassView(mode) {
    setClassViewMode(mode);
    loadGradeCards();
}

// ========== 进入班级 ==========
function enterClass(classId) {
    currentClassId = classId;
    fetch('/api/classes')
        .then(function(response) { return response.json(); })
        .then(function(classes) {
            var cls = classes.find(function(c) { return c.id === classId; });
            if (!cls) return;
            document.getElementById('analysisClassView').style.display = 'none';
            document.getElementById('analysisStudentListView').style.display = 'block';
            document.getElementById('analysisBreadcrumb').innerHTML = '<span class="bc-item" onclick="showGradeView()">📚 班级</span><span class="bc-sep">›</span><span class="bc-item active">' + cls.name + '<span class="bc-sub">' + (cls.class_number || '') + ' · ' + cls.class_type + ' · ' + cls.lesson_count + '次课</span></span><button class="bc-edit-btn" onclick="event.stopPropagation();quickEditClass(' + classId + ')" title="编辑班级">✏️</button>';
            document.getElementById('analysisAiTools').style.display = 'flex';
            // 学生视图切换按钮
            var svMode = localStorage.getItem('studentViewMode') || 'card';
            var sortArea = document.querySelector('.sort-controls');
            if (!document.getElementById('studentViewToggle')) {
                var toggleHtml = '<div class="view-toggle" id="studentViewToggle"><button class="vt-btn ' + (svMode === 'list' ? 'active' : '') + '" onclick="switchStudentView(\'list\')">📋 列表</button><button class="vt-btn ' + (svMode === 'card' ? 'active' : '') + '" onclick="switchStudentView(\'card\')">📐 卡片</button></div>';
                sortArea.insertAdjacentHTML('afterend', toggleHtml);
            }
            loadGradeStudents();
        });
}

function quickEditClass(classId) {
    fetch('/api/classes')
        .then(function(response) { return response.json(); })
        .then(function(classes) {
            var cls = classes.find(function(c) { return c.id === classId; });
            if (!cls) return;
            document.getElementById('classEditorTitle').textContent = '编辑班级';
            document.getElementById('classEditName').value = cls.name;
            document.getElementById('classEditNumber').value = cls.class_number || '';
            document.getElementById('classEditType').value = cls.class_type;
            document.getElementById('classEditLessons').value = cls.lesson_count;
            window._editingClassId = classId;
            document.getElementById('classEditorModal').classList.add('active');
        });
}

function showGradeView() {
    currentClassId = null;
    document.getElementById('analysisClassView').style.display = 'block';
    document.getElementById('analysisStudentListView').style.display = 'none';
    document.getElementById('backToGradesBtn').style.display = 'none';
    document.getElementById('analysisBreadcrumb').innerHTML = '<span class="bc-item active" onclick="showGradeView()">📚 班级列表</span>';
    document.getElementById('analysisAiTools').style.display = 'none';
    loadGradeCards();
}

function filterGradeStudents() { loadGradeStudents(); }
function applySort() { loadGradeStudents(); }

function switchStudentView(mode) {
    localStorage.setItem('studentViewMode', mode);
    document.querySelectorAll('#studentViewToggle .vt-btn').forEach(function(b) {
        b.classList.toggle('active', b.textContent.includes(mode === 'list' ? '列表' : '卡片'));
    });
    loadGradeStudents();
}

// ========== 加载学生 ==========
function loadGradeStudents() {
    var keyword = document.getElementById('analysisStudentSearch').value.trim();
    var sort_by = document.getElementById('sortBySelect').value;
    var sort_order = document.getElementById('sortOrderSelect').value;
    var svMode = localStorage.getItem('studentViewMode') || 'card';
    var url = '/api/students?class_id=' + currentClassId + '&sort_by=' + sort_by + '&sort_order=' + sort_order;
    if (keyword) url += '&keyword=' + encodeURIComponent(keyword);
    
    fetch(url)
        .then(function(response) { return response.json(); })
        .then(function(students) {
            analysisSelectedStudents = students;
            var grid = document.getElementById('analysisStudentGrid');
            if (!students.length) {
                grid.innerHTML = '<div class="empty-state" style="padding:40px"><div class="empty-icon">📭</div></div>';
                return;
            }
            
            if (svMode === 'list') {
                grid.className = 'student-list-view';
                grid.innerHTML = students.map(function(s) {
                    var c = '', l = '';
                    if (s.continuation_status === 'continued') { c = 'color:#0078D4'; l = '🟦已续班'; }
                    else if (s.continuation_status === 'no_condition') { c = 'color:#D13438'; l = '🟥无条件'; }
                    else { l = '⬜未续班'; }
                    return '<div class="student-list-item" onclick="openStudentModal(' + s.id + ')"><div class="sli-icon">👤</div><div class="sli-info"><div class="sli-name" style="' + c + '">' + s.name + '</div><div class="sli-meta">' + (s.high_school || '-') + ' · 中考' + (s.zhongkao_score || '-') + ' · 科学' + (s.science_score || '-') + ' · ' + (s.subject_preference || '未选科') + '</div></div><span class="sli-status" style="' + c + '">' + l + '</span><span class="cli-arrow">→</span></div>';
                }).join('');
            } else {
                grid.className = 'student-grid';
                grid.innerHTML = students.map(function(s) {
                    var c = '', l = '';
                    if (s.continuation_status === 'continued') { c = 'color:#0078D4'; l = '已续班'; }
                    else if (s.continuation_status === 'no_condition') { c = 'color:#D13438'; l = '无条件'; }
                    return '<div class="student-card" onclick="openStudentModal(' + s.id + ')"><div class="student-card-name" style="' + c + '">' + s.name + '</div><div class="student-card-info"><span>' + (s.high_school || '-') + '</span><span style="' + c + '">' + l + '</span></div><div class="student-card-detail"><span>中考' + (s.zhongkao_score || '-') + '</span><span>科学' + (s.science_score || '-') + '</span><span>选科' + (s.subject_preference || '-') + '</span></div></div>';
                }).join('');
            }
        });
}

// ========== Markdown渲染（备用）==========
function renderMarkdownV2(text) {
    if (!text) return '';
    var html = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/### (.+)/g, '<h3>$1</h3>')
        .replace(/## (.+)/g, '<h2>$1</h2>')
        .replace(/# (.+)/g, '<h1>$1</h1>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`(.+?)`/g, '<code>$1</code>')
        .replace(/^- (.+)/gm, '<li>$1</li>')
        .replace(/\n/g, '<br>');
    return html;
}

// ========== AI分析 ==========
function addAnalysisMsg(role, content) {
    var container = document.getElementById('analysisAiMsgs');
    var div = document.createElement('div');
    var isUser = (role === 'user');
    var avatar = isUser ? '👤' : (role === 'ai' ? '🤖' : '🔔');
    var rendered = (role === 'ai' && typeof renderMarkdownV2 === 'function') ? renderMarkdownV2(content) : content.replace(/\n/g, '<br>');
    div.className = 'chat-msg ' + (isUser ? 'user-msg' : '');
    div.innerHTML = '<div class="msg-avatar">' + avatar + '</div><div class="msg-bubble ' + (isUser ? 'user' : '') + '">' + rendered + '</div>';
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function sendAnalysisMsg() {
    var input = document.getElementById('analysisAiInput');
    var text = input.value.trim();
    if (!text) return;
    addAnalysisMsg('user', text);
    input.value = '';
    var messages = [];
    var contextInfo = '当前班级学生列表：\n';
    if (analysisSelectedStudents.length > 0) {
        analysisSelectedStudents.slice(0, 20).forEach(function(s) {
            contextInfo += '- ' + s.name + ' (' + (s.grade || '?') + ') 中考' + (s.zhongkao_score || '-') + ' 科学' + (s.science_score || '-') + ' 选科' + (s.subject_preference || '-') + ' 续班:' + (s.continuation_status || '未续班') + '\n';
        });
        if (analysisSelectedStudents.length > 20) contextInfo += '...' + analysisSelectedStudents.length + '人';
    }
    messages.push({ role: 'system', content: '你是一位教育AI助手，辅助教师管理学生数据。当前在学员分析模式。\n\n' + contextInfo + '\n你可以：\n1. 分析学生数据\n2. 生成统计报告\n3. 建议编辑学生信息\n4. 回答教育相关问题\n请用Markdown格式输出。' });
    var msgs = document.querySelectorAll('#analysisAiMsgs .chat-msg');
    Array.from(msgs).slice(-6).forEach(function(msg) {
        var isUserMsg = msg.classList.contains('user-msg');
        var bubble = msg.querySelector('.msg-bubble');
        if (bubble) {
            var avatar = msg.querySelector('.msg-avatar');
            if (avatar && avatar.textContent === '🔔') return;
            messages.push({ role: isUserMsg ? 'user' : 'assistant', content: bubble.textContent.replace(/<br>/g, '\n').replace(/<[^>]*>/g, '') });
        }
    });
    messages.push({ role: 'user', content: text });
    var loading = document.createElement('div');
    loading.className = 'chat-msg'; loading.id = 'analysisAiLoading';
    loading.innerHTML = '<div class="msg-avatar">🤖</div><div class="msg-bubble"><div class="spinner"></div> 思考中...</div>';
    document.getElementById('analysisAiMsgs').appendChild(loading);
    fetch('/api/ai/chat', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({messages: messages}) })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            var loadingEl = document.getElementById('analysisAiLoading');
            if (loadingEl) loadingEl.remove();
            if (result.success) addAnalysisMsg('ai', result.content);
            else addAnalysisMsg('ai', '❌ ' + result.error);
        })
        .catch(function() {
            var loadingEl = document.getElementById('analysisAiLoading');
            if (loadingEl) loadingEl.remove();
            addAnalysisMsg('ai', '❌ 请求失败');
        });
}

function aiAnalyzeCurrent() {
    if (!currentClassId) { showToast('请先选择一个班级', 'error'); return; }
    var msg = '请对当前班级的学生数据进行整体分析，包括：\n1. 班级整体学情概况\n2. 续班情况分析\n3. 选科分布统计\n4. 建议关注的学生';
    document.getElementById('analysisAiInput').value = msg; sendAnalysisMsg();
}

function aiBatchAnalyze() {
    if (!currentClassId) { showToast('请先选择一个班级', 'error'); return; }
    var msg = '请对当前班级所有学生进行批量分析，按续班状态分类：\n1. 已续班学生：有哪些共同特点？\n2. 无条件续班学生：问题在哪里？\n3. 未续班学生：哪些有续班潜力？建议策略是什么？';
    document.getElementById('analysisAiInput').value = msg; sendAnalysisMsg();
}

// ========== AI辅助导入 ==========
function aiImportData(fileInput) {
    var file = fileInput.files[0];
    if (!file) return;
    var grade = prompt('导入年级 (如: 085):', '085');
    if (!grade) { fileInput.value = ''; return; }
    var formData = new FormData(); formData.append('file', file); formData.append('grade', grade);
    addAnalysisMsg('user', '上传文件: ' + file.name + '，导入到 ' + grade + ' 班');
    fetch('/api/students/import/preview', { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            if (result.success) {
                addAnalysisMsg('ai', '📄 检测到文件 **' + file.name + '**\n\n- 行数: ' + result.total_rows + '\n- 字段: ' + result.columns.join(', ') + '\n\n是否确认导入？回复"确认导入"即可。');
                window._pendingImport = { formData: formData, file: file.name, grade: grade };
            } else { addAnalysisMsg('ai', '❌ 解析失败: ' + result.error); }
        })
        .catch(function() { addAnalysisMsg('ai', '❌ 文件读取失败'); })
        .finally(function() { fileInput.value = ''; });
}

// ========== 处理导入确认 ==========
document.addEventListener('click', function(e) {
    var bubble = e.target.closest('.msg-bubble');
    if (bubble && window._pendingImport) {
        var text = bubble.textContent;
        if (text.includes('确认导入') || text.includes('导入')) {
            var pending = window._pendingImport;
            window._pendingImport = null;
            fetch('/api/students/import', { method: 'POST', body: pending.formData })
                .then(function(response) { return response.json(); })
                .then(function(res) {
                    if (res.success) { addAnalysisMsg('ai', '✅ 成功导入 ' + res.count + ' 名学生到 ' + pending.grade + '!'); loadGradeCards(); }
                    else addAnalysisMsg('ai', '❌ 导入失败: ' + res.error);
                });
        }
    }
});

// ========== 可编辑学生弹窗 ==========
function openStudentModal(studentId) {
    modalMode = 'view';
    modalStudentData = null;
    fetch('/api/students/' + studentId)
        .then(function(response) { return response.json(); })
        .then(function(s) {
            modalStudentData = s;
            document.getElementById('studentModalTitle').textContent = s.name;
            document.getElementById('studentModal').classList.add('active');
            document.querySelectorAll('.ve-btn').forEach(function(b) { b.classList.toggle('active', b.dataset.mode === 'view'); });
            document.getElementById('saveEditBtn').style.display = 'none';
            renderViewMode(s);
            renderEditMode(s);
            switchModalMode('view');
        });
}

function switchModalMode(mode) {
    modalMode = mode;
    document.querySelectorAll('.ve-btn').forEach(function(b) { b.classList.toggle('active', b.dataset.mode === mode); });
    document.getElementById('modalViewMode').style.display = (mode === 'view') ? 'block' : 'none';
    document.getElementById('modalEditMode').style.display = (mode === 'edit') ? 'block' : 'none';
    document.getElementById('saveEditBtn').style.display = (mode === 'edit') ? 'inline-flex' : 'none';
}

function renderViewMode(s) {
    var statusLabel = '未续班', statusColor = '';
    if (s.continuation_status === 'continued') { statusLabel = '已续班'; statusColor = '#0078D4'; }
    else if (s.continuation_status === 'no_condition') { statusLabel = '无条件续班'; statusColor = '#D13438'; }
    document.getElementById('modalViewName').textContent = s.name;
    document.getElementById('modalViewName').style.color = statusColor;
    document.getElementById('modalViewGrade').textContent = (s.grade || '?') + '班';
    document.getElementById('modalViewStatus').textContent = statusLabel;
    document.getElementById('modalViewStatus').style.background = statusColor ? (statusColor + '15') : '#f5f5f5';
    document.getElementById('modalViewStatus').style.color = statusColor || 'var(--text-secondary)';
    
    var fields = [
        ['初中学校','middle_school'],['高中学校','high_school'],
        ['中考成绩','zhongkao_score'],['科学成绩','science_score'],
        ['初中文理','middle_school_type'],['选科倾向','subject_preference'],
        ['错题类型','wrong_questions'],['学生反馈','feedback'],
        ['续班意向','continuation_intent']
    ];
    document.getElementById('modalViewBody').innerHTML = fields.map(function(f){
        var l = f[0], k = f[1]; var v = (s[k]||''); return '<div class="info-item"><span class="label">' + l + '</span><span class="value">' + v + '</span></div>';
    }).join('');
    
    var lessonCount = s.lesson_count || 5;
    document.getElementById('modalViewLessonCount').textContent = lessonCount;
    var hwLabels = ['第1次','第2次','第3次','第4次','第5次','第6次','第7次','第8次','第9次','第10次','第11次','第12次','第13次','第14次','第15次','第16次','第17次','第18次','第19次','第20次'];
    var hwContainer = document.getElementById('modalViewHomework'); hwContainer.innerHTML = '';
    for (var i = 1; i <= lessonCount; i++) {
        var key = 'l' + i + '_status'; var v = s[key] || ''; var ok = (v === '1');
        hwContainer.innerHTML += '<div class="info-item"><span class="label">' + (hwLabels[i-1] || '第' + i + '次') + '</span><span class="value"><span class="homework-status ' + (ok ? 'submitted' : 'unsubmitted') + '">' + (ok ? '✅已提交' : '❌未提交') + '</span>' + ((v && v !== '1') ? '<span style="font-size:12px;color:var(--text-muted);margin-left:4px">(' + v + ')</span>' : '') + '</span></div>';
    }
    
    var statusButtons = [{v:'none',l:'⬜未续班'},{v:'continued',l:'🟦已续班'},{v:'no_condition',l:'🟥无条件续班'}];
    document.getElementById('modalViewStatusBtns').innerHTML = statusButtons.map(function(x) {
        return '<span class="status-badge ' + (s.continuation_status === x.v ? 'active-' + x.v : '') + '">' + x.l + '</span>';
    }).join('');
}

function renderEditMode(s) {
    var fields = [
        ['name','姓名'],['middle_school','初中学校'],['high_school','高中学校'],
        ['zhongkao_score','中考成绩'],['science_score','科学成绩'],
        ['middle_school_type','初中文理'],['subject_preference','选科倾向'],
        ['wrong_questions','错题类型'],['feedback','学生反馈'],
        ['continuation_intent','续班意向'],['grade','年级']
    ];
    document.getElementById('modalEditBody').innerHTML = fields.map(function(f){
        var k = f[0], l = f[1]; var val = (s[k]||'').replace(/"/g, '&quot;');
        return '<div class="edit-field"><label class="edit-label">' + l + '</label><input class="edit-input" data-field="' + k + '" value="' + val + '"> </div>';
    }).join('');
    
    var lessonCount = s.lesson_count || 5;
    document.getElementById('modalEditLessonCount').textContent = lessonCount;
    var hwLabels = ['第1次','第2次','第3次','第4次','第5次','第6次','第7次','第8次','第9次','第10次','第11次','第12次','第13次','第14次','第15次','第16次','第17次','第18次','第19次','第20次'];
    var hwContainer = document.getElementById('modalEditHomework'); hwContainer.innerHTML = '';
    for (var i = 1; i <= lessonCount; i++) {
        var key = 'l' + i + '_status'; var v = s[key] || '';
        var sel1 = (v === '1') ? 'selected' : '';
        var sel0 = (v === '') ? 'selected' : '';
        hwContainer.innerHTML += '<div class="edit-field"><label class="edit-label">' + (hwLabels[i-1] || '第' + i + '次') + '</label><select class="edit-input" data-field="' + key + '"><option value=""' + sel0 + '>未提交</option><option value="1"' + sel1 + '>✅ 已提交</option></select></div>';
    }
    
    var statusButtons = [{v:'none',l:'⬜未续班'},{v:'continued',l:'🟦已续班'},{v:'no_condition',l:'🟥无条件续班'}];
    document.getElementById('modalEditStatus').innerHTML = statusButtons.map(function(x) {
        return '<button class="status-btn ' + (s.continuation_status === x.v ? 'active-' + x.v : '') + "' onclick=\"selectEditStatus('" + x.v + "')\">" + x.l + '</button>';
    }).join('');
    var hs = document.getElementById('modalEditStatusVal'); if (!hs) { hs = document.createElement('input'); hs.type='hidden'; hs.id='modalEditStatusVal'; document.getElementById('modalEditStatus').appendChild(hs); } hs.value = s.continuation_status;
}

function selectEditStatus(val) {
    document.querySelectorAll('#modalEditStatus .status-btn').forEach(function(b) { b.className = 'status-btn'; });
    document.querySelectorAll('#modalEditStatus .status-btn').forEach(function(b) {
        var txt = b.textContent;
        if (txt.includes((val === 'none' ? '未续班' : (val === 'continued' ? '已续班' : '无条件'))) ) { b.className = 'status-btn active-' + val; }
    });
    document.getElementById('modalEditStatusVal').value = val;
}

function saveModalEdits() {
    if (!modalStudentData) return;
    var data = {}; document.querySelectorAll('#modalEditMode .edit-input').forEach(function(el) { data[el.dataset.field] = el.value; });
    var sv = document.getElementById('modalEditStatusVal'); if (sv) data.continuation_status = sv.value;
    fetch('/api/students/' + modalStudentData.id, { method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            showToast('✅ 保存成功', 'success');
            fetch('/api/students/' + modalStudentData.id)
                .then(function(response) { return response.json(); })
                .then(function(s) {
                    modalStudentData = s;
                    renderViewMode(s); renderEditMode(s); switchModalMode('view');
                    loadGradeStudents(); loadGradeCards();
                });
        });
}

function closeStudentModal() { document.getElementById('studentModal').classList.remove('active'); modalStudentData = null; }

// ========== AI分析 ==========
function aiAnalyzeStudent() {
    var id = modalStudentData ? modalStudentData.id : null;
    if (!id) return; var chat = document.getElementById('analysisAiChat');
    var loading = document.createElement('div'); loading.className = 'chat-msg'; loading.id = 'aiModalLoading';
    loading.innerHTML = '<div class="msg-avatar">🤖</div><div class="msg-bubble"><div class="spinner"></div> 分析中...</div>'; chat.appendChild(loading); chat.scrollTop = chat.scrollHeight;
    fetch('/api/ai/analyze', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({student_id: id}) })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            document.getElementById('aiModalLoading').remove();
            var div = document.createElement('div'); div.className = 'chat-msg'; div.innerHTML = '<div class="msg-avatar">🤖</div><div class="msg-bubble">' + (result.success ? result.content.replace(/\n/g, '<br>') : '❌ ' + result.error) + '</div>';
            chat.appendChild(div); chat.scrollTop = chat.scrollHeight;
        });
}

function aiChatSuggestion() {
    var id = modalStudentData ? modalStudentData.id : null;
    if (!id) return; var chat = document.getElementById('analysisAiChat');
    var loading = document.createElement('div'); loading.className = 'chat-msg'; loading.id = 'aiModalLoading';
    loading.innerHTML = '<div class="msg-avatar">🤖</div><div class="msg-bubble"><div class="spinner"></div> 生成中...</div>'; chat.appendChild(loading); chat.scrollTop = chat.scrollHeight;
    fetch('/api/ai/chat_suggestion', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({student_id: id}) })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            document.getElementById('aiModalLoading').remove();
            var div = document.createElement('div'); div.className = 'chat-msg'; div.innerHTML = '<div class="msg-avatar">🤖</div><div class="msg-bubble">' + (result.success ? result.content.replace(/\n/g, '<br>') : '❌ ' + result.error) + '</div>';
            chat.appendChild(div); chat.scrollTop = chat.scrollHeight;
        });
}

// ========== 清空 ==========