<?php
/**
 * Global Admin Panel - World Proxy Controller
 *
 * Connects to a specific world's DB, bootstraps its context, and loads
 * the existing per-world admin controllers. This reuses all 57 existing
 * controllers without rewriting them.
 *
 * URL: ?action=world&world=s1&worldAction=editPlayer&uid=2
 *
 * How it works:
 *  1. Reads the world's connection info from gameServers + connection.php
 *  2. Defines the constants that the per-world code expects
 *  3. Injects admin session vars so Session::getInstance() sees us as admin
 *  4. Resets per-world singletons (Config, DB, Caching, Session, Dispatcher)
 *  5. Loads the per-world admin Dispatcher which runs the requested controller
 *  6. Captures the HTML output and wraps it in the global admin layout
 */

class WorldProxyCtrl
{
    private $db;
    private $auth;
    private $title = 'World Admin';
    private $worldId;

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
        $this->worldId = $_GET['world'] ?? '';
        if ($this->worldId === '') {
            return '<div class="ga-msg ga-msg-err">No world specified.</div>';
        }

        // Fetch world info from gameServers
        $stmt = $this->db->prepare('SELECT * FROM gameServers WHERE worldId = :wid LIMIT 1');
        $stmt->execute([':wid' => $this->worldId]);
        $world = $stmt->fetch();

        if (!$world) {
            return '<div class="ga-msg ga-msg-err">World "' . h($this->worldId) . '" not found.</div>';
        }

        $this->title = $world['worldId'] . ' - World Admin';

        // Validate and resolve configFileLocation path
        $connectionFile = $world['configFileLocation'];
        $projectRoot = dirname(__DIR__, 4);
        $expectedBase = realpath($projectRoot . '/servers/');
        if ($expectedBase !== false) {
            $realConn = realpath($connectionFile);
            if ($realConn === false || strpos($realConn, $expectedBase . DIRECTORY_SEPARATOR) !== 0) {
                return '<div class="ga-msg ga-msg-err">Invalid connection file path for this world.</div>';
            }
        }

        if (!file_exists($connectionFile)) {
            return '<div class="ga-msg ga-msg-err">Connection file not found: ' . h($connectionFile) . '</div>';
        }

        $worldIncludeDir = dirname($connectionFile);
        $worldDir = dirname($worldIncludeDir);
        $srcDir = $projectRoot . '/src';

        // Set the worldAction for the per-world Dispatcher
        $worldAction = $_GET['worldAction'] ?? 'main';
        $_REQUEST['action'] = $worldAction;
        $_GET['action'] = $worldAction;

        try {
            $content = $this->bootstrapAndRun($world, $connectionFile, $worldIncludeDir, $projectRoot, $srcDir);
        } catch (Throwable $e) {
            $content = '<div class="ga-msg ga-msg-err">'
                . '<strong>Error loading world admin:</strong><br>'
                . h($e->getMessage())
                . '<pre style="font-size:10px; margin-top:8px;">' . h($e->getTraceAsString()) . '</pre>'
                . '</div>';
        }

        return $content;
    }

    /**
     * Reset a singleton's static instance so it re-initializes on next getInstance().
     */
    private function resetSingleton(string $class): void
    {
        try {
            $ref = new ReflectionProperty($class, '_self');
            $ref->setAccessible(true);
            $ref->setValue(null, null);
        } catch (ReflectionException $e) {
            // Class or property doesn't exist yet — that's fine
        }
    }

    /**
     * Bootstrap the world context and run the per-world admin controller.
     */
    private function bootstrapAndRun(
        array $world,
        string $connectionFile,
        string $worldIncludeDir,
        string $projectRoot,
        string $srcDir
    ): string {
        // ----------------------------------------------------------------
        // 1. Define constants the per-world code expects
        //    (constants can only be defined once, so guard with defined())
        // ----------------------------------------------------------------
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', $projectRoot . DIRECTORY_SEPARATOR);
        }
        if (!defined('INCLUDE_PATH')) {
            define('INCLUDE_PATH', $srcDir . DIRECTORY_SEPARATOR);
        }
        if (!defined('RESOURCES_PATH')) {
            define('RESOURCES_PATH', INCLUDE_PATH . 'resources' . DIRECTORY_SEPARATOR);
        }
        if (!defined('LOCALE_PATH')) {
            define('LOCALE_PATH', RESOURCES_PATH . 'Translation' . DIRECTORY_SEPARATOR);
        }
        if (!defined('TEMPLATES_PATH')) {
            define('TEMPLATES_PATH', RESOURCES_PATH . 'Templates' . DIRECTORY_SEPARATOR);
        }
        if (!defined('CONNECTION_FILE')) {
            define('CONNECTION_FILE', $connectionFile);
        }
        if (!defined('GLOBAL_CONFIG_FILE')) {
            define('GLOBAL_CONFIG_FILE', $projectRoot . '/config.php');
        }
        if (!defined('CONFIG_CUSTOM_FILE')) {
            define('CONFIG_CUSTOM_FILE', $worldIncludeDir . '/config.custom.php');
        }
        if (!defined('ERROR_LOG_FILE')) {
            define('ERROR_LOG_FILE', $worldIncludeDir . '/error_log.log');
        }
        if (!defined('BACKUP_PATH')) {
            define('BACKUP_PATH', dirname($worldIncludeDir) . '/backups/');
        }
        if (!defined('SRC_PATH_PROD')) {
            define('SRC_PATH_PROD', $srcDir);
        }
        if (!defined('SRC_PATH_DEV')) {
            define('SRC_PATH_DEV', $srcDir);
        }
        if (!defined('IS_DEV')) {
            define('IS_DEV', true);
        }
        if (!defined('PUBLIC_PATH')) {
            define('PUBLIC_PATH', dirname($worldIncludeDir) . '/public/');
        }
        if (!defined('GLOBAL_CACHING_KEY')) {
            define('GLOBAL_CACHING_KEY', get_current_user());
        }
        if (!defined('FILTERING_PATH')) {
            define('FILTERING_PATH', $srcDir . '/filtering/');
        }
        if (!defined('ADMIN_PANEL')) {
            define('ADMIN_PANEL', true);
        }

        // Set start_time global expected by Dispatcher
        $GLOBALS['start_time'] = microtime(true);

        // ----------------------------------------------------------------
        // 2. Load vendor autoload + custom autoloader + helpers
        // ----------------------------------------------------------------
        $vendorAutoload = $projectRoot . '/vendor/autoload.php';
        if (file_exists($vendorAutoload)) {
            require_once $vendorAutoload;
        }
        require_once INCLUDE_PATH . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';
        require_once INCLUDE_PATH . 'functions.general.php';

        // ----------------------------------------------------------------
        // 3. Read the world's connection info
        // ----------------------------------------------------------------
        global $connection;
        require $connectionFile;

        // ----------------------------------------------------------------
        // 4. Connect to the world DB to get admin credentials
        //    We need the Multihunter (uid=2) password hash to inject into session
        // ----------------------------------------------------------------
        $worldDbConf = $connection['database'];
        $worldPdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s',
                $worldDbConf['hostname'], $worldDbConf['database'], $worldDbConf['charset'] ?? 'utf8mb4'),
            $worldDbConf['username'],
            $worldDbConf['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        // Get Multihunter (uid=2) password - the per-world admin user
        $mhStmt = $worldPdo->query('SELECT id, password FROM users WHERE id = 2 LIMIT 1');
        $mh = $mhStmt->fetch();
        if (!$mh) {
            return '<div class="ga-msg ga-msg-err">Multihunter user (id=2) not found in world DB.</div>';
        }

        // ----------------------------------------------------------------
        // 5. Reset singletons so they re-initialize for this world
        //    (critical for PHP-FPM workers that handle multiple requests)
        // ----------------------------------------------------------------
        $this->resetSingleton(\Core\Config::class);
        $this->resetSingleton(\Core\Database\DB::class);
        $this->resetSingleton(\Core\Database\GlobalDB::class);
        $this->resetSingleton(\Core\Caching\Caching::class);
        $this->resetSingleton(\Core\Caching\GlobalCaching::class);
        $this->resetSingleton(\Core\Session::class);

        // Reset Dispatcher singleton (it's in the global namespace)
        if (class_exists('Dispatcher', false)) {
            $this->resetSingleton(\Dispatcher::class);
        }

        // ----------------------------------------------------------------
        // 6. Initialize Config singleton (reads connection.php + config.php)
        // ----------------------------------------------------------------
        $config = \Core\Config::getInstance();
        if (!property_exists($config, 'db')) {
            return '<div class="ga-msg ga-msg-err">World config not properly initialized (missing db property).</div>';
        }

        // ----------------------------------------------------------------
        // 7. Initialize DB singleton
        // ----------------------------------------------------------------
        $worldDb = \Core\Database\DB::getInstance();

        // Load world config from DB (same as bootstrap.php)
        $result = $worldDb->query('SELECT * FROM config');
        if (!$result->num_rows) {
            return '<div class="ga-msg ga-msg-err">No config row found in world DB.</div>';
        }
        $config->dynamic = (object)$result->fetch_assoc();
        $config->game->start_time = $config->dynamic->startTime;
        $config->settings->worldUniqueId = $config->dynamic->worldUniqueId;

        if (!defined('MAP_SIZE')) {
            define('MAP_SIZE', $config->dynamic->map_size);
        }

        // Load config.after.php
        if (file_exists(INCLUDE_PATH . 'config/config.after.php')) {
            require_once INCLUDE_PATH . 'config/config.after.php';
        }

        // ----------------------------------------------------------------
        // 8. Initialize Caching
        // ----------------------------------------------------------------
        \Core\Caching\Caching::getInstance();

        // ----------------------------------------------------------------
        // 9. Inject admin session vars so Session sees us as Multihunter
        // ----------------------------------------------------------------
        $prefix = $config->settings->worldUniqueId . ':';
        $_SESSION[$prefix . 'uid'] = 2; // Multihunter user ID
        $_SESSION[$prefix . 'pw']  = $mh['password']; // SHA1 hash from DB
        $_SESSION[$prefix . 'admin_uid'] = 0; // Mark as admin to bypass session timeout check

        // ----------------------------------------------------------------
        // 10. Load and run the per-world admin controller DIRECTLY
        //     (bypassing Dispatcher to avoid its redirect/exit on auth failure)
        // ----------------------------------------------------------------
        $adminDir = $srcDir . '/admin/include/';

        // Load AdminLog (guard against re-declaration)
        if (!class_exists('AdminLog', false)) {
            require_once $adminDir . 'Core/AdminLog.php';
        }

        // We need a Dispatcher that controllers can call appendContent() on,
        // but we CANNOT load the real Dispatcher.php because it does
        // require("Template.php") internally which causes class redeclaration.
        // Instead, define a lightweight Dispatcher stand-in if not already loaded.
        if (!class_exists('Dispatcher', false)) {
            eval('
            class Dispatcher {
                private static $_self;
                public $data = ["menus" => null, "content" => null, "infoSide" => null,
                                "loadStartTime" => 0, "currentTime" => "", "gameWorldUrl" => ""];
                public static function getInstance() {
                    if (!(self::$_self instanceof self)) {
                        self::$_self = new self();
                    }
                    return self::$_self;
                }
                public function addMenu($url, $name, $color = false) {}
                public function addMenuTitle($title) {}
                public function appendContent($content) { $this->data["content"] .= $content; }
                public function appendInfoSide($content) { $this->data["infoSide"] .= $content; }
            }
            ');
        }
        // Also ensure the per-world Template classes exist for controllers that use them
        if (!class_exists('Template', false)) {
            require_once $adminDir . 'Core/Template.php';
        }

        // Initialize Session — this must succeed with our injected session vars
        $session = \Core\Session::getInstance();
        if (!$session->isValid() || !$session->isAdmin()) {
            return '<div class="ga-msg ga-msg-err">'
                . 'Failed to authenticate as Multihunter in world "' . h($world['worldId']) . '". '
                . 'Session validation failed. The Multihunter password in your session may not match the world DB.'
                . '</div>';
        }

        // Now load the requested controller directly
        $worldAction = $_GET['worldAction'] ?? 'main';
        $ctrlClassName = ucfirst($worldAction) . 'Ctrl';
        $ctrlFile = $adminDir . 'Controllers/' . $ctrlClassName . '.php';

        if (!is_file($ctrlFile)) {
            return '<div class="ga-msg ga-msg-err">Controller not found: ' . h($ctrlClassName) . '</div>';
        }

        // Reset and configure the Dispatcher singleton
        $this->resetSingleton(\Dispatcher::class);
        $dispatcher = \Dispatcher::getInstance();
        $dispatcher->data = [
            'menus'         => null,
            'content'       => null,
            'infoSide'      => null,
            'loadStartTime' => $GLOBALS['start_time'],
            'currentTime'   => date('H:i:s'),
            'gameWorldUrl'  => $world['gameWorldUrl'],
        ];

        // Load and run the controller (controllers execute in __construct())
        require_once $ctrlFile;
        ob_start();
        new $ctrlClassName();
        ob_end_clean();

        // ----------------------------------------------------------------
        // 11. Return the captured content
        // ----------------------------------------------------------------
        $data = $dispatcher->data;
        $output = '';

        // Breadcrumb
        $output .= '<div style="margin-bottom:10px; font-size:11px; color:#888;">';
        $output .= '<a href="index.php?action=dashboard">Dashboard</a> &raquo; ';
        $output .= '<a href="index.php?action=servers">Servers</a> &raquo; ';
        $output .= '<b>' . h($world['worldId']) . '</b> (' . h($world['name']) . ')';
        $output .= '</div>';

        // The per-world admin content
        if (!empty($data['content'])) {
            $output .= $data['content'];
        }

        // Info side panel (if any)
        if (!empty($data['infoSide'])) {
            $output .= '<div style="margin-top:15px; padding:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">';
            $output .= $data['infoSide'];
            $output .= '</div>';
        }

        return $output;
    }
}
