<?php
/**
 * Global Admin Panel - Admin Users Controller
 *
 * Manage admin accounts: add, remove, change password.
 * Only super admins can manage other admins.
 */

class AdminUsersCtrl
{
    private $db;
    private $auth;
    private $title = 'Admin Users';

    public function __construct(PDO $db, Auth $auth)
    {
        $this->db   = $db;
        $this->auth = $auth;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function handle(): string
    {
        // Only super admins can manage admin users
        if (!$this->auth->isSuper()) {
            return '<div class="ga-msg ga-msg-err">Only super admins can manage admin accounts.</div>';
        }

        $section = $_GET['section'] ?? 'list';

        switch ($section) {
            case 'add':
                return $this->handleAdd();
            case 'changePassword':
                return $this->handleChangePassword();
            case 'delete':
                return $this->handleDelete();
            default:
                return $this->handleList();
        }
    }

    /**
     * List all admin users.
     */
    private function handleList(): string
    {
        $msg = $_GET['msg'] ?? '';

        ob_start();
        ?>
        <h1>Admin Users</h1>
        <?php if ($msg === 'added'): ?>
            <div class="ga-msg ga-msg-ok">Admin user created successfully.</div>
        <?php elseif ($msg === 'deleted'): ?>
            <div class="ga-msg ga-msg-ok">Admin user deleted.</div>
        <?php elseif ($msg === 'password_changed'): ?>
            <div class="ga-msg ga-msg-ok">Password changed successfully.</div>
        <?php endif; ?>

        <div style="margin:10px 0;">
            <a class="ga-btn ga-btn-primary" href="index.php?action=adminUsers&section=add">Add Admin</a>
        </div>

        <table class="ga-table">
            <thead>
                <tr><th>#</th><th>Username</th><th>Role</th><th>Created</th><th>Last Login</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php
            $users = $this->db->query('SELECT * FROM admin_users ORDER BY id ASC')->fetchAll();
            foreach ($users as $u):
            ?>
                <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><b><?= h($u['username']) ?></b></td>
                    <td>
                        <?php if ($u['role'] === 'super'): ?>
                            <span class="ga-badge ga-badge-blue">Super Admin</span>
                        <?php else: ?>
                            <span class="ga-badge ga-badge-green">Admin</span>
                        <?php endif; ?>
                    </td>
                    <td><?= formatTime((int)$u['created_at']) ?></td>
                    <td><?= $u['last_login'] ? formatTime((int)$u['last_login']) : '<em>Never</em>' ?></td>
                    <td>
                        <a class="ga-btn" href="index.php?action=adminUsers&section=changePassword&id=<?= (int)$u['id'] ?>">Change Password</a>
                        <?php
                        // Can't delete yourself or the last super admin
                        $currentUser = $this->auth->getUser();
                        $canDelete = ($u['id'] != $currentUser['id']);
                        if ($u['role'] === 'super') {
                            $superCount = (int)$this->db->query("SELECT COUNT(*) FROM admin_users WHERE role='super'")->fetchColumn();
                            if ($superCount <= 1) {
                                $canDelete = false;
                            }
                        }
                        if ($canDelete):
                        ?>
                        <a class="ga-btn ga-btn-danger"
                           href="index.php?action=adminUsers&section=delete&id=<?= (int)$u['id'] ?>&_csrf=<?= urlencode(csrfToken()) ?>"
                           onclick="return confirm(<?= h(json_encode('Delete admin user ' . $u['username'] . '?')) ?>)">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Add a new admin user.
     */
    private function handleAdd(): string
    {
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = ($_POST['role'] ?? 'admin') === 'super' ? 'super' : 'admin';

            if ($username === '' || strlen($username) < 3) {
                $error = 'Username must be at least 3 characters.';
            } elseif ($password === '' || strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                // Check if username exists
                $check = $this->db->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :u');
                $check->execute([':u' => $username]);
                if ((int)$check->fetchColumn() > 0) {
                    $error = 'Username already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $this->db->prepare(
                        'INSERT INTO admin_users (username, password, role, created_at) VALUES (:u, :p, :r, :t)'
                    );
                    $stmt->execute([':u' => $username, ':p' => $hash, ':r' => $role, ':t' => time()]);

                    header('Location: index.php?action=adminUsers&msg=added');
                    exit;
                }
            }
        }

        ob_start();
        ?>
        <h1>Add Admin User</h1>
        <p><a href="index.php?action=adminUsers">&laquo; Back to list</a></p>

        <?php if ($error): ?>
            <div class="ga-msg ga-msg-err"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="ga-form">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <label>Username</label>
            <input type="text" name="username" value="<?= h($_POST['username'] ?? '') ?>" required minlength="3">
            <label>Password</label>
            <input type="password" name="password" required minlength="6">
            <label>Role</label>
            <select name="role">
                <option value="admin">Admin</option>
                <option value="super">Super Admin</option>
            </select>
            <div style="margin:15px 0;">
                <button class="ga-btn ga-btn-primary" type="submit">Create Admin</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Change an admin user's password.
     */
    private function handleChangePassword(): string
    {
        $id = (int)($_GET['id'] ?? 0);
        $error = '';

        // Fetch user
        $stmt = $this->db->prepare('SELECT * FROM admin_users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            return '<div class="ga-msg ga-msg-err">User not found.</div>';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $password = $_POST['password'] ?? '';
            if ($password === '' || strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $this->db->prepare('UPDATE admin_users SET password = :p WHERE id = :id');
                $upd->execute([':p' => $hash, ':id' => $id]);

                header('Location: index.php?action=adminUsers&msg=password_changed');
                exit;
            }
        }

        ob_start();
        ?>
        <h1>Change Password: <?= h($user['username']) ?></h1>
        <p><a href="index.php?action=adminUsers">&laquo; Back to list</a></p>

        <?php if ($error): ?>
            <div class="ga-msg ga-msg-err"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="ga-form">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <label>New Password</label>
            <input type="password" name="password" required minlength="6">
            <div style="margin:15px 0;">
                <button class="ga-btn ga-btn-primary" type="submit">Update Password</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Delete an admin user.
     */
    private function handleDelete(): string
    {
        $id = (int)($_GET['id'] ?? 0);
        $csrf = $_GET['_csrf'] ?? '';

        if (!$id || !hash_equals(csrfToken(), $csrf)) {
            header('Location: index.php?action=adminUsers');
            exit;
        }

        // Can't delete yourself
        $currentUser = $this->auth->getUser();
        if ($id == $currentUser['id']) {
            return '<div class="ga-msg ga-msg-err">You cannot delete your own account.</div>';
        }

        // Can't delete the last super admin
        $stmt = $this->db->prepare('SELECT role FROM admin_users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $target = $stmt->fetch();
        if ($target && $target['role'] === 'super') {
            $superCount = (int)$this->db->query("SELECT COUNT(*) FROM admin_users WHERE role='super'")->fetchColumn();
            if ($superCount <= 1) {
                return '<div class="ga-msg ga-msg-err">Cannot delete the last super admin.</div>';
            }
        }

        $del = $this->db->prepare('DELETE FROM admin_users WHERE id = :id');
        $del->execute([':id' => $id]);

        header('Location: index.php?action=adminUsers&msg=deleted');
        exit;
    }
}
