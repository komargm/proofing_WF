<?php
declare(strict_types=1);

/**
 * Sekcje ("sub-albumy") w obrębie albumu.
 *
 * Wymaga tabeli: album_sections
 *   - id, album_id, title, sort_order, created_at
 */
final class AlbumSectionRepository {

  /** @return array<int, array<string,mixed>> */
  public function listForAlbum(int $albumId): array {
    $sql = "
      SELECT id, album_id, title, sort_order, created_at
      FROM album_sections
      WHERE album_id = :aid
      ORDER BY sort_order ASC, title ASC, id ASC
    ";
    $st = db()->prepare($sql);
    $st->execute(['aid' => $albumId]);
    return $st->fetchAll() ?: [];
  }

  public function findById(int $sectionId): ?array {
    $sql = "SELECT id, album_id, title, sort_order, created_at FROM album_sections WHERE id = :id LIMIT 1";
    $st = db()->prepare($sql);
    $st->execute(['id' => $sectionId]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function create(int $albumId, string $title): int {
    $title = trim($title);
    if ($title === '') {
      throw new InvalidArgumentException('Title is required');
    }

    // sort_order = max+1
    $sqlMax = "SELECT COALESCE(MAX(sort_order), 0) FROM album_sections WHERE album_id = :aid";
    $st = db()->prepare($sqlMax);
    $st->execute(['aid' => $albumId]);
    $next = (int)($st->fetchColumn() ?: 0) + 1;

    $sql = "INSERT INTO album_sections (album_id, title, sort_order) VALUES (:aid, :t, :s)";
    $st = db()->prepare($sql);
    $st->execute(['aid' => $albumId, 't' => $title, 's' => $next]);
    return (int)db()->lastInsertId();
  }

  public function delete(int $sectionId): void {
    $sql = "DELETE FROM album_sections WHERE id = :id";
    $st = db()->prepare($sql);
    $st->execute(['id' => $sectionId]);
  }

  public function rename(int $sectionId, string $title): void {
    $title = trim($title);
    if ($title === '') {
      throw new InvalidArgumentException('Title is required');
    }
    $sql = "UPDATE album_sections SET title = :t WHERE id = :id";
    $st = db()->prepare($sql);
    $st->execute(['id' => $sectionId, 't' => $title]);
  }

  /**
   * Aktualizuje sort_order wg listy ID.
   * @param array<int,int> $orderedIds
   */
  public function reorder(int $albumId, array $orderedIds): void {
    $sql = "UPDATE album_sections SET sort_order = :s WHERE id = :id AND album_id = :aid";
    $st = db()->prepare($sql);
    $i = 1;
    foreach ($orderedIds as $id) {
      $st->execute(['s' => $i, 'id' => (int)$id, 'aid' => $albumId]);
      $i++;
    }
  }
}
