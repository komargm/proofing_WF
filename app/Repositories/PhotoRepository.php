<?php
declare(strict_types=1);

final class PhotoRepository {

  /**
   * Lista zdjęć w albumie, ale tylko jeśli user ma dostęp (user_album_access).
   * Zwraca thumb + preview (ścieżki z photo_files) oraz ostatni komentarz (publiczny).
   *
   * @return array<int, array<string, mixed>>
   */
  /**
   * @param ?bool $selectedOnly true = tylko z serduszkiem, null/false = bez filtra
   * @param null|int|'none' $ratingFilter null = bez filtra, 'none' = brak oceny (NULL/0), int=1..6
   */
  public function listForUserAlbum(int $userId, int $albumId, ?int $sectionId = null, ?bool $selectedOnly = null, $ratingFilter = null): array {
    $sql = "
      SELECT
        p.id,
        p.sort_order,
        p.section_id,
        p.client_rating,
        p.admin_rating,
        p.client_selected_at,
        p.download_allowed_at,

        tf.path AS thumb_path,
        pf.path AS preview_path,

        lc.comment_text AS last_comment_text,
        lc.created_at   AS last_comment_at

      FROM user_album_access uaa
      JOIN albums a ON a.id = uaa.album_id AND a.is_visible = 1
      JOIN photos p ON p.album_id = uaa.album_id
      LEFT JOIN album_sections s ON s.id = p.section_id
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
        " . ($sectionId !== null ? " AND p.section_id = :sid " : "") . "
        " . ($selectedOnly ? " AND p.client_selected_at IS NOT NULL " : "") . "
        " . ($ratingFilter === 'none' ? " AND (p.client_rating IS NULL OR p.client_rating = 0) " : "") . "
        " . (is_int($ratingFilter) ? " AND p.client_rating = :cr " : "") . "

      ORDER BY p.sort_order ASC, p.id ASC
    ";

    $stmt = db()->prepare($sql);
    $bind = ['uid' => $userId, 'aid' => $albumId];
    if ($sectionId !== null) $bind['sid'] = $sectionId;
    if (is_int($ratingFilter)) $bind['cr'] = $ratingFilter;
    $stmt->execute($bind);
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
      JOIN albums a ON a.id = uaa.album_id AND a.is_visible = 1
      JOIN photos p ON p.album_id = uaa.album_id
      JOIN photo_files f ON f.photo_id = p.id AND f.kind = :kind
      WHERE uaa.user_id = :uid
        AND p.id = :pid
        AND p.is_visible = 1
      LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId, 'pid' => $photoId, 'kind' => $kind]);
    $row = $stmt->fetch();
    return $row['path'] ?? null;
  }

  /** @return array<int, array<string, mixed>> */
  /**
   * @param ?bool $selectedOnly true = tylko z serduszkiem, null/false = bez filtra
   * @param null|int|'none' $ratingFilter null = bez filtra, 'none' = brak oceny (NULL/0), int=1..6
   */
  public function listForAdminAlbum(int $albumId, ?int $sectionId = null, ?bool $selectedOnly = null, $ratingFilter = null): array {
    $sql = "
      SELECT
        p.id,
        p.sort_order,
        p.section_id,
        p.client_rating,
        p.admin_rating,
        p.client_selected_at,
        p.download_allowed_at,
        p.is_visible,

        tf.path AS thumb_path,
        pf.path AS preview_path,

        lc.comment_text AS last_comment_text,
        lc.created_at   AS last_comment_at

      FROM photos p
      LEFT JOIN album_sections s ON s.id = p.section_id
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
        " . ($sectionId !== null ? " AND p.section_id = :sid " : "") . "
        " . ($selectedOnly ? " AND p.client_selected_at IS NOT NULL " : "") . "
        " . ($ratingFilter === 'none' ? " AND (p.client_rating IS NULL OR p.client_rating = 0) " : "") . "
        " . (is_int($ratingFilter) ? " AND p.client_rating = :cr " : "") . "
      ORDER BY p.sort_order ASC, p.id ASC
    ";
    $stmt = db()->prepare($sql);
    $bind = ['aid' => $albumId];
    if ($sectionId !== null) $bind['sid'] = $sectionId;
    if (is_int($ratingFilter)) $bind['cr'] = $ratingFilter;
    $stmt->execute($bind);
    return $stmt->fetchAll() ?: [];
  }

  public function setSection(int $photoId, ?int $sectionId): void {
    if ($sectionId !== null && $sectionId <= 0) $sectionId = null;
    $sql = "UPDATE photos SET section_id = :sid WHERE id = :pid";
    $st = db()->prepare($sql);
    $st->execute(['sid' => $sectionId, 'pid' => $photoId]);
  }

  /** @param array<int,int> $photoIds */
  public function bulkSetSection(array $photoIds, ?int $sectionId): void {
    if (empty($photoIds)) return;
    if ($sectionId !== null && $sectionId <= 0) $sectionId = null;

    $in = implode(',', array_fill(0, count($photoIds), '?'));
    $sql = "UPDATE photos SET section_id = ? WHERE id IN ($in)";
    $st = db()->prepare($sql);
    $params = array_merge([$sectionId], array_values(array_map('intval', $photoIds)));
    $st->execute($params);
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



  /**
   * @param ?bool $selectedOnly true = tylko z serduszkiem, null/false = bez filtra
   * @param null|int|'none' $ratingFilter null = bez filtra, 'none' = brak oceny (NULL/0), int=1..6
   */
  public function viewerForUser(int $userId, int $photoId, ?int $sectionId = null, ?bool $selectedOnly = null, $ratingFilter = null): ?array {
    $sql = "
      SELECT
        p.id,
        p.album_id,
        p.sort_order,
        p.section_id,
        p.client_rating,
        p.admin_rating,
        p.client_selected_at,
        p.download_allowed_at,
        p.created_at AS photo_created_at,

        a.title AS album_title,
        a.created_at AS album_created_at,

        au.first_name AS photographer_first_name,

        pf.path AS preview_path,
        ofl.path AS original_path

      FROM user_album_access uaa
      JOIN photos p ON p.album_id = uaa.album_id
      JOIN albums a ON a.id = p.album_id AND a.is_visible = 1
      LEFT JOIN users au ON au.id = a.created_by
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

    // Jeśli przekazano filtr (sekcja/serce/ocena), ale zdjęcie do niego nie pasuje,
    // ignorujemy dany filtr, żeby podgląd + nawigacja nie była pusta.
    if ($sectionId !== null && (int)($row['section_id'] ?? 0) !== $sectionId) {
      $sectionId = null;
    }
    if ($selectedOnly && empty($row['client_selected_at'])) {
      $selectedOnly = null;
    }
    if ($ratingFilter === 'none') {
      $cr = (int)($row['client_rating'] ?? 0);
      if ($cr !== 0) {
        $ratingFilter = null;
      }
    } elseif (is_int($ratingFilter)) {
      $cr = (int)($row['client_rating'] ?? 0);
      if ($cr !== $ratingFilter) {
        $ratingFilter = null;
      }
    }

    $albumId = (int)$row['album_id'];
    $sortOrder = (int)$row['sort_order'];
    // (sekcja) – już wyżej normalizujemy $sectionId, tu nie blokujemy podglądu

    $sqlPrev = "
      SELECT p2.id
      FROM user_album_access uaa
      JOIN albums a ON a.id = uaa.album_id AND a.is_visible = 1
      JOIN photos p2 ON p2.album_id = uaa.album_id
      WHERE uaa.user_id = :uid
        AND uaa.album_id = :aid
        AND p2.is_visible = 1
        " . ($sectionId !== null ? " AND p2.section_id = :sid " : "") . "
        " . ($selectedOnly ? " AND p2.client_selected_at IS NOT NULL " : "") . "
        " . ($ratingFilter === 'none' ? " AND (p2.client_rating IS NULL OR p2.client_rating = 0) " : "") . "
        " . (is_int($ratingFilter) ? " AND p2.client_rating = :cr " : "") . "
        AND (p2.sort_order < :s1 OR (p2.sort_order = :s2 AND p2.id < :pid))
      ORDER BY p2.sort_order DESC, p2.id DESC
      LIMIT 1
    ";
    $st = db()->prepare($sqlPrev);
    $bindPrev = ['uid' => $userId, 'aid' => $albumId, 's1' => $sortOrder, 's2' => $sortOrder, 'pid' => $photoId];
    if ($sectionId !== null) $bindPrev['sid'] = $sectionId;
    if (is_int($ratingFilter)) $bindPrev['cr'] = $ratingFilter;
    $st->execute($bindPrev);
    $prevId = (int)($st->fetchColumn() ?: 0);

    $sqlNext = "
      SELECT p2.id
      FROM user_album_access uaa
      JOIN albums a ON a.id = uaa.album_id AND a.is_visible = 1
      JOIN photos p2 ON p2.album_id = uaa.album_id
      WHERE uaa.user_id = :uid
        AND uaa.album_id = :aid
        AND p2.is_visible = 1
        " . ($sectionId !== null ? " AND p2.section_id = :sid " : "") . "
        " . ($selectedOnly ? " AND p2.client_selected_at IS NOT NULL " : "") . "
        " . ($ratingFilter === 'none' ? " AND (p2.client_rating IS NULL OR p2.client_rating = 0) " : "") . "
        " . (is_int($ratingFilter) ? " AND p2.client_rating = :cr " : "") . "
        AND (p2.sort_order > :s1 OR (p2.sort_order = :s2 AND p2.id > :pid))
      ORDER BY p2.sort_order ASC, p2.id ASC
      LIMIT 1
    ";
    $st = db()->prepare($sqlNext);
    $bindNext = ['uid' => $userId, 'aid' => $albumId, 's1' => $sortOrder, 's2' => $sortOrder, 'pid' => $photoId];
    if ($sectionId !== null) $bindNext['sid'] = $sectionId;
    if (is_int($ratingFilter)) $bindNext['cr'] = $ratingFilter;
    $st->execute($bindNext);
    $nextId = (int)($st->fetchColumn() ?: 0);

    $sqlC = "
      SELECT
        pc.id,
        pc.comment_text,
        pc.created_at,
        u.first_name,
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
        'section_id' => isset($row['section_id']) ? (int)$row['section_id'] : null,
        'client_rating' => $row['client_rating'] !== null ? (int)$row['client_rating'] : null,
        'admin_rating' => $row['admin_rating'] !== null ? (int)$row['admin_rating'] : null,
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
        'photographer_first_name' => (string)($row['photographer_first_name'] ?? ''),
      ],
      'nav' => [
        'prev_id' => $prevId > 0 ? $prevId : null,
        'next_id' => $nextId > 0 ? $nextId : null,
        'section_id' => $sectionId,
        'selected' => $selectedOnly ? 1 : null,
        'rating' => $ratingFilter,
      ],
      'comments' => $comments,
    ];
  }



  /**
   * @param ?bool $selectedOnly true = tylko z serduszkiem, null/false = bez filtra
   * @param null|int|'none' $ratingFilter null = bez filtra, 'none' = brak oceny (NULL/0), int=1..6
   */
  public function viewerForAdmin(int $photoId, ?int $sectionId = null, ?bool $selectedOnly = null, $ratingFilter = null): ?array {
    $sql = "
      SELECT
        p.id,
        p.album_id,
        p.sort_order,
        p.section_id,
        p.client_rating,
        p.admin_rating,
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

    // Jeśli przekazano filtr (sekcja/serce/ocena), ale zdjęcie do niego nie pasuje,
    // ignorujemy dany filtr, żeby podgląd + nawigacja nie była pusta.
    if ($sectionId !== null && (int)($row['section_id'] ?? 0) !== $sectionId) {
      $sectionId = null;
    }
    if ($selectedOnly && empty($row['client_selected_at'])) {
      $selectedOnly = null;
    }
    if ($ratingFilter === 'none') {
      $cr = (int)($row['client_rating'] ?? 0);
      if ($cr !== 0) {
        $ratingFilter = null;
      }
    } elseif (is_int($ratingFilter)) {
      $cr = (int)($row['client_rating'] ?? 0);
      if ($cr !== $ratingFilter) {
        $ratingFilter = null;
      }
    }

    $albumId = (int)$row['album_id'];
    $sortOrder = (int)$row['sort_order'];

    $sqlPrev = "
      SELECT p2.id
      FROM photos p2
      WHERE p2.album_id = :aid
        AND p2.is_visible = 1
        " . ($sectionId !== null ? " AND p2.section_id = :sid " : "") . "
        " . ($selectedOnly ? " AND p2.client_selected_at IS NOT NULL " : "") . "
        " . ($ratingFilter === 'none' ? " AND (p2.client_rating IS NULL OR p2.client_rating = 0) " : "") . "
        " . (is_int($ratingFilter) ? " AND p2.client_rating = :cr " : "") . "
        AND (p2.sort_order < :s1 OR (p2.sort_order = :s2 AND p2.id < :pid))
      ORDER BY p2.sort_order DESC, p2.id DESC
      LIMIT 1
    ";
    $st = db()->prepare($sqlPrev);
    $bindPrev = ['aid' => $albumId, 's1' => $sortOrder, 's2' => $sortOrder, 'pid' => $photoId];
    if ($sectionId !== null) $bindPrev['sid'] = $sectionId;
    if (is_int($ratingFilter)) $bindPrev['cr'] = $ratingFilter;
    $st->execute($bindPrev);
    $prevId = (int)($st->fetchColumn() ?: 0);

    $sqlNext = "
      SELECT p2.id
      FROM photos p2
      WHERE p2.album_id = :aid
        AND p2.is_visible = 1
        " . ($sectionId !== null ? " AND p2.section_id = :sid " : "") . "
        " . ($selectedOnly ? " AND p2.client_selected_at IS NOT NULL " : "") . "
        " . ($ratingFilter === 'none' ? " AND (p2.client_rating IS NULL OR p2.client_rating = 0) " : "") . "
        " . (is_int($ratingFilter) ? " AND p2.client_rating = :cr " : "") . "
        AND (p2.sort_order > :s1 OR (p2.sort_order = :s2 AND p2.id > :pid))
      ORDER BY p2.sort_order ASC, p2.id ASC
      LIMIT 1
    ";
    $st = db()->prepare($sqlNext);
    $bindNext = ['aid' => $albumId, 's1' => $sortOrder, 's2' => $sortOrder, 'pid' => $photoId];
    if ($sectionId !== null) $bindNext['sid'] = $sectionId;
    if (is_int($ratingFilter)) $bindNext['cr'] = $ratingFilter;
    $st->execute($bindNext);
    $nextId = (int)($st->fetchColumn() ?: 0);

    $sqlC = "
      SELECT
        pc.id,
        pc.comment_text,
        pc.created_at,
        u.first_name,
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
        'section_id' => $row['section_id'] !== null ? (int)$row['section_id'] : null,
        'client_rating' => $row['client_rating'] !== null ? (int)$row['client_rating'] : null,
        'admin_rating' => $row['admin_rating'] !== null ? (int)$row['admin_rating'] : null,
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
        'photographer_first_name' => (string)($row['photographer_first_name'] ?? ''),
      ],
      'nav' => [
        'prev_id' => $prevId > 0 ? $prevId : null,
        'next_id' => $nextId > 0 ? $nextId : null,
        'section_id' => $sectionId,
        'selected' => $selectedOnly ? 1 : null,
        'rating' => $ratingFilter,
      ],
      'comments' => $comments,
    ];
  }


  /**
   * Admin: usuwa zdjęcie z systemu (DB) i zwraca ścieżki preview/thumb do skasowania z dysku.
   * UWAGA: NIE usuwa pliku oryginału (original_jpg) z NAS – usuwa tylko rekordy w DB.
   *
   * @return array{album_id:int, delete_paths:array<int,string>}|null
   */
  public function adminDeletePhoto(int $photoId): ?array {
    $pdo = db();

    // album_id + ścieżki plików do usunięcia
    $stmt = $pdo->prepare("SELECT album_id FROM photos WHERE id = :pid LIMIT 1");
    $stmt->execute(['pid' => $photoId]);
    $albumId = (int)($stmt->fetchColumn() ?: 0);
    if ($albumId <= 0) return null;

    $stmt = $pdo->prepare("SELECT path FROM photo_files WHERE photo_id = :pid AND kind IN ('preview_800','thumb')");
    $stmt->execute(['pid' => $photoId]);
    $paths = array_map(fn($r) => (string)$r['path'], $stmt->fetchAll() ?: []);

    $pdo->beginTransaction();
    try {
      // komentarze
      $st = $pdo->prepare("DELETE FROM photo_comments WHERE photo_id = :pid");
      $st->execute(['pid' => $photoId]);

      // pliki (mapowanie)
      $st = $pdo->prepare("DELETE FROM photo_files WHERE photo_id = :pid");
      $st->execute(['pid' => $photoId]);

      // rekord zdjęcia
      $st = $pdo->prepare("DELETE FROM photos WHERE id = :pid");
      $st->execute(['pid' => $photoId]);

      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }

    return ['album_id' => $albumId, 'delete_paths' => $paths];
  }


}