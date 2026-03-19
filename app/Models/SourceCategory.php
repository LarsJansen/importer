<?php
namespace App\Models;

use App\Core\Database;

class SourceCategory
{
    public static function paginate(int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $pdo = Database::connection();
        [$where, $params] = self::buildWhere($filters);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM source_categories {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM source_categories {$where} ORDER BY entry_count DESC, full_path ASC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
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

    private static function buildWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (!empty($filters['branch'])) {
            $clauses[] = 'top_branch = :branch';
            $params['branch'] = $filters['branch'];
        }
        if (!empty($filters['status'])) {
            $clauses[] = 'mapping_status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['path'])) {
            $clauses[] = 'full_path LIKE :path';
            $params['path'] = '%' . $filters['path'] . '%';
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        return [$where, $params];
    }

    public static function branches(): array
    {
        $stmt = Database::connection()->query('SELECT DISTINCT top_branch FROM source_categories WHERE top_branch IS NOT NULL AND top_branch <> "" ORDER BY top_branch');
        return $stmt->fetchAll();
    }

    public static function bulkUpdateStatus(array $ids, string $status): void
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE source_categories SET mapping_status = ?, updated_at = NOW() WHERE id IN ({$placeholders})";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$status], $ids));
    }

    public static function insert(array $data): void
    {
        $sql = 'INSERT INTO source_categories
            (batch_id, source_category_id, full_path, category_name, parent_path, path_depth, entry_count, description_raw, geo_raw, geo_lat, geo_lng, top_branch, local_path_candidate, mapping_status, created_at, updated_at)
            VALUES
            (:batch_id, :source_category_id, :full_path, :category_name, :parent_path, :path_depth, :entry_count, :description_raw, :geo_raw, :geo_lat, :geo_lng, :top_branch, :local_path_candidate, :mapping_status, NOW(), NOW())';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($data);
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM source_categories')->fetchColumn();
    }

    public static function unmappedCount(): int
    {
        return (int) Database::connection()->query("SELECT COUNT(*) FROM source_categories WHERE mapping_status = 'pending'")->fetchColumn();
    }
}
