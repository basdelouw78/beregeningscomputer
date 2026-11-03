<?php
// Laadt configuratie, database, sessie en hulpfuncties.

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads');

require APP_ROOT . '/app/helpers.php';
require APP_ROOT . '/app/db.php';
require APP_ROOT . '/app/session.php';

if (!is_file(APP_ROOT . '/config.php')) {
    header('Location: ' . url('/install.php'));
    exit;
}

$GLOBALS['__config'] = require APP_ROOT . '/config.php';
date_default_timezone_set($GLOBALS['__config']['timezone'] ?? 'Europe/Amsterdam');
mb_internal_encoding('UTF-8');

$GLOBALS['__db'] = db_connect($GLOBALS['__config']);
$GLOBALS['__db']->exec("SET time_zone = '" . date('P') . "'");

function config(string $key, $default = null)
{
    return $GLOBALS['__config'][$key] ?? $default;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

start_session();
