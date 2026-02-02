<?php
declare(strict_types=1);

final class AdminProfileController {
  public function __construct(private UserRepository $users) {}

  public function show(): void {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
      Response::redirect('/login');
    }

    $user = $this->users->getAdminById($uid);
    if (!$user) {
      Response::html('Not Found', 404);
      return;
    }

    $flashOk = $_SESSION['flash_success'] ?? null;
    unset($_SESSION['flash_success']);

    $flashErr = $_SESSION['flash_error'] ?? null;
    unset($_SESSION['flash_error']);

    $old = $_SESSION['flash_old'] ?? null;
    unset($_SESSION['flash_old']);

    $form = is_array($old) ? array_merge($user, $old) : $user;

    Response::html(View::page('admin/profile', [
      'user' => $user,
      'old' => $form,
      'ok' => $flashOk,
      'err' => $flashErr,
      'csrf' => Csrf::token(),
    ]));
  }

  public function updateProfile(): void {
    Csrf::validate();

    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
      Response::redirect('/login');
    }

    $data = [
      'first_name' => trim((string)($_POST['first_name'] ?? '')),
      'last_name' => trim((string)($_POST['last_name'] ?? '')),
      'phone' => trim((string)($_POST['phone'] ?? '')),
      'messenger' => trim((string)($_POST['messenger'] ?? '')),
      'contact_notes' => trim((string)($_POST['contact_notes'] ?? '')),
    ];

    $_SESSION['flash_old'] = $data;

    try {
      $this->users->updateAdminProfile($uid, $data);

      // odśwież dane w sesji (do topbara)
      $_SESSION['user_first_name'] = $data['first_name'];
      $_SESSION['user_last_name'] = $data['last_name'];

      unset($_SESSION['flash_old']);
      $_SESSION['flash_success'] = 'Zapisano dane profilu.';
      Response::redirect('/admin/profile');
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = $e->getMessage();
      Response::redirect('/admin/profile');
    }
  }

  public function changePassword(): void {
    Csrf::validate();

    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
      Response::redirect('/login');
    }

    $current = (string)($_POST['current_password'] ?? '');
    $new1 = (string)($_POST['new_password'] ?? '');
    $new2 = (string)($_POST['new_password2'] ?? '');

    if ($new1 === '' || $new2 === '' || $current === '') {
      $_SESSION['flash_error'] = 'Uzupełnij: aktualne hasło oraz nowe hasło (2x).';
      Response::redirect('/admin/profile');
    }

    if ($new1 !== $new2) {
      $_SESSION['flash_error'] = 'Nowe hasła nie są identyczne.';
      Response::redirect('/admin/profile');
    }

    if (mb_strlen($new1) < 8) {
      $_SESSION['flash_error'] = 'Nowe hasło musi mieć co najmniej 8 znaków.';
      Response::redirect('/admin/profile');
    }

    try {
      $this->users->changeAdminPassword($uid, $current, $new1);
      $_SESSION['flash_success'] = 'Hasło zostało zmienione.';
      Response::redirect('/admin/profile');
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = $e->getMessage();
      Response::redirect('/admin/profile');
    }
  }
}
