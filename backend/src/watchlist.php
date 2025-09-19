<?php
declare(strict_types=1);

class Watchlist {
    private \PDO $pdo;
    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

    public function list(int $userId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM watchlist WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function add(int $userId, array $data): array {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') return ['ok'=>false,'error'=>'Title required'];

        $status = in_array($data['status'] ?? 'to-watch', ['to-watch','watching','completed'], true)
          ? $data['status'] : 'to-watch';
        $rating = (int)($data['rating'] ?? 0);
        if ($rating < 0 || $rating > 5) $rating = 0;

        $stmt = $this->pdo->prepare(
            "INSERT INTO watchlist (user_id, title, description, status, rating, poster_url)
             VALUES (?,?,?,?,?,?)"
        );
        $stmt->execute([
            $userId,
            $title,
            $data['description'] ?? null,
            $status,
            $rating,
            $data['poster_url'] ?? null
        ]);

        return ['ok'=>true, 'id'=>(int)$this->pdo->lastInsertId()];
    }
}
