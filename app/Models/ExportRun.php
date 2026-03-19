<?php
namespace App\Models;

use App\Core\Database;

class ExportRun
{
    public static function paginate(int $page = 1, int $perPage = 25): array
    {
        $offset = ($page - 1) * $perPage;
        $pdo = Database::connection();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM export_runs')->fetchColumn();
        $sql = 'SELECT er.*, ib.label AS batch_label
            FROM export_runs er
            LEFT JOIN import_batches ib ON ib.id = er.batch_id
            ORDER BY er.created_at DESC, er.id DESC LIMIT :limit OFFSET :offset';
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

    public static function create(array $data): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO export_runs (batch_id, filename, categories_count, sites_count, created_at) VALUES (:batch_id, :filename, :categories_count, :sites_count, NOW())');
        $stmt->execute([
            'batch_id' => $data['batch_id'] ?? null,
            'filename' => $data['filename'],
            'categories_count' => $data['categories_count'],
            'sites_count' => $data['sites_count'],
        ]);
    }
}
