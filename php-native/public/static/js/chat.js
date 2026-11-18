/* ============================================
   单聊辅助 - 单聊模式 (三栏 50:30:20)
   ============================================ */

var chatSelectedStudent = null;
var chatDebounceTimer = null;

function initChatMode() {
    loadChatScripts();
    loadChatScriptCategories();
}

// ========== 左栏: 搜索学生 ==========
function chatSearchInput() {
    clearTimeout(chatDebounceTimer);
    chatDebounceTimer = setTimeout(function() {
        var input = document.getElementById('chatSearchInput');
        if (!input) return;
        var keyword = input.value.trim();
        if (!keyword) {
            var container = document.getElementById('chatStudentResults');
            if (container) container.innerHTML = '';
            return;
        }
        fetch('/api/students?keyword=' + encodeURIComponent(keyword))
            .then(function(r) { return r.json(); })
            .then(function(students) {
                var container = document.getElementById('chatStudentResults');
                if (!container) return;
                if (students.length === 0) {
                    container.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-muted);font-size:13px">未找到匹配的学生</div>';
                    return;
                }
                var html = '';
                for (var i = 0; i < students.length; i++) {
                    var s = students[i];
                    var c = '';
                    if (s.continuation_status === 'continued') c = 'color:#0078D4;';
                    else if (s.continuation_status === 'no_condition') c = 'color:#D13438;';
                    var isExpanded = (chatSelectedStudent && chatSelectedStudent.id === s.id);
                    var wrapperClass = isExpanded ? 'chat-student-wrapper expanded' : 'chat-student-wrapper';
                    var icon = isExpanded ? '&#x25BC;' : '&#x25B6;';
                    html += '<div class="' + wrapperClass + '">';
                    html += '<div class="chat-student-item" onclick="selectChatStudent(' + s.id + ')">';
                    html += '<div class="chat-student-name" style="' + c + '">' + escHtml(s.name) + '</div>';
                    html += '<div class="chat-student-meta"><span>' + (s.grade || '?') + '</span><span>' + (s.high_school || '-') + '</span></div>';
                    html += '<span class="chat-expand-icon">' + icon + '</span>';
                    html += '</div>';
                    if (isExpanded) {
                        html += buildStudentDetail(s);
                    }
                    html += '</div>';
                }
                container.innerHTML = html;
            });
    }, 300);
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ========== 学生详情面板（展开在下方） ==========
function buildStudentDetail(s) {
    if (!s) return '';
    var fields = [
        ['name','姓名'],['middle_school','初中'],['high_school','高中'],
        ['zhongkao_score','中考'],['science_score','科学'],
        ['subject_preference','选科'],['wrong_questions','错题'],
        ['feedback','反馈'],['continuation_intent','续班意向']
    ];
    var infoFields = '';
    for (var f = 0; f < fields.length; f++) {
        var k = fields[f][0], l = fields[f][1];
        var val = (s[k] || '');
        infoFields += '<div class="csd-field"><span class="csd-label">' + l + '</span><input class="csd-input" data-field="' + k + '" value="' + escHtml(val) + '" data-sid="' + s.id + '"></div>';
    }
    
    var lessonCount = s.lesson_count || 5;
    var hwFields = '';
    var hwLabels = ['第1次','第2次','第3次','第4次','第5次','第6次','第7次','第8次','第9次','第10次','第11次','第12次','第13次','第14次','第15次','第16次','第17次','第18次','第19次','第20次'];
    for (var i = 1; i <= lessonCount; i++) {
        var key = 'l' + i + '_status';
        var v = s[key] || '';
        var label = (i <= 10) ? hwLabels[i-1] : ('第' + i + '次');
        var sel1 = (v === '1') ? 'selected' : '';
        var sel0 = (v === '') ? 'selected' : '';
        hwFields += '<div class="csd-field"><span class="csd-label">' + label + '</span><select class="csd-input" data-field="' + key + '" data-sid="' + s.id + '"><option value=""' + sel0 + '>未提交</option><option value="1"' + sel1 + '>✅ 已提交</option></select></div>';
    }
    
    var status = s.continuation_status || 'none';
    var statusBtns = '';
    var btnConfig = [{v:'none',l:'⬜未续班'},{v:'continued',l:'🟦已续班'},{v:'no_condition',l:'🟥无条件'}];
    for (var b = 0; b < btnConfig.length; b++) {
        var x = btnConfig[b];
        var active = (status === x.v) ? 'active-' + x.v : '';
        statusBtns += '<button class="csd-status-btn ' + active + '" onclick="quickUpdateStatus(' + s.id + ',\'' + x.v + '\')">' + x.l + '</button>';
    }
    
    return '<div class="chat-student-detail">' +
        '<div class="csd-section"><div class="csd-grid">' + infoFields + '</div></div>' +
        '<div class="csd-section"><div class="csd-section-title">📝 作业提交</div><div class="csd-grid" style="grid-template-columns:1fr 1fr">' + hwFields + '</div></div>' +
        '<div class="csd-section"><div class="csd-section-title">🎯 续班状态</div><div class="csd-status-group">' + statusBtns + '</div></div>' +
        '<div class="csd-actions">' +
        '<button class="fluent-btn primary small" onclick="saveChatStudentEdits(' + s.id + ')">💾 保存</button>' +
        '<button class="fluent-btn small" onclick="copyChatStudentInfo(' + s.id + ')">📋 复制信息</button>' +
        '<button class="fluent-btn small" onclick="aiChatSuggestion()">🤖 AI建议</button>' +
        '</div></div>';
}

// ========== 选中学生 - 展开/折叠 ==========
function selectChatStudent(studentId) {
    if (chatSelectedStudent && chatSelectedStudent.id === studentId) {
        chatSelectedStudent = null;
        var ctxEl = document.getElementById('chatContextStudent');
        if (ctxEl) ctxEl.textContent = '';
        chatSearchInput();
        return;
    }
    
    fetch('/api/students/' + studentId)
        .then(function(r) { return r.json(); })
        .then(function(student) {
            chatSelectedStudent = student;
            var ctxEl = document.getElementById('chatContextStudent');
            if (ctxEl) ctxEl.textContent = '当前: ' + (student.name || '') + ' (' + (student.grade || '?') + ')';
            chatSearchInput();
            
            var infoText = '已选择学生：' + (student.name || '');
            if (student.high_school) infoText += '\n📋 学校：' + student.high_school;
            if (student.zhongkao_score) infoText += '\n📊 中考：' + student.zhongkao_score;
            if (student.science_score) infoText += ' | 科学：' + student.science_score;
            if (student.subject_preference) infoText += '\n🎯 选科倾向：' + student.subject_preference;
            if (student.feedback) infoText += '\n💬 反馈：' + student.feedback;
            var statusMap = {'continued': '已续班 🟦', 'no_condition': '无条件续班 🟥'};
            infoText += '\n📝 续班：' + (statusMap[student.continuation_status] || '未续班 ⬜');
            
            addMsg('system', '已展开学生「' + (student.name || '') + '」的信息');
            addMsg('ai', '已收到 ' + (student.name || '') + ' 的信息，我来辅助你进行单聊分析。\n\n' + infoText + '\n\n请问你想了解什么？\n1. 该学生的学情分析\n2. 沟通策略建议\n3. 续班话术建议');
        })
        .catch(function(err) { console.error('selectChatStudent error:', err); });
}

// ========== 快速保存编辑 ==========
function saveChatStudentEdits(studentId) {
    var data = {};
    var inputs = document.querySelectorAll('.csd-input[data-sid="' + studentId + '"]');
    for (var i = 0; i < inputs.length; i++) {
        data[inputs[i].dataset.field] = inputs[i].value;
    }
    fetch('/api/students/' + studentId, {
        method: 'PUT', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(function(r) { return r.json(); }).then(function() {
        showToast('✅ 保存成功', 'success');
        fetch('/api/students/' + studentId).then(function(r) { return r.json(); }).then(function(s) {
            chatSelectedStudent = s;
            chatSearchInput();
        });
    });
}

function quickUpdateStatus(studentId, status) {
    fetch('/api/students/' + studentId, {
        method: 'PUT', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({continuation_status: status})
    }).then(function() {
        showToast('续班状态已更新', 'success');
        fetch('/api/students/' + studentId).then(function(r) { return r.json(); }).then(function(s) {
            chatSelectedStudent = s;
            chatSearchInput();
        });
    });
}

function copyChatStudentInfo(studentId) {
    fetch('/api/students/' + studentId)
        .then(function(r) { return r.json(); })
        .then(function(s) {
            var text = '学生：' + s.name + '\n年级：' + (s.grade || '?') + ' | 高中：' + (s.high_school || '-') + '\n中考：' + (s.zhongkao_score || '-') + ' | 科学：' + (s.science_score || '-') + '\n选科：' + (s.subject_preference || '-') + ' | 反馈：' + (s.feedback || '-') + '\n续班状态：' + (s.continuation_status === 'continued' ? '已续班' : s.continuation_status === 'no_condition' ? '无条件续班' : '未续班');
            copyToClipboard(text);
        });
}

// ========== AI建议（聊天模式专用） ==========
function aiChatSuggestion() {
    var student = chatSelectedStudent;
    if (!student) { showToast('请先选择一名学生', 'error'); return; }
    
    addMsg('user', '请为 ' + student.name + ' 生成沟通建议');
    
    var infoText = '学生：' + student.name + '\n高中：' + (student.high_school || '-') + '\n中考：' + (student.zhongkao_score || '-') + ' | 科学：' + (student.science_score || '-') + '\n选科倾向：' + (student.subject_preference || '-') + '\n反馈：' + (student.feedback || '-') + '\n续班：' + (student.continuation_status === 'continued' ? '已续班' : student.continuation_status === 'no_condition' ? '无条件续班' : '未续班');
    
    var messages = [
        {role: 'system', content: '你是一位经验丰富的教育辅导老师，擅长与学生和家长沟通。请根据学生信息提供针对性的沟通建议。用Markdown格式输出。'},
        {role: 'user', content: '请为以下学生准备一对一沟通建议：\n\n' + infoText + '\n\n请提供：\n1. 开场白建议\n2. 沟通要点\n3. 话术建议\n4. 注意事项'}
    ];
    
    var loadingDiv = document.createElement('div');
    loadingDiv.className = 'chat-msg';
    loadingDiv.id = 'chatAiSuggestionLoading';
    loadingDiv.innerHTML = '<div class="msg-avatar">🤖</div><div class="msg-bubble"><div class="spinner"></div> 生成建议中...</div>';
    document.getElementById('chatMessages').appendChild(loadingDiv);
    
    fetch('/api/ai/chat', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({messages: messages})
    }).then(function(r) { return r.json(); }).then(function(result) {
        var loading = document.getElementById('chatAiSuggestionLoading');
        if (loading) loading.remove();
        if (result.success) addMsg('ai', result.content);
        else addMsg('ai', '❌ ' + result.error);
    }).catch(function() {
        var loading = document.getElementById('chatAiSuggestionLoading');
        if (loading) loading.remove();
        addMsg('ai', '❌ 请求失败');
    });
}

// ========== 中栏: AI对话 ==========
function addMsg(role, content) {
    var container = document.getElementById('chatMessages');
    if (!container) return;
    var div = document.createElement('div');
    var isUser = (role === 'user');
    var avatar = isUser ? '👤' : (role === 'ai' ? '🤖' : '🔔');
    var rendered = content.replace(/\n/g, '<br>');
    if (role === 'ai' && typeof renderMarkdown === 'function') {
        rendered = renderMarkdown(content);
    }
    div.className = 'chat-msg' + (isUser ? ' user-msg' : '');
    div.innerHTML = '<div class="msg-avatar">' + avatar + '</div><div class="msg-bubble' + (isUser ? ' user' : '') + '">' + rendered + '</div>';
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

function sendChatMessage() {
    var input = document.getElementById('chatInput');
    if (!input) return;
    var text = input.value.trim();
    if (!text) return;
    addMsg('user', text);
    input.value = '';
    
    var messages = [];
    if (chatSelectedStudent) {
        var statusMap = {'continued': '已续班', 'no_condition': '无条件续班'};
        messages.push({
            role: 'system',
            content: '你是一位经验丰富的教育辅导老师。当前正在与学生「' + chatSelectedStudent.name + '」进行一对一沟通分析。\n\n学生信息：\n- 姓名：' + chatSelectedStudent.name + '\n- 高中：' + (chatSelectedStudent.high_school || '未知') + '\n- 中考成绩：' + (chatSelectedStudent.zhongkao_score || '未知') + '\n- 科学成绩：' + (chatSelectedStudent.science_score || '未知') + '\n- 选科倾向：' + (chatSelectedStudent.subject_preference || '未知') + '\n- 学生反馈：' + (chatSelectedStudent.feedback || '未知') + '\n- 续班状态：' + (statusMap[chatSelectedStudent.continuation_status] || '未续班') + '\n- 年级：' + (chatSelectedStudent.grade || '未知') + '\n\n请根据以上信息，辅助教师进行一对一沟通分析，提供专业建议。你可以：\n1. 分析学生学情\n2. 提供沟通策略和话术\n3. 建议需要编辑修改的学生信息字段\n4. 回答教育相关问题\n请用Markdown格式输出。'
        });
    } else {
        messages.push({role: 'system', content: '你是一位经验丰富的教育辅导老师，请辅助教师进行学生沟通分析。'});
    }
    
    var msgs = document.querySelectorAll('#chatMessages .chat-msg');
    var recentMsgs = Array.from(msgs).slice(-6);
    for (var i = 0; i < recentMsgs.length; i++) {
        var msg = recentMsgs[i];
        var isUserMsg = msg.classList.contains('user-msg');
        var bubble = msg.querySelector('.msg-bubble');
        if (bubble) {
            var avatarEl = msg.querySelector('.msg-avatar');
            if (avatarEl && avatarEl.textContent === '🔔') continue;
            messages.push({
                role: isUserMsg ? 'user' : 'assistant',
                content: bubble.textContent.replace(/<br>/g, '\n').replace(/<[^>]*>/g, '')
            });
        }
    }
    messages.push({role: 'user', content: text});
    
    // 加载中
    var loadingDiv = document.createElement('div');
    loadingDiv.className = 'chat-msg';
    loadingDiv.id = 'chatLoading';
    loadingDiv.innerHTML = '<div class="msg-avatar">🤖</div><div class="msg-bubble"><div class="spinner"></div> 思考中...</div>';
    document.getElementById('chatMessages').appendChild(loadingDiv);
    
    fetch('/api/ai/chat', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({messages: messages})
    }).then(function(r) { return r.json(); }).then(function(result) {
        var loading = document.getElementById('chatLoading');
        if (loading) loading.remove();
        if (result.success) addMsg('ai', result.content);
        else addMsg('ai', '❌ ' + result.error);
    }).catch(function() {
        var loading = document.getElementById('chatLoading');
        if (loading) loading.remove();
        addMsg('ai', '❌ 请求失败');
    });
}

// ========== 右栏: 话术库 ==========
function loadChatScriptCategories() {
    fetch('/api/scripts/categories')
        .then(function(r) { return r.json(); })
        .then(function(categories) {
            var container = document.getElementById('chatScriptCategories');
            if (!container) return;
            var html = '<button class="category-tab active" onclick="filterChatScripts(event,\'\')">全部</button>';
            for (var i = 0; i < categories.length; i++) {
                html += '<button class="category-tab" onclick="filterChatScripts(event,\'' + categories[i] + '\')">' + categories[i] + '</button>';
            }
            container.innerHTML = html;
        });
}

function filterChatScripts(event, category) {
    var tabs = document.querySelectorAll('#chatScriptCategories .category-tab');
    for (var i = 0; i < tabs.length; i++) tabs[i].classList.remove('active');
    event.target.classList.add('active');
    loadChatScripts(category);
}

function loadChatScripts(category) {
    if (!category) category = '';
    var url = '/api/scripts';
    if (category) url += '?category=' + encodeURIComponent(category);
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(scripts) {
            var container = document.getElementById('chatScriptList');
            if (!container) return;
            if (scripts.length === 0) {
                container.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-muted);font-size:13px">暂无话术</div>';
                return;
            }
            var html = '';
            for (var i = 0; i < scripts.length; i++) {
                var s = scripts[i];
                var preview = s.content.substring(0, 80);
                if (s.content.length > 80) preview += '...';
                html += '<div class="chat-script-item"><div class="chat-script-header"><span class="chat-script-category">' + s.category + '</span><button class="copy-btn" onclick="copyChatScript(' + s.id + ', this)">📋 复制</button></div><div class="chat-script-title">' + escHtml(s.title) + '</div><div class="chat-script-preview">' + escHtml(preview) + '</div></div>';
            }
            container.innerHTML = html;
        });
}

function copyChatScript(scriptId, btn) {
    fetch('/api/scripts')
        .then(function(r) { return r.json(); })
        .then(function(scripts) {
            for (var i = 0; i < scripts.length; i++) {
                if (scripts[i].id === scriptId) {
                    copyToClipboard(scripts[i].content, btn);
                    return;
                }
            }
        });
}