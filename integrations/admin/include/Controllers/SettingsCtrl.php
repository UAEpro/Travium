<?php
/**
 * Global Admin Panel - Settings Controller
 *
 * Manage global settings: payment config, email blacklist, IP bans,
 * newsletter, news, and other cross-world settings stored in maindb.
 */

class SettingsCtrl
{
    private $db;
    private $auth;
    private $title = 'Global Settings';

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
        $section = $_GET['section'] ?? 'overview';

        switch ($section) {
            case 'ipban':
                return $this->handleIpBan();
            case 'emailBlacklist':
                return $this->handleEmailBlacklist();
            case 'newsletter':
                return $this->handleNewsletter();
            case 'news':
                return $this->handleNews();
            default:
                return $this->handleOverview();
        }
    }

    /**
     * Settings overview with links to sub-sections.
     */
    private function handleOverview(): string
    {
        global $globalConfig;

        ob_start();
        ?>
        <h1>Global Settings</h1>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin:15px 0;">
            <div style="background:#fff; border:1px solid #ddd; border-radius:5px; padding:15px;">
                <h3 style="margin:0 0 10px;">Sections</h3>
                <ul style="list-style:none; padding:0; margin:0;">
                    <li style="padding:5px 0;"><a href="index.php?action=payment">Payment Management</a></li>
                    <li style="padding:5px 0;"><a href="index.php?action=settings&section=ipban">IP Bans</a></li>
                    <li style="padding:5px 0;"><a href="index.php?action=settings&section=emailBlacklist">Email Blacklist</a></li>
                    <li style="padding:5px 0;"><a href="index.php?action=settings&section=newsletter">Newsletter Subscribers</a></li>
                    <li style="padding:5px 0;"><a href="index.php?action=settings&section=news">Global News</a></li>
                </ul>
            </div>
            <div style="background:#fff; border:1px solid #ddd; border-radius:5px; padding:15px;">
                <h3 style="margin:0 0 10px;">Configuration Info</h3>
                <table class="ga-table">
                    <tr><td><b>Domain</b></td><td><?= h(getBaseDomain()) ?></td></tr>
                    <tr><td><b>Default Language</b></td><td><?= h($globalConfig['staticParameters']['default_language'] ?? 'en') ?></td></tr>
                    <tr><td><b>Default Timezone</b></td><td><?= h($globalConfig['staticParameters']['default_timezone'] ?? 'UTC') ?></td></tr>
                    <tr><td><b>Mailer Driver</b></td><td><?= h($globalConfig['mailer']['driver'] ?? 'local') ?></td></tr>
                    <tr><td><b>reCAPTCHA</b></td><td><?= !empty($globalConfig['staticParameters']['recaptcha_public_key']) ? '<span class="ga-badge ga-badge-green">Configured</span>' : '<span class="ga-badge ga-badge-yellow">Not set</span>' ?></td></tr>
                </table>
            </div>
        </div>

        <?php
        // Quick stats
        $ipBanCount = (int)$this->db->query('SELECT COUNT(*) FROM banIP')->fetchColumn();
        $emailBlCount = (int)$this->db->query('SELECT COUNT(*) FROM email_blacklist')->fetchColumn();
        $newsCount = (int)$this->db->query('SELECT COUNT(*) FROM news')->fetchColumn();
        $nlCount = (int)$this->db->query('SELECT COUNT(*) FROM newsletter')->fetchColumn();
        ?>
        <div style="margin:15px 0;">
            <div class="ga-stat-box">
                <div class="num"><?= $ipBanCount ?></div>
                <div class="lbl">IP Bans</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $emailBlCount ?></div>
                <div class="lbl">Blacklisted Emails</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $newsCount ?></div>
                <div class="lbl">News Articles</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $nlCount ?></div>
                <div class="lbl">Newsletter Subs</div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * IP ban management.
     */
    private function handleIpBan(): string
    {
        $msg = '';

        // Handle add
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $ipStr = trim($_POST['ip'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            $duration = (int)($_POST['duration'] ?? 0);

            $ip = ip2long($ipStr);
            if ($ip === false) {
                $msg = '<div class="ga-msg ga-msg-err">Invalid IP address.</div>';
            } else {
                $blockTill = $duration > 0 ? time() + $duration : 0;
                $stmt = $this->db->prepare('INSERT INTO banIP (ip, reason, time, blockTill) VALUES (:ip, :reason, :time, :till)');
                $stmt->execute([':ip' => $ip, ':reason' => $reason, ':time' => time(), ':till' => $blockTill]);
                $msg = '<div class="ga-msg ga-msg-ok">IP banned successfully.</div>';
            }
        }

        // Handle delete
        if (isset($_GET['delete']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $stmt = $this->db->prepare('DELETE FROM banIP WHERE id = :id');
            $stmt->execute([':id' => (int)$_GET['delete']]);
            $msg = '<div class="ga-msg ga-msg-ok">IP ban removed.</div>';
        }

        ob_start();
        ?>
        <h1>IP Bans</h1>
        <p><a href="index.php?action=settings">&laquo; Back to Settings</a></p>
        <?= $msg ?>

        <h2>Add IP Ban</h2>
        <form method="post" class="ga-form" style="margin:10px 0;">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:10px; align-items:end;">
                <div>
                    <label>IP Address</label>
                    <input type="text" name="ip" placeholder="1.2.3.4" required>
                </div>
                <div>
                    <label>Reason</label>
                    <input type="text" name="reason" placeholder="Ban reason">
                </div>
                <div>
                    <label>Duration (seconds, 0=permanent)</label>
                    <input type="number" name="duration" value="0" min="0">
                </div>
                <div>
                    <button class="ga-btn ga-btn-danger" type="submit">Ban IP</button>
                </div>
            </div>
        </form>

        <h2>Current Bans</h2>
        <table class="ga-table">
            <thead>
                <tr><th>#</th><th>IP</th><th>Reason</th><th>Banned</th><th>Expires</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php
            $bans = $this->db->query('SELECT * FROM banIP ORDER BY id DESC')->fetchAll();
            if (empty($bans)): ?>
                <tr><td colspan="6" style="text-align:center; color:#888;">No IP bans.</td></tr>
            <?php endif; ?>
            <?php foreach ($bans as $ban): ?>
                <tr>
                    <td><?= (int)$ban['id'] ?></td>
                    <td><?= h(long2ip($ban['ip'])) ?></td>
                    <td><?= h($ban['reason']) ?></td>
                    <td><?= formatTime((int)$ban['time']) ?></td>
                    <td><?= $ban['blockTill'] ? formatTime((int)$ban['blockTill']) : '<em>Permanent</em>' ?></td>
                    <td>
                        <a class="ga-btn ga-btn-danger" href="index.php?action=settings&section=ipban&delete=<?= (int)$ban['id'] ?>&_csrf=<?= urlencode(csrfToken()) ?>"
                           onclick="return confirm('Remove this IP ban?')">Remove</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Email blacklist management.
     */
    private function handleEmailBlacklist(): string
    {
        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $email = trim($_POST['email'] ?? '');
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $this->db->prepare('INSERT IGNORE INTO email_blacklist (email, time) VALUES (:email, :time)');
                $stmt->execute([':email' => $email, ':time' => time()]);
                $msg = '<div class="ga-msg ga-msg-ok">Email blacklisted.</div>';
            } else {
                $msg = '<div class="ga-msg ga-msg-err">Invalid email address.</div>';
            }
        }

        if (isset($_GET['delete']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $stmt = $this->db->prepare('DELETE FROM email_blacklist WHERE id = :id');
            $stmt->execute([':id' => (int)$_GET['delete']]);
            $msg = '<div class="ga-msg ga-msg-ok">Email removed from blacklist.</div>';
        }

        ob_start();
        ?>
        <h1>Email Blacklist</h1>
        <p><a href="index.php?action=settings">&laquo; Back to Settings</a></p>
        <?= $msg ?>

        <form method="post" class="ga-form" style="margin:10px 0;">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <div style="display:grid; grid-template-columns:1fr auto; gap:10px; align-items:end;">
                <div>
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="user@example.com" required>
                </div>
                <div>
                    <button class="ga-btn ga-btn-danger" type="submit">Add to Blacklist</button>
                </div>
            </div>
        </form>

        <table class="ga-table">
            <thead><tr><th>#</th><th>Email</th><th>Added</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $emails = $this->db->query('SELECT * FROM email_blacklist ORDER BY id DESC')->fetchAll();
            if (empty($emails)): ?>
                <tr><td colspan="4" style="text-align:center; color:#888;">No blacklisted emails.</td></tr>
            <?php endif; ?>
            <?php foreach ($emails as $e): ?>
                <tr>
                    <td><?= (int)$e['id'] ?></td>
                    <td><?= h($e['email']) ?></td>
                    <td><?= formatTime((int)$e['time']) ?></td>
                    <td>
                        <a class="ga-btn ga-btn-danger" href="index.php?action=settings&section=emailBlacklist&delete=<?= (int)$e['id'] ?>&_csrf=<?= urlencode(csrfToken()) ?>"
                           onclick="return confirm('Remove from blacklist?')">Remove</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Newsletter subscriber management.
     */
    private function handleNewsletter(): string
    {
        $msg = '';

        if (isset($_GET['delete']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $stmt = $this->db->prepare('DELETE FROM newsletter WHERE id = :id');
            $stmt->execute([':id' => (int)$_GET['delete']]);
            $msg = '<div class="ga-msg ga-msg-ok">Subscriber removed.</div>';
        }

        ob_start();
        ?>
        <h1>Newsletter Subscribers</h1>
        <p><a href="index.php?action=settings">&laquo; Back to Settings</a></p>
        <?= $msg ?>

        <table class="ga-table">
            <thead><tr><th>#</th><th>Email</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $subs = $this->db->query('SELECT * FROM newsletter ORDER BY id DESC LIMIT 100')->fetchAll();
            if (empty($subs)): ?>
                <tr><td colspan="3" style="text-align:center; color:#888;">No subscribers.</td></tr>
            <?php endif; ?>
            <?php foreach ($subs as $s): ?>
                <tr>
                    <td><?= (int)$s['id'] ?></td>
                    <td><?= h($s['email']) ?></td>
                    <td>
                        <a class="ga-btn ga-btn-danger" href="index.php?action=settings&section=newsletter&delete=<?= (int)$s['id'] ?>&_csrf=<?= urlencode(csrfToken()) ?>"
                           onclick="return confirm('Remove subscriber?')">Remove</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Global news management.
     */
    private function handleNews(): string
    {
        $msg = '';

        // Handle add/edit
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $shortDesc = trim($_POST['shortDesc'] ?? '');
            $moreLink = trim($_POST['moreLink'] ?? '');
            $expireDays = max(1, (int)($_POST['expire_days'] ?? 30));
            $editId = (int)($_POST['edit_id'] ?? 0);

            if ($title !== '') {
                if ($editId > 0) {
                    $stmt = $this->db->prepare('UPDATE news SET title=:t, content=:c, shortDesc=:sd, moreLink=:ml, expire=:exp WHERE id=:id');
                    $stmt->execute([
                        ':t' => $title, ':c' => $content, ':sd' => $shortDesc,
                        ':ml' => $moreLink, ':exp' => time() + ($expireDays * 86400), ':id' => $editId,
                    ]);
                    $msg = '<div class="ga-msg ga-msg-ok">News updated.</div>';
                } else {
                    $stmt = $this->db->prepare('INSERT INTO news (title, content, shortDesc, moreLink, expire, time) VALUES (:t, :c, :sd, :ml, :exp, :time)');
                    $stmt->execute([
                        ':t' => $title, ':c' => $content, ':sd' => $shortDesc,
                        ':ml' => $moreLink, ':exp' => time() + ($expireDays * 86400), ':time' => time(),
                    ]);
                    $msg = '<div class="ga-msg ga-msg-ok">News created.</div>';
                }
            }
        }

        if (isset($_GET['delete']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $stmt = $this->db->prepare('DELETE FROM news WHERE id = :id');
            $stmt->execute([':id' => (int)$_GET['delete']]);
            $msg = '<div class="ga-msg ga-msg-ok">News deleted.</div>';
        }

        // Load edit data if editing
        $editData = null;
        if (isset($_GET['edit'])) {
            $stmt = $this->db->prepare('SELECT * FROM news WHERE id = :id');
            $stmt->execute([':id' => (int)$_GET['edit']]);
            $editData = $stmt->fetch();
        }

        ob_start();
        ?>
        <h1>Global News</h1>
        <p><a href="index.php?action=settings">&laquo; Back to Settings</a></p>
        <?= $msg ?>

        <h2><?= $editData ? 'Edit' : 'Add' ?> News</h2>
        <form method="post" class="ga-form">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="edit_id" value="<?= (int)$editData['id'] ?>">
            <?php endif; ?>
            <label>Title</label>
            <input type="text" name="title" value="<?= h($editData['title'] ?? '') ?>" required style="width:400px;">
            <label>Short Description</label>
            <textarea name="shortDesc" style="width:400px; height:60px;"><?= h($editData['shortDesc'] ?? '') ?></textarea>
            <label>Full Content</label>
            <textarea name="content" style="width:400px; height:100px;"><?= h($editData['content'] ?? '') ?></textarea>
            <label>More Link</label>
            <input type="text" name="moreLink" value="<?= h($editData['moreLink'] ?? '') ?>">
            <label>Expire in (days)</label>
            <input type="number" name="expire_days" value="30" min="1">
            <div style="margin:10px 0;">
                <button class="ga-btn ga-btn-primary" type="submit"><?= $editData ? 'Update' : 'Create' ?> News</button>
                <?php if ($editData): ?>
                    <a class="ga-btn" href="index.php?action=settings&section=news">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <h2>Existing News</h2>
        <table class="ga-table">
            <thead><tr><th>#</th><th>Title</th><th>Created</th><th>Expires</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $news = $this->db->query('SELECT * FROM news ORDER BY id DESC')->fetchAll();
            if (empty($news)): ?>
                <tr><td colspan="5" style="text-align:center; color:#888;">No news articles.</td></tr>
            <?php endif; ?>
            <?php foreach ($news as $n): ?>
                <tr>
                    <td><?= (int)$n['id'] ?></td>
                    <td><?= h($n['title']) ?></td>
                    <td><?= formatTime((int)$n['time']) ?></td>
                    <td>
                        <?php if ((int)$n['expire'] < time()): ?>
                            <span class="ga-badge ga-badge-red">Expired</span>
                        <?php else: ?>
                            <?= formatTime((int)$n['expire']) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="ga-btn" href="index.php?action=settings&section=news&edit=<?= (int)$n['id'] ?>">Edit</a>
                        <a class="ga-btn ga-btn-danger" href="index.php?action=settings&section=news&delete=<?= (int)$n['id'] ?>&_csrf=<?= urlencode(csrfToken()) ?>"
                           onclick="return confirm('Delete this news?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}
