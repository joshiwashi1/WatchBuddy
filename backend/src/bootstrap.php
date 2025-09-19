<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$config = require __DIR__ . '/../config/config.php';

/* ----- Sessions (safer defaults) ----- */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
      || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

$cookieSecure = isset($config['session']['secure'])
  ? (bool)$config['session']['secure']
  : $https; // fallback to auto

if (!empty($config['session']['name'])) {
  session_name($config['session']['name']);
}

session_set_cookie_params([
  'lifetime' => (int)($config['session']['lifetime'] ?? 0),
  'path'     => '/', // cookie valid everywhere
  'domain'   => '',  // current host only
  'secure'   => $cookieSecure,
  'httponly' => true,
  'samesite' => $config['session']['samesite'] ?? 'Lax',
]);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* ----- DB (PDO MySQL) ----- */
$dsn = sprintf(
  'mysql:host=%s;port=%d;dbname=%s;charset=%s',
  $config['db']['host'],
  $config['db']['port'],
  $config['db']['name'],
  $config['db']['charset']
);

try {
  global $pdo;
  $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok'     => false,
    'error'  => 'DB connection failed',
    'details'=> $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/* ----- App classes/helpers ----- */
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/validators.php';
require_once __DIR__ . '/auth.php';
