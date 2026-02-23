<?php
/**
 * Global Admin Panel - Authentication
 *
 * Manages login, logout, and session checks for the global admin panel.
 * Uses password_hash()/password_verify() with the admin_users table in maindb.
 */

class Auth
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Check if the current session is authenticated.
     */
    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin_id']);
    }

    /**
     * Get current admin user info.
     */
    public function getUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        return [
            'id'       => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'],
            'role'     => $_SESSION['admin_role'],
        ];
    }

    /**
     * Attempt login with username and password.
     * Returns true on success, false on failure.
     */
    public function login(string $username, string $password): bool
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return false;
        }

        $stmt = $this->db->prepare('SELECT id, username, password, role FROM admin_users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Update last_login
        $upd = $this->db->prepare('UPDATE admin_users SET last_login = :t WHERE id = :id');
        $upd->execute([':t' => time(), ':id' => $user['id']]);

        // Regenerate session ID BEFORE writing sensitive data to prevent fixation
        session_regenerate_id(true);

        // Set session
        $_SESSION['admin_id']       = (int)$user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_role']     = $user['role'];
        $_SESSION['admin_ip']       = $_SERVER['REMOTE_ADDR'] ?? '';

        return true;
    }

    /**
     * Destroy the admin session.
     */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /**
     * Check if admin has super role.
     */
    public function isSuper(): bool
    {
        return ($this->getUser()['role'] ?? '') === 'super';
    }
}
