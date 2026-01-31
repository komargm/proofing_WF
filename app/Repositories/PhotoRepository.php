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


  public function originalPathIfAllowedForUser(int $userId, int $photoId): ?string {
    $sql = "
      SELECT f.path
      FROM user_album_access uaa
      JOIN photos p ON p.album_id = uaa.album_id
      JOIN photo_files f ON f.photo_id = p.id AND f.kind = 'original_jpg'
      WHERE uaa.user_id = :uid
        AND p.id = :pid
        AND p.download_allowed_at IS NOT NULL
      LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId, 'pid' => $photoId]);
    $row = $stmt->fetch();
    return $row['path'] ?? null;
  }



  public function setDownloadAllowed(int $photoId, bool $allowed): void {
    if ($allowed) {
      $sql = "UPDATE photos SET download_allowed_at = NOW() WHERE id = :pid";
    } else {
      $sql = "UPDATE photos SET download_allowed_at = NULL WHERE id = :pid";
    }
    $stmt = db()->prepare($sql);
    $stmt->execute(['pid' => $photoId]);
  }



  public function viewerForUser(int $userId, int $photoId): ?array {
    $sql = "
      SELECT
        p.id,
        p.album_id,
        p.sort_order,
        p.client_rating,
        p.client_selected_at,
        p.download_allowed_at,
        p.created_at AS photo_created_at,

        a.title AS album_title,
        a.created_at AS album_created_at,

        pf.path AS preview_path,
        ofl.path AS original_path

      FROM user_album_access uaa
      JOIN photos p ON p.album_id = uaa.album_id
      JOIN albums a ON a.id = p.album_id
      LEFT JOIN photo_files pf ON pf.photo_id = p.id AND pf.kind = 'preview_800'
      LEFT JOIN photo_files ofl ON ofl.photo_id = p.id AND ofl.kind = 'original_jpg'
      WHERE uaa.user_id = :uid
        AND p.id = :pid
        AND p.is_visible = 1
      LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId, 'pid' => $photoId]);
    $row = $stmt->fetch();
    if (!$row) return null;

    $albumId = (int)$row['album_id'];
    $sortOrder = (int)$row['sort_order'];

    $sqlPrev = "
      SELECT p2.id
      FROM user_album_access uaa
      JOIN photos p2 ON p2.album_id = uaa.album_id
      WHERE uaa.user_id = :uid
        AND uaa.album_id = :aid
        AND p2.is_visible = 1
        AND (p2.sort_order < :s1 OR (p2.sort_order = :s2 AND p2.id < :pid))
      ORDER BY p2.sort_order DESC, p2.id DESC
      LIMIT 1
    ";
    $st = db()->prepare($sqlPrev);
    $st->execute(['uid' => $userId, 'aid' => $albumId, 's1' => $sortOrder, 's2' => $sortOrder, 'pid' => $photoId]);
    $prevId = (int)($st->fetchColumn() ?: 0);

    $sqlNext = "
      SELECT p2.id
      FROM user_album_access uaa
      JOIN photos p2 ON p2.album_id = uaa.album_id
      WHERE uaa.user_id = :uid
        AND uaa.album_id = :aid
        AND p2.is_visible = 1
        AND (p2.sort_order > :s1 OR (p2.sort_order = :s2 AND p2.id > :pid))
      ORDER BY p2.sort_order ASC, p2.id ASC
      LIMIT 1
    ";
    $st = db()->prepare($sqlNext);
    $st->execute(['uid' => $userId, 'aid' => $albumId, 's1' => $sortOrder, 's2' => $sortOrder, 'pid' => $photoId]);
    $nextId = (int)($st->fetchColumn() ?: 0);

    $sqlC = "
      SELECT
        pc.id,
        pc.comment_text,
        pc.created_at,
        u.first_name,
        u.last_name,
        r.name AS role_name
      FROM photo_comments pc
      LEFT JOIN users u ON u.id = pc.user_id
      LEFT JOIN user_roles ur ON ur.user_id = u.id
      LEFT JOIN roles r ON r.id = ur.role_id
      WHERE pc.photo_id = :pid
        AND pc.is_internal = 0
      ORDER BY pc.created_at ASC, pc.id ASC
    ";
    $st = db()->prepare($sqlC);
    $st->execute(['pid' => $photoId]);
    $comments = $st->fetchAll() ?: [];

    $originalPath = (string)($row['original_path'] ?? '');
    $basename = $originalPath !== '' ? basename($originalPath) : '';

    return [
      'photo' => [
        'id' => (int)$row['id'],
        'album_id' => $albumId,
        'sort_order' => $sortOrder,
        'client_rating' => $row['client_rating'] !== null ? (int)$row['client_rating'] : null,
        'client_selected_at' => $row['client_selected_at'],
        'download_allowed_at' => $row['download_allowed_at'],
        'photo_created_at' => $row['photo_created_at'],
        'preview_path' => $row['preview_path'],
        'original_basename' => $basename,
      ],
      'album' => [
        'id' => $albumId,
        'title' => (string)$row['album_title'],
        'created_at' => (string)$row['album_created_at'],
      ],
      'nav' => [
        'prev_id' => $prevId > 0 ? $prevId : null,
        'next_id' => $nextId > 0 ? $nextId : null,
      ],
      'comments' => $comments,
    ];
  }



  public function viewerForAdmin(int $photoId): ?array {
    $sql = "
      SELECT
        p.id,
        p.album_id,
        p.sort_order,
        p.client_rating,
        p.client_selected_at,
        p.download_allowed_at,
        p.created_at AS photo_created_at,

        a.title AS album_title,
        a.created_at AS album_created_at,

        pf.path AS preview_path,
        ofl.path AS original_path

      FROM photos p
      JOIN albums a ON a.id = p.album_id
      LEFT JOIN photo_files pf ON pf.photo_id = p.id AND pf.kind = 'preview_800'
      LEFT JOIN photo_files ofl ON ofl.photo_id = p.id AND ofl.kind = 'original_jpg'
      WHERE p.id = :pid
      LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['pid' => $photoId]);
    $row = $stmt->fetch();
    if (!$row) return null;

    $albumId = (int)$row['album_id'];
    $sortOrder = (int)$row['sort_order'];

    $sqlPrev = "
      SELECT p2.id
      FROM photos p2
      WHERE p2.album_id = :aid
        AND p2.is_visible = 1
        AND (p2.sort_order < :s1 OR (p2.sort_order = :s2 AND p2.id < :pid))
      ORDER BY p2.sort_order DESC, p2.id DESC
      LIMIT 1
    ";
    $st = db()->prepare($sqlPrev);
    $st->execute(['aid' => $albumId, 's1' => $sortOrder, 's2' => $sortOrder, 'pid' => $photoId]);
    $prevId = (int)($st->fetchColumn() ?: 0);

    $sqlNext = "
      SELECT p2.id
      FROM photos p2
      WHERE p2.album_id = :aid
        AND p2.is_visible = 1
        AND (p2.sort_order > :s1 OR (p2.sort_order = :s2 AND p2.id > :pid))
      ORDER BY p2.sort_order ASC, p2.id ASC
      LIMIT 1
    ";
    $st = db()->prepare($sqlNext);
    $st->execute(['aid' => $albumId, 's1' => $sortOrder, 's2' => $sortOrder, 'pid' => $photoId]);
    $nextId = (int)($st->fetchColumn() ?: 0);

    $sqlC = "
      SELECT
        pc.id,
        pc.comment_text,
        pc.created_at,
        u.first_name,
        u.last_name,
        r.name AS role_name
      FROM photo_comments pc
      LEFT JOIN users u ON u.id = pc.user_id
      LEFT JOIN user_roles ur ON ur.user_id = u.id
      LEFT JOIN roles r ON r.id = ur.role_id
      WHERE pc.photo_id = :pid
        AND pc.is_internal = 0
      ORDER BY pc.created_at ASC, pc.id ASC
    ";
    $st = db()->prepare($sqlC);
    $st->execute(['pid' => $photoId]);
    $comments = $st->fetchAll() ?: [];

    $originalPath = (string)($row['original_path'] ?? '');
    $basename = $originalPath !== '' ? basename($originalPath) : '';

    return [
      'photo' => [
        'id' => (int)$row['id'],
        'album_id' => $albumId,
        'sort_order' => $sortOrder,
        'client_rating' => $row['client_rating'] !== null ? (int)$row['client_rating'] : null,
        'client_selected_at' => $row['client_selected_at'],
        'download_allowed_at' => $row['download_allowed_at'],
        'photo_created_at' => $row['photo_created_at'],
        'preview_path' => $row['preview_path'],
        'original_basename' => $basename,
      ],
      'album' => [
        'id' => $albumId,
        'title' => (string)$row['album_title'],
        'created_at' => (string)$row['album_created_at'],
      ],
      'nav' => [
        'prev_id' => $prevId > 0 ? $prevId : null,
        'next_id' => $nextId > 0 ? $nextId : null,
      ],
      'comments' => $comments,
    ];
  }

}