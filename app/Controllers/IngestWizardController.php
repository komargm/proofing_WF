<?php
declare(strict_types=1);

final class IngestWizardController {
  public function __construct(
    private IngestRepository $ingest,
  ) {}

  // --- STEP 1: dane albumu ---
  public function step1(): void {
    $clients = $this->ingest->listClientUsers();
    $state = $this->state();

    Response::html(View::page('admin/ingest/step1', [
      'clients' => $clients,
      'state' => $state,
      'csrf' => Csrf::token(),
    ]));
  }

  public function step1Post(): void {
    Csrf::validate();

    $title = trim((string)($_POST['title'] ?? ''));
    $albumComment = trim((string)($_POST['album_comment'] ?? ''));
    $clientId = (int)($_POST['client_user_id'] ?? 0);

    if ($clientId <= 0 || $albumComment === '') {
      $_SESSION['flash_error'] = 'Uzupełnij wymagane pola.';
      Response::redirect('/admin/albums/create');
    }

    $this->state([
      'title' => $title,
      'album_comment' => $albumComment,
      'client_user_id' => $clientId,
    ]);

    Response::redirect('/admin/albums/create/source');
  }

  // --- STEP 2: wybór folderu ---
  public function step2(): void {
    $config = require __DIR__ . '/../../config/config.php';
    $root = (string)($config['app']['path_originals'] ?? '/var/www/photos/originals');
    $state = $this->state();

    Response::html(View::page('admin/ingest/step2', [
      'root' => $root,
      'state' => $state,
      'csrf' => Csrf::token(),
    ]));
  }

  public function listDirs(): void {
    $config = require __DIR__ . '/../../config/config.php';
    $root = (string)($config['app']['path_originals'] ?? '/var/www/photos/originals');
    $rel = (string)($_GET['path'] ?? '');
    $rel = ltrim($rel, '/');

    $abs = $this->safeJoin($root, $rel);
    if (!$abs || !is_dir($abs)) {
      Response::json(['ok' => false, 'error' => 'Folder nie istnieje']);
    }

    $items = [];
    foreach (new DirectoryIterator($abs) as $it) {
      if ($it->isDot()) continue;
      if (!$it->isDir()) continue;
      $name = $it->getFilename();
      if (str_starts_with($name, '.')) continue;
      $items[] = $name;
    }
    sort($items);

    Response::json(['ok' => true, 'path' => $rel, 'dirs' => $items]);
  }

  public function step2Post(): void {
    Csrf::validate();
    $folder = (string)($_POST['folder'] ?? '');
    $folder = ltrim(trim($folder), '/');
    if ($folder === '') {
      $_SESSION['flash_error'] = 'Wybierz folder.';
      Response::redirect('/admin/albums/create/source');
    }
    $this->state(['folder' => $folder]);
    Response::redirect('/admin/albums/create/select');
  }

  // --- STEP 3: selekcja jpg ---
  public function step3(): void {
    $state = $this->state();
    if (empty($state['folder'])) {
      Response::redirect('/admin/albums/create/source');
    }
    Response::html(View::page('admin/ingest/step3', [
      'state' => $state,
      'csrf' => Csrf::token(),
    ]));
  }

  public function listJpgs(): void {
    $config = require __DIR__ . '/../../config/config.php';
    $root = (string)($config['app']['path_originals'] ?? '/var/www/photos/originals');
    $folder = (string)($_GET['folder'] ?? '');
    $folder = ltrim($folder, '/');

    $abs = $this->safeJoin($root, $folder);
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
    Response::json(['ok' => true, 'folder' => $folder, 'files' => $files]);
  }

  // --- STEP 4: uruchomienie procesu ---
  public function finalize(): void {
    Csrf::validate();
    $state = $this->state();
    if (empty($state['folder']) || empty($state['client_user_id']) || empty($state['album_comment'])) {
      Response::redirect('/admin/albums/create');
    }

    $watermark = ((string)($_POST['watermark'] ?? '1')) === '1';
    $deleteUnselected = ((string)($_POST['delete_unselected'] ?? '0')) === '1'; // na teraz niewykorzystane

    $selected = $_POST['selected'] ?? [];
    if (!is_array($selected) || count($selected) === 0) {
      $_SESSION['flash_error'] = 'Zaznacz przynajmniej jedno zdjęcie.';
      Response::redirect('/admin/albums/create/select');
    }

    $selected = array_values(array_filter(array_map('strval', $selected), fn($x) => $x !== ''));

    $config = require __DIR__ . '/../../config/config.php';
    $origRoot = (string)($config['app']['path_originals'] ?? '/var/www/photos/originals');
    $proofRoot = (string)($config['app']['path_proofing'] ?? '/var/www/photos/previews');

    $createdBy = (int)($_SESSION['user_id'] ?? 0);
    $clientId = (int)$state['client_user_id'];
    $title = (string)($state['title'] ?? '');
    $comment = (string)$state['album_comment'];
    $folder = (string)$state['folder'];

    // DB: album + plan
    $plan = $this->ingest->createAlbumAndPlan(
      $createdBy,
      $clientId,
      $title,
      $comment,
      $folder,
      $selected,
      $origRoot,
      $proofRoot,
    );

    // Manifest dla Pythona
    $jobId = bin2hex(random_bytes(8));
    $manifestPath = "/tmp/wf_ingest_{$jobId}.json";
    $logPath = "/tmp/wf_ingest_{$jobId}.log";

    $manifest = [
      'job_id' => $jobId,
      'album_id' => $plan['album_id'],
      'album_code' => $plan['album_code'],
      'watermark' => $watermark,
      'delete_unselected' => $deleteUnselected,
      'items' => $plan['items'],
    ];
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($logPath, "[WF] Start ingest job {$jobId}\n");

    $py = '/usr/bin/python3';
    $script = '/var/www/html/app/Ingest/ingest.py';
    $cmd = escapeshellcmd($py) . ' ' . escapeshellarg($script)
      . ' --manifest ' . escapeshellarg($manifestPath)
      . ' --log ' . escapeshellarg($logPath)
      . ' >> ' . escapeshellarg($logPath) . ' 2>&1 &';

    // best-effort (w kontenerze PHP-FPM)
    @shell_exec($cmd);

    // czyścimy stan kreatora
    unset($_SESSION['ingest_wizard']);

    Response::redirect('/admin/albums/create/run/' . $jobId);
  }

  public function runPage(array $params): void {
    $jobId = (string)($params['job'] ?? '');
    if ($jobId === '') Response::html('Bad Request', 400);
    Response::html(View::page('admin/ingest/run', [
      'job_id' => $jobId,
    ]));
  }

  /** SSE: logi na żywo */
  public function stream(array $params): void {
    $jobId = (string)($params['job'] ?? '');
    if ($jobId === '') Response::html('Bad Request', 400);

    $logPath = "/tmp/wf_ingest_{$jobId}.log";
    if (!is_file($logPath)) {
      Response::html('Not Found', 404);
    }

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    $fp = fopen($logPath, 'rb');
    if ($fp === false) {
      Response::html('Not Found', 404);
    }

    $pos = 0;
    while (!connection_aborted()) {
      clearstatcache(true, $logPath);
      $size = filesize($logPath);
      if ($size !== false && $size > $pos) {
        fseek($fp, $pos);
        while (($line = fgets($fp)) !== false) {
          $line = rtrim($line, "\r\n");
          echo 'data: ' . str_replace(["\r", "\n"], ' ', $line) . "\n\n";
          @ob_flush();
          @flush();
        }
        $pos = ftell($fp);
      }
      // ping co ~1s
      echo ": ping\n\n";
      @ob_flush();
      @flush();
      usleep(900000);
    }
    fclose($fp);
    exit;
  }

  // --- helpers ---
  /**
   * Stan kreatora w sesji.
   * Jeśli $merge != null, to merge i zapis.
   * @param array<string, mixed>|null $merge
   * @return array<string, mixed>
   */
  private function state(?array $merge = null): array {
    if (!isset($_SESSION['ingest_wizard']) || !is_array($_SESSION['ingest_wizard'])) {
      $_SESSION['ingest_wizard'] = [];
    }
    if (is_array($merge)) {
      $_SESSION['ingest_wizard'] = array_merge($_SESSION['ingest_wizard'], $merge);
    }
    return $_SESSION['ingest_wizard'];
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
