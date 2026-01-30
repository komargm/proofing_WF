<?php
declare(strict_types=1);

final class AuthController {
  public function __construct(private AuthService $auth) {}

  public function showLogin(): void {
    if (!empty($_SESSION['user_id'])) {
      $this->redirectAfterLogin();
    }

    Response::html(View::page('auth/login', [
      'error' => null,
      'csrf'  => Csrf::token(),
    ]));
  }

  public function doLogin(): void {
    Csrf::verifyOrFail($_POST['csrf'] ?? null);

    $email = (string)($_POST['email'] ?? '');
    $pass  = (string)($_POST['password'] ?? '');

    $ok = $this->auth->attemptLogin($email, $pass);
    if (!$ok) {
      Response::html(View::page('auth/login', [
        'error' => 'Błędne dane logowania',
        'csrf'  => Csrf::token(),
      ]));
    }

    $this->redirectAfterLogin();
  }

  public function logout(): void {
    $this->auth->logout();
    Response::redirect('/login');
  }

  private function redirectAfterLogin(): void {
    $role = $_SESSION['user_role'] ?? null;
    if ($role === 'admin')  Response::redirect('/admin/dashboard');
    if ($role === 'client') Response::redirect('/client/dashboard');
    Response::redirect('/login');
  }
}
