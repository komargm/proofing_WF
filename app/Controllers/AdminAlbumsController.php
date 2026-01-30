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
}
