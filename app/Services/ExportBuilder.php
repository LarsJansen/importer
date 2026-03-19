<?php
namespace App\Services;

use App\Core\Database;

class ExportBuilder
{
    public static function preview(?int $batchId = null, ?string $branch = null): array
    {
        $pdo = Database::connection();
        [$catWhere, $catParams] = self::buildCategoryWhere($batchId, $branch);
        [$siteWhere, $siteParams] = self::buildSiteWhere($batchId, $branch);

        $catStmt = $pdo->prepare("SELECT COUNT(*) FROM source_categories sc {$catWhere}");
        $catStmt->execute($catParams);
        $categories = (int) $catStmt->fetchColumn();

        $siteStmt = $pdo->prepare("SELECT COUNT(*)
            FROM source_sites ss
            INNER JOIN source_categories sc ON sc.source_category_id = ss.source_category_id AND sc.batch_id = ss.batch_id
            {$siteWhere}");
        $siteStmt->execute($siteParams);
        $sites = (int) $siteStmt->fetchColumn();

        return ['categories' => $categories, 'sites' => $sites];
    }

    public static function generate(?int $batchId = null, ?string $branch = null): array
    {
        $config = app_config();
        $pdo = Database::connection();

        [$catWhere, $catParams] = self::buildCategoryWhere($batchId, $branch);
        [$siteWhere, $siteParams] = self::buildSiteWhere($batchId, $branch);

        $catSql = "SELECT sc.*
            FROM source_categories sc
            {$catWhere}
            ORDER BY sc.path_depth ASC, sc.local_path_candidate ASC";
        $catStmt = $pdo->prepare($catSql);
        $catStmt->execute($catParams);
        $categories = $catStmt->fetchAll();

        $siteSql = "SELECT ss.*, sc.local_path_candidate, sc.category_name, sc.full_path
            FROM source_sites ss
            INNER JOIN source_categories sc ON sc.source_category_id = ss.source_category_id AND sc.batch_id = ss.batch_id
            {$siteWhere}
            ORDER BY sc.local_path_candidate ASC, ss.id ASC";
        $siteStmt = $pdo->prepare($siteSql);
        $siteStmt->execute($siteParams);
        $sites = $siteStmt->fetchAll();

        $filename = self::buildFilename($batchId, $branch);
        $fullPath = rtrim($config['paths']['exports'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $fh = fopen($fullPath, 'wb');
        if (!$fh) {
            throw new \RuntimeException('Failed to write export file.');
        }

        fwrite($fh, "-- Curlie Importer Lab Phase 3 export\n");
        fwrite($fh, "-- Generated at " . date('Y-m-d H:i:s') . "\n");
        fwrite($fh, "-- Batch filter: " . ($batchId ? (string) $batchId : 'all approved batches') . "\n");
        fwrite($fh, "-- Branch filter: " . ($branch ?: 'all branches') . "\n");
        fwrite($fh, "-- Descriptions: kept as-is, including HTML\n");
        fwrite($fh, "-- IMPORTANT: This SQL assumes your live directory schema has:\n");
        fwrite($fh, "--   categories(name, path, parent_id, description, is_active, created_at, updated_at)\n");
        fwrite($fh, "--   sites(category_id, title, url, normalized_url, description, status, is_active, created_at, updated_at)\n\n");
        fwrite($fh, "START TRANSACTION;\n\n");

        fwrite($fh, "-- APPROVED CATEGORIES\n");
        foreach ($categories as $row) {
            $path = $row['local_path_candidate'];
            $name = $row['category_name'];
            $desc = $row['description_raw'];
            $parentPath = $row['parent_path'] ? self::slugPath($row['parent_path']) : self::parentPathFromCandidate($path);
            fwrite($fh, self::categoryInsertSql($name, $path, $parentPath, $desc));
        }

        fwrite($fh, "\n-- APPROVED SITES\n");
        foreach ($sites as $row) {
            fwrite($fh, self::siteInsertSql($row));
        }

        fwrite($fh, "\nCOMMIT;\n");
        fclose($fh);

        return [
            'filename' => $filename,
            'full_path' => $fullPath,
            'categories_count' => count($categories),
            'sites_count' => count($sites),
            'batch_id' => $batchId,
        ];
    }

    private static function buildFilename(?int $batchId, ?string $branch): string
    {
        $parts = ['curlie_export', date('Ymd_His')];
        if ($batchId) {
            $parts[] = 'batch' . $batchId;
        }
        if ($branch) {
            $parts[] = preg_replace('/[^a-z0-9]+/i', '-', strtolower($branch));
        }
        return implode('_', $parts) . '.sql';
    }

    private static function buildCategoryWhere(?int $batchId, ?string $branch): array
    {
        $clauses = ["sc.mapping_status = 'approved'"];
        $params = [];
        if ($batchId) {
            $clauses[] = 'sc.batch_id = :batch_id';
            $params['batch_id'] = $batchId;
        }
        if ($branch) {
            $clauses[] = 'sc.top_branch = :branch';
            $params['branch'] = $branch;
        }
        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    private static function buildSiteWhere(?int $batchId, ?string $branch): array
    {
        $clauses = [
            "ss.import_status = 'approved'",
            "ss.duplicate_flag = 0",
            "ss.source_category_row_id IS NOT NULL",
            "sc.mapping_status = 'approved'",
        ];
        $params = [];
        if ($batchId) {
            $clauses[] = 'ss.batch_id = :batch_id';
            $params['batch_id'] = $batchId;
        }
        if ($branch) {
            $clauses[] = 'sc.top_branch = :branch';
            $params['branch'] = $branch;
        }
        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    private static function categoryInsertSql(string $name, string $path, ?string $parentPath, ?string $description): string
    {
        $nameSql = self::sql($name);
        $pathSql = self::sql($path);
        $descSql = self::sqlNullable($description);
        if ($parentPath) {
            $parentSql = self::sql($parentPath);
            return "INSERT INTO categories (name, path, parent_id, description, is_active, created_at, updated_at)\n"
                . "SELECT {$nameSql}, {$pathSql}, p.id, {$descSql}, 1, NOW(), NOW()\n"
                . "FROM categories p\n"
                . "WHERE p.path = {$parentSql}\n"
                . "  AND NOT EXISTS (SELECT 1 FROM categories existing WHERE existing.path = {$pathSql});\n\n";
        }

        return "INSERT INTO categories (name, path, parent_id, description, is_active, created_at, updated_at)\n"
            . "SELECT {$nameSql}, {$pathSql}, NULL, {$descSql}, 1, NOW(), NOW()\n"
            . "FROM DUAL\n"
            . "WHERE NOT EXISTS (SELECT 1 FROM categories existing WHERE existing.path = {$pathSql});\n\n";
    }

    private static function siteInsertSql(array $row): string
    {
        $categoryPathSql = self::sql($row['local_path_candidate']);
        $titleSql = self::sql($row['title']);
        $urlSql = self::sql($row['url']);
        $normSql = self::sql($row['normalized_url']);
        $descSql = self::sqlNullable($row['description_raw']);

        return "INSERT INTO sites (category_id, title, url, normalized_url, description, status, is_active, created_at, updated_at)\n"
            . "SELECT c.id, {$titleSql}, {$urlSql}, {$normSql}, {$descSql}, 'active', 1, NOW(), NOW()\n"
            . "FROM categories c\n"
            . "WHERE c.path = {$categoryPathSql}\n"
            . "  AND NOT EXISTS (SELECT 1 FROM sites existing WHERE existing.normalized_url = {$normSql});\n\n";
    }

    private static function sql(string $value): string
    {
        return Database::connection()->quote($value);
    }

    private static function sqlNullable(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }
        return self::sql($value);
    }

    private static function parentPathFromCandidate(string $path): ?string
    {
        if (!str_contains($path, '/')) {
            return null;
        }
        return substr($path, 0, strrpos($path, '/'));
    }

    private static function slugPath(string $sourcePath): string
    {
        $parts = array_filter(array_map('trim', explode('/', $sourcePath)), fn ($part) => $part !== '');
        $slugged = array_map(function ($part) {
            $part = strtolower($part);
            $part = preg_replace('/[^a-z0-9]+/i', '-', $part);
            return trim($part, '-');
        }, $parts);
        return implode('/', array_filter($slugged, fn ($part) => $part !== ''));
    }
}
