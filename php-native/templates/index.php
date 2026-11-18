<?php /** @var array|null $user */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学员信息管理系统</title>
    <link rel="stylesheet" href="/static/css/fluent.css?v=2">
</head>
<body>
    <nav class="top-nav">
        <div class="nav-brand">📚 学员信息管理系统</div>
        <div class="nav-tabs">
            <button class="nav-tab active" data-mode="analysis" onclick="switchMode('analysis')">📊 学员分析</button>
            <button class="nav-tab" data-mode="chat" onclick="switchMode('chat')">💬 单聊模式</button>
            <button class="nav-tab" data-mode="settings" onclick="switchMode('settings')">⚙️ 设置</button>
        </div>
        <div class="nav-user">
            <span id="navUserName"></span>
            <span class="nav-role-badge" id="navUserRole"></span>
            <button onclick="logout()">退出</button>
        </div>
    </nav>

    <div class="main-container">
        <!-- ============ 学员分析模式 (70%左列表 + 30%右AI) ============ -->
        <div id="mode-analysis" class="mode-container" style="display:flex;width:100%">
            <div class="analysis-two-col">
                <!-- 左栏 70%: 班级层级 + 学生列表 -->
                <div class="analysis-left">
                    <div class="analysis-left-header">
                        <div class="analysis-breadcrumb" id="analysisBreadcrumb">
                            <span class="bc-item active" onclick="showGradeView()">📚 班级</span>
                        </div>
                        <div class="analysis-left-actions">
                            <button class="fluent-btn small" onclick="showClassEditor()">➕ 新建班级</button>
                            <button class="fluent-btn small" onclick="document.getElementById('aiImportFileAnalysis').click()">📄 导入</button>
                            <input type="file" id="aiImportFileAnalysis" accept=".csv,.xlsx,.xls" style="display:none" onchange="aiImportData(this)">
                        </div>
                    </div>
                    <div class="analysis-left-body">
                        <!-- 班级卡片视图 -->
                        <div id="analysisClassView">
                            <div class="grade-cards" id="analysisGradeCards"></div>
                        </div>
                        <!-- 班级内学生列表 -->
                        <div id="analysisStudentListView" style="display:none">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px">
                                <div class="sort-controls">
                                    <span style="font-size:12px;color:var(--text-muted)">排序：</span>
                                    <select id="sortBySelect" onchange="applySort()">
                                        <option value="name">姓名</option>
                                        <option value="continuation">续班状态</option>
                                    </select>
                                    <select id="sortOrderSelect" onchange="applySort()">
                                        <option value="asc">A→Z / 未续班在前</option>
                                        <option value="desc">Z→A / 已续班在前</option>
                                    </select>
                                </div>
                                <div style="display:flex;gap:6px">
                                    <button class="fluent-btn small" onclick="showHomeworkModal()">📝 作业提交</button>
                                    <div class="search-box" style="width:180px;margin:0">
                                        <input type="text" id="analysisStudentSearch" placeholder="搜索学生..." oninput="filterGradeStudents()">
                                    </div>
                                </div>
                            </div>
                            <div class="student-grid" id="analysisStudentGrid"></div>
                        </div>
                    </div>
                </div>
                <!-- 右栏 30%: AI分析助手 -->
                <div class="analysis-right">
                    <div class="analysis-right-header">🤖 AI 分析助手</div>
                    <div class="analysis-ai-msgs" id="analysisAiMsgs">
                        <div class="chat-msg">
                            <div class="msg-avatar">🤖</div>
                            <div class="msg-bubble">你好！我是AI分析助手。<br><br>📌 你可以：<br>• 左侧选择班级/学生查看信息<br>• 上传文件让我帮你导入数据<br>• 让我分析单个或批量学生<br>• 让我帮你编辑学生信息</div>
                        </div>
                    </div>
                    <div class="analysis-ai-bottom">
                        <div class="analysis-ai-tools" id="analysisAiTools" style="display:none">
                            <button class="fluent-btn small" onclick="aiAnalyzeCurrent()">📊 分析选中</button>
                            <button class="fluent-btn small" onclick="aiBatchAnalyze()">📋 批量分析</button>
                        </div>
                        <div class="analysis-ai-input">
                            <textarea id="analysisAiInput" rows="2" placeholder="输入指令或上传文件..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendAnalysisMsg()}"></textarea>
                            <button class="fluent-btn primary small" onclick="sendAnalysisMsg()">发送</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ 单聊模式 ============ -->
        <div id="mode-chat" class="mode-container" style="display:none;width:100%">
            <div class="three-col">
                <div class="col col-5">
                    <div class="col-header">🔍 搜索学生</div>
                    <div class="col-body">
                        <div class="search-box" style="margin:0 0 12px 0">
                            <input type="text" id="chatSearchInput" placeholder="输入姓名或学校搜索..." oninput="chatSearchInput()">
                        </div>
                        <div class="chat-student-results" id="chatStudentResults"></div>
                    </div>
                </div>
                <div class="col col-3">
                    <div class="col-header">🤖 AI 对话 <span id="chatContextStudent" style="font-size:12px;color:var(--text-muted);margin-left:8px"></span></div>
                    <div class="chat-messages" id="chatMessages">
                        <div class="chat-msg">
                            <div class="msg-avatar">🤖</div>
                            <div class="msg-bubble">在左侧搜索并选择学生，AI自动加载信息辅助分析。</div>
                        </div>
                    </div>
                    <div class="chat-input">
                        <textarea id="chatInput" rows="2" placeholder="输入消息..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChatMessage()}"></textarea>
                        <button class="fluent-btn primary" onclick="sendChatMessage()">发送</button>
                    </div>
                </div>
                <div class="col col-2">
                    <div class="col-header">💬 话术库</div>
                    <div class="col-body">
                        <div class="category-tabs" id="chatScriptCategories"></div>
                        <div class="chat-script-list" id="chatScriptList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ 设置 ============ -->
        <div id="mode-settings" class="mode-container" style="display:none;width:100%">
            <div class="settings-container">
                <div class="settings-sidebar">
                    <button class="settings-tab active" data-stab="ai" onclick="switchSettingsTab('ai')">🤖 AI设置</button>
                    <button class="settings-tab" data-stab="aidialog" onclick="switchSettingsTab('aidialog')">💬 AI对话</button>
                    <button class="settings-tab" data-stab="classes" onclick="switchSettingsTab('classes')">🏫 班级管理</button>
                    <button class="settings-tab" data-stab="import" onclick="switchSettingsTab('import')">📁 导入导出</button>
                    <button class="settings-tab" data-stab="scripts" onclick="switchSettingsTab('scripts')">💬 话术管理</button>
                    <button class="settings-tab" data-stab="search" onclick="switchSettingsTab('search')">🔍 学生搜索</button>
                    <button class="settings-tab" data-stab="roles" onclick="switchSettingsTab('roles')">👥 角色管理</button>
                    <button class="settings-tab" data-stab="account" onclick="switchSettingsTab('account')">👤 账户设置</button>
                </div>
                <div class="settings-content">
                    <div id="stab-ai" class="settings-section"><h3>🤖 AI 接口配置</h3>
                        <div class="fluent-field"><label>API 地址</label><input type="url" id="aiApiUrl"></div>
                        <div class="fluent-field"><label>API 密钥</label><input type="password" id="aiApiKey"></div>
                        <div class="fluent-field"><label>模型名称</label><input type="text" id="aiModel"></div>
                        <div class="fluent-field"><label>系统提示词</label><textarea id="aiSystemPrompt" rows="4"></textarea></div>
                        <div class="action-bar"><button class="fluent-btn primary" onclick="saveAiConfig()">💾 保存</button><button class="fluent-btn" onclick="testAiConnection()">🔗 测试</button><span id="aiTestResult" style="font-size:13px;margin-left:8px"></span></div>
                    </div>
                    <div id="stab-aidialog" class="settings-section" style="display:none">
                        <h3>💬 AI 数据管理助手</h3>
                        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">直接对话AI，进行学生数据查询、编辑、统计等操作</p>
                        <div class="settings-ai-chat" id="settingsAiMsgs">
                            <div class="chat-msg">
                                <div class="msg-avatar">🤖</div>
                                <div class="msg-bubble">你好！我是数据管理助手。你可以让我：<br><br>📊 <strong>查询统计</strong>：如"统计各年级人数"<br>✏️ <strong>编辑数据</strong>：如"把张三的续班状态改为已续班"<br>📋 <strong>批量操作</strong>：如"把所有085班未续班的学生列出来"<br>📈 <strong>生成报告</strong>：如"分析一下续班率"</div>
                            </div>
                        </div>
                        <div class="settings-ai-input">
                            <textarea id="settingsAiInput" rows="2" placeholder="输入指令..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendSettingsAiMsg()}"></textarea>
                            <button class="fluent-btn primary" onclick="sendSettingsAiMsg()">发送</button>
                        </div>
                    </div>
                    <div id="stab-classes" class="settings-section" style="display:none"><h3>🏫 班级管理</h3><div class="action-bar"><button class="fluent-btn primary" onclick="showClassEditor()">➕ 新建班级</button></div><div id="classManagementList"></div></div>
                    <div id="stab-import" class="settings-section" style="display:none"><h3>📁 导入数据</h3><div class="upload-zone" onclick="document.getElementById('importFile').click()"><div class="upload-icon">📄</div><div class="upload-text">点击选择文件</div></div><input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none" onchange="previewImport()"><div class="fluent-field"><label>年级</label><select id="importGrade"><option value="085">085</option><option value="084">084</option><option value="083">083</option><option value="">其他</option></select></div><div id="importPreviewArea"></div><button class="fluent-btn primary" id="confirmImportBtn" style="display:none" onclick="confirmImport()">✅ 确认导入</button><hr style="margin:24px 0"><h3>📤 导出数据</h3><div class="fluent-field"><label>年级</label><select id="exportGrade"><option value="">全部</option><option value="085">085</option><option value="084">084</option><option value="083">083</option></select></div><div class="action-bar"><button class="fluent-btn" onclick="exportStudents('csv')">📤 CSV</button><button class="fluent-btn" onclick="exportStudents('xlsx')">📤 Excel</button></div></div>
                    <div id="stab-scripts" class="settings-section" style="display:none"><h3>💬 话术管理</h3><div class="action-bar"><button class="fluent-btn primary" onclick="showScriptEditor()">➕ 新增</button></div><div class="fluent-field"><label>分类</label><select id="scriptFilterCategory" onchange="loadScriptsForManagement()"><option value="">全部</option></select></div><div id="scriptManagementList"></div></div>
                    <div id="stab-search" class="settings-section" style="display:none"><h3>🔍 学生搜索</h3><div class="fluent-field"><label>关键词</label><input type="text" id="globalSearchKeyword" oninput="globalSearch()"></div><div class="grade-filter"><button class="grade-btn active" onclick="filterGlobalSearchGrade('')">全部</button><button class="grade-btn" onclick="filterGlobalSearchGrade('085')">085</button><button class="grade-btn" onclick="filterGlobalSearchGrade('084')">084</button><button class="grade-btn" onclick="filterGlobalSearchGrade('083')">083</button></div><div id="globalSearchResults"></div></div>
                    <div id="stab-roles" class="settings-section" style="display:none"><h3>👥 角色管理</h3>
                        <div class="action-bar"><button class="fluent-btn primary" onclick="showUserEditor()">➕ 添加用户</button></div>
                        <div id="userManagementList"></div>
                        <div style="margin-top:16px;padding:16px;background:var(--bg);border-radius:8px;font-size:13px;color:var(--text-secondary)">
                            <strong>角色说明：</strong><br>
                            👑 <strong>管理员</strong> - 所有权限，包括用户管理、系统设置<br>
                            👩‍🏫 <strong>教师</strong> - 学员分析、单聊模式、话术管理、数据导入导出<br>
                            🤝 <strong>助教</strong> - 学员查看、单聊模式、话术使用
                        </div>
                    </div>
                    <div id="stab-account" class="settings-section" style="display:none"><h3>👤 账户设置</h3><div class="info-card"><div class="info-card-title">修改密码</div><div class="fluent-field"><label>当前密码</label><input type="password" id="oldPassword"></div><div class="fluent-field"><label>新密码</label><input type="password" id="newPassword"></div><div class="fluent-field"><label>确认新密码</label><input type="password" id="confirmPassword"></div><button class="fluent-btn primary" onclick="changePassword()">修改</button><span id="passwordResult" style="margin-left:8px;font-size:13px"></span></div><div class="info-card" style="margin-top:16px"><div class="info-card-title">关于</div><p style="font-size:14px;color:var(--text-secondary);line-height:1.8"><strong>单聊辅助</strong> v1.0</p></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 学生信息弹窗 (查看/编辑分离) -->
    <div class="modal-overlay" id="studentModal">
        <div class="modal" style="max-width:800px;max-height:85vh">
            <div class="modal-header">
                <h3 id="studentModalTitle">学生信息</h3>
                <div class="modal-header-actions">
                    <div class="view-edit-toggle" id="viewEditToggle">
                        <button class="ve-btn active" data-mode="view" onclick="switchModalMode('view')">👁 查看</button>
                        <button class="ve-btn" data-mode="edit" onclick="switchModalMode('edit')">✏️ 编辑</button>
                    </div>
                    <button class="modal-close" onclick="closeStudentModal()">×</button>
                </div>
            </div>
            <div class="modal-body">
                <!-- 查看模式 -->
                <div id="modalViewMode">
                    <div class="student-info-header">
                        <span class="student-info-name" id="modalViewName"></span>
                        <span class="student-info-badge" id="modalViewGrade"></span>
                        <span class="student-info-status" id="modalViewStatus"></span>
                    </div>
                    <div class="info-section">
                        <div class="info-section-title">📋 基本信息</div>
                        <div class="info-grid" id="modalViewBody" style="grid-template-columns:1fr 1fr"></div>
                    </div>
                    <div class="info-section">
                        <div class="info-section-title">📝 作业提交 (<span id="modalViewLessonCount">5</span>次课)</div>
                        <div class="info-grid" id="modalViewHomework" style="grid-template-columns:1fr 1fr 1fr 1fr"></div>
                    </div>
                    <div class="info-section">
                        <div class="info-section-title">🎯 续班状态</div>
                        <div class="status-buttons" id="modalViewStatusBtns"></div>
                    </div>
                </div>
                <!-- 编辑模式 -->
                <div id="modalEditMode" style="display:none">
                    <div class="edit-notice">💡 修改后记得点击底部的「保存修改」</div>
                    <div class="edit-section">
                        <div class="info-section-title">📋 基本信息</div>
                        <div class="edit-grid" id="modalEditBody"></div>
                    </div>
                    <div class="edit-section">
                        <div class="info-section-title">📝 作业提交 (<span id="modalEditLessonCount">5</span>次课)</div>
                        <div class="edit-grid" id="modalEditHomework" style="grid-template-columns:1fr 1fr 1fr 1fr"></div>
                    </div>
                    <div class="edit-section">
                        <div class="info-section-title">🎯 续班状态</div>
                        <div class="status-buttons" id="modalEditStatus"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="modalFooter">
                <button class="fluent-btn" onclick="closeStudentModal()">关闭</button>
                <button class="fluent-btn primary" id="saveEditBtn" style="display:none" onclick="saveModalEdits()">💾 保存修改</button>
            </div>
        </div>
    </div>
            <div class="modal-footer"><button class="fluent-btn" onclick="closeStudentModal()">关闭</button></div>
        </div>
    </div>

    <!-- 用户编辑弹窗 -->
    <div class="modal-overlay" id="userEditorModal">
        <div class="modal" style="max-width:450px">
            <div class="modal-header"><h3 id="userEditorTitle">添加用户</h3><button class="modal-close" onclick="closeUserEditor()">×</button></div>
            <div class="modal-body">
                <div class="fluent-field"><label>用户名 *</label><input type="text" id="userEditUsername"></div>
                <div class="fluent-field"><label>显示名称</label><input type="text" id="userEditDisplay"></div>
                <div class="fluent-field"><label>密码 *</label><input type="password" id="userEditPassword"></div>
                <div class="fluent-field"><label>角色</label>
                    <select id="userEditRole">
                        <option value="admin">👑 管理员</option>
                        <option value="teacher" selected>👩‍🏫 教师</option>
                        <option value="assistant">🤝 助教</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="fluent-btn" onclick="closeUserEditor()">取消</button>
                <button class="fluent-btn primary" onclick="saveUserEditor()">保存</button>
            </div>
        </div>
    </div>

    <!-- 班级编辑弹窗 -->
    <div class="modal-overlay" id="classEditorModal">
        <div class="modal" style="max-width:450px">
            <div class="modal-header"><h3 id="classEditorTitle">新建班级</h3><button class="modal-close" onclick="closeClassEditor()">×</button></div>
            <div class="modal-body">
                <div class="fluent-field"><label>班级名称 <span style="color:var(--danger)">*</span></label><input type="text" id="classEditName"></div>
                <div class="fluent-field"><label>班号</label><input type="text" id="classEditNumber"></div>
                <div class="fluent-field"><label>班型</label><select id="classEditType"><option value="A1">A1</option><option value="A2">A2</option><option value="B1">B1</option><option value="B2">B2</option><option value="S">S</option></select></div>
                <div class="fluent-field"><label>课次数量</label><input type="number" id="classEditLessons" value="5" min="1" max="20"></div>
            </div>
            <div class="modal-footer"><button class="fluent-btn" onclick="closeClassEditor()">取消</button><button class="fluent-btn primary" onclick="saveClassEditor()">保存</button></div>
        </div>
    </div>

    <!-- 作业提交弹窗 -->
    <div class="modal-overlay" id="homeworkModal">
        <div class="modal" style="max-width:700px">
            <div class="modal-header"><h3 id="homeworkModalTitle">📝 作业提交管理</h3><button class="modal-close" onclick="closeHomeworkModal()">×</button></div>
            <div class="modal-body">
                <div class="homework-top-bar">
                    <div class="homework-selects">
                        <label style="font-size:13px;color:var(--text-secondary)">选择课次：</label>
                        <select id="homeworkLessonSelect" onchange="loadHomeworkList()" style="padding:6px 12px;border:1px solid var(--border);border-radius:6px;font-family:inherit;font-size:13px">
                        </select>
                        <button class="fluent-btn small" onclick="importHomeworkNames()" style="margin-left:8px">📄 导入名单</button>
                        <input type="file" id="homeworkImportFile" accept=".csv,.txt,.xlsx" style="display:none" onchange="processHomeworkImport(this)">
                    </div>
                    <div class="homework-stats" id="homeworkStats"></div>
                </div>
                <div class="homework-search-box" style="margin:12px 0">
                    <div class="search-box" style="margin:0">
                        <input type="text" id="homeworkSearchInput" placeholder="搜索学生姓名..." oninput="filterHomeworkList()">
                    </div>
                </div>
                <div class="homework-tabs">
                    <button class="homework-tab active" onclick="switchHomeworkTab('all')">全部</button>
                    <button class="homework-tab" onclick="switchHomeworkTab('submitted')">✅ 已提交</button>
                    <button class="homework-tab" onclick="switchHomeworkTab('unsubmitted')">❌ 未提交</button>
                </div>
                <div class="homework-actions-bar">
                    <button class="fluent-btn primary small" onclick="batchSubmitHomework()">✅ 一键提交选中</button>
                    <button class="fluent-btn small" onclick="copyUnsubmittedList()">📋 复制未提交名单</button>
                    <button class="fluent-btn small" onclick="copyAllList()">📋 复制全部名单</button>
                </div>
                <div class="homework-list" id="homeworkList"></div>
            </div>
            <div class="modal-footer">
                <button class="fluent-btn" onclick="closeHomeworkModal()">关闭</button>
            </div>
        </div>
    </div>

    <!-- 话术编辑弹窗 -->
    <div class="modal-overlay" id="scriptEditorModal">
        <div class="modal" style="max-width:500px">
            <div class="modal-header"><h3 id="scriptEditorTitle">编辑话术</h3><button class="modal-close" onclick="closeScriptEditor()">×</button></div>
            <div class="modal-body">
                <div class="fluent-field"><label>标题</label><input type="text" id="scriptEditTitle"></div>
                <div class="fluent-field"><label>分类</label><input type="text" id="scriptEditCategory" list="scriptCategoryList"><datalist id="scriptCategoryList"></datalist></div>
                <div class="fluent-field"><label>内容</label><textarea id="scriptEditContent" rows="8"></textarea></div>
            </div>
            <div class="modal-footer"><button class="fluent-btn" onclick="closeScriptEditor()">取消</button><button class="fluent-btn primary" onclick="saveScriptEditor()">保存</button></div>
        </div>
    </div>

    <div class="toast" id="toast"></div>
    <script src="/static/js/app.js"></script>
    <script src="/static/js/analysis.js"></script>
    <script src="/static/js/chat.js"></script>
    <script src="/static/js/settings.js"></script>
    <script>
        // 注入当前用户信息 (供前端读取)
        window.__CURRENT_USER__ = <?= json_encode($user, JSON_UNESCAPED_UNICODE) ?>;
    </script>
</body>
</html>
