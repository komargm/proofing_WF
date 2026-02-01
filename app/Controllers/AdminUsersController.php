<?php
declare(strict_types=1);

final class AdminUsersController {
  public function __construct(private UserRepository $users) {}

  public function index(): void {
    $users = $this->users->listClients();

    $flash = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);

    Response::html(View::page('admin/users/index', [
      'users' => $users,
      'flash' => $flash,
    ]));
  }

  public function create(): void {
    $err = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);

    $old = $_SESSION['flash_old'] ?? [];
    unset($_SESSION['flash_old']);

    Response::html(View::page('admin/users/create', [
      'err' => $err,
      'old' => is_array($old) ? $old : [],
      'csrf' => Csrf::token(),
    ]));
  }

  public function store(): void {
    Csrf::validate();

    $data = [
      'email' => (string)($_POST['email'] ?? ''),
      'password' => (string)($_POST['password'] ?? ''),
      'first_name' => trim((string)($_POST['first_name'] ?? '')),
      'last_name' => trim((string)($_POST['last_name'] ?? '')),
      'phone' => trim((string)($_POST['phone'] ?? '')),
      'messenger' => trim((string)($_POST['messenger'] ?? '')),
      'contact_notes' => trim((string)($_POST['contact_notes'] ?? '')),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    // zachowaj w razie błędu (bez hasła)
    $_SESSION['flash_old'] = $data;
    $_SESSION['flash_old']['password'] = '';

    try {
      $userId = $this->users->createClient($data);

      unset($_SESSION['flash_old']);
      $_SESSION['flash_success'] = [
        'user_id' => $userId,
        'email' => trim(mb_strtolower($data['email'])),
        'password' => $data['password'], // pokaż tylko raz po utworzeniu
      ];

      Response::redirect('/admin/users');
    } catch (Throwable $e) {
      // Typowe: duplicate email, walidacja
      $msg = $e->getMessage();
      if (str_contains($msg, 'Duplicate') || str_contains($msg, 'UNIQUE') || str_contains($msg, 'email')) {
        $msg = 'Nie udało się utworzyć użytkownika. Sprawdź email (czy nie istnieje już w systemie).';
      }
      $_SESSION['flash_error'] = $msg;
      Response::redirect('/admin/users/create');
    }
  }

  public function edit(array $params): void {
    $userId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($userId <= 0) {
      Response::html('Bad Request', 400);
    }

    $err = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);

    $old = $_SESSION['flash_old'] ?? null;
    unset($_SESSION['flash_old']);

    $user = $this->users->getClientById($userId);
    if (!$user) {
      Response::html('Not Found', 404);
    }

    $form = is_array($old) ? array_merge($user, $old) : $user;

    Response::html(View::page('admin/users/edit', [
      'err' => $err,
      'user' => $user,
      'old' => $form,
      'csrf' => Csrf::token(),
    ]));
  }

  public function update(array $params): void {
    $userId = isset($params['id']) ? (int)$params['id'] : 0;
    if ($userId <= 0) {
      Response::html('Bad Request', 400);
    }

    Csrf::validate();

    $data = [
      'email' => (string)($_POST['email'] ?? ''),
      'password' => (string)($_POST['password'] ?? ''), // opcjonalnie
      'first_name' => trim((string)($_POST['first_name'] ?? '')),
      'last_name' => trim((string)($_POST['last_name'] ?? '')),
      'phone' => trim((string)($_POST['phone'] ?? '')),
      'messenger' => trim((string)($_POST['messenger'] ?? '')),
      'contact_notes' => trim((string)($_POST['contact_notes'] ?? '')),
      'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    $_SESSION['flash_old'] = $data;
    // nie przechowuj hasła w sesji
    $_SESSION['flash_old']['password'] = '';

    try {
      $this->users->updateClient($userId, $data);

      unset($_SESSION['flash_old']);
      $_SESSION['flash_success'] = [
        'updated_user_id' => $userId,
        'updated_email' => trim(mb_strtolower($data['email'])),
        'password_changed' => ($data['password'] !== ''),
      ];

      Response::redirect('/admin/users');
    } catch (Throwable $e) {
      $msg = $e->getMessage();
      $_SESSION['flash_error'] = $msg;
      Response::redirect("/admin/users/{$userId}/edit");
    }
  }

}
