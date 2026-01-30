<?php
declare(strict_types=1);

final class AlbumRepository {

  /** @return array<int, array<string, mixed>> */
  public function listForUser(int $userId): array {
    $sql = "SELECT a.id, a.title, a.created_at
            FROM user_album_access uaa
            JOIN albums a ON a.id = uaa.album_id
            WHERE uaa.user_id = :uid
            ORDER BY a.created_at DESC, a.id DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll() ?: [];
  }

  public function findForUser(int $userId, int $albumId): ?array {
    $sql = "SELECT a.id, a.title, a.created_at
            FROM user_album_access uaa
            JOIN albums a ON a.id = uaa.album_id
            WHERE uaa.user_id = :uid AND uaa.album_id = :aid
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId, 'aid' => $albumId]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  /** @return array<int, array<string, mixed>> */
  public function listAll(): array {
    // albums table in current schema:
    // id, code, title, album_comment, created_by, created_at, is_archived
    $sql = "SELECT id, code, title, is_archived, created_at
            FROM albums
            ORDER BY created_at DESC, id DESC";
    $stmt = db()->query($sql);
    return $stmt->fetchAll() ?: [];
  }

  public function findById(int $albumId): ?array {
    $sql = "SELECT id, code, title, album_comment, is_archived, created_at
            FROM albums
            WHERE id = :id
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $albumId]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public function updateSettings(int $albumId, string $title, ?string $albumComment): void {
    $title = trim($title);
    if ($title === '' || mb_strlen($title) > 255) {
      Response::html('Bad Request', 400);
    }

    // In schema: album_comment is NOT NULL. Keep it non-empty.
    $albumComment = $albumComment !== null ? trim($albumComment) : '';
    if ($albumComment === '' || mb_strlen($albumComment) > 20000) {
      Response::html('Bad Request', 400);
    }

    $sql = "UPDATE albums
            SET title = :t,
                album_comment = :c
            WHERE id = :id";
    $stmt = db()->prepare($sql);
    $stmt->bindValue('t', $title, PDO::PARAM_STR);
    $stmt->bindValue('c', $albumComment, PDO::PARAM_STR);
    $stmt->bindValue('id', $albumId, PDO::PARAM_INT);
    $stmt->execute();
  }
}
