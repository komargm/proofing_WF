<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$router = new Router();

$usersRepo   = new UserRepository();
$authService = new AuthService($usersRepo);

$albumRepo = new AlbumRepository();
$sectionRepo = new AlbumSectionRepository();
$photoRepo = new PhotoRepository();
$photoActionsRepo = new PhotoActionsRepository();
$ingestRepo = new IngestRepository();

$auth   = new AuthController($authService);
$client = new ClientController($albumRepo, $sectionRepo, $photoRepo);
$admin  = new AdminController();
$adminUsers = new AdminUsersController($usersRepo);
$adminProfile = new AdminProfileController($usersRepo);
$ingest = new IngestWizardController($ingestRepo);

$adminAlbums = new AdminAlbumsController($albumRepo, $sectionRepo, $photoRepo);
$adminPhotoActionsRepo = new AdminPhotoActionsRepository();
$adminPhotoActions = new AdminPhotoActionsController($adminPhotoActionsRepo);
$adminPhoto = new AdminPhotoController($photoRepo, $albumRepo);

$media  = new MediaController($photoRepo);
$clientActions = new ClientActionsController($photoActionsRepo);

// Routes
$router->get('/', fn() => Response::redirect('/login'));

$router->get('/login', fn() => $auth->showLogin());
$router->post('/login', fn() => $auth->doLogin());
$router->get('/logout', fn() => $auth->logout(), [RequireAuth::handle()]);

$router->get('/client/dashboard', fn() => $client->dashboard(), [
  RequireAuth::handle(),
  RequireRole::handle('client'),
]);

$router->get('/client/album/{id}', fn($params) => $client->album($params), [
  RequireAuth::handle(),
  RequireRole::handle('client'),
]);
$router->get('/client/photo/{id}', fn($params) => $client->photo($params), [
  RequireAuth::handle(),
  RequireRole::handle('client'),
]);


$router->post('/client/photo/{id}/toggle-select', fn($params) => $clientActions->toggleSelect($params), [
  RequireAuth::handle(),
  RequireRole::handle('client'),
]);

$router->post('/client/photo/{id}/rate', fn($params) => $clientActions->setRating($params), [
  RequireAuth::handle(),
  RequireRole::handle('client'),
]);

$router->post('/client/photo/{id}/comment', fn($params) => $clientActions->addComment($params), [
  RequireAuth::handle(),
  RequireRole::handle('client'),
]);

$router->get('/media/photo/{id}/original', fn($params) => $media->downloadOriginal($params), [
  RequireAuth::handle(),
  RequireRole::handleAny(['client','admin']),
]);

$router->get('/media/photo/{id}/{kind}', fn($params) => $media->photoFile($params), [
  RequireAuth::handle(),
  RequireRole::handleAny(['client','admin']),
]);


$router->get('/admin/dashboard', fn() => $admin->dashboard(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);


$router->get('/admin/profile', fn() => $adminProfile->show(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->post('/admin/profile/update', fn() => $adminProfile->updateProfile(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->post('/admin/profile/password', fn() => $adminProfile->changePassword(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/albums', fn() => $adminAlbums->index(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/album/{id}/edit', fn($p) => $adminAlbums->edit($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->post('/admin/album/{id}/edit', fn($p) => $adminAlbums->update($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

// Admin: usuwanie całego albumu (DB + preview/thumb)
$router->post('/admin/album/{id}/delete', fn($p) => $adminAlbums->delete($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

// Admin: Rescan albumu (odśwież preview/thumb gdy oryginał się zmienił)
$router->post('/admin/album/{id}/rescan', fn($p) => $adminAlbums->rescanStart($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/album/{id}/rescan/run/{job}', fn($p) => $adminAlbums->rescanRunPage($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/album/{id}/rescan/stream/{job}', fn($p) => $adminAlbums->rescanStream($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/album/{id}/photos', fn($p) => $adminAlbums->photos($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

// Admin: sekcje w albumie ("sub-albumy")
$router->get('/admin/album/{id}/sections', fn($p) => $adminAlbums->sectionsPage($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->post('/admin/album/{id}/sections/create', fn($p) => $adminAlbums->sectionCreate($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->post('/admin/album/{id}/sections/{sid}/rename', fn($p) => $adminAlbums->sectionRename($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->post('/admin/album/{id}/sections/{sid}/delete', fn($p) => $adminAlbums->sectionDelete($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

// Admin: Dodaj pojedyncze zdjęcie do istniejącego albumu (Pick from NAS)
$router->get('/admin/album/{id}/add-photo', fn($p) => $adminAlbums->addPhotoPage($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/album/{id}/add-photo/list', fn($p) => $adminAlbums->addPhotoList($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->post('/admin/album/{id}/add-photo/start', fn($p) => $adminAlbums->addPhotoStart($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/album/{id}/add-photo/run/{job}', fn($p) => $adminAlbums->addPhotoRunPage($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/album/{id}/add-photo/stream/{job}', fn($p) => $adminAlbums->addPhotoStream($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/photo/{id}', fn($p) => $adminPhoto->photo($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->post('/admin/photo/{id}/set-section', fn($p) => $adminPhoto->setSection($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->post('/admin/photo/{id}/download-allowed', fn($p) => $adminPhoto->setDownloadAllowed($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->post('/admin/photo/{id}/delete', fn($p) => $adminPhoto->delete($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
// Admin: Rescan pojedynczego zdjęcia (wymuszone przeliczenie preview/thumb)
$router->post('/admin/photo/{id}/rescan', fn($p) => $adminPhoto->rescanStart($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->post('/admin/photo/{id}/comment', fn($p) => $adminPhotoActions->addComment($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->post('/admin/photo/{id}/rate', fn($p) => $adminPhotoActions->setAdminRating($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/users', fn() => $adminUsers->index(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/users/create', fn() => $adminUsers->create(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->post('/admin/users/create', fn() => $adminUsers->store(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);


$router->get('/admin/users/{id}/edit', fn($p) => $adminUsers->edit($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->post('/admin/users/{id}/edit', fn($p) => $adminUsers->update($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

// Ingest Wizard (Faza 5)
$router->get('/admin/albums/create', fn() => $ingest->step1(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->post('/admin/albums/create/step1', fn() => $ingest->step1Post(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/albums/create/source', fn() => $ingest->step2(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->get('/admin/albums/create/source/list', fn() => $ingest->listDirs(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->post('/admin/albums/create/source', fn() => $ingest->step2Post(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/albums/create/select', fn() => $ingest->step3(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->get('/admin/albums/create/select/list', fn() => $ingest->listJpgs(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->post('/admin/albums/create/finalize', fn() => $ingest->finalize(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->get('/admin/albums/create/run/{job}', fn($p) => $ingest->runPage($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);
$router->get('/admin/albums/create/stream/{job}', fn($p) => $ingest->stream($p), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->dispatch();