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

  /**
   * Buduje plan rescanu dla pojedynczego albumu:
   * - porównuje rozmiar + mtime pliku oryginalnego (photo_files.kind=original_jpg)
   *   z wartościami w bazie (file_size_bytes, file_mtime)
   * - jeśli różne (albo NULL) -> wrzuca do items do regeneracji preview_800 + thumb
   * - aktualizuje w DB metadane oryginału do bieżących (żeby następny rescan był szybki)
   *
   * @return array{items:array<int, array{photo_id:int, src:string, preview_dest:string, thumb_dest:string}>}
   */
  public function buildRescanPlan(int $albumId): array {
    $sql = "
      SELECT
        p.id AS photo_id,
        ofi.path AS src,
        ofi.file_size_bytes AS db_size,
        ofi.file_mtime AS db_mtime,
        pfi.path AS preview_dest,
        tfi.path AS thumb_dest
      FROM photos p
      JOIN photo_files ofi ON ofi.photo_id = p.id AND ofi.kind = 'original_jpg'
      JOIN photo_files pfi ON pfi.photo_id = p.id AND pfi.kind = 'preview_800'
      JOIN photo_files tfi ON tfi.photo_id = p.id AND tfi.kind = 'thumb'
      WHERE p.album_id = :aid
      ORDER BY p.sort_order ASC, p.id ASC
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['aid' => $albumId]);
    $rows = $stmt->fetchAll() ?: [];

    $items = [];

    $upd = db()->prepare(
      "UPDATE photo_files
       SET file_size_bytes = :sz,
           file_mtime = :mt
       WHERE photo_id = :pid AND kind = 'original_jpg'"
    );

    foreach ($rows as $r) {
      $pid = (int)$r['photo_id'];
      $src = (string)$r['src'];
      $preview = (string)$r['preview_dest'];
      $thumb = (string)$r['thumb_dest'];

      if ($src === '' || !is_file($src)) {
        // brak oryginału: pomijamy, ale nie wywalamy całego joba
        continue;
      }

      $fsz = @filesize($src);
      $fmt = @filemtime($src);
      if ($fsz === false || $fmt === false) {
        continue;
      }

      $dbSize = $r['db_size'] !== null ? (int)$r['db_size'] : null;
      // db_mtime przychodzi jako string DATETIME albo null
      $dbMtimeStr = $r['db_mtime'] !== null ? (string)$r['db_mtime'] : null;
      $dbMtime = null;
      if ($dbMtimeStr) {
        $ts = strtotime($dbMtimeStr . ' UTC');
        if ($ts !== false) $dbMtime = $ts;
      }

      $needs = false;
      if ($dbSize === null || $dbMtime === null) {
        $needs = true;
      } else {
        if ($dbSize !== (int)$fsz) $needs = true;
        // Zaokrąglamy do sekund (DATETIME)
        if ((int)$dbMtime !== (int)$fmt) $needs = true;
      }

      // aktualizujemy metadane oryginału (zawsze — nawet jeśli nie ma zmian, bo po ingest mogło być NULL)
      $upd->execute([
        'sz' => (int)$fsz,
        'mt' => gmdate('Y-m-d H:i:s', (int)$fmt),
        'pid' => $pid,
      ]);

      if ($needs) {
        $items[] = [
          'photo_id' => $pid,
          'src' => $src,
          'preview_dest' => $preview,
          'thumb_dest' => $thumb,
        ];
      }
    }

    return ['items' => $items];
  }
}
