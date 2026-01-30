<?php
declare(strict_types=1);

final class UserRepository {
  public function findByEmail(string $email): ?array {
    $sql = "SELECT id, email, password_hash, is_active
            FROM users
            WHERE email = :email
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute(['email' => $email]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public function getRoleNameForUser(int $userId): ?string {
    $sql = "SELECT r.name
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :uid
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch();
    return $row['name'] ?? null;
  }
}
