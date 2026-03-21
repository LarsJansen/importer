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

    public static function stats(int $batchId): array
    {
        $pdo = Database::connection();

        $stats = [
            'categories' => 0,
            'approved_categories' => 0,
            'pending_categories' => 0,
            'skipped_categories' => 0,
            'sites' => 0,
            'approved_sites' => 0,
            'ready_sites' => 0,
            'rejected_sites' => 0,
            'invalid_sites' => 0,
            'duplicate_sites' => 0,
            'missing_category_sites' => 0,
            'ready_export_sites' => 0,
        ];

        $stmt = $pdo->prepare("SELECT
                COUNT(*) AS total_categories,
                SUM(CASE WHEN mapping_status = 'approved' THEN 1 ELSE 0 END) AS approved_categories,
                SUM(CASE WHEN mapping_status = 'pending' THEN 1 ELSE 0 END) AS pending_categories,
                SUM(CASE WHEN mapping_status = 'skipped' THEN 1 ELSE 0 END) AS skipped_categories
            FROM source_categories
            WHERE batch_id = :batch_id");
        $stmt->execute(['batch_id' => $batchId]);
        $row = $stmt->fetch() ?: [];
        $stats['categories'] = (int) ($row['total_categories'] ?? 0);
        $stats['approved_categories'] = (int) ($row['approved_categories'] ?? 0);
        $stats['pending_categories'] = (int) ($row['pending_categories'] ?? 0);
        $stats['skipped_categories'] = (int) ($row['skipped_categories'] ?? 0);

        $stmt = $pdo->prepare("SELECT
                COUNT(*) AS total_sites,
                SUM(CASE WHEN import_status = 'approved' THEN 1 ELSE 0 END) AS approved_sites,
                SUM(CASE WHEN import_status = 'ready' THEN 1 ELSE 0 END) AS ready_sites,
                SUM(CASE WHEN import_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_sites,
                SUM(CASE WHEN import_status = 'invalid' THEN 1 ELSE 0 END) AS invalid_sites,
                SUM(CASE WHEN duplicate_flag = 1 THEN 1 ELSE 0 END) AS duplicate_sites,
                SUM(CASE WHEN source_category_row_id IS NULL THEN 1 ELSE 0 END) AS missing_category_sites,
                SUM(CASE WHEN import_status = 'approved' AND duplicate_flag = 0 AND source_category_row_id IS NOT NULL THEN 1 ELSE 0 END) AS ready_export_sites
            FROM source_sites
            WHERE batch_id = :batch_id");
        $stmt->execute(['batch_id' => $batchId]);
        $row = $stmt->fetch() ?: [];
        $stats['sites'] = (int) ($row['total_sites'] ?? 0);
        $stats['approved_sites'] = (int) ($row['approved_sites'] ?? 0);
        $stats['ready_sites'] = (int) ($row['ready_sites'] ?? 0);
        $stats['rejected_sites'] = (int) ($row['rejected_sites'] ?? 0);
        $stats['invalid_sites'] = (int) ($row['invalid_sites'] ?? 0);
        $stats['duplicate_sites'] = (int) ($row['duplicate_sites'] ?? 0);
        $stats['missing_category_sites'] = (int) ($row['missing_category_sites'] ?? 0);
        $stats['ready_export_sites'] = (int) ($row['ready_export_sites'] ?? 0);

        return $stats;
    }
}
