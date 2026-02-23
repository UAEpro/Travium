<?php
/**
 * Global Admin Panel - Bootstrap
 *
 * Loads config, starts session, sets up DB connection and autoloading.
 */

error_reporting(E_ALL);

// Load global config
$globalConfigPath = dirname(__DIR__, 3) . '/config.php';
if (!file_exists($globalConfigPath)) {
    http_response_code(500);
    echo '<h1>config.php not found</h1>';
    exit;
}

global $globalConfig;
require $globalConfigPath;

// Session
if (php_sapi_name() !== 'cli') {
    session_name('travium_global_admin');
    session_start();
}

// PDO connection to maindb (global database)
$dbConf = $globalConfig['dataSources']['globalDB'];
try {
    $db = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', $dbConf['hostname'], $dbConf['database'], $dbConf['charset'] ?? 'utf8mb4'),
        $dbConf['username'],
        $dbConf['password'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo '<h1>Database connection failed</h1>';
    exit;
}

// Load core classes
require __DIR__ . '/functions.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Router.php';
require __DIR__ . '/Template.php';
