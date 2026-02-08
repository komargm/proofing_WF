<?php
declare(strict_types=1);

final class AlbumRepository {

  /**
   * Usuwa album (DB + pliki proofingu).
   *
   * - Z bazy: kasuje rekord z albums; reszta znika przez ON DELETE CASCADE
   *   (photos, photo_files, photo_comments, user_album_access, album_sections).
   * - Z dysku: usuwa TYLKO preview/thumb (ścieżki zapisane w photo_files)
   *   oraz katalog albumu w path_proofing.
   *
   * UWAGA: oryginały (kind=original_jpg) zwykle są na NAS i bywają montowane RO,
   * dlatego ich nie dotykamy.
   *
   * @return array{deleted_files:int, deleted_dirs:int}
   */
  public function deleteAlbum(int $albumId, string $pathProofingRoot): array {
    $albumId = (int)$albumId;
    if ($albumId <= 0) {
      throw new RuntimeException('Nieprawidłowe ID albumu.');
    }

    $pathProofingRoot = rtrim((string)$pathProofingRoot, '/');
    $rootReal = realpath($pathProofingRoot);

    // 1) Zbierz ścieżki wygenerowanych plików (preview/thumb)
    $sql = "
      SELECT DISTINCT f.path
      FROM photos p
      JOIN photo_files f ON f.photo_id = p.id
      WHERE p.album_id = :aid
        AND f.kind IN ('preview_800','thumb')
    ";
    $st = db()->prepare($sql);
    $st->execute(['aid' => $albumId]);
    $paths = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $deletedFiles = 0;
    if ($rootReal) {
      $rootReal = rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
      foreach ($paths as $p) {
        $p = (string)$p;
        if ($p === '') continue;

        // bezpieczeństwo: kasujemy tylko pliki w obrębie path_proofing
        $real = @realpath($p);
        if (!$real) continue; // brak na dysku -> pomiń
        if (!str_starts_with($real, $rootReal)) continue;
        if (is_file($real) && @unlink($real)) {
          $deletedFiles++;
        }
      }
    }

    // 2) Spróbuj usunąć folder albumu (np. .../album_123)
    $deletedDirs = 0;
    if ($rootReal) {
      $albumDir = $pathProofingRoot . "/album_{$albumId}";
      $albumReal = @realpath($albumDir);
      if ($albumReal && str_starts_with($albumReal . DIRECTORY_SEPARATOR, $rootReal)) {
        $deletedDirs = $this->rrmdir($albumReal);
      }
    }

    // 3) DB: usuń album (CASCADE posprząta resztę)
    $pdo = db();
    $pdo->beginTransaction();
    try {
      $stmt = $pdo->prepare('DELETE FROM albums WHERE id = :id');
      $stmt->execute(['id' => $albumId]);
      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }

    return ['deleted_files' => $deletedFiles, 'deleted_dirs' => $deletedDirs];
  }

  /**
   * Rekurencyjne usuwanie katalogu. Zwraca liczbę usuniętych katalogów.
   */
  private function rrmdir(string $dir): int {
    $count = 0;
    if (!is_dir($dir)) return 0;

    $it = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $fileInfo) {
      /** @var SplFileInfo $fileInfo */
      $path = $fileInfo->getPathname();
      if ($fileInfo->isDir()) {
        @rmdir($path);
        $count++;
      } else {
        @unlink($path);
      }
    }
    @rmdir($dir);
    $count++;
    return $count;
  }

  /** @return array<int, array<string, mixed>> */
  public function listForUser(int $userId): array {
    $sql = "SELECT a.id, a.title, a.created_at
            FROM user_album_access uaa
            JOIN albums a ON a.id = uaa.album_id
            WHERE uaa.user_id = :uid
              AND a.is_visible = 1
            ORDER BY a.created_at DESC, a.id DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll() ?: [];
  }

  public function findForUser(int $userId, int $albumId): ?array {
    $sql = "SELECT a.id, a.title, a.created_at,
                   au.first_name AS photographer_first_name
            FROM user_album_access uaa
            JOIN albums a ON a.id = uaa.album_id
            LEFT JOIN users au ON au.id = a.created_by
            WHERE uaa.user_id = :uid AND uaa.album_id = :aid
              AND a.is_visible = 1
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
    $sql = "SELECT id, code, title, is_visible, is_archived, created_at
            FROM albums
            ORDER BY created_at DESC, id DESC";
    $stmt = db()->query($sql);
    return $stmt->fetchAll() ?: [];
  }

  public function findById(int $albumId): ?array {
    $sql = "SELECT id, code, title, album_comment, is_visible, is_archived, created_at
            FROM albums
            WHERE id = :id
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $albumId]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  /**
   * Zwraca absolutną ścieżkę pierwszego oryginału (photo_files.kind='original_jpg') w albumie.
   * Przydaje się jako "domyślny folder" do funkcji Pick from NAS.
   */
  public function firstOriginalPathForAlbum(int $albumId): ?string {
    $sql = "
      SELECT f.path
      FROM photos p
      JOIN photo_files f ON f.photo_id = p.id AND f.kind = 'original_jpg'
      WHERE p.album_id = :aid
      ORDER BY p.sort_order ASC, p.id ASC
      LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['aid' => $albumId]);
    $row = $stmt->fetch();
    return $row['path'] ?? null;
  }

  /**
   * Dodaje pojedyncze zdjęcie do istniejącego albumu i zwraca item do manifestu Pythona.
   * Tworzy: photos + photo_files (original_jpg / preview_800 / thumb)
   * Dodatkowo zapisuje metadane oryginału (file_size_bytes, file_mtime).
   *
   * @return array{photo_id:int, src:string, preview_dest:string, thumb_dest:string}
   */
  public function addSinglePhotoToAlbumPlan(int $albumId, string $srcAbsPath, string $pathProofingRoot): array {
    $srcAbsPath = trim($srcAbsPath);
    if ($srcAbsPath === '' || !is_file($srcAbsPath)) {
      throw new RuntimeException('Plik źródłowy nie istnieje.');
    }

    $ext = strtolower(pathinfo($srcAbsPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg'], true)) {
      throw new RuntimeException('Dozwolone są tylko pliki JPG/JPEG.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
      // duplikat?
      $stmt = $pdo->prepare(
        "SELECT 1
         FROM photos p
         JOIN photo_files f ON f.photo_id = p.id AND f.kind = 'original_jpg'
         WHERE p.album_id = :aid AND f.path = :p
         LIMIT 1"
      );
      $stmt->execute(['aid' => $albumId, 'p' => $srcAbsPath]);
      if ($stmt->fetch()) {
        throw new RuntimeException('To zdjęcie jest już dodane do albumu.');
      }

      // następny sort_order
      $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) AS m FROM photos WHERE album_id = :aid");
      $stmt->execute(['aid' => $albumId]);
      $m = (int)($stmt->fetch()['m'] ?? 0);
      $nextSort = $m + 1;

      // katalog docelowy w proofing
      $albumDir = rtrim($pathProofingRoot, '/')."/album_{$albumId}";
      $previewDir = $albumDir . '/previews';
      $thumbDir   = $albumDir . '/thumbs';
      if (!is_dir($previewDir) && !@mkdir($previewDir, 0777, true) && !is_dir($previewDir)) {
        throw new RuntimeException('Nie mogę utworzyć katalogu: ' . $previewDir);
      }
      if (!is_dir($thumbDir) && !@mkdir($thumbDir, 0777, true) && !is_dir($thumbDir)) {
        throw new RuntimeException('Nie mogę utworzyć katalogu: ' . $thumbDir);
      }

      // photos
      $stmt = $pdo->prepare(
        "INSERT INTO photos (album_id, sort_order, is_visible) VALUES (:aid, :sort, 1)"
      );
      $stmt->execute(['aid' => $albumId, 'sort' => $nextSort]);
      $photoId = (int)$pdo->lastInsertId();

      // photo_files
      $previewDest = $previewDir . "/p_{$photoId}.jpg";
      $thumbDest   = $thumbDir   . "/t_{$photoId}.jpg";

      $stmt = $pdo->prepare(
        "INSERT INTO photo_files (photo_id, kind, path, file_size_bytes, file_mtime)
         VALUES (:pid, :kind, :path, :sz, :mt)"
      );

      $fsz = @filesize($srcAbsPath);
      $fmt = @filemtime($srcAbsPath);
      $sz = $fsz === false ? null : (int)$fsz;
      $mt = $fmt === false ? null : gmdate('Y-m-d H:i:s', (int)$fmt);

      // oryginał (z metadanymi)
      $stmt->execute([
        'pid' => $photoId,
        'kind' => 'original_jpg',
        'path' => $srcAbsPath,
        'sz' => $sz,
        'mt' => $mt,
      ]);

      // preview/thumb bez metadanych
      $stmt->execute([
        'pid' => $photoId,
        'kind' => 'preview_800',
        'path' => $previewDest,
        'sz' => null,
        'mt' => null,
      ]);
      $stmt->execute([
        'pid' => $photoId,
        'kind' => 'thumb',
        'path' => $thumbDest,
        'sz' => null,
        'mt' => null,
      ]);

      $pdo->commit();
      return [
        'photo_id' => $photoId,
        'src' => $srcAbsPath,
        'preview_dest' => $previewDest,
        'thumb_dest' => $thumbDest,
      ];
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

  public function updateSettings(int $albumId, string $title, ?string $albumComment, bool $isVisible = true): void {
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
                album_comment = :c,
                is_visible = :v
            WHERE id = :id";
    $stmt = db()->prepare($sql);
    $stmt->bindValue('t', $title, PDO::PARAM_STR);
    $stmt->bindValue('c', $albumComment, PDO::PARAM_STR);
    $stmt->bindValue('v', $isVisible ? 1 : 0, PDO::PARAM_INT);
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
  
  /**
   * Buduje plan rescanu tylko dla jednego zdjęcia.
   * $force = true -> zawsze regeneruje preview/thumb (przydatne po retuszu / zmianie watermarka),
   * ale i tak aktualizuje w DB metadane oryginału (size/mtime).
   *
   * @return ?array{album_id:int, item:array{photo_id:int,src:string,preview_dest:string,thumb_dest:string}}
   */
  public function buildRescanPlanForPhoto(int $photoId, bool $force = true): ?array {
    $sql = "
      SELECT
        p.id AS photo_id,
        p.album_id AS album_id,
        ofi.path AS src,
        ofi.file_size_bytes AS db_size,
        ofi.file_mtime AS db_mtime,
        pfi.path AS preview_dest,
        tfi.path AS thumb_dest
      FROM photos p
      JOIN photo_files ofi ON ofi.photo_id = p.id AND ofi.kind = 'original_jpg'
      JOIN photo_files pfi ON pfi.photo_id = p.id AND pfi.kind = 'preview_800'
      JOIN photo_files tfi ON tfi.photo_id = p.id AND tfi.kind = 'thumb'
      WHERE p.id = :pid
      LIMIT 1
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute(['pid' => $photoId]);
    $r = $stmt->fetch();
    if (!$r) return null;

    $pid = (int)$r['photo_id'];
    $albumId = (int)$r['album_id'];
    $src = (string)$r['src'];
    $preview = (string)$r['preview_dest'];
    $thumb = (string)$r['thumb_dest'];

    if ($src === '' || !is_file($src)) {
      return null;
    }

    $fsz = @filesize($src);
    $fmt = @filemtime($src);
    if ($fsz === false || $fmt === false) {
      return null;
    }

    $dbSize = $r['db_size'] !== null ? (int)$r['db_size'] : null;
    $dbMtimeStr = $r['db_mtime'] !== null ? (string)$r['db_mtime'] : null;
    $dbMtime = null;
    if ($dbMtimeStr) {
      $ts = strtotime($dbMtimeStr . ' UTC');
      if ($ts !== false) $dbMtime = $ts;
    }

    $needs = false;
    if ($force) {
      $needs = true;
    } else {
      if ($dbSize === null || $dbMtime === null) {
        $needs = true;
      } else {
        if ($dbSize !== (int)$fsz) $needs = true;
        if ((int)$dbMtime !== (int)$fmt) $needs = true;
      }
    }

    // aktualizujemy metadane oryginału (zawsze)
    $upd = db()->prepare(
      "UPDATE photo_files
       SET file_size_bytes = :sz,
           file_mtime = :mt
       WHERE photo_id = :pid AND kind = 'original_jpg'"
    );
    $upd->execute([
      'sz' => (int)$fsz,
      'mt' => gmdate('Y-m-d H:i:s', (int)$fmt),
      'pid' => $pid,
    ]);

    if (!$needs) {
      // w praktyce może nie być użyte, ale zostawiamy możliwość trybu "smart"
      return ['album_id' => $albumId, 'item' => [
        'photo_id' => $pid,
        'src' => $src,
        'preview_dest' => $preview,
        'thumb_dest' => $thumb,
      ]];
    }

    return ['album_id' => $albumId, 'item' => [
      'photo_id' => $pid,
      'src' => $src,
      'preview_dest' => $preview,
      'thumb_dest' => $thumb,
    ]];
  }

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
