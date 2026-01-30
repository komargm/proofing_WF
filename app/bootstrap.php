<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config/config.php';
$config = require $configPath;

if (!is_array($config)) {
  throw new RuntimeException("Config file did not return array: {$configPath}");
}

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');

if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
  ini_set('session.cookie_secure', '1');
}

session_name($config['app']['session_name']);
session_start();

require_once __DIR__ . '/Core/Router.php';
require_once __DIR__ . '/Core/Response.php';
require_once __DIR__ . '/Core/View.php';
require_once __DIR__ . '/Core/Csrf.php';

require_once __DIR__ . '/Middlewares/RequireAuth.php';
require_once __DIR__ . '/Middlewares/RequireRole.php';

require_once __DIR__ . '/Repositories/UserRepository.php';
require_once __DIR__ . '/Repositories/AlbumRepository.php';
require_once __DIR__ . '/Repositories/PhotoRepository.php';
require_once __DIR__ . '/Repositories/PhotoActionsRepository.php';
require_once __DIR__ . '/Repositories/IngestRepository.php';

require_once __DIR__ . '/Services/AuthService.php';

require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Controllers/ClientController.php';
require_once __DIR__ . '/Controllers/ClientActionsController.php';
require_once __DIR__ . '/Controllers/AdminController.php';
require_once __DIR__ . '/Controllers/AdminUsersController.php';
require_once __DIR__ . '/Controllers/IngestWizardController.php';
require_once __DIR__ . '/Controllers/MediaController.php';

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  $config = require __DIR__ . '/../config/config.php';
  $db = $config['db'];

  $dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $db['host'],
    $db['name'],
    $db['charset']
  );

  $pdo = new PDO($dsn, $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  return $pdo;
}
