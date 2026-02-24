<?php
/**
 * Global Admin Panel - Dashboard Controller
 *
 * Overview page: all game worlds, status, quick actions, summary stats.
 */

class DashboardCtrl
{
    private $db;
    private $auth;
    private $title = 'Dashboard';

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
        ob_start();

        // Fetch all game worlds
        $worlds = $this->db->query('SELECT * FROM gameServers ORDER BY id DESC')->fetchAll();

        // Summary stats
        $totalWorlds   = count($worlds);
        $activeWorlds  = 0;
        $finishedWorlds = 0;
        foreach ($worlds as $w) {
            if ($w['finished']) {
                $finishedWorlds++;
            } else {
                $activeWorlds++;
            }
        }

        // Count total players across active worlds (count gameServers entries — actual player counts need per-world DB)
        // For now just show world-level stats
        ?>
        <h1>Dashboard</h1>

        <div style="margin: 15px 0;">
            <div class="ga-stat-box">
                <div class="num"><?= $totalWorlds ?></div>
                <div class="lbl">Total Worlds</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $activeWorlds ?></div>
                <div class="lbl">Active</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $finishedWorlds ?></div>
                <div class="lbl">Finished</div>
            </div>
        </div>

        <h2>Game Worlds</h2>
        <table class="ga-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>World ID</th>
                    <th>Name</th>
                    <th>Speed</th>
                    <th>Style</th>
                    <th>Status</th>
                    <th>Registration</th>
                    <th>Start Time</th>
                    <th>Round (days)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($worlds)): ?>
                <tr><td colspan="10" style="text-align:center; padding:20px; color:#888;">No game worlds found. <a href="index.php?action=servers">Create one</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($worlds as $w): ?>
                <tr>
                    <td><?= (int)$w['id'] ?></td>
                    <td><b><?= h($w['worldId']) ?></b></td>
                    <td><?= h($w['name']) ?></td>
                    <td><?= (int)$w['speed'] ?>x</td>
                    <td>
                        <?php $style = isset($w['serverStyle']) ? $w['serverStyle'] : 'modern'; ?>
                        <span class="ga-badge <?= $style === 'classic' ? 'ga-badge-yellow' : 'ga-badge-blue' ?>"><?= ucfirst(h($style)) ?></span>
                    </td>
                    <td>
                        <?php if ($w['finished']): ?>
                            <span class="ga-badge ga-badge-red">Finished</span>
                        <?php elseif ($w['hidden']): ?>
                            <span class="ga-badge ga-badge-yellow">Hidden</span>
                        <?php else: ?>
                            <span class="ga-badge ga-badge-green">Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($w['registerClosed']): ?>
                            <span class="ga-badge ga-badge-red">Closed</span>
                        <?php elseif ($w['preregistration_key_only']): ?>
                            <span class="ga-badge ga-badge-yellow">Key Only</span>
                        <?php else: ?>
                            <span class="ga-badge ga-badge-green">Open</span>
                        <?php endif; ?>
                    </td>
                    <td><?= formatTime((int)$w['startTime']) ?></td>
                    <td><?= (int)$w['roundLength'] ?></td>
                    <td>
                        <a class="ga-btn ga-btn-primary" href="index.php?action=world&world=<?= urlencode($w['worldId']) ?>&worldAction=main">Manage</a>
                        <a class="ga-btn" href="<?= h($w['gameWorldUrl']) ?>" target="_blank">Visit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Task Queue</h2>
        <?php
        $tasks = $this->db->query("SELECT * FROM taskQueue ORDER BY id DESC LIMIT 10")->fetchAll();
        if (empty($tasks)):
        ?>
            <div class="ga-msg ga-msg-info">No tasks in queue.</div>
        <?php else: ?>
        <table class="ga-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $t): ?>
                <tr>
                    <td><?= (int)$t['id'] ?></td>
                    <td><?= h($t['type']) ?></td>
                    <td><?= h($t['description'] ?? '') ?></td>
                    <td>
                        <?php
                        $badge = 'ga-badge-yellow';
                        if ($t['status'] === 'done') $badge = 'ga-badge-green';
                        if ($t['status'] === 'failed') $badge = 'ga-badge-red';
                        ?>
                        <span class="ga-badge <?= $badge ?>"><?= h($t['status']) ?></span>
                    </td>
                    <td><?= formatTime((int)$t['time']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php

        return ob_get_clean();
    }
}
