<?php
namespace App\Models;

use App\Core\Database;

class SourceSite
{
    public static function paginate(int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $pdo = Database::connection();

        [$where, $params] = self::buildWhere($filters);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM source_sites ss LEFT JOIN source_categories sc ON sc.source_category_id = ss.source_category_id {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT ss.*, sc.full_path, sc.top_branch
                FROM source_sites ss
                LEFT JOIN source_categories sc ON sc.source_category_id = ss.source_category_id
                {$where}
                ORDER BY ss.id DESC
                LIMIT :limit OFFSET :offset";
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

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'duplicates') {
                $clauses[] = 'ss.duplicate_flag = 1';
            } elseif ($filters['status'] === 'missing_category') {
                $clauses[] = 'ss.source_category_row_id IS NULL';
            } elseif ($filters['status'] === 'ready_export') {
                $clauses[] = "ss.import_status = 'approved'";
                $clauses[] = 'ss.duplicate_flag = 0';
                $clauses[] = 'ss.source_category_row_id IS NOT NULL';
            } else {
                $clauses[] = 'ss.import_status = :status';
                $params['status'] = $filters['status'];
            }
        }

        if (!empty($filters['branch'])) {
            $clauses[] = 'sc.top_branch = :branch';
            $params['branch'] = $filters['branch'];
        }

        if (!empty($filters['path'])) {
            $clauses[] = 'sc.full_path LIKE :path';
            $params['path'] = '%' . $filters['path'] . '%';
        }

        if (!empty($filters['url'])) {
            $clauses[] = '(ss.url LIKE :url OR ss.normalized_url LIKE :url OR ss.title LIKE :url)';
            $params['url'] = '%' . $filters['url'] . '%';
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        return [$where, $params];
    }

    public static function bulkUpdateStatus(array $ids, string $status): void
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE source_sites SET import_status = ?, updated_at = NOW() WHERE id IN ({$placeholders})";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$status], $ids));
    }

    public static function insert(array $data): void
    {
        $sql = 'INSERT INTO source_sites (batch_id, source_category_id, source_category_row_id, url, normalized_url, title, description_raw, http_scheme, import_status, duplicate_flag, notes, created_at, updated_at) VALUES (:batch_id, :source_category_id, :source_category_row_id, :url, :normalized_url, :title, :description_raw, :http_scheme, :import_status, :duplicate_flag, :notes, NOW(), NOW())';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($data);
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM source_sites')->fetchColumn();
    }

    public static function counts(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN import_status = 'ready' THEN 1 ELSE 0 END) AS ready_count,
                    SUM(CASE WHEN import_status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
                    SUM(CASE WHEN import_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                    SUM(CASE WHEN import_status = 'invalid' THEN 1 ELSE 0 END) AS invalid_count,
                    SUM(CASE WHEN duplicate_flag = 1 THEN 1 ELSE 0 END) AS duplicate_count,
                    SUM(CASE WHEN source_category_row_id IS NULL THEN 1 ELSE 0 END) AS missing_category_count,
                    SUM(CASE WHEN import_status = 'approved' AND duplicate_flag = 0 AND source_category_row_id IS NOT NULL THEN 1 ELSE 0 END) AS ready_export_count
                FROM source_sites";
        $row = Database::connection()->query($sql)->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'ready' => (int) ($row['ready_count'] ?? 0),
            'approved' => (int) ($row['approved_count'] ?? 0),
            'rejected' => (int) ($row['rejected_count'] ?? 0),
            'invalid' => (int) ($row['invalid_count'] ?? 0),
            'duplicates' => (int) ($row['duplicate_count'] ?? 0),
            'missing_category' => (int) ($row['missing_category_count'] ?? 0),
            'ready_export' => (int) ($row['ready_export_count'] ?? 0),
        ];
    }

    public static function duplicateCount(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM source_sites WHERE duplicate_flag = 1')->fetchColumn();
    }

    public static function missingCategoryCount(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM source_sites WHERE source_category_row_id IS NULL')->fetchColumn();
    }
}
