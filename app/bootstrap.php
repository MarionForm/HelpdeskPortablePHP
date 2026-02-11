<?php
declare(strict_types=1);

session_start();

define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');
define('EXPORTS_PATH', STORAGE_PATH . '/exports');
define('DB_PATH', STORAGE_PATH . '/helpdesk.sqlite');

@mkdir(STORAGE_PATH, 0775, true);
@mkdir(UPLOADS_PATH, 0775, true);
@mkdir(EXPORTS_PATH, 0775, true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/views.php';
require_once __DIR__ . '/export.php';

$pdo = db();
