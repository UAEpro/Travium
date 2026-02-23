<?php
/**
 * Global Admin Panel - Login Controller
 *
 * Handles the login form and authentication.
 * Renders its own standalone layout (no sidebar).
 */

class LoginCtrl
{
    private $db;
    private $auth;
    private $title = 'Login';

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
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($this->auth->login($username, $password)) {
                header('Location: index.php?action=dashboard');
                exit;
            }
            $error = 'Invalid username or password.';
        }

        ob_start();
        $this->renderLoginPage($error);
        return ob_get_clean();
    }

    private function renderLoginPage(string $error): void
    {
        $domain  = getBaseDomain();
        $bgImage = 'http://' . $domain . '/dist/8c198efd2ffc51138cf1425c6156bcb4.jpg';
        ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Travium Global Admin - Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    :root {
        --glass-bg: rgba(17, 17, 17, 0.55);
        --glass-brd: rgba(255,255,255,0.18);
        --text: #eaeaea;
        --muted: #b9c0c8;
        --accent: #a58cff;
        --ok: #3ecf8e;
        --bad: #ff6b6b;
    }
    html, body { height: 100%; margin: 0; }
    body {
        background: url('<?= h($bgImage) ?>') center/cover fixed no-repeat;
        font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial;
        color: var(--text);
    }
    .wrap {
        min-height: 100%;
        backdrop-filter: blur(2px);
        display: flex; align-items: center; justify-content: center;
        padding: 40px 16px;
        background: linear-gradient(0deg, rgba(0,0,0,0.35), rgba(0,0,0,0.35));
    }
    .card {
        width: min(420px, 95vw);
        background: var(--glass-bg);
        border: 1px solid var(--glass-brd);
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.45);
        overflow: hidden;
    }
    .header {
        padding: 22px 28px;
        background: linear-gradient(90deg, rgba(165,140,255,0.18), rgba(165,140,255,0.04));
        border-bottom: 1px solid var(--glass-brd);
        text-align: center;
    }
    .title { font-size: 20px; letter-spacing: .3px; }
    .body { padding: 24px 28px; }
    label { display: block; font-size: 12px; color: var(--muted); margin: 14px 0 6px; }
    input[type=text], input[type=password] {
        width: 100%; padding: 12px 10px; box-sizing: border-box;
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.18);
        background: rgba(0,0,0,0.35);
        color: var(--text); outline: none; font-size: 14px;
    }
    input:focus { border-color: var(--accent); }
    .btn {
        width: 100%; margin-top: 20px; padding: 14px;
        border-radius: 12px; border: 1px solid rgba(255,255,255,0.18);
        background: linear-gradient(180deg, rgba(165,140,255,0.35), rgba(165,140,255,0.18));
        color: #fff; cursor: pointer; font-weight: 600; font-size: 14px; letter-spacing: .3px;
    }
    .btn:hover { background: linear-gradient(180deg, rgba(165,140,255,0.50), rgba(165,140,255,0.30)); }
    .error {
        background: rgba(255,0,0,0.13);
        border: 1px solid rgba(255,0,0,0.25);
        color: #ffdede;
        padding: 10px 12px; border-radius: 10px; margin-bottom: 10px; font-size: 12px;
    }
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="header">
            <div class="title">Travium Global Admin</div>
        </div>
        <div class="body">
            <?php if ($error): ?>
                <div class="error"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <label>Username</label>
                <input type="text" name="username" autofocus required>
                <label>Password</label>
                <input type="password" name="password" required>
                <button class="btn" type="submit">Login</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
<?php
    }
}
