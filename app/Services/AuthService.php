<?php
declare(strict_types=1);

final class AuthService {
  public function __construct(private UserRepository $users) {}

  public function attemptLogin(string $email, string $password): bool {
    $email = trim(mb_strtolower($email));
    $user = $this->users->findByEmail($email);

    // Jednolity błąd: nie zdradzamy czy email istnieje
    if (!$user || (int)$user['is_active'] !== 1) {
      return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
      return false;
    }

    $role = $this->users->getRoleNameForUser((int)$user['id']);
    if (!$role) return false;

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = $role;

    return true;
  }

  public function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
      );
    }
    session_destroy();
  }
}
