<?php
declare(strict_types=1);

final class AdminPhotoController {
  public function __construct(private PhotoRepository $photos) {}

  public function photo(array $params): void {
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($photoId <= 0) {
      Response::html('Bad Request', 400);
    }

    $sectionId = isset($_GET['section']) ? (int)$_GET['section'] : null;
    if ($sectionId !== null && $sectionId <= 0) $sectionId = null;

    $data = $this->photos->viewerForAdmin($photoId, $sectionId);
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
  public function delete(array $params): void {
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($photoId <= 0) {
      Response::html('Bad Request', 400);
    }

    $sectionId = isset($_GET['section']) ? (int)$_GET['section'] : null;
    if ($sectionId !== null && $sectionId <= 0) $sectionId = null;

    // Zapamiętaj "gdzie wrócić" zanim skasujemy rekord
    $viewer = $this->photos->viewerForAdmin($photoId, $sectionId);
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
    $qs = ($sectionId !== null && (int)$sectionId > 0) ? ('?section='.(int)$sectionId) : '';
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
