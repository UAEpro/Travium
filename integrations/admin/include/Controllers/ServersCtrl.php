<?php
/**
 * Global Admin Panel - Servers Controller
 *
 * List/create/archive game worlds. Absorbs the web installer flow.
 */

class ServersCtrl
{
    private $db;
    private $auth;
    private $title = 'Game Worlds';

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
        $section = $_GET['section'] ?? 'list';

        switch ($section) {
            case 'create':
                return $this->handleCreate();
            case 'edit':
                return $this->handleEdit();
            case 'toggle':
                return $this->handleToggle();
            case 'checkWorld':
                return $this->ajaxCheckWorld();
            default:
                return $this->handleList();
        }
    }

    /**
     * List all game worlds.
     */
    private function handleList(): string
    {
        $worlds = $this->db->query('SELECT * FROM gameServers ORDER BY id DESC')->fetchAll();

        ob_start();
        ?>
        <h1>Game Worlds</h1>
        <div style="margin: 10px 0;">
            <a class="ga-btn ga-btn-primary" href="index.php?action=servers&section=create">Create New World</a>
        </div>

        <table class="ga-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>World ID</th>
                    <th>Name</th>
                    <th>Speed</th>
                    <th>Status</th>
                    <th>URL</th>
                    <th>Start</th>
                    <th>Round</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($worlds)): ?>
                <tr><td colspan="9" style="text-align:center; padding:20px; color:#888;">No game worlds yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($worlds as $w): ?>
                <tr>
                    <td><?= (int)$w['id'] ?></td>
                    <td><b><?= h($w['worldId']) ?></b></td>
                    <td><?= h($w['name']) ?></td>
                    <td><?= (int)$w['speed'] ?>x</td>
                    <td>
                        <?php if ($w['finished']): ?>
                            <span class="ga-badge ga-badge-red">Finished</span>
                        <?php else: ?>
                            <span class="ga-badge ga-badge-green">Active</span>
                        <?php endif; ?>
                        <?php if ($w['hidden']): ?>
                            <span class="ga-badge ga-badge-yellow">Hidden</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?= h($w['gameWorldUrl']) ?>" target="_blank"><?= h($w['gameWorldUrl']) ?></a></td>
                    <td><?= formatTime((int)$w['startTime']) ?></td>
                    <td><?= (int)$w['roundLength'] ?>d (ends <?= gmdate('Y-m-d H:i', (int)$w['startTime'] + (int)$w['roundLength'] * 86400) ?> UTC)</td>
                    <td>
                        <a class="ga-btn" href="index.php?action=servers&section=edit&id=<?= (int)$w['id'] ?>">Edit Times</a>
                        <a class="ga-btn ga-btn-primary" href="index.php?action=world&world=<?= urlencode($w['worldId']) ?>&worldAction=main">Manage</a>
                        <?php if (!$w['finished']): ?>
                            <a class="ga-btn ga-btn-danger" href="index.php?action=servers&section=toggle&id=<?= (int)$w['id'] ?>&field=finished&value=1&_csrf=<?= urlencode(csrfToken()) ?>"
                               onclick="return confirm('Mark this world as FINISHED?')">Finish</a>
                        <?php else: ?>
                            <a class="ga-btn ga-btn-success" href="index.php?action=servers&section=toggle&id=<?= (int)$w['id'] ?>&field=finished&value=0&_csrf=<?= urlencode(csrfToken()) ?>"
                               onclick="return confirm('Re-activate this world?')">Reactivate</a>
                        <?php endif; ?>
                        <?php if ($w['hidden']): ?>
                            <a class="ga-btn" href="index.php?action=servers&section=toggle&id=<?= (int)$w['id'] ?>&field=hidden&value=0&_csrf=<?= urlencode(csrfToken()) ?>">Unhide</a>
                        <?php else: ?>
                            <a class="ga-btn" href="index.php?action=servers&section=toggle&id=<?= (int)$w['id'] ?>&field=hidden&value=1&_csrf=<?= urlencode(csrfToken()) ?>">Hide</a>
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
     * Edit start time and round length for a game world.
     */
    private function handleEdit(): string
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            header('Location: index.php?action=servers');
            exit;
        }

        $stmt = $this->db->prepare('SELECT * FROM gameServers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $world = $stmt->fetch();

        if (!$world) {
            header('Location: index.php?action=servers');
            exit;
        }

        $errors = [];
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $startTimeDT  = trim((string)($_POST['startTimeDT'] ?? ''));
            $roundLength  = (int)($_POST['roundLength'] ?? 0);

            // Validate start time
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $startTimeDT, new DateTimeZone('UTC'));
            if ($dt === false) {
                $errors[] = 'Invalid start time.';
            }
            if ($roundLength <= 0) {
                $errors[] = 'Round length must be a positive number of days.';
            }

            if (!$errors) {
                $startTs = $dt->getTimestamp();

                // Update gameServers table
                $upd = $this->db->prepare('UPDATE gameServers SET startTime = :start, roundLength = :length WHERE id = :id');
                $upd->execute([':start' => $startTs, ':length' => $roundLength, ':id' => $id]);

                // Sync per-world config DB
                $connectionFile = $world['configFileLocation'];
                if ($connectionFile && file_exists($connectionFile)) {
                    try {
                        // Read the world's connection info (same pattern as WorldProxyCtrl)
                        $connection = [];
                        // Use a closure to isolate the require scope
                        (function () use ($connectionFile, &$connection) {
                            require $connectionFile;
                        })();

                        if (!empty($connection['database'])) {
                            $dbConf = $connection['database'];
                            $worldPdo = new PDO(
                                sprintf('mysql:host=%s;dbname=%s;charset=%s',
                                    $dbConf['hostname'], $dbConf['database'], $dbConf['charset'] ?? 'utf8mb4'),
                                $dbConf['username'],
                                $dbConf['password'],
                                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                            );
                            $worldPdo->prepare('UPDATE config SET startTime = :ts')
                                     ->execute([':ts' => $startTs]);
                        }
                    } catch (Throwable $e) {
                        $errors[] = 'gameServers updated, but per-world config sync failed: ' . $e->getMessage();
                    }
                }

                if (!$errors) {
                    $success = true;
                    // Re-fetch to show updated values
                    $stmt->execute([':id' => $id]);
                    $world = $stmt->fetch();
                }
            }
        }

        $currentStartDT = gmdate('Y-m-d\TH:i', (int)$world['startTime']);
        $currentRoundLength = (int)$world['roundLength'];
        $endTime = (int)$world['startTime'] + $currentRoundLength * 86400;

        ob_start();
        ?>
        <h1>Edit Times — <?= h($world['name']) ?> (<?= h($world['worldId']) ?>)</h1>
        <p><a href="index.php?action=servers">&laquo; Back to list</a></p>

        <?php if ($errors): ?>
            <div class="ga-msg ga-msg-err">
                <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="ga-msg ga-msg-ok"><strong>Times updated successfully.</strong></div>
        <?php endif; ?>

        <form method="post" class="ga-form">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; max-width:700px;">
                <div>
                    <label>Start Time (UTC)</label>
                    <input name="startTimeDT" type="datetime-local" value="<?= h($currentStartDT) ?>" required>
                </div>
                <div>
                    <label>Round Length (days)</label>
                    <input name="roundLength" type="number" min="1" value="<?= $currentRoundLength ?>" required>
                </div>
                <div>
                    <label>Computed End Time</label>
                    <input type="text" value="<?= gmdate('Y-m-d H:i', $endTime) ?> UTC" readonly style="background:#eee;">
                </div>
            </div>

            <div style="margin:20px 0;">
                <button class="ga-btn ga-btn-primary" type="submit">Save Changes</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Toggle a boolean field on a game world (finish, hide, registration, etc.).
     */
    private function handleToggle(): string
    {
        $id    = (int)($_GET['id'] ?? 0);
        $field = $_GET['field'] ?? '';
        $value = (int)($_GET['value'] ?? 0);
        $csrf  = $_GET['_csrf'] ?? '';

        $allowed = ['finished', 'hidden', 'registerClosed', 'activation'];
        if (!$id || !in_array($field, $allowed, true) || !hash_equals(csrfToken(), $csrf)) {
            header('Location: index.php?action=servers');
            exit;
        }

        $stmt = $this->db->prepare("UPDATE gameServers SET `$field` = :val WHERE id = :id");
        $stmt->execute([':val' => $value, ':id' => $id]);

        header('Location: index.php?action=servers');
        exit;
    }

    /**
     * AJAX endpoint to check if a world path exists.
     */
    private function ajaxCheckWorld(): string
    {
        $worldId = strtolower(preg_replace('~[^a-z0-9-]~', '', $_GET['worldId'] ?? ''));
        $serversRoot = dirname(__DIR__, 4) . '/servers/';
        $worldPath = $serversRoot . $worldId . '/';

        header('Content-Type: application/json');
        echo json_encode([
            'ok'      => true,
            'exists'  => is_dir($worldPath),
            'worldId' => $worldId,
        ]);
        exit;
    }

    /**
     * Create new game world form + processing.
     * Absorbs the logic from integrations/install/index.php.
     */
    private function handleCreate(): string
    {
        global $globalConfig;

        $domain = getBaseDomain();
        $errors = [];
        $result = null;

        // Main DB creds from config
        $mainDb = [
            'host'    => $globalConfig['dataSources']['globalDB']['hostname'] ?? 'localhost',
            'user'    => $globalConfig['dataSources']['globalDB']['username'] ?? '',
            'pass'    => $globalConfig['dataSources']['globalDB']['password'] ?? '',
            'name'    => $globalConfig['dataSources']['globalDB']['database'] ?? '',
            'charset' => $globalConfig['dataSources']['globalDB']['charset'] ?? 'utf8mb4',
        ];

        // Defaults
        $defaults = [
            'db_host'                    => getenv('GAME_DB_HOST') ?: (getenv('DB_HOST') ?: 'localhost'),
            'db_user'                    => getenv('GAME_DB_USER') ?: (getenv('MYSQL_USER') ?: ''),
            'db_name'                    => '',
            'db_password'                => getenv('GAME_DB_PASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: ''),
            'worldId'                    => '',
            'serverName'                 => '',
            'speed'                      => 50000,
            'roundLength'                => 7,
            'mapSize'                    => 100,
            'isPromoted'                 => 0,
            'startGold'                  => 3600,
            'buyTroops'                  => 0,
            'buyTroopsInterval'          => 0,
            'buyResources'               => 0,
            'buyResourcesInterval'       => 0,
            'buyAnimals'                 => 0,
            'buyAnimalsInterval'         => 0,
            'protectionHours'            => 24,
            'needPreregistrationCode'    => 0,
            'serverHidden'               => 0,
            'instantFinishTraining'      => 1,
            'buyAdventure'               => 1,
            'activation'                 => 0,
            'auto_reinstall'             => 0,
            'auto_reinstall_start_after' => 86400,
            'startTimeDT'                => (new DateTime('+1 hour', new DateTimeZone('UTC')))->format('Y-m-d\TH:i'),
            'admin_password'             => getenv('GAME_ADMIN_PASSWORD') ?: '',
        ];

        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $g = function (string $k, $fallback = null) {
                return $_POST[$k] ?? $fallback;
            };

            $input = [
                'db_host'     => trim((string)$g('db_host', $defaults['db_host'])),
                'db_user'     => trim((string)$g('db_user', $defaults['db_user'])),
                'db_name'     => trim((string)$g('db_name', $defaults['db_name'])),
                'db_password' => (string)$g('db_password', $defaults['db_password']),
                'worldId'     => strtolower(trim((string)$g('worldId', $defaults['worldId']))),
                'serverName'  => trim((string)$g('serverName', $defaults['serverName'])),
                'speed'       => (int)$g('speed', $defaults['speed']),
                'roundLength' => (int)$g('roundLength', $defaults['roundLength']),
                'mapSize'     => (int)$g('mapSize', $defaults['mapSize']),
                'isPromoted'  => (int)!!$g('isPromoted', $defaults['isPromoted']),
                'startGold'   => (int)$g('startGold', $defaults['startGold']),
                'buyTroops'   => (int)!!$g('buyTroops', $defaults['buyTroops']),
                'buyTroopsInterval'    => (int)$g('buyTroopsInterval', $defaults['buyTroopsInterval']),
                'buyResources'=> (int)!!$g('buyResources', $defaults['buyResources']),
                'buyResourcesInterval' => (int)$g('buyResourcesInterval', $defaults['buyResourcesInterval']),
                'buyAnimals'  => (int)!!$g('buyAnimals', $defaults['buyAnimals']),
                'buyAnimalsInterval'   => (int)$g('buyAnimalsInterval', $defaults['buyAnimalsInterval']),
                'protectionHours'      => max(0, (int)$g('protectionHours', $defaults['protectionHours'])),
                'needPreregistrationCode' => (int)!!$g('needPreregistrationCode', $defaults['needPreregistrationCode']),
                'serverHidden'            => (int)!!$g('serverHidden', $defaults['serverHidden']),
                'instantFinishTraining'   => (int)!!$g('instantFinishTraining', $defaults['instantFinishTraining']),
                'buyAdventure'            => (int)!!$g('buyAdventure', $defaults['buyAdventure']),
                'activation'              => (int)!!$g('activation', $defaults['activation']),
                'auto_reinstall'          => (int)!!$g('auto_reinstall', $defaults['auto_reinstall']),
                'auto_reinstall_start_after' => (int)$g('auto_reinstall_start_after', $defaults['auto_reinstall_start_after']),
                'admin_password'          => (string)$g('admin_password', ''),
                'startTimeDT'             => (string)$g('startTimeDT', $defaults['startTimeDT']),
            ];

            // Validate
            if (!preg_match('~^[a-z0-9-]{1,32}$~', $input['worldId'])) {
                $errors[] = 'World ID must be 1-32 chars [a-z0-9-].';
            }
            if ($input['serverName'] === '') {
                $errors[] = 'Server name is required.';
            }
            foreach (['db_host', 'db_user', 'db_name'] as $k) {
                if ($input[$k] === '') {
                    $errors[] = "Field '$k' is required.";
                }
            }
            if ($input['admin_password'] === '' || strlen($input['admin_password']) < 6) {
                $errors[] = 'Admin password is required and must be at least 6 characters.';
            }
            if ($input['speed'] <= 0 || $input['roundLength'] <= 0 || $input['mapSize'] <= 0) {
                $errors[] = 'Speed, round length and map size must be positive.';
            }

            $startTs = null;
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $input['startTimeDT'], new DateTimeZone('UTC'));
            if ($dt !== false) {
                $startTs = $dt->getTimestamp();
            } else {
                $errors[] = 'Invalid start time.';
            }

            $gameWorldUrl = "http://{$input['worldId']}.{$domain}/";

            if (!$errors) {
                try {
                    $result = $this->runInstaller($input, $startTs, $gameWorldUrl, $mainDb);
                } catch (Throwable $e) {
                    $errors[] = 'Installer failed: ' . $e->getMessage();
                }
            }

            // Merge input back to defaults for form re-population
            $defaults = array_merge($defaults, $input);
        }

        ob_start();
        ?>
        <h1>Create New Game World</h1>
        <p><a href="index.php?action=servers">&laquo; Back to list</a></p>

        <?php if ($errors): ?>
            <div class="ga-msg ga-msg-err">
                <strong>Fix these issues:</strong>
                <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="ga-msg <?= $result['success'] ? 'ga-msg-ok' : 'ga-msg-err' ?>">
                <?php if ($result['success']): ?>
                    <strong>World created successfully!</strong>
                    World ID: <b><?= h($result['worldId']) ?></b> |
                    URL: <a href="<?= h($result['gameWorldUrl']) ?>" target="_blank"><?= h($result['gameWorldUrl']) ?></a>
                <?php else: ?>
                    <strong>Installation had issues.</strong> Check output below.
                <?php endif; ?>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:10px 0;">
                <div>
                    <h3>Installer output (exit <?= (int)$result['cmd1_code'] ?>)</h3>
                    <pre style="background:#222; color:#ddd; padding:8px; border-radius:4px; max-height:200px; overflow:auto; font-size:10px;"><?= h($result['cmd1_out']) ?></pre>
                </div>
                <div>
                    <h3>Updater output (exit <?= (int)$result['cmd2_code'] ?>)</h3>
                    <pre style="background:#222; color:#ddd; padding:8px; border-radius:4px; max-height:200px; overflow:auto; font-size:10px;"><?= h($result['cmd2_out']) ?></pre>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off" class="ga-form">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">

            <h2>Server Identity</h2>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                <div>
                    <label>World ID</label>
                    <input id="worldId" name="worldId" type="text" value="<?= h($defaults['worldId']) ?>" pattern="[a-z0-9-]{1,32}" required>
                </div>
                <div>
                    <label>Server Name</label>
                    <input name="serverName" type="text" value="<?= h($defaults['serverName']) ?>">
                </div>
                <div>
                    <label>Speed</label>
                    <input name="speed" type="number" min="1" value="<?= (int)$defaults['speed'] ?>">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                <div>
                    <label>Round Length (days)</label>
                    <input name="roundLength" type="number" min="1" value="<?= (int)$defaults['roundLength'] ?>">
                </div>
                <div>
                    <label>Map Size</label>
                    <input name="mapSize" type="number" min="1" value="<?= (int)$defaults['mapSize'] ?>">
                </div>
                <div>
                    <label>Start Time (UTC)</label>
                    <input name="startTimeDT" type="datetime-local" value="<?= h($defaults['startTimeDT']) ?>">
                </div>
            </div>

            <h2>Game Database</h2>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:10px;">
                <div>
                    <label>DB Host</label>
                    <input name="db_host" type="text" value="<?= h($defaults['db_host']) ?>">
                </div>
                <div>
                    <label>DB User</label>
                    <input name="db_user" type="text" value="<?= h($defaults['db_user']) ?>">
                </div>
                <div>
                    <label>DB Name</label>
                    <input id="dbName" name="db_name" type="text" value="<?= h($defaults['db_name']) ?>">
                </div>
                <div>
                    <label>DB Password</label>
                    <input name="db_password" type="password" value="<?= h($defaults['db_password']) ?>">
                </div>
            </div>

            <h2>Options</h2>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                <div>
                    <label>Start Gold</label>
                    <input name="startGold" type="number" min="0" value="<?= (int)$defaults['startGold'] ?>">
                </div>
                <div>
                    <label>Protection (hours)</label>
                    <input name="protectionHours" type="number" min="0" value="<?= (int)$defaults['protectionHours'] ?>">
                </div>
                <div>
                    <label>Admin Password (Multihunter)</label>
                    <input name="admin_password" type="password" value="<?= h($defaults['admin_password']) ?>">
                </div>
            </div>
            <div style="margin:10px 0; display:grid; grid-template-columns:repeat(3,1fr); gap:8px;">
                <?php
                $checkboxes = [
                    'instantFinishTraining' => 'Instant Finish Training',
                    'buyAdventure'          => 'Buy Adventure',
                    'activation'            => 'Activation Required',
                    'isPromoted'            => 'Promoted',
                    'serverHidden'          => 'Hidden',
                    'needPreregistrationCode' => 'Preregistration Key Only',
                    'buyTroops'             => 'Buy Troops',
                    'buyResources'          => 'Buy Resources',
                    'buyAnimals'            => 'Buy Animals',
                    'auto_reinstall'        => 'Auto Reinstall',
                ];
                foreach ($checkboxes as $name => $label):
                    $checked = !empty($defaults[$name]) ? 'checked' : '';
                ?>
                    <label style="display:inline; font-weight:normal;">
                        <input type="checkbox" name="<?= h($name) ?>" value="1" <?= $checked ?>> <?= h($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px;">
                <div>
                    <label>Buy Troops Interval (sec)</label>
                    <input name="buyTroopsInterval" type="number" min="0" value="<?= (int)$defaults['buyTroopsInterval'] ?>">
                </div>
                <div>
                    <label>Buy Resources Interval (sec)</label>
                    <input name="buyResourcesInterval" type="number" min="0" value="<?= (int)$defaults['buyResourcesInterval'] ?>">
                </div>
                <div>
                    <label>Buy Animals Interval (sec)</label>
                    <input name="buyAnimalsInterval" type="number" min="0" value="<?= (int)$defaults['buyAnimalsInterval'] ?>">
                </div>
                <div>
                    <label>Auto Reinstall After (sec)</label>
                    <input name="auto_reinstall_start_after" type="number" min="0" value="<?= (int)$defaults['auto_reinstall_start_after'] ?>">
                </div>
            </div>

            <div style="margin:20px 0;">
                <button class="ga-btn ga-btn-primary" type="submit">Create World</button>
            </div>
        </form>

        <script>
        (function(){
            var wEl = document.getElementById('worldId');
            var dbEl = document.getElementById('dbName');
            var manual = false;
            dbEl.addEventListener('input', function(){ manual = true; });
            wEl.addEventListener('input', function(){
                if (!manual) {
                    var w = (wEl.value||'').toLowerCase().replace(/[^a-z0-9-]/g,'');
                    dbEl.value = 'travium_' + w.replace(/-/g,'_');
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Run the actual world installation.
     * Mirrors the logic from integrations/install/index.php.
     */
    private function runInstaller(array $input, int $startTs, string $gameWorldUrl, array $mainDb): array
    {
        $htdocsRoot   = dirname(__DIR__, 4) . '/';
        $basePath     = $htdocsRoot . 'src/';
        $serversRoot  = $htdocsRoot . 'servers/';
        $templateRoot = $htdocsRoot . 'server.tpl';
        $worldIdSafe  = $input['worldId'];
        $worldRoot    = $serversRoot . $worldIdSafe . '/';
        $publicRoot   = $worldRoot . 'public/';
        $includePath  = $worldRoot . 'include/';
        $connectionFile = $includePath . 'connection.php';
        $installerFile  = $includePath . 'install.php';
        $updateFile     = $includePath . 'update.php';
        $envFile        = $includePath . 'env.php';

        // Archive existing world if any
        if (is_dir($worldRoot)) {
            $archivePath = rtrim($worldRoot, '/') . '-' . time();
            if (!@rename($worldRoot, $archivePath)) {
                throw new RuntimeException("Failed to archive existing world: $worldRoot");
            }
        }

        // Create fresh world dir from template
        if (!is_dir($worldRoot) && !mkdir($worldRoot, 0775, true)) {
            throw new RuntimeException("Failed to create world directory: $worldRoot");
        }
        if (!is_dir($templateRoot)) {
            throw new RuntimeException("Template not found at: $templateRoot");
        }

        $copyCmd = sprintf('/bin/cp -a %s/. %s 2>&1', escapeshellarg($templateRoot), escapeshellarg($worldRoot));
        $copyOut = [];
        $copyCode = 0;
        exec($copyCmd, $copyOut, $copyCode);
        if ($copyCode !== 0) {
            throw new RuntimeException("Failed to copy template: " . implode("\n", $copyOut));
        }

        if (!is_dir($includePath) && !mkdir($includePath, 0775, true)) {
            throw new RuntimeException("Failed to ensure include/ directory: $includePath");
        }

        // Auto-create game DB
        $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $input['db_name']);
        $tmpPdo = new PDO(
            "mysql:host={$input['db_host']};charset=utf8mb4",
            $input['db_user'],
            $input['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $tmpPdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $tmpPdo = null;

        // Connect to the game world DB
        $gameDb = new PDO(
            "mysql:host={$input['db_host']};dbname={$input['db_name']};charset=utf8mb4",
            $input['db_user'],
            $input['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        // Ensure env flag
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            if ($envContent !== false) {
                $envContent = str_replace("'%[IS_DEV]%'", 'true', $envContent);
                file_put_contents($envFile, $envContent);
            }
        }

        // Prepare connection.php
        if (!file_exists($connectionFile)) {
            throw new RuntimeException("Missing $connectionFile");
        }
        $connection_content = file_get_contents($connectionFile);

        $order = [
            '[PAYMENT_FEATURES_TOTALLY_DISABLED]', '[TITLE]', '[GAME_WORLD_URL]',
            '[GAME_SERVER_NAME]', '[DATABASE_HOST]', '[DATABASE_DATABASE]',
            '[DATABASE_USERNAME]', '[DATABASE_PASSWORD]',
        ];
        $order_values = [
            'false', $input['worldId'], $gameWorldUrl, $input['serverName'],
            $input['db_host'], $input['db_name'], $input['db_user'], $input['db_password'],
        ];
        $connection_content = str_replace($order, $order_values, $connection_content);

        // Insert gameServers row
        $stmt = $this->db->prepare("
            INSERT INTO `gameServers`
            (`worldId`,`speed`,`name`,`version`,`gameWorldUrl`,`startTime`,`roundLength`,
             `preregistration_key_only`,`promoted`,`hidden`,`configFileLocation`,`activation`)
            VALUES (:worldId,:speed,:name,0,:url,:start,:length,:preKey,:promoted,:hidden,:cfg,:activation)
        ");
        $stmt->execute([
            ':worldId'   => $input['worldId'],
            ':speed'     => $input['speed'],
            ':name'      => $input['serverName'],
            ':url'       => $gameWorldUrl,
            ':start'     => $startTs,
            ':length'    => $input['roundLength'],
            ':preKey'    => $input['needPreregistrationCode'],
            ':promoted'  => $input['isPromoted'],
            ':hidden'    => $input['serverHidden'],
            ':cfg'       => $connectionFile,
            ':activation' => $input['activation'],
        ]);
        $worldUniqueId = (int)$this->db->lastInsertId();

        $processName = 'travian_500x.service';
        $order2 = [
            '[SETTINGS_WORLD_ID]', '[SETTINGS_WORLD_UNIQUE_ID]', '[GAME_SPEED]',
            '[GAME_START_TIME]', '[GAME_ROUND_LENGTH]', '[SECURE_HASH_CODE]',
            '[AUTO_REINSTALL]', '[AUTO_REINSTALL_START_AFTER]', '[ENGINE_FILENAME]',
        ];
        $order2_values = [
            $input['worldId'], $worldUniqueId, $input['speed'], $startTs,
            $input['roundLength'], md5(sha1(microtime())),
            $input['auto_reinstall'], $input['auto_reinstall_start_after'], $processName,
        ];
        $connection_content = str_replace($order2, $order2_values, $connection_content);
        file_put_contents($connectionFile, $connection_content);

        // Import schema
        $schemaPath = $basePath . 'schema/T4.4.sql';
        if (!file_exists($schemaPath)) {
            throw new RuntimeException("Missing schema at $schemaPath");
        }
        $sql = file_get_contents($schemaPath);
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($queries as $query) {
            if ($query !== '') {
                $gameDb->exec($query . ';');
            }
        }

        // Add config row
        $cfgStmt = $gameDb->prepare("INSERT INTO `config`
            (`startTime`,`map_size`,`worldUniqueId`,`installed`,`loginInfoTitle`,`loginInfoHTML`,`message`)
            VALUES (:start,:map,:uid,0,'','','')");
        $cfgStmt->execute([
            ':start' => $startTs,
            ':map'   => $input['mapSize'],
            ':uid'   => $worldUniqueId,
        ]);

        // Write config.custom.php
        $configCustom = [
            '<?php',
            'global $globalConfig, $config;',
            '$config->gold->startGold = ' . (int)$input['startGold'] . ';',
            '$config->extraSettings->buyTroops[\'enabled\'] = ' . ($input['buyTroops'] ? 'true' : 'false') . ';',
            '$config->extraSettings->buyAnimal[\'enabled\'] = ' . ($input['buyAnimals'] ? 'true' : 'false') . ';',
            '$config->extraSettings->buyResources[\'enabled\'] = ' . ($input['buyResources'] ? 'true' : 'false') . ';',
            '$config->extraSettings->buyTroops[\'buyInterval\'] = ' . (int)$input['buyTroopsInterval'] . ';',
            '$config->extraSettings->buyResources[\'buyInterval\'] = ' . (int)$input['buyResourcesInterval'] . ';',
            '$config->extraSettings->buyAnimal[\'buyInterval\'] = ' . (int)$input['buyAnimalsInterval'] . ';',
            '$config->game->protection_time = ' . ((int)$input['protectionHours'] * 3600) . ';',
            '$config->extraSettings->generalOptions->finishTraining->enabled = ' . ($input['instantFinishTraining'] ? 'true' : 'false') . ';',
            '$config->extraSettings->generalOptions->buyAdventure->enabled = ' . ($input['buyAdventure'] ? 'true' : 'false') . ';',
        ];
        file_put_contents($includePath . 'config.custom.php', implode("\n", $configCustom) . "\n");

        // Run installer + updater via CLI
        $phpBin = PHP_BINDIR . '/php';
        $cmd1 = "$phpBin $installerFile install " . escapeshellarg($input['admin_password']);
        $cmd2 = "$phpBin $updateFile";

        $out1 = [];
        $code1 = 0;
        exec($cmd1 . ' 2>&1', $out1, $code1);
        $out2 = [];
        $code2 = 0;
        exec($cmd2 . ' 2>&1', $out2, $code2);

        return [
            'success'       => ($code1 === 0 && $code2 === 0),
            'worldId'       => $input['worldId'],
            'worldUniqueId' => $worldUniqueId,
            'gameWorldUrl'  => $gameWorldUrl,
            'cmd1'          => $cmd1,
            'cmd1_code'     => $code1,
            'cmd1_out'      => implode("\n", $out1),
            'cmd2'          => $cmd2,
            'cmd2_code'     => $code2,
            'cmd2_out'      => implode("\n", $out2),
        ];
    }
}
