<?php
/**
 * Global Admin Panel - Template
 *
 * Renders the admin layout. Reuses the same CSS as the per-world admin panel,
 * served from a game world's public path or CDN.
 */

class GlobalAdminTemplate
{
    /**
     * Render the full admin layout page.
     */
    public function render(array $data): void
    {
        $title   = $data['title']   ?? 'Global Admin';
        $content = $data['content'] ?? '';
        $menus   = $data['menus']   ?? '';
        $user    = $data['user']    ?? [];

        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= h($title) ?> - Travium Global Admin</title>
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" crossorigin="anonymous"></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: Verdana, Arial, sans-serif; font-size: 12px; background: #f0f0f0; }
        .ga-topbar {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: #fff; padding: 8px 20px;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 13px; box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        .ga-topbar a { color: #ecf0f1; text-decoration: none; margin-left: 15px; }
        .ga-topbar a:hover { text-decoration: underline; }
        .ga-topbar .brand { font-weight: bold; font-size: 14px; letter-spacing: 0.5px; }
        .ga-layout { display: flex; min-height: calc(100vh - 40px); }
        .ga-sidebar {
            width: 180px; min-width: 180px; background: #fff; border-right: 1px solid #ddd;
            padding: 8px 0; overflow-y: auto;
        }
        .ga-sidebar a { display: block; padding: 5px 12px; color: #333; text-decoration: none; font-size: 11px; }
        .ga-sidebar a:hover { background: #e8e8e8; color: #333; }
        .ga-sidebar a.active { background: #3498db; color: #fff; }
        .ga-sidebar a b { color: #2c3e50; font-size: 11px; }
        .ga-sidebar a.active b { color: #fff; }
        .ga-content { flex: 1; padding: 15px 20px; min-width: 0; overflow-x: auto; }
        .ga-table { width: 100%; border-collapse: collapse; margin: 10px 0; background: #fff; }
        .ga-table th { background: #2c3e50; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
        .ga-table td { padding: 6px 10px; border-bottom: 1px solid #ddd; font-size: 11px; }
        .ga-table tr:hover td { background: #f5f5f5; }
        .ga-btn { display: inline-block; padding: 5px 12px; border-radius: 3px; text-decoration: none; font-size: 11px; cursor: pointer; border: 1px solid #ccc; background: #f5f5f5; color: #333; }
        .ga-btn:hover { background: #e5e5e5; }
        .ga-btn-primary { background: #3498db; color: #fff; border-color: #2980b9; }
        .ga-btn-primary:hover { background: #2980b9; }
        .ga-btn-danger { background: #e74c3c; color: #fff; border-color: #c0392b; }
        .ga-btn-danger:hover { background: #c0392b; }
        .ga-btn-success { background: #27ae60; color: #fff; border-color: #229954; }
        .ga-btn-success:hover { background: #229954; }
        .ga-form label { display: block; margin: 8px 0 3px; font-weight: bold; font-size: 11px; float: none; width: auto; }
        .ga-form input[type=text], .ga-form input[type=password], .ga-form input[type=number],
        .ga-form input[type=email], .ga-form select, .ga-form textarea {
            padding: 5px 8px; border: 1px solid #ccc; border-radius: 3px; font-size: 11px; width: 250px;
        }
        .ga-form textarea { width: 400px; height: 100px; }
        .ga-msg { padding: 8px 12px; border-radius: 3px; margin: 10px 0; font-size: 11px; }
        .ga-msg-ok { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .ga-msg-err { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .ga-msg-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .ga-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .ga-badge-green { background: #27ae60; color: #fff; }
        .ga-badge-red { background: #e74c3c; color: #fff; }
        .ga-badge-yellow { background: #f39c12; color: #fff; }
        .ga-badge-blue { background: #3498db; color: #fff; }
        .ga-stat-box { display: inline-block; background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 12px 20px; margin: 5px; text-align: center; min-width: 120px; }
        .ga-stat-box .num { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .ga-stat-box .lbl { font-size: 10px; color: #888; margin-top: 4px; }
        h1 { font-size: 18px; margin: 0 0 10px 0; padding: 0; }
        h2 { font-size: 14px; margin: 0 0 8px 0; padding: 0; }
        a:link, a:visited { color: #3498db; text-decoration: none; }
        a:hover { color: #2980b9; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="ga-topbar">
        <span class="brand">Travium Global Admin</span>
        <span>
            Logged in as <b><?= h($user['username'] ?? '') ?></b>
            (<?= h($user['role'] ?? '') ?>)
            <a href="index.php?action=dashboard">Dashboard</a>
            <a href="index.php?action=logout">Logout</a>
        </span>
    </div>
    <div class="ga-layout">
        <div class="ga-sidebar">
            <?= $menus ?>
        </div>
        <div class="ga-content">
            <?= $content ?>
        </div>
    </div>
</body>
</html>
<?php
    }
}
