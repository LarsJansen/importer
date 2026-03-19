<?php
namespace App\Models;

use App\Core\Database;

class Mapping
{
    public static function paginate(int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        $pdo = Database::connection();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM category_mapping')->fetchColumn();
        $sql = 'SELECT * FROM category_mapping ORDER BY updated_at DESC, id DESC LIMIT :limit OFFSET :offset';
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

    public static function syncFromCategories(): void
    {
        $pdo = Database::connection();
        $sql = "INSERT INTO category_mapping (source_category_id, source_full_path, local_path_candidate, local_path_final, mapping_status, notes, created_at, updated_at)
                SELECT sc.source_category_id, sc.full_path, sc.local_path_candidate, NULL, sc.mapping_status, NULL, NOW(), NOW()
                FROM source_categories sc
                LEFT JOIN category_mapping cm ON cm.source_category_id = sc.source_category_id
                WHERE cm.id IS NULL";
        $pdo->exec($sql);
    }
}
