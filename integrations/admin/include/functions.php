<?php
/**
 * Global Admin Panel - Helper Functions
 */

/**
 * Escape HTML for safe output.
 */
function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Get the base domain from config (strips www/protocol).
 */
function getBaseDomain(): string
{
    global $globalConfig;
    $indexUrl = $globalConfig['staticParameters']['indexUrl'] ?? '';
    $parsed = parse_url(
        (strpos($indexUrl, '://') === false ? 'http://' . $indexUrl : $indexUrl),
        PHP_URL_HOST
    );
    $host = $parsed ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/:\d+$/', '', $host);
    $host = preg_replace('/^www\./i', '', $host);
    return $host;
}

/**
 * Format a Unix timestamp for display (always UTC).
 */
function formatTime(int $timestamp): string
{
    if ($timestamp === 0) {
        return '-';
    }
    return gmdate('Y-m-d H:i', $timestamp) . ' UTC';
}

/**
 * Generate a CSRF token and store in session.
 */
function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/**
 * Validate the submitted CSRF token.
 */
function csrfValidate(): bool
{
    $token = $_POST['_csrf'] ?? '';
    return $token !== '' && hash_equals($_SESSION['_csrf'] ?? '', $token);
}
