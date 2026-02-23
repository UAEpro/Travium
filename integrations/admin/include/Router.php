<?php
/**
 * Global Admin Panel - Router
 *
 * Maps ?action=<name> to Controller classes.
 * Enforces authentication for all actions except login.
 */

class Router
{
    private $db;
    private $auth;

    /** action => [class file, class name] */
    private $routes = [
        'login'      => ['Controllers/LoginCtrl.php',      'LoginCtrl'],
        'dashboard'  => ['Controllers/DashboardCtrl.php',  'DashboardCtrl'],
        'servers'    => ['Controllers/ServersCtrl.php',     'ServersCtrl'],
        'world'      => ['Controllers/WorldProxyCtrl.php',  'WorldProxyCtrl'],
        'settings'   => ['Controllers/SettingsCtrl.php',    'SettingsCtrl'],
        'payment'    => ['Controllers/PaymentCtrl.php',     'PaymentCtrl'],
        'adminUsers' => ['Controllers/AdminUsersCtrl.php',  'AdminUsersCtrl'],
    ];

    public function __construct(PDO $db)
    {
        $this->db   = $db;
        $this->auth = new Auth($db);
    }

    public function dispatch(): void
    {
        $action = $_GET['action'] ?? '';

        // Logout
        if ($action === 'logout') {
            $this->auth->logout();
            header('Location: index.php?action=login');
            exit;
        }

        // Unauthenticated -> force login
        if (!$this->auth->isLoggedIn() && $action !== 'login') {
            header('Location: index.php?action=login');
            exit;
        }

        // Default action when logged in
        if ($this->auth->isLoggedIn() && ($action === '' || $action === 'login')) {
            header('Location: index.php?action=dashboard');
            exit;
        }

        // Resolve controller
        if (!isset($this->routes[$action])) {
            http_response_code(404);
            echo '<h1>404 - Action not found</h1>';
            exit;
        }

        [$file, $class] = $this->routes[$action];
        require __DIR__ . '/' . $file;

        $controller = new $class($this->db, $this->auth);
        $content    = $controller->handle();

        // Login page renders its own layout
        if ($action === 'login') {
            echo $content;
            return;
        }

        // All other pages use the admin layout
        $tpl = new GlobalAdminTemplate();
        $tpl->render([
            'title'    => $controller->getTitle(),
            'content'  => $content,
            'menus'    => $this->buildMenu($action),
            'user'     => $this->auth->getUser(),
            'world'    => $_GET['world'] ?? null,
        ]);
    }

    /**
     * Build the left sidebar menu HTML.
     */
    private function buildMenu(string $currentAction): string
    {
        $items = [
            ['action' => 'dashboard',  'label' => 'Dashboard'],
            ['action' => 'servers',    'label' => 'Game Worlds'],
            ['action' => 'settings',   'label' => 'Global Settings'],
            ['action' => 'payment',    'label' => 'Payment'],
            ['action' => 'adminUsers', 'label' => 'Admin Users'],
        ];

        $html = '';
        foreach ($items as $item) {
            $active = ($item['action'] === $currentAction) ? ' class="active"' : '';
            $html .= '<a href="index.php?action=' . h($item['action']) . '"' . $active . '>' . h($item['label']) . '</a>';
        }

        // Payment sub-menu
        if ($currentAction === 'payment') {
            $html .= $this->buildPaymentMenu();
        }

        // If a world is selected, add world-specific menu
        $worldId = $_GET['world'] ?? null;
        if ($worldId && $currentAction === 'world') {
            $html .= '<br />';
            $html .= '<a href="#"><b>' . h($worldId) . ' - World Admin</b></a>';
            $html .= $this->buildWorldMenu($worldId);
        }

        $html .= '<br />';
        $html .= '<a href="index.php?action=logout">Logout</a>';

        return $html;
    }

    /**
     * Build payment sub-menu for the sidebar.
     */
    private function buildPaymentMenu(): string
    {
        $currentSection = $_GET['section'] ?? 'overview';
        $base = 'index.php?action=payment&section=';

        $sections = [
            'overview'  => 'Overview',
            'settings'  => 'Settings',
            'locations' => 'Locations',
            'providers' => 'Providers',
            'products'  => 'Products',
            'vouchers'  => 'Vouchers',
            'logs'      => 'Logs',
            'codes'     => 'Codes',
            'giftCodes' => 'Gift Codes',
        ];

        $html = '<br />';
        $html .= '<a href="#"><b>Payment</b></a>';
        foreach ($sections as $key => $label) {
            $active = ($key === $currentSection) ? ' style="color: green; font-weight: bold;"' : '';
            $html .= '<a href="' . h($base . $key) . '"' . $active . '>' . h($label) . '</a>';
        }

        return $html;
    }

    /**
     * Build per-world admin menu (mirrors existing admin dispatcher menu).
     */
    private function buildWorldMenu(string $worldId): string
    {
        $base = 'index.php?action=world&world=' . urlencode($worldId) . '&worldAction=';

        $sections = [
            'Management' => [
                'main'               => 'Control Panel Home',
                'configurationDetails' => 'Info & Settings',
                'truce'              => 'Truce',
                'editPlayer'         => 'Edit Player',
                'editVillage'        => 'Edit Village',
                'upgradeVillage'     => 'Upgrade Village',
            ],
            'Users' => [
                'fakeUser'           => 'Fake Users',
                'deleting'           => 'Deleting Users',
                'verificationList'   => 'Verification List',
                'activationList'     => 'Activation List',
                'bannedList'         => 'Ban/Unban',
                'multiAccount'       => 'Multi-account Users',
            ],
            'Filtering' => [
                'filteredUrls'       => 'URLs',
                'badWords'           => 'Bad Words',
                'blackListedNames'   => 'Blacklisted Names',
            ],
            'Communication' => [
                'news'               => 'News',
                'publicInfobox'      => 'Public InfoBox',
                'privateInfobox'     => 'Private InfoBox',
                'sendPublicMessage'  => 'Send Public Msg',
                'sendPrivateMessage' => 'Send Private Msg',
                'reportedMessages'   => 'Reported Messages',
            ],
            'Hero' => [
                'heroAuction'        => 'Hero Auction',
                'heroAddItem'        => 'Add Hero Item',
            ],
            'Other' => [
                'IPBan'              => 'IP Ban',
                'blackListEmail'     => 'Email Blacklist',
                'Cleanup'            => 'Cleanup',
                'fixes'              => 'Fixes',
                'backups'            => 'Backups',
                'gifts'              => 'Gifts',
                'advertisement'      => 'Advertisements',
                'minimap'            => 'Minimap',
                'adminLog'           => 'Admin Log',
            ],
            'Newsletter' => [
                'sendEmail'          => 'Send Email',
                'sendTestEmail'      => 'Send Test Email',
                'importEmail'        => 'Import Email',
                'deleteEmailNewsletter' => 'Unsubscribe',
            ],
        ];

        $currentWorldAction = $_GET['worldAction'] ?? '';
        $html = '';
        foreach ($sections as $title => $actions) {
            $html .= '<br />';
            $html .= '<a href="#"><b>' . h($title) . '</b></a>';
            foreach ($actions as $action => $label) {
                $active = ($action === $currentWorldAction) ? ' style="color: green; font-weight: bold;"' : '';
                $html .= '<a href="' . h($base . $action) . '"' . $active . '>' . h($label) . '</a>';
            }
        }

        return $html;
    }
}
