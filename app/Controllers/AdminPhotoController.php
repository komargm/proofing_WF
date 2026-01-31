<?php
declare(strict_types=1);

final class AdminPhotoController {
  public function __construct(private PhotoRepository $photos) {}

  public function photo(array $params): void {
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($photoId <= 0) {
      Response::html('Bad Request', 400);
    }

    $data = $this->photos->viewerForAdmin($photoId);
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
}
