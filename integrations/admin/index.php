<?php
/**
 * Global Admin Panel - Entry Point
 *
 * Routes all requests through the Router after bootstrapping.
 */

require __DIR__ . '/include/bootstrap.php';

$router = new Router($db);
$router->dispatch();
