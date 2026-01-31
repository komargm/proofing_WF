<?php
declare(strict_types=1);

final class AdminAlbumsController {
  public function __construct(
    private AlbumRepository $albums,
    private PhotoRepository $photos,
  ) {}

  public function index(): void {
    $rows = $this->albums->listAll();
    Response::html(View::page('admin/albums/index', [
      'albums' => $rows,
    ]));
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

    $this->albums->updateSettings($id, $title, $comment);
    Response::redirect('/admin/albums');
  }

  public function photos(array $params): void {
    $id = isset($params['id']) ? (int)$params['id'] : 0;
    if ($id <= 0) Response::html('Bad Request', 400);

    $album = $this->albums->findById($id);
    if (!$album) Response::html('Not Found', 404);

    $photos = $this->photos->listForAdminAlbum($id);
    Response::html(View::page('admin/albums/photos', [
      'album' => $album,
      'photos' => $photos,
    ]));
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

    // pozwalamy na długi request, ale bez blokowania PHP
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

      usleep(250000); // 250ms
    }
  }
}
