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
  /** @return array<int, array<string,mixed>> */
  public function listClients(): array {
    $sql = "
      SELECT u.id, u.email, u.is_active, u.first_name, u.last_name, u.phone, u.messenger, u.contact_notes, u.created_at
      FROM users u
      JOIN user_roles ur ON ur.user_id = u.id
      JOIN roles r ON r.id = ur.role_id
      WHERE r.name = 'client'
      ORDER BY u.created_at DESC, u.id DESC
    ";
    $stmt = db()->query($sql);
    return $stmt->fetchAll() ?: [];
  }

  /**
   * Tworzy nowego klienta + przypisuje rolę 'client'.
   * Zwraca user_id.
   * @param array{email:string,password:string,first_name?:string,last_name?:string,phone?:string,messenger?:string,contact_notes?:string,is_active?:int} $data
   */
  public function createClient(array $data): int {
    $email = trim(mb_strtolower((string)($data['email'] ?? '')));
    $password = (string)($data['password'] ?? '');

    if ($email === '' || $password === '') {
      throw new InvalidArgumentException('Email i hasło są wymagane.');
    }

    // Minimalna walidacja emaila (serwerowa, UI też ma "type=email")
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new InvalidArgumentException('Nieprawidłowy email.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
      // Upewnij się, że rola istnieje
      $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'client' LIMIT 1");
      $stmt->execute();
      $roleId = (int)($stmt->fetch()['id'] ?? 0);
      if ($roleId <= 0) {
        throw new RuntimeException("Brak roli 'client' w tabeli roles.");
      }

      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare(
        "INSERT INTO users (email, password_hash, is_active, first_name, last_name, phone, messenger, contact_notes)
         VALUES (:email, :ph, :active, :fn, :ln, :phone, :ms, :notes)"
      );
      $stmt->execute([
        'email' => $email,
        'ph' => $hash,
        'active' => (int)($data['is_active'] ?? 1),
        'fn' => ($data['first_name'] ?? null) ?: null,
        'ln' => ($data['last_name'] ?? null) ?: null,
        'phone' => ($data['phone'] ?? null) ?: null,
        'ms' => ($data['messenger'] ?? null) ?: null,
        'notes' => ($data['contact_notes'] ?? null) ?: null,
      ]);

      $userId = (int)$pdo->lastInsertId();

      $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
      $stmt->execute(['uid' => $userId, 'rid' => $roleId]);

      // Audit (opcjonalnie)
      try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, event, meta_json, ip) VALUES (:uid, 'user.created', :meta, :ip)");
        $meta = json_encode(['created_user_id' => $userId, 'role' => 'client', 'email' => $email], JSON_UNESCAPED_UNICODE);
        $stmt->execute([
          'uid' => (int)($_SESSION['user_id'] ?? 0) ?: null,
          'meta' => $meta,
          'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
      } catch (Throwable $e) {
        // nie blokuj tworzenia usera gdy audit się wysypie
      }

      $pdo->commit();
      return $userId;
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

}
