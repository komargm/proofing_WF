<?php
declare(strict_types=1);

final class ClientController {
  public function __construct(
    private AlbumRepository $albums,
    private AlbumSectionRepository $sections,
    private PhotoRepository $photos,
  ) {}

  public function dashboard(): void {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $albums = $this->albums->listForUser($userId);

    Response::html(View::page('client/dashboard', [
      'user_id' => $userId,
      'albums'  => $albums,
    ]));
  }

  public function album(array $params): void {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $albumId = isset($params['id']) ? (int)$params['id'] : 0;

    if ($albumId <= 0) {
      Response::html('Bad Request', 400);
    }

    $album = $this->albums->findForUser($userId, $albumId);
    if (!$album) {
      Response::html('Forbidden', 403);
    }

    $sectionId = isset($_GET['section']) ? (int)$_GET['section'] : null;
    if ($sectionId !== null && $sectionId <= 0) $sectionId = null;

    $sections = $this->sections->listForAlbum($albumId);
    $photos = $this->photos->listForUserAlbum($userId, $albumId, $sectionId);

    Response::html(View::page('client/album', [
      'album' => $album,
      'sections' => $sections,
      'section_id' => $sectionId,
      'photos' => $photos,
      'count' => count($photos),
    ]));
  }

  public function photo(array $params): void {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $photoId = isset($params['id']) ? (int)$params['id'] : 0;

    if ($photoId <= 0) {
      Response::html('Bad Request', 400);
    }

    $sectionId = isset($_GET['section']) ? (int)$_GET['section'] : null;
    if ($sectionId !== null && $sectionId <= 0) $sectionId = null;

    $data = $this->photos->viewerForUser($userId, $photoId, $sectionId);
    if (!$data) {
      Response::html('Forbidden', 403);
    }

    $data['sections'] = $this->sections->listForAlbum((int)($data['album']['id'] ?? 0));

    Response::html(View::page('client/photo', $data));
  }
}
