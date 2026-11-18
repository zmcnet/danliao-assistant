<?php
/**
 * 单聊辅助 - 认证模块
 * 处理用户注册、登录、密码修改、Session 管理
 *
 * 密码算法：与Python版本保持一致，使用 SHA256(password + salt)
 * 升级建议：可改用 password_hash() / password_verify() (但会破坏已有用户数据格式)
 */

declare(strict_types=1);

namespace DanChat;

final class Auth
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 密码哈希 (兼容Python: hashlib.sha256((password + salt).encode()).hexdigest())
     */
    public function hashPassword(string $password): string
    {
        $salt = config('password_salt');
        return hash('sha256', $password . $salt);
    }

    /**
     * 验证密码
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return hash_equals($hash, $this->hashPassword($password));
    }

    /**
     * 注册新用户
     * @return array{success: bool, error?: string, user_id?: int}
     */
    public function register(
        string $username,
        string $password,
        string $displayName = '',
        string $role = 'teacher'
    ): array {
        $username = trim($username);
        $displayName = trim($displayName);

        if ($username === '' || $password === '') {
            return ['success' => false, 'error' => '用户名和密码不能为空'];
        }
        if (strlen($password) < 4) {
            return ['success' => false, 'error' => '密码至少4位'];
        }

        // 检查重复
        $exists = $this->db->fetchValue('SELECT id FROM users WHERE username = ?', [$username]);
        if ($exists !== null) {
            return ['success' => false, 'error' => '用户名已存在'];
        }

        // 角色处理: is_first 表示首注册自动为admin
        if ($role === 'is_first') {
            $count = (int)$this->db->fetchValue('SELECT COUNT(*) FROM users');
            $role = $count === 0 ? 'admin' : 'teacher';
        }
        if (!in_array($role, ['admin', 'teacher', 'assistant'], true)) {
            $role = 'teacher';
        }

        try {
            $id = $this->db->insert('users', [
                'username'     => $username,
                'password_hash'=> $this->hashPassword($password),
                'display_name' => $displayName !== '' ? $displayName : $username,
                'role'         => $role,
            ]);
            return ['success' => true, 'user_id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => '注册失败: ' . $e->getMessage()];
        }
    }

    /**
     * 登录验证 (成功后写入 session)
     * @return array{success: bool, user?: array, error?: string}
     */
    public function login(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return ['success' => false, 'error' => '用户名或密码错误'];
        }
        $row = $this->db->fetchOne('SELECT * FROM users WHERE username = ?', [$username]);
        if (!$row) {
            return ['success' => false, 'error' => '用户名或密码错误'];
        }
        if (!$this->verifyPassword($password, $row['password_hash'])) {
            return ['success' => false, 'error' => '用户名或密码错误'];
        }
        // 写入 session
        $_SESSION['user_id']      = (int)$row['id'];
        $_SESSION['username']     = $row['username'];
        $_SESSION['display_name'] = $row['display_name'];
        $_SESSION['role']         = $row['role'];

        return [
            'success' => true,
            'user'    => [
                'id'           => (int)$row['id'],
                'username'     => $row['username'],
                'display_name' => $row['display_name'],
                'role'         => $row['role'],
            ],
        ];
    }

    /**
     * 根据 session 中的 user_id 取用户
     */
    public function getUser(int $userId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT id, username, display_name, role FROM users WHERE id = ?',
            [$userId]
        );
        if (!$row) return null;
        $row['id'] = (int)$row['id'];
        return $row;
    }

    /**
     * 修改密码
     */
    public function changePassword(int $userId, string $oldPwd, string $newPwd): array
    {
        if (strlen($newPwd) < 4) {
            return ['success' => false, 'error' => '新密码至少4位'];
        }
        $row = $this->db->fetchOne('SELECT password_hash FROM users WHERE id = ?', [$userId]);
        if (!$row) {
            return ['success' => false, 'error' => '用户不存在'];
        }
        if (!$this->verifyPassword($oldPwd, $row['password_hash'])) {
            return ['success' => false, 'error' => '原密码错误'];
        }
        $this->db->update('users', ['password_hash' => $this->hashPassword($newPwd)], 'id = ?', [$userId]);
        return ['success' => true];
    }

    /**
     * 是否无任何用户 (用于首次注册引导)
     */
    public function isFirstUser(): bool
    {
        $count = (int)$this->db->fetchValue('SELECT COUNT(*) FROM users');
        return $count === 0;
    }

    /**
     * 获取所有用户 (管理员)
     */
    public function getAllUsers(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, username, display_name, role, created_at FROM users ORDER BY id'
        );
        return array_map(function (array $u): array {
            $u['id'] = (int)$u['id'];
            $u['created_at'] = $u['created_at'] ? substr((string)$u['created_at'], 0, 10) : '';
            return $u;
        }, $rows);
    }

    public function deleteUser(int $userId): void
    {
        $this->db->delete('users', 'id = ?', [$userId]);
    }

    public function updateUserRole(int $userId, string $role): void
    {
        if (!in_array($role, ['admin', 'teacher', 'assistant'], true)) {
            $role = 'teacher';
        }
        $this->db->update('users', ['role' => $role], 'id = ?', [$userId]);
    }

    // ---------------- Session helpers ----------------

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public function currentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function currentUser(): ?array
    {
        if (!$this->isLoggedIn()) return null;
        return $this->getUser((int)$_SESSION['user_id']);
    }

    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            json_response(['error' => '未登录'], 401);
        }
    }

    public function requireAdmin(): void
    {
        $this->requireLogin();
        $u = $this->currentUser();
        if (!$u || $u['role'] !== 'admin') {
            json_response(['error' => '无权限'], 403);
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}
