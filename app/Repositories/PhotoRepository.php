<?php
declare(strict_types=1);

final class PhotoRepository {

  /**
   * Lista zdjęć w albumie, ale tylko jeśli user ma dostęp (user_album_access).
   * Zwraca thumb + preview (ścieżki z photo_files) oraz ostatni komentarz (publiczny).
   *
   * @return array<int, array<string, mixed>>
   */
  public function listForUserAlbum(int $userId, int $albumId): array {
    $sql = "
      SELECT
        p.id,
        p.sort_order,
        p.client_rating,
        p.client_selected_at,
        p.download_allowed_at,

        tf.path AS thumb_path,
        pf.path AS preview_path,

        lc.comment_text AS last_comment_text,
        lc.created_at   AS last_comment_at

      FROM user_album_access uaa
      JOIN photos p ON p.album_id = uaa.album_id
      LEFT JOIN photo_files tf ON tf.photo_id = p.id AND tf.kind = 'thumb'
      LEFT JOIN photo_files pf ON pf.photo_id = p.id AND pf.kind = 'preview_800'
      LEFT JOIN (
        SELECT pc1.photo_id, pc1.comment_text, pc1.created_at
        FROM photo_comments pc1
        JOIN (
          SELECT photo_id, MAX(created_at) AS max_created
          FROM photo_comments
          WHERE is_internal = 0
          GROUP BY photo_id
        ) t ON t.photo_id = pc1.photo_id AND t.max_created = pc1.created_at
        WHERE pc1.is_internal = 0
      ) lc ON lc.photo_id = p.id

      WHERE uaa.user_id = :uid
        AND uaa.album_id = :aid
        AND p.is_visible = 1

      ORDER BY p.sort_order ASC, p.id ASC
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId, 'aid' => $albumId]);
    return $stmt->fetchAll() ?: [];
  }

  /**
   * Zwraca ścieżkę pliku dla photo_id i kind, ale tylko jeśli user ma dostęp do albumu.
   * kind: 'thumb' | 'preview_800' | 'original_jpg'
   */
  public function filePathForUser(int $userId, int $photoId, string $kind): ?string {
    $sql = "
      SELECT f.path
      FROM user_album_access uaa
      JOIN photos p ON p.album_id = uaa.album_id
      JOIN photo_files f ON f.photo_id = p.id AND f.kind = :kind
      WHERE uaa.user_id = :uid
        AND p.id = :pid
      LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId, 'pid' => $photoId, 'kind' => $kind]);
    $row = $stmt->fetch();
    return $row['path'] ?? null;
  }

  /** @return array<int, array<string, mixed>> */
  public function listForAdminAlbum(int $albumId): array {
    $sql = "
      SELECT
        p.id,
        p.sort_order,
        p.client_rating,
        p.client_selected_at,
        p.download_allowed_at,
        p.is_visible,

        tf.path AS thumb_path,
        pf.path AS preview_path,

        lc.comment_text AS last_comment_text,
        lc.created_at   AS last_comment_at

      FROM photos p
      LEFT JOIN photo_files tf ON tf.photo_id = p.id AND tf.kind = 'thumb'
      LEFT JOIN photo_files pf ON pf.photo_id = p.id AND pf.kind = 'preview_800'
      LEFT JOIN (
        SELECT pc1.photo_id, pc1.comment_text, pc1.created_at
        FROM photo_comments pc1
        JOIN (
          SELECT photo_id, MAX(created_at) AS max_created
          FROM photo_comments
          WHERE is_internal = 0
          GROUP BY photo_id
        ) t ON t.photo_id = pc1.photo_id AND t.max_created = pc1.created_at
        WHERE pc1.is_internal = 0
      ) lc ON lc.photo_id = p.id

      WHERE p.album_id = :aid
      ORDER BY p.sort_order ASC, p.id ASC
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['aid' => $albumId]);
    return $stmt->fetchAll() ?: [];
  }

  public function filePathForAdmin(int $photoId, string $kind): ?string {
    $sql = "
      SELECT f.path
      FROM photo_files f
      WHERE f.photo_id = :pid AND f.kind = :kind
      LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['pid' => $photoId, 'kind' => $kind]);
    $row = $stmt->fetch();
    return $row['path'] ?? null;
  }
}
