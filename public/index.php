<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$router = new Router();

$usersRepo   = new UserRepository();
$authService = new AuthService($usersRepo);

$albumRepo = new AlbumRepository();
$photoRepo = new PhotoRepository();
$photoActionsRepo = new PhotoActionsRepository();
$ingestRepo = new IngestRepository();

$auth   = new AuthController($authService);
$client = new ClientController($albumRepo, $photoRepo);
$admin  = new AdminController();
$adminUsers = new AdminUsersController($usersRepo);
$ingest = new IngestWizardController($ingestRepo);

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

$router->get('/media/photo/{id}/{kind}', fn($params) => $media->photoFile($params), [
  RequireAuth::handle(),
  RequireRole::handle('client'),
]);

$router->get('/admin/dashboard', fn() => $admin->dashboard(), [
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