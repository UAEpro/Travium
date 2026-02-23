<?php
/**
 * Generates config.php from environment variables.
 * Run by the Docker entrypoint before PHP-FPM starts.
 */

$configPath = '/var/www/html/config.php';

$domain            = getenv('DOMAIN') ?: 'travium.local';
$dbHost            = getenv('DB_HOST') ?: 'mysql';
$dbUser            = getenv('MYSQL_USER') ?: 'maindb';
$dbPassword        = getenv('MYSQL_PASSWORD') ?: '';
$dbName            = getenv('MYSQL_DATABASE') ?: 'maindb';
$recaptchaPublic   = getenv('RECAPTCHA_PUBLIC_KEY') ?: '';
$recaptchaPrivate  = getenv('RECAPTCHA_PRIVATE_KEY') ?: '';
$installerKey      = getenv('INSTALLER_KEY') ?: 'change_me';
$votingSecret      = getenv('VOTING_SECRET') ?: 'change_me';
$votingGtop100     = getenv('VOTING_GTOP100') ?: 'https://gtop100.com/Travian/server-105295';
$votingTopg        = getenv('VOTING_TOPG') ?: 'https://topg.org/travian-private-servers/server-676770';
$votingArena       = getenv('VOTING_ARENA') ?: 'https://www.arena-top100.com/index.php?a=in&u=hoxer';
$mailerDriver      = getenv('MAILER_DRIVER') ?: 'local';
$mailerFromName    = getenv('MAILER_FROM_NAME') ?: 'Travium';
$smtpHost          = getenv('SMTP_HOST') ?: '';
$smtpUser          = getenv('SMTP_USER') ?: '';
$smtpPass          = getenv('SMTP_PASS') ?: '';
$smtpPort          = getenv('SMTP_PORT') ?: '587';
$smtpEncryption    = getenv('SMTP_ENCRYPTION') ?: 'tls';
$defaultLanguage   = getenv('DEFAULT_LANGUAGE') ?: 'en';
$defaultTimezone   = getenv('DEFAULT_TIMEZONE') ?: 'Europe/London';
$sessionTimeout    = getenv('SESSION_TIMEOUT') ?: '21600';
$redisHost         = getenv('REDIS_HOST') ?: '127.0.0.1';

$config = <<<PHP
<?php
global \$globalConfig;
\$globalConfig = [];
\$globalConfig['staticParameters'] = [];
\$globalConfig['dataSources'] = [];

\$globalConfig['staticParameters']['recaptcha_public_key'] = '{$recaptchaPublic}';
\$globalConfig['staticParameters']['recaptcha_private_key'] = '{$recaptchaPrivate}';

// Urls
\$globalConfig['staticParameters']['indexUrl'] = 'http://www.{$domain}/';
\$globalConfig['staticParameters']['forumUrl'] = 'http://forum.{$domain}/';
\$globalConfig['staticParameters']['answersUrl'] = 'https://answers.travian.com/index.php';
\$globalConfig['staticParameters']['helpUrl'] = 'http://help.{$domain}/';
\$globalConfig['staticParameters']['adminEmail'] = 'admin@{$domain}';

// Main database
\$globalConfig['dataSources']['globalDB']['hostname'] = '{$dbHost}';
\$globalConfig['dataSources']['globalDB']['username'] = '{$dbUser}';
\$globalConfig['dataSources']['globalDB']['password'] = '{$dbPassword}';
\$globalConfig['dataSources']['globalDB']['database'] = '{$dbName}';
\$globalConfig['dataSources']['globalDB']['charset'] = 'utf8mb4';

// Voting
\$globalConfig['voting'] = [
    'secret'        => '{$votingSecret}',
    'gtop100'       => '{$votingGtop100}',
    'topg'          => '{$votingTopg}',
    'arenatop100'   => '{$votingArena}',
];

// Email
\$globalConfig['mailer'] = [
    'driver'            => '{$mailerDriver}',
    'from_email'        => 'noreply@{$domain}',
    'from_name'         => '{$mailerFromName}',
    'smtp_host'         => '{$smtpHost}',
    'smtp_user'         => '{$smtpUser}',
    'smtp_pass'         => '{$smtpPass}',
    'smtp_port'         => '{$smtpPort}',
    'smtp_encryption'   => '{$smtpEncryption}',
    'smtp_auth'         => true,
];

// Installer key
\$globalConfig['installer_key'] = '{$installerKey}';

// Locale and formatting
\$globalConfig['staticParameters']['default_language'] = '{$defaultLanguage}';
\$globalConfig['staticParameters']['default_timezone'] = '{$defaultTimezone}';
\$globalConfig['staticParameters']['default_direction'] = 'LTR';
\$globalConfig['staticParameters']['default_dateFormat'] = 'y.m.d';
\$globalConfig['staticParameters']['default_timeFormat'] = 'H:i';

// Misc
\$globalConfig['staticParameters']['session_timeout'] = {$sessionTimeout};
\$globalConfig['staticParameters']['default_payment_location'] = 2;
\$globalConfig['staticParameters']['global_css_class'] = 'travium';

/* DO NOT EDIT BELOW THIS POINT UNLESS YOU KNOW WHAT YOU ARE DOING */
\$globalConfig['cachingServers'] = ['memcached' => [['127.0.0.1', 11211],],];
\$globalConfig['staticParameters']['gpacks'] = [
    'default' => 'a17a8f72',
    'list' => [
        'a17a8f72' => ['hash' => 'a17a8f72', 'name' => 'Travian T4.5', 'isNew' => true],
        '68597666' => ['hash' => '68597666', 'name' => 'Travian T4.4 Seasonized', 'isNew' => false],
        'd11cb434' => ['hash' => 'd11cb434', 'name' => 'Travian T4.4 Seasonized v2', 'isNew' => false],
        '3f73c13f' => ['hash' => '3f73c13f', 'name' => 'Travian T4.6', 'isNew' => false],
        '29c89d54' => ['hash' => '29c89d54', 'name' => 'Travian T4.6', 'isNew' => true],
        'TravianOld' => ['hash' => 'TravianOld', 'name' => 'Travian T4.4 Classic', 'isNew' => false]
    ]
];
PHP;

file_put_contents($configPath, $config);
echo "config.php generated at {$configPath}\n";
