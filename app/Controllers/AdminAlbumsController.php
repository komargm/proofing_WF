<?php
declare(strict_types=1);

final class AdminAlbumsController {
  public function __construct(
    private AlbumRepository $albums,
    private AlbumSectionRepository $sections,
    private PhotoRepository $photos,
  ) {}

  public function index(): void {
    $rows = $this->albums->listAll();
    Response::html(View::page('admin/albums/index', [
      'albums' => $rows,
      'csrf' => Csrf::token(),
    ]));
  }

  public function delete(array $params): void {
    $id = isset($params['id']) ? (int)$params['id'] : 0;
    if ($id <= 0) Response::html('Bad Request', 400);

    Csrf::verifyOrFail($_POST['csrf'] ?? null);

    $album = $this->albums->findById($id);
    if (!$album) Response::html('Not Found', 404);

    $config = require __DIR__ . '/../../config/config.php';
    $proofRoot = (string)($config['app']['path_proofing'] ?? '/var/www/photos/previews');

    try {
      $res = $this->albums->deleteAlbum($id, $proofRoot);

      // Audit
      try {
        $stmt = db()->prepare(
          "INSERT INTO audit_log (user_id, album_id, event, meta_json, ip)
           VALUES (:uid, :aid, 'album.deleted', :meta, :ip)"
        );
        $meta = json_encode([
          'album_code' => (string)($album['code'] ?? ''),
          'deleted_files' => (int)($res['deleted_files'] ?? 0),
          'deleted_dirs' => (int)($res['deleted_dirs'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);
        $stmt->execute([
          'uid' => (int)($_SESSION['user_id'] ?? 0) ?: null,
          'aid' => $id,
          'meta' => $meta,
          'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
      } catch (Throwable $e) {
        // nie blokuj
      }

      $_SESSION['flash_success'] = 'Album usunięty (DB + pliki preview/thumb).';
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = 'Nie udało się usunąć albumu: ' . $e->getMessage();
    }

    Response::redirect('/admin/albums');
  }

  public function edit(array $params): void {
    $id = isset($params['id']) ? (int)$params['id'] : 0;
    if ($id <= 0) Response::html('Bad Request', 400);

    $album = $this->albums->findById($id);
    if (!$album) Response::html('Not Found', 404);

    Response::html(View::page('admin/albums/edit', [
      'album' => $album,
    ]));
  }

  public function update(array $params): void {
    $id = isset($params['id']) ? (int)$params['id'] : 0;
    if ($id <= 0) Response::html('Bad Request', 400);

    Csrf::verifyOrFail($_POST['csrf'] ?? null);

    $title = (string)($_POST['title'] ?? '');
    $comment = isset($_POST['album_comment']) ? (string)$_POST['album_comment'] : null;

    // Awaryjna kontrolka: czy album widoczny dla klienta (domyślnie TAK)
    $isVisible = (($_POST['is_visible'] ?? '1') === '1');

    $this->albums->updateSettings($id, $title, $comment, $isVisible);
    Response::redirect('/admin/albums');
  }

  public function photos(array $params): void {
    $id = isset($params['id']) ? (int)$params['id'] : 0;
    if ($id <= 0) Response::html('Bad Request', 400);

    $album = $this->albums->findById($id);
    if (!$album) Response::html('Not Found', 404);

    $sectionId = isset($_GET['section']) ? (int)$_GET['section'] : null;
    if ($sectionId !== null && $sectionId <= 0) $sectionId = null;

    // Filtry (grid albumu): serduszko klienta + ocena klienta
    $selectedOnly = null;
    if (isset($_GET['selected'])) {
      $selectedOnly = ((string)$_GET['selected'] === '1');
    }

    $ratingFilter = null; // null | int(1..6) | 'none'
    if (isset($_GET['rating'])) {
      $raw = (string)$_GET['rating'];
      if ($raw === 'none' || $raw === '0') {
        $ratingFilter = 'none';
      } else {
        $v = (int)$raw;
        if ($v >= 1 && $v <= 6) {
          $ratingFilter = $v;
        }
      }
    }

    $sections = $this->sections->listForAlbum($id);
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $photos = $this->photos->listForAdminAlbum($adminId, $id, $sectionId, $selectedOnly, $ratingFilter);
    Response::html(View::page('admin/albums/photos', [
      'album' => $album,
      'sections' => $sections,
      'section_id' => $sectionId,
      'filter_selected' => $selectedOnly,
      'filter_rating' => $ratingFilter,
      'photos' => $photos,
    ]));
  }

  // =========================================================
  // Sections ("sub-albumy")
  // =========================================================

  public function sectionsPage(array $params): void {
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($albumId <= 0) Response::html('Bad Request', 400);

    $album = $this->albums->findById($albumId);
    if (!$album) Response::html('Not Found', 404);

    $sections = $this->sections->listForAlbum($albumId);

    Response::html(View::page('admin/albums/sections', [
      'album' => $album,
      'sections' => $sections,
      'csrf' => Csrf::token(),
    ]));
  }

  public function sectionCreate(array $params): void {
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($albumId <= 0) Response::html('Bad Request', 400);
    Csrf::verifyOrFail($_POST['csrf'] ?? null);

    $title = (string)($_POST['title'] ?? '');
    try {
      $this->sections->create($albumId, $title);
      $_SESSION['flash_success'] = 'Dodano sekcję.';
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = 'Nie udało się dodać sekcji: ' . $e->getMessage();
    }

    Response::redirect("/admin/album/{$albumId}/sections");
  }

  public function sectionRename(array $params): void {
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    $sectionId = isset($params['sid']) ? (int)$params['sid'] : 0;
    if ($albumId <= 0 || $sectionId <= 0) Response::html('Bad Request', 400);
    Csrf::verifyOrFail($_POST['csrf'] ?? null);

    $title = (string)($_POST['title'] ?? '');
    $sec = $this->sections->findById($sectionId);
    if (!$sec || (int)$sec['album_id'] !== $albumId) {
      Response::html('Not Found', 404);
    }

    try {
      $this->sections->rename($sectionId, $title);
      $_SESSION['flash_success'] = 'Zmieniono nazwę sekcji.';
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = 'Nie udało się zmienić nazwy: ' . $e->getMessage();
    }
    Response::redirect("/admin/album/{$albumId}/sections");
  }

  public function sectionDelete(array $params): void {
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    $sectionId = isset($params['sid']) ? (int)$params['sid'] : 0;
    if ($albumId <= 0 || $sectionId <= 0) Response::html('Bad Request', 400);
    Csrf::verifyOrFail($_POST['csrf'] ?? null);

    $sec = $this->sections->findById($sectionId);
    if (!$sec || (int)$sec['album_id'] !== $albumId) {
      Response::html('Not Found', 404);
    }

    // Uwaga: FK w photos ustawia section_id = NULL (ON DELETE SET NULL)
    $this->sections->delete($sectionId);
    $_SESSION['flash_success'] = 'Usunięto sekcję.';
    Response::redirect("/admin/album/{$albumId}/sections");
  }

  /**
   * POST: uruchamia rescan albumu (asynchronicznie), generuje logi + SSE.
   */
  public function rescanStart(array $params): void {
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($albumId <= 0) Response::html('Bad Request', 400);

    Csrf::verifyOrFail($_POST['csrf'] ?? null);

    $album = $this->albums->findById($albumId);
    if (!$album) Response::html('Not Found', 404);

    $watermark = ((string)($_POST['watermark'] ?? '1')) === '1';

    // Plan: tylko te zdjęcia, których oryginał (mtime/size) różni się od DB
    $plan = $this->albums->buildRescanPlan($albumId);

    $jobId = bin2hex(random_bytes(8));
    $manifestPath = "/tmp/wf_rescan_{$jobId}.json";
    $logPath      = "/tmp/wf_rescan_{$jobId}.log";

    $manifest = [
      'job_id' => $jobId,
      'album_id' => $albumId,
      'album_code' => (string)($album['code'] ?? ''),
      'watermark' => $watermark,
      'items' => $plan['items'],
    ];
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $count = count($plan['items']);
    file_put_contents($logPath, "[WF] RESCAN start job {$jobId} album_id={$albumId} items={$count}\n");
    if ($count === 0) {
      file_put_contents($logPath, "[WF] DONE (nothing to do)\n", FILE_APPEND);
      Response::redirect("/admin/album/{$albumId}/rescan/run/{$jobId}");
    }

    $py = '/usr/bin/python3';
    $script = '/var/www/html/app/Ingest/rescan_album.py';
    $cmd = escapeshellcmd($py) . ' ' . escapeshellarg($script)
      . ' --manifest ' . escapeshellarg($manifestPath)
      . ' --log ' . escapeshellarg($logPath)
      . ' >> ' . escapeshellarg($logPath) . ' 2>&1 &';

    @shell_exec($cmd);

    Response::redirect("/admin/album/{$albumId}/rescan/run/{$jobId}");
  }

  public function rescanRunPage(array $params): void {
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    $jobId = (string)($params['job'] ?? '');
    if ($albumId <= 0 || $jobId === '') Response::html('Bad Request', 400);

    Response::html(View::page('admin/albums/rescan_run', [
      'album_id' => $albumId,
      'job_id' => $jobId,
    ]));
  }

  /** SSE: logi rescanu na żywo */
  public function rescanStream(array $params): void {
    // 🔴 KLUCZOWE: uwalniamy lock sesji, inaczej cały portal wisi
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }

    // SSE potrafi trwać długo: nie blokuj PHP, ale kończ gdy klient zniknie
    set_time_limit(0);
    ignore_user_abort(true);

    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    $jobId = (string)($params['job'] ?? '');
    if ($albumId <= 0 || $jobId === '') Response::html('Bad Request', 400);

    $logPath = "/tmp/wf_rescan_{$jobId}.log";
    if (!is_file($logPath)) {
      Response::html('Not Found', 404);
    }

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    // --- SSE: wymuś real-time flush (ANTI 504) ---
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');

    // zamknij wszystkie bufory PHP
    while (ob_get_level() > 0) {
      @ob_end_flush();
    }
    @ob_implicit_flush(true);

    // upewnij się, że proxy nie kompresuje
    header('Content-Encoding: none');

    // NATYCHMIASTOWY sygnał do proxy (bardzo ważne)
    echo ": hello\n\n";
    @flush();

    $pos = 0;
    $done = false;
    $lastPing = microtime(true);

    // Pętla: czytamy dopisywany log, wysyłamy linie jako SSE, kończymy na DONE / rozłączeniu
    while (!connection_aborted()) {
      clearstatcache(true, $logPath);
      $size = filesize($logPath);
      if ($size === false) break;

      if ($size > $pos) {
        $fh = fopen($logPath, 'r');
        if ($fh) {
          fseek($fh, $pos);
          while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            echo "data: " . str_replace(["\r","\n"], '', $line) . "\n\n";

            // zakończ gdy job zakończony
            if (strpos($line, '[WF] DONE') !== false) {
              $done = true;
            }

            @ob_flush();
            @flush();
          }
          $pos = ftell($fh) ?: $pos;
          fclose($fh);
        }
      }

      // ping co ~1s, żeby proxy/przeglądarka nie zrywały ciszy
      if ((microtime(true) - $lastPing) >= 1.0) {
        echo ": ping\n\n";
        @ob_flush();
        @flush();
        $lastPing = microtime(true);
      }

      if ($done) break;

      usleep(250000); // 250ms
    }

    exit;
  }

  // =========================================================
  // Add single photo to existing album (Pick from NAS)
  // =========================================================

  public function addPhotoPage(array $params): void {
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($albumId <= 0) Response::html('Bad Request', 400);

    $album = $this->albums->findById($albumId);
    if (!$album) Response::html('Not Found', 404);

    $config = require __DIR__ . '/../../config/config.php';
    $root = (string)($config['app']['path_originals'] ?? '/var/www/photos/originals');

    $suggestRel = '';
    try {
      $first = $this->albums->firstOriginalPathForAlbum($albumId);
      if ($first) {
        $rootReal = realpath($root);
        $firstReal = realpath($first);
        if ($rootReal && $firstReal && str_starts_with($firstReal, $rootReal . DIRECTORY_SEPARATOR)) {
          $suggestRel = ltrim(substr($firstReal, strlen($rootReal)), DIRECTORY_SEPARATOR);
          $suggestRel = ltrim(dirname($suggestRel), '/');
        }
      }
    } catch (Throwable $e) {
      $suggestRel = '';
    }

    Response::html(View::page('admin/albums/add_photo', [
      'album' => $album,
      'root' => $root,
      'suggest_folder' => $suggestRel,
      'csrf' => Csrf::token(),
    ]));
  }

  /** JSON: lista JPG w katalogu wskazanym relatywnie do PATH_ORIGINALS */
  public function addPhotoList(array $params): void {
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($albumId <= 0) Response::json(['ok' => false, 'error' => 'Bad Request'], 400);

    // album musi istnieć
    $album = $this->albums->findById($albumId);
    if (!$album) Response::json(['ok' => false, 'error' => 'Not Found'], 404);

    $config = require __DIR__ . '/../../config/config.php';
    $root = (string)($config['app']['path_originals'] ?? '/var/www/photos/originals');
    $rel = (string)($_GET['path'] ?? '');
    $rel = ltrim($rel, '/');

    $abs = $this->safeJoin($root, $rel);
    if (!$abs || !is_dir($abs)) {
      Response::json(['ok' => false, 'error' => 'Folder nie istnieje']);
    }

    $files = [];
    foreach (new DirectoryIterator($abs) as $it) {
      if ($it->isDot()) continue;
      if (!$it->isFile()) continue;
      $name = $it->getFilename();
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg'], true)) continue;
      $files[] = $name;
    }
    sort($files);
    Response::json(['ok' => true, 'path' => $rel, 'files' => $files]);
  }

  /** POST: tworzy rekordy w DB + startuje ingest.py dla 1 zdjęcia */
  public function addPhotoStart(array $params): void {
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($albumId <= 0) Response::html('Bad Request', 400);

    Csrf::verifyOrFail($_POST['csrf'] ?? null);

    $album = $this->albums->findById($albumId);
    if (!$album) Response::html('Not Found', 404);

    $config = require __DIR__ . '/../../config/config.php';
    $origRoot = (string)($config['app']['path_originals'] ?? '/var/www/photos/originals');
    $proofRoot = (string)($config['app']['path_proofing'] ?? '/var/www/photos/previews');

    $folder = (string)($_POST['folder'] ?? '');
    $folder = ltrim(trim($folder), '/');
    $filename = (string)($_POST['filename'] ?? '');
    $filename = trim($filename);
    if ($filename === '') {
      $_SESSION['flash_error'] = 'Wybierz plik JPG do dodania.';
      Response::redirect("/admin/album/{$albumId}/add-photo");
    }

    // Bezpieczeństwo: tylko basename (1 plik)
    $filename = basename($filename);

    $dirAbs = $this->safeJoin($origRoot, $folder);
    if (!$dirAbs || !is_dir($dirAbs)) {
      $_SESSION['flash_error'] = 'Folder nie istnieje.';
      Response::redirect("/admin/album/{$albumId}/add-photo");
    }

    $fileCandidate = $dirAbs . DIRECTORY_SEPARATOR . $filename;
    $fileAbs = realpath($fileCandidate);
    $rootReal = realpath($origRoot);
    if (!$fileAbs || !$rootReal || !str_starts_with($fileAbs, $rootReal . DIRECTORY_SEPARATOR)) {
      $_SESSION['flash_error'] = 'Nieprawidłowa ścieżka pliku.';
      Response::redirect("/admin/album/{$albumId}/add-photo");
    }

    $watermark = ((string)($_POST['watermark'] ?? '1')) === '1';

    try {
      $item = $this->albums->addSinglePhotoToAlbumPlan($albumId, $fileAbs, $proofRoot);
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = $e->getMessage();
      Response::redirect("/admin/album/{$albumId}/add-photo");
    }

    // Audit (opcjonalnie)
    try {
      $stmt = db()->prepare(
        "INSERT INTO audit_log (user_id, album_id, photo_id, event, meta_json, ip)
         VALUES (:uid, :aid, :pid, 'photo.added_to_album', :meta, :ip)"
      );
      $meta = json_encode(['src' => $fileAbs, 'basename' => basename($fileAbs)], JSON_UNESCAPED_UNICODE);
      $stmt->execute([
        'uid' => (int)($_SESSION['user_id'] ?? 0) ?: null,
        'aid' => $albumId,
        'pid' => (int)$item['photo_id'],
        'meta' => $meta,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
      ]);
    } catch (Throwable $e) {
      // nie blokuj
    }

    // Manifest + log + uruchomienie Pythona (reuse ingest.py)
    $jobId = bin2hex(random_bytes(8));
    $manifestPath = "/tmp/wf_addphoto_{$jobId}.json";
    $logPath      = "/tmp/wf_addphoto_{$jobId}.log";

    $manifest = [
      'job_id' => $jobId,
      'album_id' => $albumId,
      'album_code' => (string)($album['code'] ?? ''),
      'watermark' => $watermark,
      'delete_unselected' => false,
      'items' => [$item],
    ];
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($logPath, "[WF] AddPhoto start job {$jobId} album_id={$albumId} photo_id={$item['photo_id']}\n");

    $py = '/usr/bin/python3';
    $script = '/var/www/html/app/Ingest/ingest.py';
    $cmd = escapeshellcmd($py) . ' ' . escapeshellarg($script)
      . ' --manifest ' . escapeshellarg($manifestPath)
      . ' --log ' . escapeshellarg($logPath)
      . ' >> ' . escapeshellarg($logPath) . ' 2>&1 &';
    @shell_exec($cmd);

    Response::redirect("/admin/album/{$albumId}/add-photo/run/{$jobId}");
  }

  public function addPhotoRunPage(array $params): void {
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    $jobId = (string)($params['job'] ?? '');
    if ($albumId <= 0 || $jobId === '') Response::html('Bad Request', 400);

    Response::html(View::page('admin/albums/add_photo_run', [
      'album_id' => $albumId,
      'job_id' => $jobId,
    ]));
  }

  /** SSE: logi AddPhoto na żywo */
  public function addPhotoStream(array $params): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }
    set_time_limit(0);
    ignore_user_abort(true);

    $albumId = isset($params['id']) ? (int)$params['id'] : 0;
    $jobId = (string)($params['job'] ?? '');
    if ($albumId <= 0 || $jobId === '') Response::html('Bad Request', 400);

    $logPath = "/tmp/wf_addphoto_{$jobId}.log";
    if (!is_file($logPath)) {
      Response::html('Not Found', 404);
    }

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    $pos = 0;
    while (true) {
      clearstatcache(true, $logPath);
      $size = filesize($logPath);
      if ($size === false) break;

      if ($size > $pos) {
        $fh = fopen($logPath, 'r');
        if ($fh) {
          fseek($fh, $pos);
          while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            echo "data: " . str_replace(["\r","\n"], '', $line) . "\n\n";
            @ob_flush();
            @flush();
          }
          $pos = ftell($fh) ?: $pos;
          fclose($fh);
        }
      }

      usleep(250000);
    }
  }

  private function safeJoin(string $root, string $rel): ?string {
    $rootReal = realpath($root);
    if (!$rootReal) return null;
    $candidate = $rootReal . DIRECTORY_SEPARATOR . str_replace(['..', "\\"], ['', '/'], $rel);
    $real = realpath($candidate);
    if (!$real) return null;
    if (!str_starts_with($real, $rootReal . DIRECTORY_SEPARATOR) && $real !== $rootReal) return null;
    return $real;
  }
}
