<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$router = new Router();

$usersRepo   = new UserRepository();
$authService = new AuthService($usersRepo);
$albumRepo   = new AlbumRepository();

$auth   = new AuthController($authService);
$client = new ClientController($albumRepo);
$admin  = new AdminController();

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

$router->get('/admin/dashboard', fn() => $admin->dashboard(), [
  RequireAuth::handle(),
  RequireRole::handle('admin'),
]);

$router->dispatch();
