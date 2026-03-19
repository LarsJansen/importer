<?php
namespace App\Models;

use App\Core\Database;

class Batch
{
    public static function paginate(int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        $pdo = Database::connection();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM import_batches')->fetchColumn();
        $sql = 'SELECT * FROM import_batches ORDER BY id DESC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return [
            'rows' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => max(1, (int) ceil($total / max(1, $perPage))),
        ];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM import_batches WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO import_batches (source_name, label, status, notes, started_at) VALUES (:source_name, :label, :status, :notes, NOW())';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'source_name' => $data['source_name'],
            'label' => $data['label'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function complete(int $id, array $counts, string $status = 'completed'): void
    {
        $sql = 'UPDATE import_batches
                SET status = :status,
                    finished_at = NOW(),
                    categories_imported = :categories_imported,
                    sites_imported = :sites_imported,
                    errors_count = :errors_count
                WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'status' => $status,
            'categories_imported' => $counts['categories_imported'] ?? 0,
            'sites_imported' => $counts['sites_imported'] ?? 0,
            'errors_count' => $counts['errors_count'] ?? 0,
            'id' => $id,
        ]);
    }
}
