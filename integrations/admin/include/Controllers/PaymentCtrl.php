<?php
/**
 * Global Admin Panel - Payment Management Controller
 *
 * Full CRUD for all payment entities in the global database (maindb):
 * paymentConfig, locations, paymentProviders, goldProducts,
 * paymentVoucher, paymentLog, package_codes.
 */

class PaymentCtrl
{
    private $db;
    private $auth;
    private $title = 'Payment Management';

    private $providerTypes = [
        1 => 'Zarinpal',
        2 => 'PayPal',
        4 => 'PayGol',
        9 => 'Arianpal',
    ];

    private $offerOptions = [
        0   => 'No Offer',
        10  => '10%',
        15  => '15%',
        20  => '20%',
        25  => '25%',
        30  => '30%',
        40  => '40%',
        50  => '50%',
    ];

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
            case 'settings':
                return $this->handleSettings();
            case 'locations':
                return $this->handleLocations();
            case 'providers':
                return $this->handleProviders();
            case 'products':
                return $this->handleProducts();
            case 'vouchers':
                return $this->handleVouchers();
            case 'logs':
                return $this->handleLogs();
            case 'codes':
                return $this->handleCodes();
            case 'giftCodes':
                return $this->handleCodes(true);
            default:
                return $this->handleOverview();
        }
    }

    /**
     * Build the payment sub-navigation tabs.
     */
    private function subNav(string $active): string
    {
        $tabs = [
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

        $html = '<div style="margin:0 0 15px; border-bottom:2px solid #ddd; padding-bottom:8px;">';
        foreach ($tabs as $key => $label) {
            $style = ($key === $active)
                ? 'display:inline-block; padding:5px 12px; background:#3498db; color:#fff; border-radius:3px 3px 0 0; font-size:11px; text-decoration:none; margin-right:2px;'
                : 'display:inline-block; padding:5px 12px; background:#f5f5f5; color:#333; border-radius:3px 3px 0 0; font-size:11px; text-decoration:none; margin-right:2px; border:1px solid #ddd; border-bottom:none;';
            $html .= '<a href="index.php?action=payment&section=' . h($key) . '" style="' . $style . '">' . h($label) . '</a>';
        }
        $html .= '</div>';

        return $html;
    }

    // ──────────────────────────────────────────────
    //  1. Overview
    // ──────────────────────────────────────────────

    private function handleOverview(): string
    {
        $payConfig = $this->db->query('SELECT * FROM paymentConfig LIMIT 1')->fetch();
        $providerCount = (int)$this->db->query('SELECT COUNT(*) FROM paymentProviders')->fetchColumn();
        $productCount = (int)$this->db->query('SELECT COUNT(*) FROM goldProducts')->fetchColumn();
        $locationCount = (int)$this->db->query('SELECT COUNT(*) FROM locations')->fetchColumn();
        $voucherCount = (int)$this->db->query('SELECT COUNT(*) FROM paymentVoucher WHERE used = 0')->fetchColumn();
        $logCount = (int)$this->db->query('SELECT COUNT(*) FROM paymentLog')->fetchColumn();
        $codeCount = (int)$this->db->query('SELECT COUNT(*) FROM package_codes WHERE used = 0')->fetchColumn();
        $recentStmt = $this->db->prepare('SELECT COUNT(*) FROM paymentLog WHERE time > :since');
        $recentStmt->execute([':since' => time() - 86400 * 7]);
        $recentTx = (int)$recentStmt->fetchColumn();

        ob_start();
        ?>
        <h1>Payment Management</h1>
        <?= $this->subNav('overview') ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin:15px 0;">
            <div style="background:#fff; border:1px solid #ddd; border-radius:5px; padding:15px;">
                <h3 style="margin:0 0 10px;">Payment Status</h3>
                <?php if ($payConfig): ?>
                <table class="ga-table" style="margin:0;">
                    <tr>
                        <td><b>System</b></td>
                        <td><?= $payConfig['active'] ? '<span class="ga-badge ga-badge-green">Active</span>' : '<span class="ga-badge ga-badge-red">Inactive</span>' ?></td>
                    </tr>
                    <tr>
                        <td><b>Voting Gold</b></td>
                        <td><?= (int)$payConfig['votingGold'] ?> gold per vote</td>
                    </tr>
                    <tr>
                        <td><b>Offer</b></td>
                        <td>
                            <?php if ((int)$payConfig['offer'] > 0): ?>
                                <span class="ga-badge ga-badge-yellow"><?= (int)$payConfig['offer'] ?>% active</span>
                                since <?= formatTime((int)$payConfig['offerFrom']) ?>
                            <?php else: ?>
                                <span class="ga-badge ga-badge-blue">No active offer</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php else: ?>
                <div class="ga-msg ga-msg-info">No payment config found. <a href="index.php?action=payment&section=settings">Configure now</a></div>
                <?php endif; ?>
            </div>

            <div style="background:#fff; border:1px solid #ddd; border-radius:5px; padding:15px;">
                <h3 style="margin:0 0 10px;">Quick Links</h3>
                <ul style="list-style:none; padding:0; margin:0;">
                    <li style="padding:4px 0;"><a href="index.php?action=payment&section=settings">Payment Settings</a></li>
                    <li style="padding:4px 0;"><a href="index.php?action=payment&section=locations">Manage Locations</a></li>
                    <li style="padding:4px 0;"><a href="index.php?action=payment&section=providers">Manage Providers</a></li>
                    <li style="padding:4px 0;"><a href="index.php?action=payment&section=products">Manage Products</a></li>
                    <li style="padding:4px 0;"><a href="index.php?action=payment&section=vouchers">Manage Vouchers</a></li>
                    <li style="padding:4px 0;"><a href="index.php?action=payment&section=logs">View Logs</a></li>
                    <li style="padding:4px 0;"><a href="index.php?action=payment&section=codes">Package Codes</a></li>
                    <li style="padding:4px 0;"><a href="index.php?action=payment&section=giftCodes">Gift Codes</a></li>
                </ul>
            </div>
        </div>

        <div style="margin:15px 0;">
            <div class="ga-stat-box">
                <div class="num"><?= $locationCount ?></div>
                <div class="lbl">Locations</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $providerCount ?></div>
                <div class="lbl">Providers</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $productCount ?></div>
                <div class="lbl">Products</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $voucherCount ?></div>
                <div class="lbl">Unused Vouchers</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $codeCount ?></div>
                <div class="lbl">Unused Codes</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $recentTx ?></div>
                <div class="lbl">Transactions (7d)</div>
            </div>
            <div class="ga-stat-box">
                <div class="num"><?= $logCount ?></div>
                <div class="lbl">Total Transactions</div>
            </div>
        </div>

        <h2>Recent Transactions</h2>
        <table class="ga-table">
            <thead>
                <tr><th>#</th><th>Email</th><th>Provider</th><th>Product</th><th>Amount</th><th>Status</th><th>Time</th></tr>
            </thead>
            <tbody>
            <?php
            $recent = $this->db->query(
                'SELECT pl.*, pp.name AS providerName, gp.goldProductName
                 FROM paymentLog pl
                 LEFT JOIN paymentProviders pp ON pl.paymentProvider = pp.providerId
                 LEFT JOIN goldProducts gp ON pl.productId = gp.goldProductId
                 ORDER BY pl.id DESC LIMIT 10'
            )->fetchAll();
            if (empty($recent)): ?>
                <tr><td colspan="7" style="text-align:center; color:#888;">No transactions yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td><?= h($r['email']) ?></td>
                    <td><?= h($r['providerName'] ?? 'ID:' . (int)$r['paymentProvider']) ?></td>
                    <td><?= h($r['goldProductName'] ?? 'ID:' . (int)$r['productId']) ?></td>
                    <td><?= number_format($r['payPrice'], 2) ?></td>
                    <td><?= $this->statusBadge((int)$r['status']) ?></td>
                    <td><?= formatTime((int)$r['time']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    // ──────────────────────────────────────────────
    //  2. Settings (paymentConfig)
    // ──────────────────────────────────────────────

    private function handleSettings(): string
    {
        $msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $active = (int)(!empty($_POST['active']));
            $votingGold = max(0, (int)($_POST['votingGold'] ?? 0));
            $offer = (int)($_POST['offer'] ?? 0);

            // If offer changed to non-zero, set offerFrom to now
            $payConfig = $this->db->query('SELECT * FROM paymentConfig LIMIT 1')->fetch();
            $offerFrom = ($payConfig && (int)$payConfig['offer'] === $offer) ? (int)$payConfig['offerFrom'] : time();
            if ($offer === 0) {
                $offerFrom = 0;
            }

            if ($payConfig) {
                $stmt = $this->db->prepare(
                    'UPDATE paymentConfig SET active = :active, votingGold = :vg, offer = :offer, offerFrom = :offerFrom WHERE id = :id'
                );
                $stmt->execute([
                    ':active' => $active, ':vg' => $votingGold,
                    ':offer' => $offer, ':offerFrom' => $offerFrom,
                    ':id' => $payConfig['id'],
                ]);
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO paymentConfig (active, votingGold, offer, offerFrom) VALUES (:active, :vg, :offer, :offerFrom)'
                );
                $stmt->execute([
                    ':active' => $active, ':vg' => $votingGold,
                    ':offer' => $offer, ':offerFrom' => $offerFrom,
                ]);
            }

            $msg = '<div class="ga-msg ga-msg-ok">Payment settings updated.</div>';
        }

        $payConfig = $this->db->query('SELECT * FROM paymentConfig LIMIT 1')->fetch();

        ob_start();
        ?>
        <h1>Payment Settings</h1>
        <?= $this->subNav('settings') ?>
        <?= $msg ?>

        <form method="post" class="ga-form">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">

            <div style="background:#fff; border:1px solid #ddd; border-radius:5px; padding:15px; max-width:500px;">
                <label>
                    <input type="checkbox" name="active" value="1" <?= ($payConfig && $payConfig['active']) ? 'checked' : '' ?>>
                    Payment System Active
                </label>

                <label>Voting Gold (per vote)</label>
                <input type="number" name="votingGold" value="<?= (int)($payConfig['votingGold'] ?? 50) ?>" min="0">

                <label>Offer Percentage</label>
                <select name="offer">
                    <?php foreach ($this->offerOptions as $val => $label): ?>
                        <option value="<?= $val ?>" <?= (isset($payConfig['offer']) && (int)$payConfig['offer'] === $val) ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if ($payConfig && (int)$payConfig['offerFrom'] > 0): ?>
                    <p style="color:#888; font-size:10px;">Offer active since: <?= formatTime((int)$payConfig['offerFrom']) ?></p>
                <?php endif; ?>

                <div style="margin:15px 0;">
                    <button class="ga-btn ga-btn-primary" type="submit">Save Settings</button>
                </div>
            </div>
        </form>

        <?php if ($payConfig): ?>
        <h2 style="margin-top:20px;">All Config Values</h2>
        <table class="ga-table" style="max-width:500px;">
            <?php foreach ($payConfig as $key => $val): ?>
                <?php if (!is_int($key)): ?>
                <tr><td><b><?= h($key) ?></b></td><td><?= h((string)$val) ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    // ──────────────────────────────────────────────
    //  3. Locations
    // ──────────────────────────────────────────────

    private function handleLocations(): string
    {
        $msg = '';
        $editData = null;

        // Handle delete with cascade
        if (isset($_GET['delete']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $locId = (int)$_GET['delete'];
            // Cascade: delete package_codes for products in this location
            $this->db->prepare(
                'DELETE FROM package_codes WHERE package_id IN (SELECT goldProductId FROM goldProducts WHERE goldProductLocation = :loc)'
            )->execute([':loc' => $locId]);
            // Cascade: delete products
            $this->db->prepare('DELETE FROM goldProducts WHERE goldProductLocation = :loc')->execute([':loc' => $locId]);
            // Cascade: delete providers
            $this->db->prepare('DELETE FROM paymentProviders WHERE location = :loc')->execute([':loc' => $locId]);
            // Delete location
            $this->db->prepare('DELETE FROM locations WHERE id = :id')->execute([':id' => $locId]);
            $msg = '<div class="ga-msg ga-msg-ok">Location and all associated providers, products, and codes deleted.</div>';
        }

        // Handle add/edit
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $location = trim($_POST['location'] ?? '');
            $currency = trim($_POST['content_language'] ?? '');
            $editId = (int)($_POST['edit_id'] ?? 0);

            if ($location === '' || $currency === '') {
                $msg = '<div class="ga-msg ga-msg-err">Location name and currency are required.</div>';
            } elseif ($editId > 0) {
                $stmt = $this->db->prepare('UPDATE locations SET location = :loc, content_language = :cur WHERE id = :id');
                $stmt->execute([':loc' => $location, ':cur' => $currency, ':id' => $editId]);
                $msg = '<div class="ga-msg ga-msg-ok">Location updated.</div>';
            } else {
                $stmt = $this->db->prepare('INSERT INTO locations (location, content_language) VALUES (:loc, :cur)');
                $stmt->execute([':loc' => $location, ':cur' => $currency]);
                $msg = '<div class="ga-msg ga-msg-ok">Location added.</div>';
            }
        }

        // Load edit data
        if (isset($_GET['edit'])) {
            $stmt = $this->db->prepare('SELECT * FROM locations WHERE id = :id');
            $stmt->execute([':id' => (int)$_GET['edit']]);
            $editData = $stmt->fetch();
        }

        $locations = $this->db->query('SELECT * FROM locations ORDER BY id ASC')->fetchAll();

        ob_start();
        ?>
        <h1>Payment Locations</h1>
        <?= $this->subNav('locations') ?>
        <?= $msg ?>

        <h2><?= $editData ? 'Edit' : 'Add' ?> Location</h2>
        <form method="post" class="ga-form" style="margin:10px 0;">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="edit_id" value="<?= (int)$editData['id'] ?>">
            <?php endif; ?>
            <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:10px; align-items:end;">
                <div>
                    <label>Location Name</label>
                    <input type="text" name="location" value="<?= h($editData['location'] ?? '') ?>" placeholder="e.g. Europe" required>
                </div>
                <div>
                    <label>Currency</label>
                    <input type="text" name="content_language" value="<?= h($editData['content_language'] ?? '') ?>" placeholder="e.g. EUR" required>
                </div>
                <div>
                    <button class="ga-btn ga-btn-primary" type="submit"><?= $editData ? 'Update' : 'Add' ?> Location</button>
                    <?php if ($editData): ?>
                        <a class="ga-btn" href="index.php?action=payment&section=locations">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <h2>All Locations</h2>
        <table class="ga-table">
            <thead>
                <tr><th>#</th><th>Location</th><th>Currency</th><th>Providers</th><th>Products</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($locations)): ?>
                <tr><td colspan="6" style="text-align:center; color:#888;">No locations.</td></tr>
            <?php endif; ?>
            <?php foreach ($locations as $l):
                $pCount = $this->db->prepare('SELECT COUNT(*) FROM paymentProviders WHERE location = :loc');
                $pCount->execute([':loc' => $l['id']]);
                $provCount = (int)$pCount->fetchColumn();
                $gpCount = $this->db->prepare('SELECT COUNT(*) FROM goldProducts WHERE goldProductLocation = :loc');
                $gpCount->execute([':loc' => $l['id']]);
                $prodCount = (int)$gpCount->fetchColumn();
            ?>
                <tr>
                    <td><?= (int)$l['id'] ?></td>
                    <td><b><?= h($l['location']) ?></b></td>
                    <td><?= h($l['content_language']) ?></td>
                    <td><?= $provCount ?></td>
                    <td><?= $prodCount ?></td>
                    <td>
                        <a class="ga-btn" href="index.php?action=payment&section=locations&edit=<?= (int)$l['id'] ?>">Edit</a>
                        <a class="ga-btn ga-btn-danger"
                           href="index.php?action=payment&section=locations&delete=<?= (int)$l['id'] ?>&_csrf=<?= urlencode(csrfToken()) ?>"
                           onclick="return confirm('Delete this location and ALL its providers, products, and codes?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    // ──────────────────────────────────────────────
    //  4. Providers
    // ──────────────────────────────────────────────

    private function handleProviders(): string
    {
        $msg = '';
        $editData = null;
        $locations = $this->db->query('SELECT * FROM locations ORDER BY id ASC')->fetchAll();

        // Handle toggle active
        if (isset($_GET['toggleActive']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $pid = (int)$_GET['toggleActive'];
            $this->db->prepare('UPDATE paymentProviders SET isActive = 1 - isActive WHERE providerId = :id')->execute([':id' => $pid]);
            $msg = '<div class="ga-msg ga-msg-ok">Provider active status toggled.</div>';
        }

        // Handle toggle hidden
        if (isset($_GET['toggleHidden']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $pid = (int)$_GET['toggleHidden'];
            $this->db->prepare('UPDATE paymentProviders SET hidden = 1 - hidden WHERE providerId = :id')->execute([':id' => $pid]);
            $msg = '<div class="ga-msg ga-msg-ok">Provider hidden status toggled.</div>';
        }

        // Handle delete
        if (isset($_GET['delete']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $this->db->prepare('DELETE FROM paymentProviders WHERE providerId = :id')->execute([':id' => (int)$_GET['delete']]);
            $msg = '<div class="ga-msg ga-msg-ok">Provider deleted.</div>';
        }

        // Handle add/edit
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $editId = (int)($_POST['edit_id'] ?? 0);
            $data = [
                ':providerType' => (int)($_POST['providerType'] ?? 0),
                ':location'     => (int)($_POST['location'] ?? 0),
                ':posId'        => (int)($_POST['posId'] ?? 0),
                ':name'         => trim($_POST['name'] ?? ''),
                ':description'  => trim($_POST['description'] ?? ''),
                ':img'          => trim($_POST['img'] ?? ''),
                ':delivery'     => trim($_POST['delivery'] ?? ''),
                ':connectInfo'  => trim($_POST['connectInfo'] ?? ''),
                ':isProviderLoadedByHTML' => (int)(!empty($_POST['isProviderLoadedByHTML'])),
                ':hidden'       => (int)(!empty($_POST['hidden'])),
                ':isActive'     => (int)(!empty($_POST['isActive'])),
            ];

            if ($data[':name'] === '') {
                $msg = '<div class="ga-msg ga-msg-err">Provider name is required.</div>';
            } elseif ($editId > 0) {
                $data[':id'] = $editId;
                $this->db->prepare(
                    'UPDATE paymentProviders SET providerType=:providerType, location=:location, posId=:posId, name=:name,
                     description=:description, img=:img, delivery=:delivery, connectInfo=:connectInfo,
                     isProviderLoadedByHTML=:isProviderLoadedByHTML, hidden=:hidden, isActive=:isActive
                     WHERE providerId=:id'
                )->execute($data);
                $msg = '<div class="ga-msg ga-msg-ok">Provider updated.</div>';
            } else {
                $this->db->prepare(
                    'INSERT INTO paymentProviders (providerType, location, posId, name, description, img, delivery, connectInfo, isProviderLoadedByHTML, hidden, isActive)
                     VALUES (:providerType, :location, :posId, :name, :description, :img, :delivery, :connectInfo, :isProviderLoadedByHTML, :hidden, :isActive)'
                )->execute($data);
                $msg = '<div class="ga-msg ga-msg-ok">Provider added.</div>';
            }
        }

        // Load edit data
        if (isset($_GET['edit'])) {
            $stmt = $this->db->prepare('SELECT * FROM paymentProviders WHERE providerId = :id');
            $stmt->execute([':id' => (int)$_GET['edit']]);
            $editData = $stmt->fetch();
        }

        $providers = $this->db->query(
            'SELECT pp.*, l.location AS locationName, l.content_language
             FROM paymentProviders pp
             LEFT JOIN locations l ON pp.location = l.id
             ORDER BY pp.location ASC, pp.providerId ASC'
        )->fetchAll();

        ob_start();
        ?>
        <h1>Payment Providers</h1>
        <?= $this->subNav('providers') ?>
        <?= $msg ?>

        <h2><?= $editData ? 'Edit' : 'Add' ?> Provider</h2>
        <form method="post" class="ga-form" style="margin:10px 0; background:#fff; border:1px solid #ddd; border-radius:5px; padding:15px;">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="edit_id" value="<?= (int)$editData['providerId'] ?>">
            <?php endif; ?>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                <div>
                    <label>Name</label>
                    <input type="text" name="name" value="<?= h($editData['name'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Provider Type</label>
                    <select name="providerType">
                        <?php foreach ($this->providerTypes as $val => $label): ?>
                            <option value="<?= $val ?>" <?= (isset($editData['providerType']) && (int)$editData['providerType'] === $val) ? 'selected' : '' ?>><?= h($label) ?> (<?= $val ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Location</label>
                    <select name="location">
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= (int)$loc['id'] ?>" <?= (isset($editData['location']) && (int)$editData['location'] === (int)$loc['id']) ? 'selected' : '' ?>><?= h($loc['location']) ?> (<?= h($loc['content_language']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Position ID</label>
                    <input type="number" name="posId" value="<?= (int)($editData['posId'] ?? 0) ?>" min="0">
                </div>
                <div>
                    <label>Image Filename</label>
                    <input type="text" name="img" value="<?= h($editData['img'] ?? '') ?>" placeholder="provider.png">
                </div>
                <div>
                    <label>Delivery</label>
                    <input type="text" name="delivery" value="<?= h($editData['delivery'] ?? '') ?>" placeholder="instant">
                </div>
            </div>

            <label>Description</label>
            <textarea name="description" style="width:100%; height:60px;"><?= h($editData['description'] ?? '') ?></textarea>

            <label>Connect Info (API keys, config JSON, etc.)</label>
            <textarea name="connectInfo" style="width:100%; height:60px;"><?= h($editData['connectInfo'] ?? '') ?></textarea>

            <div style="margin:10px 0;">
                <label style="display:inline; font-weight:normal;">
                    <input type="checkbox" name="isActive" value="1" <?= (!$editData || ($editData && $editData['isActive'])) ? 'checked' : '' ?>> Active
                </label>
                &nbsp;&nbsp;
                <label style="display:inline; font-weight:normal;">
                    <input type="checkbox" name="hidden" value="1" <?= ($editData && $editData['hidden']) ? 'checked' : '' ?>> Hidden
                </label>
                &nbsp;&nbsp;
                <label style="display:inline; font-weight:normal;">
                    <input type="checkbox" name="isProviderLoadedByHTML" value="1" <?= ($editData && $editData['isProviderLoadedByHTML']) ? 'checked' : '' ?>> Loaded by HTML
                </label>
            </div>

            <div style="margin:15px 0;">
                <button class="ga-btn ga-btn-primary" type="submit"><?= $editData ? 'Update' : 'Add' ?> Provider</button>
                <?php if ($editData): ?>
                    <a class="ga-btn" href="index.php?action=payment&section=providers">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <h2>All Providers</h2>
        <?php
        $currentLoc = null;
        foreach ($providers as $p):
            if ($currentLoc !== $p['location']):
                if ($currentLoc !== null) echo '</tbody></table>';
                $currentLoc = $p['location'];
        ?>
        <h3 style="margin-top:15px;"><?= h($p['locationName'] ?? 'Unknown') ?> (<?= h($p['content_language'] ?? '?') ?>)</h3>
        <table class="ga-table">
            <thead>
                <tr><th>#</th><th>Name</th><th>Type</th><th>Pos</th><th>Active</th><th>Hidden</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php endif; ?>
                <tr>
                    <td><?= (int)$p['providerId'] ?></td>
                    <td><b><?= h($p['name']) ?></b></td>
                    <td><?= h($this->providerTypes[(int)$p['providerType']] ?? 'Unknown') ?></td>
                    <td><?= (int)$p['posId'] ?></td>
                    <td>
                        <a href="index.php?action=payment&section=providers&toggleActive=<?= (int)$p['providerId'] ?>&_csrf=<?= urlencode(csrfToken()) ?>">
                            <?= $p['isActive'] ? '<span class="ga-badge ga-badge-green">Yes</span>' : '<span class="ga-badge ga-badge-red">No</span>' ?>
                        </a>
                    </td>
                    <td>
                        <a href="index.php?action=payment&section=providers&toggleHidden=<?= (int)$p['providerId'] ?>&_csrf=<?= urlencode(csrfToken()) ?>">
                            <?= $p['hidden'] ? '<span class="ga-badge ga-badge-yellow">Yes</span>' : '<span class="ga-badge ga-badge-blue">No</span>' ?>
                        </a>
                    </td>
                    <td>
                        <a class="ga-btn" href="index.php?action=payment&section=providers&edit=<?= (int)$p['providerId'] ?>">Edit</a>
                        <a class="ga-btn ga-btn-danger"
                           href="index.php?action=payment&section=providers&delete=<?= (int)$p['providerId'] ?>&_csrf=<?= urlencode(csrfToken()) ?>"
                           onclick="return confirm('Delete this provider?')">Delete</a>
                    </td>
                </tr>
        <?php endforeach; ?>
        <?php if ($currentLoc !== null): ?>
            </tbody></table>
        <?php else: ?>
            <p style="color:#888;">No providers found.</p>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    // ──────────────────────────────────────────────
    //  5. Products
    // ──────────────────────────────────────────────

    private function handleProducts(): string
    {
        $msg = '';
        $editData = null;
        $locations = $this->db->query('SELECT * FROM locations ORDER BY id ASC')->fetchAll();

        // Handle delete with cascade
        if (isset($_GET['delete']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $prodId = (int)$_GET['delete'];
            $this->db->prepare('DELETE FROM package_codes WHERE package_id = :pid')->execute([':pid' => $prodId]);
            $this->db->prepare('DELETE FROM goldProducts WHERE goldProductId = :id')->execute([':id' => $prodId]);
            $msg = '<div class="ga-msg ga-msg-ok">Product and its package codes deleted.</div>';
        }

        // Handle add/edit
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $editId = (int)($_POST['edit_id'] ?? 0);
            $data = [
                ':name'      => trim($_POST['goldProductName'] ?? ''),
                ':location'  => (int)($_POST['goldProductLocation'] ?? 0),
                ':gold'      => (int)($_POST['goldProductGold'] ?? 0),
                ':price'     => (float)($_POST['goldProductPrice'] ?? 0),
                ':moneyUnit' => trim($_POST['goldProductMoneyUnit'] ?? ''),
                ':imageName' => trim($_POST['goldProductImageName'] ?? ''),
                ':hasOffer'  => (int)(!empty($_POST['goldProductHasOffer'])),
                ':bestSeller' => (int)(!empty($_POST['isBestSeller'])),
                ':bestValue' => (int)(!empty($_POST['isBestValue'])),
                ':isActive'  => (int)(!empty($_POST['isActive'])),
            ];

            if ($data[':name'] === '' || $data[':gold'] <= 0) {
                $msg = '<div class="ga-msg ga-msg-err">Product name and gold amount are required.</div>';
            } elseif ($editId > 0) {
                $data[':id'] = $editId;
                $this->db->prepare(
                    'UPDATE goldProducts SET goldProductName=:name, goldProductLocation=:location, goldProductGold=:gold,
                     goldProductPrice=:price, goldProductMoneyUnit=:moneyUnit, goldProductImageName=:imageName,
                     goldProductHasOffer=:hasOffer, isBestSeller=:bestSeller, isBestValue=:bestValue, isActive=:isActive
                     WHERE goldProductId=:id'
                )->execute($data);
                $msg = '<div class="ga-msg ga-msg-ok">Product updated.</div>';
            } else {
                $this->db->prepare(
                    'INSERT INTO goldProducts (goldProductName, goldProductLocation, goldProductGold, goldProductPrice, goldProductMoneyUnit, goldProductImageName, goldProductHasOffer, isBestSeller, isBestValue, isActive)
                     VALUES (:name, :location, :gold, :price, :moneyUnit, :imageName, :hasOffer, :bestSeller, :bestValue, :isActive)'
                )->execute($data);
                $msg = '<div class="ga-msg ga-msg-ok">Product added.</div>';
            }
        }

        // Load edit data
        if (isset($_GET['edit'])) {
            $stmt = $this->db->prepare('SELECT * FROM goldProducts WHERE goldProductId = :id');
            $stmt->execute([':id' => (int)$_GET['edit']]);
            $editData = $stmt->fetch();
        }

        $products = $this->db->query(
            'SELECT gp.*, l.location AS locationName, l.content_language
             FROM goldProducts gp
             LEFT JOIN locations l ON gp.goldProductLocation = l.id
             ORDER BY gp.goldProductLocation ASC, gp.goldProductId ASC'
        )->fetchAll();

        ob_start();
        ?>
        <h1>Gold Products</h1>
        <?= $this->subNav('products') ?>
        <?= $msg ?>

        <h2><?= $editData ? 'Edit' : 'Add' ?> Product</h2>
        <form method="post" class="ga-form" style="margin:10px 0; background:#fff; border:1px solid #ddd; border-radius:5px; padding:15px;">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="edit_id" value="<?= (int)$editData['goldProductId'] ?>">
            <?php endif; ?>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                <div>
                    <label>Product Name</label>
                    <input type="text" name="goldProductName" value="<?= h($editData['goldProductName'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Location</label>
                    <select name="goldProductLocation">
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= (int)$loc['id'] ?>" <?= (isset($editData['goldProductLocation']) && (int)$editData['goldProductLocation'] === (int)$loc['id']) ? 'selected' : '' ?>><?= h($loc['location']) ?> (<?= h($loc['content_language']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Gold Amount</label>
                    <input type="number" name="goldProductGold" value="<?= (int)($editData['goldProductGold'] ?? 0) ?>" min="1" required>
                </div>
                <div>
                    <label>Price</label>
                    <input type="number" name="goldProductPrice" value="<?= number_format($editData['goldProductPrice'] ?? 0, 2, '.', '') ?>" step="0.01" min="0">
                </div>
                <div>
                    <label>Currency</label>
                    <input type="text" name="goldProductMoneyUnit" value="<?= h($editData['goldProductMoneyUnit'] ?? '') ?>" placeholder="EUR">
                </div>
                <div>
                    <label>Image Filename</label>
                    <input type="text" name="goldProductImageName" value="<?= h($editData['goldProductImageName'] ?? '') ?>" placeholder="4_6_1.png">
                </div>
            </div>

            <div style="margin:10px 0;">
                <label style="display:inline; font-weight:normal;">
                    <input type="checkbox" name="goldProductHasOffer" value="1" <?= ($editData && $editData['goldProductHasOffer']) ? 'checked' : '' ?>> Has Offer
                </label>
                &nbsp;&nbsp;
                <label style="display:inline; font-weight:normal;">
                    <input type="checkbox" name="isBestSeller" value="1" <?= ($editData && $editData['isBestSeller']) ? 'checked' : '' ?>> Best Seller
                </label>
                &nbsp;&nbsp;
                <label style="display:inline; font-weight:normal;">
                    <input type="checkbox" name="isBestValue" value="1" <?= ($editData && $editData['isBestValue']) ? 'checked' : '' ?>> Best Value
                </label>
                &nbsp;&nbsp;
                <label style="display:inline; font-weight:normal;">
                    <input type="checkbox" name="isActive" value="1" <?= (!$editData || ($editData && $editData['isActive'])) ? 'checked' : '' ?>> Active
                </label>
            </div>

            <div style="margin:15px 0;">
                <button class="ga-btn ga-btn-primary" type="submit"><?= $editData ? 'Update' : 'Add' ?> Product</button>
                <?php if ($editData): ?>
                    <a class="ga-btn" href="index.php?action=payment&section=products">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <h2>All Products</h2>
        <?php
        $currentLoc = null;
        foreach ($products as $p):
            if ($currentLoc !== $p['goldProductLocation']):
                if ($currentLoc !== null) echo '</tbody></table>';
                $currentLoc = $p['goldProductLocation'];
        ?>
        <h3 style="margin-top:15px;"><?= h($p['locationName'] ?? 'Unknown') ?> (<?= h($p['content_language'] ?? '?') ?>)</h3>
        <table class="ga-table">
            <thead>
                <tr><th>#</th><th>Name</th><th>Gold</th><th>Price</th><th>Currency</th><th>Offer</th><th>Active</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php endif; ?>
                <tr>
                    <td><?= (int)$p['goldProductId'] ?></td>
                    <td>
                        <b><?= h($p['goldProductName']) ?></b>
                        <?php if ($p['isBestSeller']): ?><span class="ga-badge ga-badge-yellow">Best Seller</span><?php endif; ?>
                        <?php if ($p['isBestValue']): ?><span class="ga-badge ga-badge-blue">Best Value</span><?php endif; ?>
                    </td>
                    <td><?= number_format($p['goldProductGold']) ?></td>
                    <td><?= number_format($p['goldProductPrice'], 2) ?></td>
                    <td><?= h($p['goldProductMoneyUnit']) ?></td>
                    <td><?= $p['goldProductHasOffer'] ? '<span class="ga-badge ga-badge-green">Yes</span>' : 'No' ?></td>
                    <td><?= $p['isActive'] ? '<span class="ga-badge ga-badge-green">Yes</span>' : '<span class="ga-badge ga-badge-red">No</span>' ?></td>
                    <td>
                        <a class="ga-btn" href="index.php?action=payment&section=products&edit=<?= (int)$p['goldProductId'] ?>">Edit</a>
                        <a class="ga-btn ga-btn-danger"
                           href="index.php?action=payment&section=products&delete=<?= (int)$p['goldProductId'] ?>&_csrf=<?= urlencode(csrfToken()) ?>"
                           onclick="return confirm('Delete this product and all its package codes?')">Delete</a>
                    </td>
                </tr>
        <?php endforeach; ?>
        <?php if ($currentLoc !== null): ?>
            </tbody></table>
        <?php else: ?>
            <p style="color:#888;">No products found.</p>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    // ──────────────────────────────────────────────
    //  6. Vouchers
    // ──────────────────────────────────────────────

    private function handleVouchers(): string
    {
        $msg = '';

        // Handle delete
        if (isset($_GET['delete']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $this->db->prepare('DELETE FROM paymentVoucher WHERE id = :id')->execute([':id' => (int)$_GET['delete']]);
            $msg = '<div class="ga-msg ga-msg-ok">Voucher deleted.</div>';
        }

        // Handle add
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $email = trim($_POST['email'] ?? '');
            $gold = (int)($_POST['gold'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');

            if ($gold <= 0) {
                $msg = '<div class="ga-msg ga-msg-err">Gold amount must be greater than 0.</div>';
            } else {
                $voucherCode = strtoupper(bin2hex(random_bytes(8)));
                $stmt = $this->db->prepare(
                    'INSERT INTO paymentVoucher (gold, email, reason, voucherCode, time, used) VALUES (:gold, :email, :reason, :code, :time, 0)'
                );
                $stmt->execute([
                    ':gold' => $gold, ':email' => $email, ':reason' => $reason,
                    ':code' => $voucherCode, ':time' => time(),
                ]);
                $msg = '<div class="ga-msg ga-msg-ok">Voucher created: <b>' . h($voucherCode) . '</b></div>';
            }
        }

        $filter = $_GET['filter'] ?? 'all';
        $voucherParams = [];
        $voucherWhere = '';
        if ($filter === 'used') {
            $voucherWhere = 'WHERE used = :used';
            $voucherParams[':used'] = 1;
        } elseif ($filter === 'unused') {
            $voucherWhere = 'WHERE used = :used';
            $voucherParams[':used'] = 0;
        }

        $voucherStmt = $this->db->prepare('SELECT * FROM paymentVoucher ' . $voucherWhere . ' ORDER BY id DESC LIMIT 100');
        $voucherStmt->execute($voucherParams);
        $vouchers = $voucherStmt->fetchAll();

        ob_start();
        ?>
        <h1>Payment Vouchers</h1>
        <?= $this->subNav('vouchers') ?>
        <?= $msg ?>

        <h2>Create Voucher</h2>
        <form method="post" class="ga-form" style="margin:10px 0;">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr auto; gap:10px; align-items:end;">
                <div>
                    <label>Email (optional)</label>
                    <input type="text" name="email" placeholder="user@example.com">
                </div>
                <div>
                    <label>Gold Amount</label>
                    <input type="number" name="gold" min="1" value="100" required>
                </div>
                <div>
                    <label>Reason (optional)</label>
                    <input type="text" name="reason" placeholder="Gift, compensation, etc.">
                </div>
                <div>
                    <button class="ga-btn ga-btn-primary" type="submit">Create Voucher</button>
                </div>
            </div>
        </form>

        <h2>Vouchers</h2>
        <div style="margin:5px 0;">
            Filter:
            <a class="ga-btn <?= $filter === 'all' ? 'ga-btn-primary' : '' ?>" href="index.php?action=payment&section=vouchers&filter=all">All</a>
            <a class="ga-btn <?= $filter === 'unused' ? 'ga-btn-primary' : '' ?>" href="index.php?action=payment&section=vouchers&filter=unused">Unused</a>
            <a class="ga-btn <?= $filter === 'used' ? 'ga-btn-primary' : '' ?>" href="index.php?action=payment&section=vouchers&filter=used">Used</a>
        </div>
        <table class="ga-table">
            <thead>
                <tr><th>#</th><th>Code</th><th>Gold</th><th>Email</th><th>Reason</th><th>Created</th><th>Status</th><th>Used By</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($vouchers)): ?>
                <tr><td colspan="9" style="text-align:center; color:#888;">No vouchers found.</td></tr>
            <?php endif; ?>
            <?php foreach ($vouchers as $v): ?>
                <tr>
                    <td><?= (int)$v['id'] ?></td>
                    <td><code style="background:#f5f5f5; padding:2px 6px; border-radius:3px;"><?= h($v['voucherCode']) ?></code></td>
                    <td><b><?= number_format($v['gold']) ?></b></td>
                    <td><?= h($v['email']) ?></td>
                    <td><?= h($v['reason'] ?? '') ?></td>
                    <td><?= formatTime((int)$v['time']) ?></td>
                    <td><?= $v['used'] ? '<span class="ga-badge ga-badge-red">Used</span>' : '<span class="ga-badge ga-badge-green">Available</span>' ?></td>
                    <td>
                        <?php if ($v['used']): ?>
                            <?= h($v['usedEmail'] ?? '') ?>
                            <?php if ($v['usedWorldId']): ?><br><small>World: <?= h($v['usedWorldId']) ?></small><?php endif; ?>
                            <?php if ($v['usedTime']): ?><br><small><?= formatTime((int)$v['usedTime']) ?></small><?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="ga-btn ga-btn-danger"
                           href="index.php?action=payment&section=vouchers&delete=<?= (int)$v['id'] ?>&_csrf=<?= urlencode(csrfToken()) ?>"
                           onclick="return confirm('Delete this voucher?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    // ──────────────────────────────────────────────
    //  7. Logs
    // ──────────────────────────────────────────────

    private function handleLogs(): string
    {
        $filterWorld = trim($_GET['world'] ?? '');
        $where = '';
        $params = [];
        if ($filterWorld !== '') {
            $where = 'WHERE pl.worldUniqueId = :world';
            $params[':world'] = $filterWorld;
        }

        // Get distinct worlds for filter
        $worlds = $this->db->query('SELECT DISTINCT worldUniqueId FROM paymentLog ORDER BY worldUniqueId')->fetchAll(PDO::FETCH_COLUMN);

        // Get logs
        $stmt = $this->db->prepare(
            'SELECT pl.*, pp.name AS providerName, gp.goldProductName, gp.goldProductMoneyUnit
             FROM paymentLog pl
             LEFT JOIN paymentProviders pp ON pl.paymentProvider = pp.providerId
             LEFT JOIN goldProducts gp ON pl.productId = gp.goldProductId
             ' . $where . '
             ORDER BY pl.id DESC LIMIT 100'
        );
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Income summary
        $incomeSql = 'SELECT gp.goldProductMoneyUnit AS currency, SUM(pl.payPrice) AS total, COUNT(*) AS cnt
                      FROM paymentLog pl
                      LEFT JOIN goldProducts gp ON pl.productId = gp.goldProductId
                      WHERE pl.status = 1 ' . ($filterWorld !== '' ? 'AND pl.worldUniqueId = :world' : '') . '
                      GROUP BY gp.goldProductMoneyUnit';
        $incStmt = $this->db->prepare($incomeSql);
        $incStmt->execute($filterWorld !== '' ? [':world' => $filterWorld] : []);
        $income = $incStmt->fetchAll();

        ob_start();
        ?>
        <h1>Payment Logs</h1>
        <?= $this->subNav('logs') ?>

        <div style="margin:10px 0;">
            <form method="get" style="display:inline;">
                <input type="hidden" name="action" value="payment">
                <input type="hidden" name="section" value="logs">
                Filter by world:
                <select name="world" onchange="this.form.submit()">
                    <option value="">All Worlds</option>
                    <?php foreach ($worlds as $w): ?>
                        <option value="<?= h($w) ?>" <?= $filterWorld === (string)$w ? 'selected' : '' ?>><?= h($w) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (!empty($income)): ?>
        <h2>Income Summary (Completed)</h2>
        <div style="margin:10px 0;">
            <?php foreach ($income as $inc): ?>
            <div class="ga-stat-box">
                <div class="num"><?= number_format($inc['total'], 2) ?></div>
                <div class="lbl"><?= h($inc['currency'] ?? 'Unknown') ?> (<?= (int)$inc['cnt'] ?> tx)</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h2>Transaction Log</h2>
        <table class="ga-table">
            <thead>
                <tr><th>#</th><th>World</th><th>Email</th><th>Provider</th><th>Product</th><th>Amount</th><th>Status</th><th>Time</th></tr>
            </thead>
            <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="8" style="text-align:center; color:#888;">No transactions found.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td><?= (int)$l['id'] ?></td>
                    <td><?= h($l['worldUniqueId']) ?></td>
                    <td><?= h($l['email']) ?></td>
                    <td><?= h($l['providerName'] ?? 'ID:' . (int)$l['paymentProvider']) ?></td>
                    <td><?= h($l['goldProductName'] ?? 'ID:' . (int)$l['productId']) ?></td>
                    <td><?= number_format($l['payPrice'], 2) ?> <?= h($l['goldProductMoneyUnit'] ?? '') ?></td>
                    <td><?= $this->statusBadge((int)$l['status']) ?></td>
                    <td><?= formatTime((int)$l['time']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    // ──────────────────────────────────────────────
    //  8. Package Codes (regular + gift)
    // ──────────────────────────────────────────────

    private function handleCodes(bool $isGift = false): string
    {
        $msg = '';
        $giftVal = $isGift ? 1 : 0;
        $sectionKey = $isGift ? 'giftCodes' : 'codes';
        $titleLabel = $isGift ? 'Gift Codes' : 'Package Codes';

        $products = $this->db->query(
            'SELECT gp.*, l.location AS locationName
             FROM goldProducts gp
             LEFT JOIN locations l ON gp.goldProductLocation = l.id
             ORDER BY gp.goldProductId ASC'
        )->fetchAll();

        // Handle delete
        if (isset($_GET['delete']) && isset($_GET['_csrf']) && hash_equals(csrfToken(), $_GET['_csrf'])) {
            $this->db->prepare('DELETE FROM package_codes WHERE id = :id AND isGift = :gift')->execute([':id' => (int)$_GET['delete'], ':gift' => $giftVal]);
            $msg = '<div class="ga-msg ga-msg-ok">Code deleted.</div>';
        }

        // Handle generate
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidate()) {
            $productId = (int)($_POST['product_id'] ?? 0);
            $count = min(500, max(1, (int)($_POST['count'] ?? 1)));

            if ($productId <= 0) {
                $msg = '<div class="ga-msg ga-msg-err">Select a product.</div>';
            } else {
                $stmt = $this->db->prepare('INSERT INTO package_codes (package_id, code, isGift, used) VALUES (:pid, :code, :gift, 0)');
                $generated = 0;
                for ($i = 0; $i < $count; $i++) {
                    $code = strtoupper(substr(bin2hex(random_bytes(10)), 0, 16));
                    $code = implode('-', str_split($code, 4));
                    $stmt->execute([':pid' => $productId, ':code' => $code, ':gift' => $giftVal]);
                    $generated++;
                }
                $msg = '<div class="ga-msg ga-msg-ok">' . $generated . ' codes generated.</div>';
            }
        }

        // Get codes grouped by product
        $filterProduct = isset($_GET['product']) ? (int)$_GET['product'] : null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $countSql = 'SELECT COUNT(*) FROM package_codes WHERE isGift = :gift';
        $codeSql = 'SELECT pc.*, gp.goldProductName FROM package_codes pc LEFT JOIN goldProducts gp ON pc.package_id = gp.goldProductId WHERE pc.isGift = :gift';
        $countParams = [':gift' => $giftVal];
        $codeParams = [':gift' => $giftVal];

        if ($filterProduct !== null) {
            $countSql .= ' AND package_id = :pid';
            $codeSql .= ' AND pc.package_id = :pid';
            $countParams[':pid'] = $filterProduct;
            $codeParams[':pid'] = $filterProduct;
        }

        $totalStmt = $this->db->prepare($countSql);
        $totalStmt->execute($countParams);
        $totalCodes = (int)$totalStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalCodes / $perPage));

        $codeSql .= ' ORDER BY pc.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $codeStmt = $this->db->prepare($codeSql);
        $codeStmt->execute($codeParams);
        $codes = $codeStmt->fetchAll();

        ob_start();
        ?>
        <h1><?= h($titleLabel) ?></h1>
        <?= $this->subNav($sectionKey) ?>
        <?= $msg ?>

        <h2>Generate <?= h($titleLabel) ?></h2>
        <form method="post" class="ga-form" style="margin:10px 0;">
            <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
            <div style="display:grid; grid-template-columns:1fr auto auto; gap:10px; align-items:end;">
                <div>
                    <label>Product</label>
                    <select name="product_id" required>
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['goldProductId'] ?>"><?= h($p['goldProductName']) ?> (<?= number_format($p['goldProductGold']) ?> gold - <?= h($p['locationName'] ?? '?') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Count</label>
                    <input type="number" name="count" value="10" min="1" max="500" style="width:80px;">
                </div>
                <div>
                    <button class="ga-btn ga-btn-primary" type="submit">Generate</button>
                </div>
            </div>
        </form>

        <h2><?= h($titleLabel) ?> (<?= $totalCodes ?> total)</h2>
        <div style="margin:5px 0;">
            Filter by product:
            <a class="ga-btn <?= $filterProduct === null ? 'ga-btn-primary' : '' ?>" href="index.php?action=payment&section=<?= h($sectionKey) ?>">All</a>
            <?php foreach ($products as $p): ?>
                <a class="ga-btn <?= $filterProduct === (int)$p['goldProductId'] ? 'ga-btn-primary' : '' ?>"
                   href="index.php?action=payment&section=<?= h($sectionKey) ?>&product=<?= (int)$p['goldProductId'] ?>"><?= h($p['goldProductName']) ?></a>
            <?php endforeach; ?>
        </div>

        <table class="ga-table">
            <thead>
                <tr><th>#</th><th>Code</th><th>Product</th><th>Used</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($codes)): ?>
                <tr><td colspan="6" style="text-align:center; color:#888;">No codes found.</td></tr>
            <?php endif; ?>
            <?php foreach ($codes as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><code style="background:#f5f5f5; padding:2px 6px; border-radius:3px;"><?= h($c['code']) ?></code></td>
                    <td><?= h($c['goldProductName'] ?? 'ID:' . (int)$c['package_id']) ?></td>
                    <td><?= $c['used'] ? '<span class="ga-badge ga-badge-red">Used</span>' : '<span class="ga-badge ga-badge-green">Available</span>' ?></td>
                    <td><?= h($c['time']) ?></td>
                    <td>
                        <a class="ga-btn ga-btn-danger"
                           href="index.php?action=payment&section=<?= h($sectionKey) ?>&delete=<?= (int)$c['id'] ?>&_csrf=<?= urlencode(csrfToken()) ?><?= $filterProduct !== null ? '&product=' . $filterProduct : '' ?>"
                           onclick="return confirm('Delete this code?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div style="margin:10px 0;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="ga-btn ga-btn-primary"><?= $i ?></span>
                <?php else: ?>
                    <a class="ga-btn" href="index.php?action=payment&section=<?= h($sectionKey) ?>&page=<?= $i ?><?= $filterProduct !== null ? '&product=' . $filterProduct : '' ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function statusBadge(int $status): string
    {
        switch ($status) {
            case 1:
                return '<span class="ga-badge ga-badge-green">Completed</span>';
            case 2:
                return '<span class="ga-badge ga-badge-red">Failed</span>';
            default:
                return '<span class="ga-badge ga-badge-yellow">Pending</span>';
        }
    }
}
