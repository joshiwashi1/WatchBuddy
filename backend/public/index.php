<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/response.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/watchlist.php';


$auth = new Auth($pdo);
$watch = new Watchlist($pdo);

function json(array $data, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function badRequest(string $msg = 'Invalid request', array $errors = []): void
{
  json(['ok' => false, 'error' => $msg, 'errors' => $errors], 400);
}

/* ========== normalize ONCE ========== */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawUri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$base = ($scriptDir === '/') ? '' : $scriptDir;

$uri = $rawUri;
if ($base && strncasecmp($uri, $base, strlen($base)) === 0) {
  $uri = substr($uri, strlen($base));
}
if ($uri === '' || $uri[0] !== '/') $uri = '/' . $uri;
if ($uri !== '/' && substr($uri, -1) === '/') $uri = rtrim($uri, '/');

// (Optional) debug
if (isset($_GET['__debug'])) {
  json(['method' => $method, 'rawUri' => $rawUri, 'base' => $base, 'uri' => $uri, 'script' => $_SERVER['SCRIPT_NAME'] ?? null]);
}

$auth = new Auth($pdo);

/* ========== PUBLIC ROUTES ========== */
if ($method === 'POST' && $uri === '/api/register') {
  $data = parseJsonBody();
  $out  = $auth->register($data);
  json($out, $out['ok'] ? 200 : 400);
}

if ($method === 'POST' && $uri === '/api/login') {
  $data = parseJsonBody();
  $out  = $auth->login($data);
  json($out, $out['ok'] ? 200 : 401);
}

if ($method === 'POST' && $uri === '/api/logout') {
  json($auth->logout());
}

/* ========== PROTECTED ROUTES ========== */
if ($method === 'GET' && $uri === '/api/me') {
  $user = $auth->meOr401();
  json(['ok' => true, 'user' => $user]);
}

if ($method === 'GET' && $uri === '/api/watchlist') {
  $uid = Auth::requireAuth();

  $page   = max(1, (int)($_GET['page'] ?? 1));
  $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
  $offset = ($page - 1) * $limit;

  $stmt = $pdo->prepare(
    "SELECT id, title, description, status, rating, poster_url, review,
          UNIX_TIMESTAMP(created_at) AS ts
     FROM watchlist
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?"
  );

  $stmt->bindValue(1, $uid, PDO::PARAM_INT);
  $stmt->bindValue(2, $limit, PDO::PARAM_INT);
  $stmt->bindValue(3, $offset, PDO::PARAM_INT);
  $stmt->execute();
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  json(['ok' => true, 'items' => $items, 'page' => $page, 'limit' => $limit]);
}

if ($method === 'POST' && $uri === '/api/watchlist') {
  $uid  = Auth::requireAuth();
  $data = parseJsonBody();

  $errors = [];
  $title = trim((string)($data['title'] ?? ''));
  if ($title === '' || mb_strlen($title) < 2 || mb_strlen($title) > 120) $errors['title'] = 'Title must be 2–120 characters.';
  $status  = (string)($data['status'] ?? 'to-watch');
  $allowed = ['to-watch', 'watching', 'completed'];
  if (!in_array($status, $allowed, true)) $errors['status'] = 'Invalid status.';
  $rating = (int)($data['rating'] ?? 0);
  if ($rating < 0 || $rating > 5) $errors['rating'] = 'Rating must be between 0 and 5.';
  $description = trim((string)($data['description'] ?? ''));
  $posterUrl   = trim((string)($data['poster_url'] ?? ''));

  if ($errors) badRequest('Please fix the highlighted fields.', $errors);

  $stmt = $pdo->prepare(
    "INSERT INTO watchlist (user_id, title, description, status, rating, poster_url)
     VALUES (?,?,?,?,?,?)"
  );
  $stmt->execute([$uid, $title, $description ?: null, $status, $rating, $posterUrl ?: null]);

  json(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}

if ($method === 'PUT' && preg_match('#^/api/watchlist/(\d+)$#', $uri, $m)) {
  $uid  = Auth::requireAuth();
  $id   = (int)$m[1];
  $data = parseJsonBody();

  $errors = [];
  $title = trim((string)($data['title'] ?? ''));
  if ($title === '' || mb_strlen($title) < 2 || mb_strlen($title) > 120) {
    $errors['title'] = 'Title must be 2–120 characters.';
  }

  $status  = (string)($data['status'] ?? 'to-watch');
  $allowed = ['to-watch', 'watching', 'completed'];
  if (!in_array($status, $allowed, true)) {
    $errors['status'] = 'Invalid status.';
  }

  $rating = (int)($data['rating'] ?? 0);
  if ($rating < 0 || $rating > 5) {
    $errors['rating'] = 'Rating must be between 0 and 5.';
  }

  $description = trim((string)($data['description'] ?? ''));
  $posterUrl   = trim((string)($data['poster_url'] ?? ''));

  if ($errors) {
    badRequest('Please fix the highlighted fields.', $errors);
  }

  // Update only rows that belong to this user
  $stmt = $pdo->prepare(
    "UPDATE watchlist
        SET title = ?, description = ?, status = ?, rating = ?, poster_url = ?
      WHERE id = ? AND user_id = ?"
  );
  $ok = $stmt->execute([$title, $description ?: null, $status, $rating, $posterUrl ?: null, $id, $uid]);

  if (!$ok || $stmt->rowCount() === 0) {
    json(['ok' => false, 'error' => 'Update failed or not found'], 404);
  }

  json([
    'ok'   => true,
    'id'   => $id,
    'item' => [
      'id'          => $id,
      'title'       => $title,
      'description' => $description,
      'status'      => $status,
      'rating'      => $rating,
      'poster_url'  => $posterUrl,
    ]
  ]);
}

//add review
if ($method === 'PUT' && preg_match('#^/api/watchlist/(\d+)/review$#', $uri, $m)) {
  $uid  = Auth::requireAuth();
  $id   = (int)$m[1];
  $data = parseJsonBody();

  $review = trim((string)($data['review'] ?? ''));
  $rating = (int)($data['rating'] ?? 0);

  // Basic checks
  if (mb_strlen($review) > 5000) {
    badRequest('Please fix the highlighted fields.', ['review' => 'Review too long (max 5000 chars).']);
  }
  if ($rating < 0 || $rating > 5) {
    badRequest('Please fix the highlighted fields.', ['rating' => 'Rating must be 0–5.']);
  }

  // Ensure row exists, belongs to user, and is COMPLETED
  $row = $pdo->prepare("SELECT id, status FROM watchlist WHERE id=? AND user_id=?");
  $row->execute([$id, $uid]);
  $found = $row->fetch(PDO::FETCH_ASSOC);
  if (!$found) {
    json(['ok' => false, 'error' => 'Not found'], 404);
  }
  if (($found['status'] ?? '') !== 'completed') {
    json(['ok' => false, 'error' => 'Only completed movies can be reviewed'], 409);
  }

  // Update review (+ optional rating)
  $stmt = $pdo->prepare(
    "UPDATE watchlist
      SET review = ?, rating = ?
    WHERE id = ? AND user_id = ?"
  );
  $ok = $stmt->execute([$review !== '' ? $review : null, $rating, $id, $uid]);
  if (!$ok) {
    json(['ok' => false, 'error' => 'Database error while updating review'], 500);
  }

  json(['ok' => true, 'id' => $id]);
}

// DELETE /api/watchlist/{id}
if ($method === 'DELETE' && preg_match('#^/api/watchlist/(\d+)$#', $uri, $m)) {
  $uid = Auth::requireAuth();
  $id  = (int)$m[1];

  try {
    $stmt = $pdo->prepare("DELETE FROM watchlist WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$id, $uid]);

    if ($stmt->rowCount() === 0) {
      json(['ok' => false, 'error' => 'Not found or not allowed'], 404);
    }

    json(['ok' => true]);
  } catch (PDOException $e) {
    // Foreign key constraint? surface the message for easier debug:
    json(['ok' => false, 'error' => 'DB error: '.$e->getMessage()], 409);
  }
}



/* ========== 404 LAST ========== */
json(['ok' => false, 'error' => 'Not found', 'path' => $uri], 404);
