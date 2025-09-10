<?php
declare(strict_types=1);


final class Auth
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function register(array $data): array
    {
        $first  = Validators::s($data['firstName'] ?? '');
        $middle = ($data['middleName'] ?? '') !== '' ? Validators::s((string)$data['middleName']) : null;
        $last   = Validators::s($data['lastName'] ?? '');
        $suffix = ($data['suffix'] ?? '') !== '' ? Validators::s((string)$data['suffix'], 20) : null;
        $email  = Validators::email($data['email'] ?? '');
        $pass   = (string)($data['password'] ?? '');

        if ($first === '' || $last === '' || $email === '' || $pass === '') {
            return ['ok' => false, 'error' => 'Missing required fields'];
        }
        if (strlen($pass) < 6) {
            return ['ok' => false, 'error' => 'Password must be at least 6 chars'];
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (first_name,middle_name,last_name,suffix,email,password_hash) VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$first, $middle, $last, $suffix, $email, $hash]);
            $id = (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            $isDup = ($e->getCode() === '23000') || ((int)($e->errorInfo[1] ?? 0) === 1062);
            if ($isDup) return ['ok' => false, 'error' => 'Email already registered'];
            throw $e;
        }

        session_regenerate_id(true);
        $_SESSION['uid'] = $id;

        return ['ok' => true, 'user' => $this->me($id)];
    }

    public function login(array $data): array
    {
        $email = Validators::email($data['email'] ?? '');
        $pass  = (string)($data['password'] ?? '');
        if ($email === '' || $pass === '') {
            return ['ok' => false, 'error' => 'Invalid credentials'];
        }

        $stmt = $this->db->prepare('SELECT id, password_hash FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$u || !password_verify($pass, (string)$u['password_hash'])) {
            return ['ok' => false, 'error' => 'Invalid credentials'];
        }

        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        return ['ok' => true, 'user' => $this->me((int)$u['id'])];
    }

    public function logout(): array
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
        return ['ok' => true];
    }

    public function me(?int $uid = null): ?array
    {
        $uid = $uid ?? (int)($_SESSION['uid'] ?? 0);
        if ($uid <= 0) return null;

        $stmt = $this->db->prepare(
            'SELECT id,
                    first_name  AS firstName,
                    middle_name AS middleName,
                    last_name   AS lastName,
                    suffix,
                    email,
                    created_at  AS createdAt
             FROM users
             WHERE id = ?'
        );
        $stmt->execute([$uid]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) return null;

        $parts = [$u['firstName']];
        if (!empty($u['middleName'])) $parts[] = $u['middleName'];
        $parts[] = $u['lastName'];
        $display = trim(implode(' ', array_filter($parts)));
        if (!empty($u['suffix'])) $display .= ', ' . $u['suffix'];

        $u['fullName'] = $display;
        return $u;
    }

    /** Use inside routes when you need a 401 on missing session */
    public function meOr401(): array
    {
        $u = $this->me();
        if (!$u) {
            Response::json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
        return $u;
    }

    /** Static helper if you don’t need the user payload */
    public static function requireAuth(): int
    {
        $uid = (int)($_SESSION['uid'] ?? 0);
        if ($uid <= 0) {
            Response::json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
        return $uid;
    }
}
