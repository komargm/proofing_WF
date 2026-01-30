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
}
