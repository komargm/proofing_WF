<?php
declare(strict_types=1);

final class IngestRepository {

  /**
   * Tworzy album + przypisanie klienta + rekordy photos + photo_files.
   * Zwraca: ['album_id' => int, 'album_code' => string, 'items' => array]
   * gdzie items = lista z polami potrzebnymi do generacji plików.
   *
   * @param array<int, string> $filenames
   * @return array{album_id:int, album_code:string, items:array<int, array{photo_id:int, src:string, preview_dest:string, thumb_dest:string}>}
   */
  public function createAlbumAndPlan(
    int $createdBy,
    int $clientUserId,
    string $title,
    string $albumComment,
    string $relativeFolder,
    array $filenames,
    string $pathOriginalsRoot,
    string $pathProofingRoot,
  ): array {
    $albumCode = $this->generateAlbumCode();

    $pdo = db();
    $pdo->beginTransaction();

    try {
      $stmt = $pdo->prepare(
        "INSERT INTO albums (code, title, album_comment, created_by) VALUES (:code, :title, :comment, :created_by)"
      );
      $stmt->execute([
        'code' => $albumCode,
        'title' => $title,
        'comment' => $albumComment,
        'created_by' => $createdBy,
      ]);
      $albumId = (int)$pdo->lastInsertId();

      $stmt = $pdo->prepare(
        "INSERT INTO user_album_access (user_id, album_id, access_role) VALUES (:uid, :aid, 'owner')"
      );
      $stmt->execute(['uid' => $clientUserId, 'aid' => $albumId]);

      // Folder docelowy w proofing
      $albumDir = rtrim($pathProofingRoot, '/')."/album_{$albumId}";
      $previewDir = $albumDir . '/previews';
      $thumbDir   = $albumDir . '/thumbs';
      if (!is_dir($previewDir) && !@mkdir($previewDir, 0777, true) && !is_dir($previewDir)) {
        throw new RuntimeException('Nie mogę utworzyć katalogu: ' . $previewDir);
      }
      if (!is_dir($thumbDir) && !@mkdir($thumbDir, 0777, true) && !is_dir($thumbDir)) {
        throw new RuntimeException('Nie mogę utworzyć katalogu: ' . $thumbDir);
      }

      $items = [];
      $sort = 1;
      foreach ($filenames as $fn) {
        $stmt = $pdo->prepare(
          "INSERT INTO photos (album_id, sort_order, is_visible) VALUES (:aid, :sort, 1)"
        );
        $stmt->execute(['aid' => $albumId, 'sort' => $sort++]);
        $photoId = (int)$pdo->lastInsertId();

        $src = rtrim($pathOriginalsRoot, '/') . '/' . ltrim($relativeFolder, '/') . '/' . $fn;
        $previewDest = $previewDir . "/p_{$photoId}.jpg";
        $thumbDest   = $thumbDir   . "/t_{$photoId}.jpg";

        $stmt = $pdo->prepare(
          "INSERT INTO photo_files (photo_id, kind, path) VALUES (:pid, :kind, :path)"
        );
        $stmt->execute(['pid' => $photoId, 'kind' => 'original_jpg', 'path' => $src]);
        $stmt->execute(['pid' => $photoId, 'kind' => 'preview_800',  'path' => $previewDest]);
        $stmt->execute(['pid' => $photoId, 'kind' => 'thumb',        'path' => $thumbDest]);

        $items[] = [
          'photo_id' => $photoId,
          'src' => $src,
          'preview_dest' => $previewDest,
          'thumb_dest' => $thumbDest,
        ];
      }

      $pdo->commit();
      return ['album_id' => $albumId, 'album_code' => $albumCode, 'items' => $items];
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

  /** @return array<int, array{id:int,email:string,first_name:?string,last_name:?string}> */
  public function listClientUsers(): array {
    // klienci = rola 'client'
    $sql = "
      SELECT u.id, u.email, u.first_name, u.last_name
      FROM users u
      JOIN user_roles ur ON ur.user_id = u.id
      JOIN roles r ON r.id = ur.role_id
      WHERE r.name = 'client'
      ORDER BY u.last_name ASC, u.first_name ASC, u.email ASC
    ";
    $stmt = db()->query($sql);
    return $stmt->fetchAll() ?: [];
  }

  private function generateAlbumCode(): string {
    $rand = bin2hex(random_bytes(4));
    return 'ALB_' . gmdate('Ymd_His') . '_' . $rand;
  }
}
