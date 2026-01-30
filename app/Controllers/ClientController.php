<?php
declare(strict_types=1);

final class ClientController {
  public function __construct(
    private AlbumRepository $albums,
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

    $photos = $this->photos->listForUserAlbum($userId, $albumId);

    Response::html(View::page('client/album', [
      'album' => $album,
      'photos' => $photos,
      'count' => count($photos),
    ]));
  }
}
