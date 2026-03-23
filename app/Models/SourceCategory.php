<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class SourceCategory
{
    public static function paginate(int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $pdo = Database::connection();

        [$where, $params] = self::buildWhere($filters);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM source_categories sc {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT sc.*
                FROM source_categories sc
                {$where}
                ORDER BY sc.entry_count DESC, sc.full_path ASC
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
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

        if (!empty($filters['batch_id'])) {
            $clauses[] = 'sc.batch_id = :batch_id';
            $params['batch_id'] = (int) $filters['batch_id'];
        }

        if (!empty($filters['branch'])) {
            $clauses[] = 'sc.top_branch = :branch';
            $params['branch'] = $filters['branch'];
        }

        if (!empty($filters['status'])) {
            $clauses[] = 'sc.mapping_status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['path'])) {
            $clauses[] = 'sc.full_path LIKE :path';
            $params['path'] = '%' . $filters['path'] . '%';
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
        return [$where, $params];
    }

    public static function bulkUpdateStatus(array $ids, string $status): void
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE source_categories SET mapping_status = ?, updated_at = NOW() WHERE id IN ({$placeholders})";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$status], $ids));

        $sql = "UPDATE category_mapping cm
                INNER JOIN source_categories sc ON sc.source_category_id = cm.source_category_id
                SET cm.mapping_status = ?, cm.updated_at = NOW()
                WHERE sc.id IN ({$placeholders})";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_merge([$status], $ids));
    }

    public static function bulkApproveBranches(array $ids): void
    {
        $pdo = Database::connection();
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids));

        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rootStmt = $pdo->prepare("SELECT id, batch_id, full_path FROM source_categories WHERE id IN ({$placeholders})");
        $rootStmt->execute($ids);
        $roots = $rootStmt->fetchAll();

        if (!$roots) {
            return;
        }

        $branchClauses = [];
        $branchParams = [];
        foreach ($roots as $root) {
            $branchClauses[] = '(batch_id = ? AND (full_path = ? OR full_path LIKE ?))';
            $branchParams[] = (int) $root['batch_id'];
            $branchParams[] = (string) $root['full_path'];
            $branchParams[] = (string) $root['full_path'] . '/%';
        }

        $branchWhere = implode(' OR ', $branchClauses);
        $branchStmt = $pdo->prepare(
            "SELECT id, source_category_id
             FROM source_categories
             WHERE {$branchWhere}"
        );
        $branchStmt->execute($branchParams);
        $branchRows = $branchStmt->fetchAll();

        if (!$branchRows) {
            return;
        }

        $categoryRowIds = [];
        $sourceCategoryIds = [];
        foreach ($branchRows as $row) {
            $categoryRowIds[] = (int) $row['id'];
            $sourceCategoryIds[] = (int) $row['source_category_id'];
        }

        $categoryRowIds = array_values(array_unique($categoryRowIds));
        $sourceCategoryIds = array_values(array_unique($sourceCategoryIds));

        $pdo->beginTransaction();

        try {
            $categoryPlaceholders = implode(',', array_fill(0, count($categoryRowIds), '?'));

            $stmt = $pdo->prepare(
                "UPDATE source_categories
                 SET mapping_status = 'approved', updated_at = NOW()
                 WHERE id IN ({$categoryPlaceholders})"
            );
            $stmt->execute($categoryRowIds);

            if ($sourceCategoryIds !== []) {
                $mappingPlaceholders = implode(',', array_fill(0, count($sourceCategoryIds), '?'));
                $stmt = $pdo->prepare(
                    "UPDATE category_mapping
                     SET mapping_status = 'approved', updated_at = NOW()
                     WHERE source_category_id IN ({$mappingPlaceholders})"
                );
                $stmt->execute($sourceCategoryIds);
            }

            $stmt = $pdo->prepare(
                "UPDATE source_sites
                 SET import_status = 'approved', updated_at = NOW()
                 WHERE source_category_row_id IN ({$categoryPlaceholders})"
            );
            $stmt->execute($categoryRowIds);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function branches(): array
    {
        $sql = "SELECT DISTINCT top_branch FROM source_categories WHERE top_branch IS NOT NULL AND top_branch <> '' ORDER BY top_branch ASC";
        return Database::connection()->query($sql)->fetchAll();
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
