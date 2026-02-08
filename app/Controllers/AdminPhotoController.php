<?php
declare(strict_types=1);

final class AdminPhotoController {
  public function __construct(private PhotoRepository $photos, private AlbumRepository $albums) {}

  public function photo(array $params): void {
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($photoId <= 0) {
      Response::html('Bad Request', 400);
    }

    $sectionId = isset($_GET['section']) ? (int)$_GET['section'] : null;
    if ($sectionId !== null && $sectionId <= 0) $sectionId = null;

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

    $data = $this->photos->viewerForAdmin($photoId, $sectionId, $selectedOnly, $ratingFilter);
    if (!$data) {
      Response::html('Not Found', 404);
    }

    Response::html(View::page('admin/photo', $data));
  }

  public function setDownloadAllowed(array $params): void {
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($photoId <= 0) {
      Response::json(['ok' => false, 'error' => 'Bad Request'], 400);
    }

    $allowed = isset($_POST['allowed']) ? (int)$_POST['allowed'] : 0;
    $this->photos->setDownloadAllowed($photoId, $allowed === 1);

    Response::json(['ok' => true, 'allowed' => ($allowed === 1)]);
  }

  public function setSection(array $params): void {
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($photoId <= 0) {
      Response::json(['ok' => false, 'error' => 'Bad Request'], 400);
    }

    Csrf::validate();

    $payload = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $sid = $payload['section_id'] ?? null;
    if ($sid === '' || $sid === false) $sid = null;
    if ($sid !== null) $sid = (int)$sid;
    if ($sid !== null && $sid <= 0) $sid = null;

    $this->photos->setSection($photoId, $sid);
    Response::json(['ok' => true, 'section_id' => $sid]);
  }

  /**
   * POST: Rescan tylko jednego zdjęcia (wymuszone przeliczenie preview/thumb z watermarkiem).
   * Używa tego samego workera co rescan albumu i tej samej strony logów.
   */
  public function rescanStart(array $params): void {
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($photoId <= 0) {
      Response::html('Bad Request', 400);
    }

    Csrf::verifyOrFail($_POST['csrf'] ?? null);

    $watermark = ((string)($_POST['watermark'] ?? '1')) === '1';

    // Force rescan (nawet jeśli mtime/size się nie zmieniło) – praktyczne po retuszu / zmianie watermarka
    $plan = $this->albums->buildRescanPlanForPhoto($photoId, true);
    if (!$plan) {
      Response::html('Not Found', 404);
    }

    $albumId = (int)$plan['album_id'];
    $album = $this->albums->findById($albumId);
    if (!$album) {
      Response::html('Not Found', 404);
    }

    $jobId = bin2hex(random_bytes(8));
    $manifestPath = "/tmp/wf_rescan_{$jobId}.json";
    $logPath      = "/tmp/wf_rescan_{$jobId}.log";

    $manifest = [
      'job_id' => $jobId,
      'album_id' => $albumId,
      'album_code' => (string)($album['code'] ?? ''),
      'watermark' => $watermark,
      'items' => [ $plan['item'] ],
    ];
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($logPath, "[WF] RESCAN(one) start job {$jobId} album_id={$albumId} photo_id={$photoId} items=1\n");

    $py = '/usr/bin/python3';
    $script = '/var/www/html/app/Ingest/rescan_album.py';
    $cmd = escapeshellcmd($py) . ' ' . escapeshellarg($script)
      . ' --manifest ' . escapeshellarg($manifestPath)
      . ' --log ' . escapeshellarg($logPath)
      . ' >> ' . escapeshellarg($logPath) . ' 2>&1 &';

    @shell_exec($cmd);

    Response::redirect("/admin/album/{$albumId}/rescan/run/{$jobId}");
  }

  public function delete(array $params): void {
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($photoId <= 0) {
      Response::html('Bad Request', 400);
    }

    $sectionId = isset($_GET['section']) ? (int)$_GET['section'] : null;
    if ($sectionId !== null && $sectionId <= 0) $sectionId = null;

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

    // Zapamiętaj "gdzie wrócić" zanim skasujemy rekord
    $viewer = $this->photos->viewerForAdmin($photoId, $sectionId, $selectedOnly, $ratingFilter);
    if (!$viewer) {
      Response::html('Not Found', 404);
    }
    $nav = $viewer['nav'] ?? [];
    $albumId = (int)($viewer['album']['id'] ?? 0);

    Csrf::verifyOrFail($_POST['csrf'] ?? null);

    $res = $this->photos->adminDeletePhoto($photoId);
    if (!$res) {
      Response::html('Not Found', 404);
    }

    // Usuwamy fizycznie tylko preview/thumb (nie dotykamy oryginałów na NAS).
    foreach ($res['delete_paths'] as $p) {
      $real = $this->safeRealPathProofing((string)$p);
      if ($real && is_file($real)) {
        @unlink($real);
      }
    }

    // Po usunięciu: zostań w widoku "dużego zdjęcia" — przejdź na poprzednie (lub następne)
    $qp = [];
    if ($sectionId !== null && (int)$sectionId > 0) $qp['section'] = (int)$sectionId;
    if ($selectedOnly === true) $qp['selected'] = 1;
    if ($ratingFilter === 'none') $qp['rating'] = 'none';
    if (is_int($ratingFilter) && $ratingFilter >= 1 && $ratingFilter <= 6) $qp['rating'] = $ratingFilter;
    $qs = !empty($qp) ? ('?' . http_build_query($qp)) : '';
    $prevId = (int)($nav['prev_id'] ?? 0);
    $nextId = (int)($nav['next_id'] ?? 0);

    if ($prevId > 0) {
      Response::redirect("/admin/photo/{$prevId}{$qs}");
    }
    if ($nextId > 0) {
      Response::redirect("/admin/photo/{$nextId}{$qs}");
    }

    // Fallback: grid albumu
    $albumId = $albumId > 0 ? $albumId : (int)$res['album_id'];
    Response::redirect("/admin/album/{$albumId}/photos{$qs}");
  }


  private function safeRealPathProofing(string $path): ?string {
    $real = realpath($path);
    if (!$real) return null;

    $config = require __DIR__ . '/../../config/config.php';
    $proofing = (string)($config['app']['path_proofing'] ?? '/var/www/photos/previews');
    $rootReal = realpath($proofing);
    if (!$rootReal) return null;

    if (str_starts_with($real, $rootReal . DIRECTORY_SEPARATOR)) {
      return $real;
    }
    return null;
  }

}
