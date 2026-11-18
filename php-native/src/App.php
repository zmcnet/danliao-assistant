<?php
/**
 * 单聊辅助 - 应用控制器
 * 集中处理所有页面渲染与 API 请求
 * 1:1 复刻 Python: app.py
 */

declare(strict_types=1);

namespace DanChat;

final class App
{
    private Auth $auth;
    private Student $student;
    private Script $script;
    private ClassManager $classMgr;
    private AiConfig $aiConfig;
    private Database $db;

    public function __construct()
    {
        $this->auth     = new Auth();
        $this->student  = new Student();
        $this->script   = new Script();
        $this->classMgr = new ClassManager();
        $this->aiConfig = new AiConfig();
        $this->db       = Database::getInstance();
    }

    // ============================================================
    // 页面路由
    // ============================================================

    public function pageIndex(): void
    {
        $this->auth->requireLogin();
        render('index', [
            'user' => $this->auth->currentUser(),
        ]);
    }

    public function pageLogin(): void
    {
        if ($this->auth->isLoggedIn()) {
            header('Location: /');
            exit;
        }
        $isFirst = $this->auth->isFirstUser();
        render('login', ['is_first' => $isFirst]);
    }

    public function pageLogout(): void
    {
        $this->auth->logout();
        header('Location: /login');
        exit;
    }

    // ============================================================
    // 认证 API
    // ============================================================

    public function apiRegister(): void
    {
        $data = json_input();
        $result = $this->auth->register(
            (string)($data['username'] ?? ''),
            (string)($data['password'] ?? ''),
            (string)($data['display_name'] ?? ''),
            !empty($data['is_first']) ? 'is_first' : 'teacher'
        );
        if ($result['success']) {
            json_response(['success' => true, 'message' => '注册成功']);
        }
        json_response(['success' => false, 'error' => $result['error'] ?? '注册失败'], 400);
    }

    public function apiLogin(): void
    {
        $data = json_input();
        $result = $this->auth->login(
            (string)($data['username'] ?? ''),
            (string)($data['password'] ?? '')
        );
        if ($result['success']) {
            json_response(['success' => true, 'user' => $result['user']]);
        }
        json_response(['success' => false, 'error' => $result['error'] ?? '登录失败'], 401);
    }

    public function apiCheckAuth(): void
    {
        if ($this->auth->isLoggedIn()) {
            $u = $this->auth->currentUser();
            if ($u) json_response(['authenticated' => true, 'user' => $u]);
        }
        json_response(['authenticated' => false]);
    }

    public function apiCheckFirst(): void
    {
        json_response(['is_first' => $this->auth->isFirstUser()]);
    }

    public function apiChangePassword(): void
    {
        $this->auth->requireLogin();
        $data = json_input();
        $result = $this->auth->changePassword(
            (int)$_SESSION['user_id'],
            (string)($data['old_password'] ?? ''),
            (string)($data['new_password'] ?? '')
        );
        json_response($result);
    }

    // ============================================================
    // 用户管理
    // ============================================================

    public function apiGetUsers(): void
    {
        $this->auth->requireAdmin();
        json_response($this->auth->getAllUsers());
    }

    public function apiAddUser(): void
    {
        $this->auth->requireAdmin();
        $data = json_input();
        $role = !empty($data['is_first']) ? 'admin' : (string)($data['role'] ?? 'teacher');
        $result = $this->auth->register(
            (string)($data['username'] ?? ''),
            (string)($data['password'] ?? ''),
            (string)($data['display_name'] ?? ''),
            $role
        );
        if ($result['success']) {
            json_response(['success' => true, 'message' => '用户已添加']);
        }
        json_response($result, 400);
    }

    public function apiUpdateUserRole(int $userId): void
    {
        $this->auth->requireAdmin();
        $data = json_input();
        $this->auth->updateUserRole($userId, (string)($data['role'] ?? 'teacher'));
        json_response(['success' => true]);
    }

    public function apiDeleteUser(int $userId): void
    {
        $this->auth->requireAdmin();
        if ($userId === (int)$_SESSION['user_id']) {
            json_response(['success' => false, 'error' => '不能删除自己'], 400);
        }
        $this->auth->deleteUser($userId);
        json_response(['success' => true]);
    }

    // ============================================================
    // 学生 API
    // ============================================================

    public function apiGetStudents(): void
    {
        $grade     = (string)($_GET['grade'] ?? '');
        $keyword   = (string)($_GET['keyword'] ?? '');
        $sortBy    = (string)($_GET['sort_by'] ?? 'name');
        $sortOrder = (string)($_GET['sort_order'] ?? 'asc');
        $classId   = $_GET['class_id'] ?? '';

        if ($classId !== '') {
            $rows = $this->student->getStudentsByClass((int)$classId, $sortBy, $sortOrder);
        } elseif ($keyword !== '') {
            $rows = $this->student->searchStudents($keyword, $grade, $sortBy, $sortOrder);
        } else {
            $rows = $this->student->getAllStudents($grade, $sortBy, $sortOrder);
        }
        json_response($rows);
    }

    public function apiGetStudent(int $studentId): void
    {
        $s = $this->student->getStudent($studentId);
        if (!$s) json_response(['error' => '学生不存在'], 404);
        json_response($s);
    }

    public function apiUpdateStudent(int $studentId): void
    {
        $data = json_input();
        $this->student->updateStudent($studentId, $data);
        json_response(['success' => true]);
    }

    public function apiDeleteStudent(int $studentId): void
    {
        $this->student->deleteStudent($studentId);
        json_response(['success' => true]);
    }

    public function apiStudentStats(): void
    {
        json_response($this->student->getStats());
    }

    public function apiImportStudents(): void
    {
        if (empty($_FILES['file'])) {
            json_response(['success' => false, 'error' => '请选择文件'], 400);
        }
        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            json_response(['success' => false, 'error' => '文件上传失败'], 400);
        }
        $grade = (string)($_POST['grade'] ?? '');

        try {
            // 保存临时文件
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $tmp = tempnam(sys_get_temp_dir(), 'import_') . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $tmp)) {
                json_response(['success' => false, 'error' => '文件保存失败'], 400);
            }

            $parsed = Importer::parseFile($tmp);
            @unlink($tmp);
            $count = $this->student->importFromRows($parsed['rows'], $grade);
            json_response([
                'success' => true,
                'count'   => $count,
                'message' => "成功导入 {$count} 名学生",
            ]);
        } catch (\Throwable $e) {
            json_response(['success' => false, 'error' => '导入失败: ' . $e->getMessage()], 400);
        }
    }

    public function apiPreviewImport(): void
    {
        if (empty($_FILES['file'])) {
            json_response(['success' => false, 'error' => '请选择文件'], 400);
        }
        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            json_response(['success' => false, 'error' => '文件上传失败'], 400);
        }
        try {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $tmp = tempnam(sys_get_temp_dir(), 'preview_') . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $tmp)) {
                json_response(['success' => false, 'error' => '文件保存失败'], 400);
            }
            $parsed = Importer::preview($tmp, 10);
            @unlink($tmp);
            json_response([
                'success'    => true,
                'columns'    => $parsed['columns'],
                'preview'    => $parsed['preview'],
                'total_rows' => $parsed['total_rows'],
            ]);
        } catch (\Throwable $e) {
            json_response(['success' => false, 'error' => '读取文件失败: ' . $e->getMessage()], 400);
        }
    }

    public function apiExportStudents(): void
    {
        $data = json_input();
        $grade  = (string)($data['grade'] ?? '');
        $format = (string)($data['format'] ?? 'csv');
        try {
            $rows = $this->student->getAllStudents($grade, 'name', 'asc');
            // 与Python一致：删除 id/created_at/updated_at/note
            foreach ($rows as &$r) {
                unset($r['id'], $r['created_at'], $r['updated_at'], $r['note'], $r['class_id']);
            }
            unset($r);
            $path = Exporter::exportStudents($rows, $grade, $format);
            // 发送下载
            $filename = basename($path);
            header('Content-Type: ' . ($format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=utf-8'));
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: no-cache');
            readfile($path);
            // 清理
            @unlink($path);
            exit;
        } catch (\Throwable $e) {
            json_response(['success' => false, 'error' => '导出失败: ' . $e->getMessage()], 400);
        }
    }

    // ============================================================
    // 话术 API
    // ============================================================

    public function apiGetScripts(): void
    {
        $category = (string)($_GET['category'] ?? '');
        $rows = $category !== '' ? $this->script->getScriptsByCategory($category) : $this->script->getAllScripts();
        json_response($rows);
    }

    public function apiGetScriptCategories(): void
    {
        json_response($this->script->getCategories());
    }

    public function apiAddScript(): void
    {
        $data = json_input();
        $id = $this->script->addScript(
            (string)($data['title'] ?? ''),
            (string)($data['content'] ?? ''),
            (string)($data['category'] ?? '通用')
        );
        json_response(['success' => true, 'id' => $id]);
    }

    public function apiUpdateScript(int $scriptId): void
    {
        $data = json_input();
        $this->script->updateScript($scriptId, $data);
        json_response(['success' => true]);
    }

    public function apiDeleteScript(int $scriptId): void
    {
        $this->script->deleteScript($scriptId);
        json_response(['success' => true]);
    }

    public function apiMoveScript(int $scriptId): void
    {
        $data = json_input();
        $ok = $this->script->moveScript($scriptId, (string)($data['direction'] ?? 'up'));
        json_response(['success' => $ok]);
    }

    // ============================================================
    // 班级 API
    // ============================================================

    public function apiGetClasses(): void
    {
        json_response($this->classMgr->getAllClasses());
    }

    public function apiAddClass(): void
    {
        $data = json_input();
        try {
            $id = $this->classMgr->addClass(
                (string)($data['name'] ?? ''),
                (string)($data['class_number'] ?? ''),
                (string)($data['class_type'] ?? 'A1'),
                (int)($data['lesson_count'] ?? 5),
                (string)($data['grade'] ?? '')
            );
            json_response(['success' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            json_response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function apiUpdateClass(int $classId): void
    {
        $data = json_input();
        $this->classMgr->updateClass($classId, $data);
        json_response(['success' => true]);
    }

    public function apiDeleteClass(int $classId): void
    {
        $this->classMgr->deleteClass($classId);
        json_response(['success' => true]);
    }

    // ============================================================
    // AI 配置
    // ============================================================

    public function apiGetAiConfig(): void
    {
        $cfg = $this->aiConfig->get();
        if ($cfg && !empty($cfg['api_key'])) {
            $key = (string)$cfg['api_key'];
            $cfg['api_key_masked'] = strlen($key) > 8
                ? substr($key, 0, 4) . '****' . substr($key, -4)
                : '****';
        }
        json_response($cfg ?: new \stdClass());
    }

    public function apiSaveAiConfig(): void
    {
        $data = json_input();
        $this->aiConfig->save($data);
        json_response(['success' => true]);
    }

    public function apiTestAi(): void
    {
        $data = json_input();
        $svc = new AiService(
            (string)($data['api_url'] ?? ''),
            (string)($data['api_key'] ?? ''),
            (string)($data['model'] ?? 'gpt-3.5-turbo')
        );
        json_response($svc->testConnection());
    }

    public function apiAnalyzeStudent(): void
    {
        $data = json_input();
        $sid = (int)($data['student_id'] ?? 0);
        $student = $this->student->getStudent($sid);
        if (!$student) json_response(['success' => false, 'error' => '学生不存在'], 404);

        $cfg = $this->aiConfig->get();
        if (!$cfg || empty($cfg['api_key'])) {
            json_response(['success' => false, 'error' => '请先在设置中配置AI API'], 400);
        }
        $svc = new AiService(
            (string)$cfg['api_url'],
            (string)$cfg['api_key'],
            (string)$cfg['model'],
            (float)$cfg['temperature'],
            (int)$cfg['max_tokens']
        );
        $messages = AiService::buildStudentAnalysisPrompt($student, (string)$cfg['system_prompt']);
        $result = $svc->call($messages);
        if ($result['success']) {
            $this->student->updateStudent($sid, ['note' => $result['content']]);
        }
        json_response($result);
    }

    public function apiChatSuggestion(): void
    {
        $data = json_input();
        $sid = (int)($data['student_id'] ?? 0);
        $student = $this->student->getStudent($sid);
        if (!$student) json_response(['success' => false, 'error' => '学生不存在'], 404);

        $cfg = $this->aiConfig->get();
        if (!$cfg || empty($cfg['api_key'])) {
            json_response(['success' => false, 'error' => '请先在设置中配置AI API'], 400);
        }
        $svc = new AiService(
            (string)$cfg['api_url'],
            (string)$cfg['api_key'],
            (string)$cfg['model'],
            (float)$cfg['temperature'],
            (int)$cfg['max_tokens']
        );
        $messages = AiService::buildChatSuggestionPrompt($student, (string)$cfg['system_prompt']);
        json_response($svc->call($messages));
    }

    public function apiAnalyzeStats(): void
    {
        $cfg = $this->aiConfig->get();
        if (!$cfg || empty($cfg['api_key'])) {
            json_response(['success' => false, 'error' => '请先在设置中配置AI API'], 400);
        }
        $stats = $this->student->getStats();
        $svc = new AiService(
            (string)$cfg['api_url'],
            (string)$cfg['api_key'],
            (string)$cfg['model'],
            (float)$cfg['temperature'],
            (int)$cfg['max_tokens']
        );
        $messages = AiService::buildStatsAnalysisPrompt($stats, (string)$cfg['system_prompt']);
        json_response($svc->call($messages));
    }

    public function apiAiChat(): void
    {
        $data = json_input();
        $cfg = $this->aiConfig->get();
        if (!$cfg || empty($cfg['api_key'])) {
            json_response(['success' => false, 'error' => '请先在设置中配置AI API'], 400);
        }
        $messages = $data['messages'] ?? [];
        if (!is_array($messages)) $messages = [];
        $svc = new AiService(
            (string)$cfg['api_url'],
            (string)$cfg['api_key'],
            (string)$cfg['model'],
            (float)($data['temperature'] ?? $cfg['temperature']),
            (int)($data['max_tokens'] ?? $cfg['max_tokens'])
        );
        json_response($svc->call($messages));
    }
}
