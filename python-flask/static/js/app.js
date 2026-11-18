/* ============================================
   单聊辅助 - 主应用逻辑
   ============================================ */

let currentMode = 'analysis';

// ========== 初始化 ==========
document.addEventListener('DOMContentLoaded', function() {
    // 如果当前页面是登录页，不要重定向（防止循环）
    if (window.location.pathname === '/login' || window.location.pathname === '') {
        return;
    }
    
    fetch('/api/auth/check')
        .then(r => r.json())
        .then(data => {
            if (!data.authenticated) {
                window.location.href = '/login';
                return;
            }
            document.getElementById('navUserName').textContent = '👤 ' + (data.user.display_name || data.user.username);
            const roleMap = {'admin': '👑管理员', 'teacher': '👩‍🏫教师', 'assistant': '🤝助教'};
            document.getElementById('navUserRole').textContent = roleMap[data.user.role] || data.user.role;
            initApp();
        })
        .catch(() => window.location.href = '/login');
});

function initApp() {
    loadGradeCards();
    loadAiConfig();
}

// ========== 模式切换 ==========
function switchMode(mode) {
    currentMode = mode;
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.nav-tab[data-mode="${mode}"]`).classList.add('active');
    document.querySelectorAll('.mode-container').forEach(c => c.style.display = 'none');
    document.getElementById(`mode-${mode}`).style.display = 'flex';

    if (mode === 'chat') {
        initChatMode();
    }
}

// ========== Toast ==========
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

// ========== 复制 ==========
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        if (btn) {
            const original = btn.textContent;
            btn.textContent = '✅ 已复制';
            btn.classList.add('copied');
            setTimeout(() => {
                btn.textContent = original;
                btn.classList.remove('copied');
            }, 1500);
        } else {
            showToast('已复制到剪贴板', 'success');
        }
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        showToast('已复制到剪贴板', 'success');
    });
}

// ========== 退出 ==========
function logout() {
    fetch('/logout').then(() => window.location.href = '/login');
}