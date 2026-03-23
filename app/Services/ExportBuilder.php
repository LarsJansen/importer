<?php
namespace App\Services;

use App\Core\Database;

class ExportBuilder
{
    public static function preview(?int $batchId, ?string $branch = null): array
    {
        if ($batchId === null) {
            return [
                'categories' => 0,
                'sites' => 0,
            ];
        }

        [$categoryWhere, $categoryParams] = self::buildCategoryFilters($batchId, $branch);
        [$siteWhere, $siteParams] = self::buildSiteFilters($batchId, $branch);

        $pdo = Database::connection();

        $categorySql = "
            SELECT COUNT(*)
            FROM source_categories sc
            LEFT JOIN category_mapping cm
                ON cm.source_category_id = sc.source_category_id
            {$categoryWhere}
              AND sc.mapping_status = 'approved'
        ";
        $catStmt = $pdo->prepare($categorySql);
        $catStmt->execute($categoryParams);
        $categoryCount = (int) $catStmt->fetchColumn();

        $siteSql = "
            SELECT COUNT(*)
            FROM source_sites ss
            LEFT JOIN source_categories sc
                ON sc.id = ss.source_category_row_id
            LEFT JOIN category_mapping cm
                ON cm.source_category_id = ss.source_category_id
            {$siteWhere}
              AND ss.import_status = 'approved'
              AND ss.duplicate_flag = 0
              AND ss.source_category_row_id IS NOT NULL
              AND sc.mapping_status = 'approved'
        ";
        $siteStmt = $pdo->prepare($siteSql);
        $siteStmt->execute($siteParams);
        $siteCount = (int) $siteStmt->fetchColumn();

        return [
            'categories' => $categoryCount,
            'sites' => $siteCount,
        ];
    }

public static function generate(?int $batchId = null, ?string $branch = null): array
    {
        $config = require __DIR__ . '/../../config/config.php';
        $categoryData = self::buildCategoryExportData($batchId, $branch);

        $filename = 'curlie_export_' . date('Ymd_His') . '.sql';
        $fullPath = rtrim($config['paths']['exports'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        $tempBodyPath = tempnam(sys_get_temp_dir(), 'curlie_export_body_');
        if ($tempBodyPath === false) {
            throw new \RuntimeException('Failed to create temporary export file.');
        }

        $bodyHandle = fopen($tempBodyPath, 'wb');
        if (!$bodyHandle) {
            @unlink($tempBodyPath);
            throw new \RuntimeException('Failed to open temporary export file.');
        }

        $categoryCount = 0;
        foreach ($categoryData['categories'] as $category) {
            $pathSql = self::sql($category['path']);
            $parentIdExpr = 'NULL';
            if (!empty($category['parent_path'])) {
                $parentIdExpr = '(SELECT id FROM categories WHERE path = ' . self::sql($category['parent_path']) . ' LIMIT 1)';
            }

            fwrite($bodyHandle, "INSERT INTO categories (parent_id, slug, path, name, description, sort_order, is_active, source_type, source_key, import_batch_id, created_at, updated_at)\n");
            fwrite($bodyHandle, "SELECT {$parentIdExpr}, " .
                self::sql($category['slug']) . ", " .
                $pathSql . ", " .
                self::sql($category['name']) . ", " .
                self::sql($category['description']) . ", 0, 1, 'dmoz_import', " .
                self::sql($category['source_key']) . ", @import_batch_id, NOW(), NOW()\n");
            fwrite($bodyHandle, "WHERE NOT EXISTS (SELECT 1 FROM categories WHERE path = {$pathSql} LIMIT 1)\n");
            fwrite($bodyHandle, "ON DUPLICATE KEY UPDATE id = id;\n\n");
            $categoryCount++;
        }

        $pdo = Database::connection();
        [$siteWhere, $siteParams] = self::buildSiteFilters($batchId, $branch);
        $siteSql = "
            SELECT
                ss.id,
                ss.source_category_id,
                ss.url,
                ss.normalized_url,
                ss.title,
                ss.description_raw,
                sc.local_path_candidate AS source_local_path_candidate,
                cm.local_path_candidate AS mapping_local_path_candidate,
                cm.local_path_final
            FROM source_sites ss
            INNER JOIN source_categories sc
                ON sc.id = ss.source_category_row_id
            LEFT JOIN category_mapping cm
                ON cm.source_category_id = ss.source_category_id
            {$siteWhere}
              AND ss.import_status = 'approved'
              AND ss.duplicate_flag = 0
              AND ss.source_category_row_id IS NOT NULL
              AND sc.mapping_status = 'approved'
            ORDER BY ss.id ASC
        ";
        $stmt = $pdo->prepare($siteSql);
        $stmt->execute($siteParams);

        $sitesCount = 0;
        $seenNormalized = [];
        $usedSlugs = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $categoryPath = self::resolveExportPath(
                $row['source_local_path_candidate'] ?? null,
                $row['mapping_local_path_candidate'] ?? null,
                $row['local_path_final'] ?? null
            );

            if ($categoryPath === '' || !isset($categoryData['categories_by_path'][$categoryPath])) {
                continue;
            }

            $normalizedUrl = UrlNormalizer::normalize((string) (($row['normalized_url'] ?? '') ?: ($row['url'] ?? '')));
            if ($normalizedUrl === '') {
                continue;
            }

            if (isset($seenNormalized[$normalizedUrl])) {
                continue;
            }
            $seenNormalized[$normalizedUrl] = true;

            $baseSlug = self::slugify((string) $row['title']);
            if ($baseSlug === '') {
                $baseSlug = 'site-' . (int) $row['id'];
            }
            $slug = self::uniqueSlug($baseSlug, $categoryPath, $usedSlugs);

            $normalizedSql = self::sql($normalizedUrl);
            $categoryPathSql = self::sql($categoryPath);

            fwrite($bodyHandle, "INSERT INTO sites (category_id, title, slug, url, normalized_url, description, language_code, country_code, status, source_type, source_key, original_title, original_description, original_url, import_batch_id, submitted_by_user_id, is_reviewed, approved_at, is_active, created_at, updated_at)\n");
            fwrite($bodyHandle, "SELECT " .
                "(SELECT id FROM categories WHERE path = {$categoryPathSql} LIMIT 1), " .
                self::sql((string) $row['title']) . ", " .
                self::sql($slug) . ", " .
                self::sql((string) $row['url']) . ", " .
                $normalizedSql . ", " .
                self::sql($row['description_raw']) . ", NULL, NULL, 'active', 'dmoz_import', " .
                self::sql('curlie:' . (string) $row['source_category_id'] . ':' . (int) $row['id']) . ", " .
                self::sql((string) $row['title']) . ", " .
                self::sql($row['description_raw']) . ", " .
                self::sql((string) $row['url']) . ", @import_batch_id, NULL, 1, NOW(), 1, NOW(), NOW()\n");
            fwrite($bodyHandle, "WHERE EXISTS (SELECT 1 FROM categories WHERE path = {$categoryPathSql} LIMIT 1)\n");
            fwrite($bodyHandle, "  AND NOT EXISTS (SELECT 1 FROM sites WHERE normalized_url = {$normalizedSql} LIMIT 1)\n");
            fwrite($bodyHandle, "ON DUPLICATE KEY UPDATE id = id;\n\n");

            $sitesCount++;
        }

        fclose($bodyHandle);

        $finalHandle = fopen($fullPath, 'wb');
        if (!$finalHandle) {
            @unlink($tempBodyPath);
            throw new \RuntimeException('Failed to create export file: ' . $fullPath);
        }

        fwrite($finalHandle, "-- Curlie importer export\n");
        fwrite($finalHandle, "-- Generated at " . date('Y-m-d H:i:s') . "\n");
        fwrite($finalHandle, "-- Filters: batch_id=" . ($batchId ?? 'all') . ", branch=" . ($branch ?: 'all') . "\n");
        fwrite($finalHandle, "-- Export Summary\n");
        fwrite($finalHandle, "-- Categories: " . $categoryCount . "\n");
        fwrite($finalHandle, "-- Sites: " . $sitesCount . "\n");
        fwrite($finalHandle, "-- Leaf categories:\n");
        foreach ($categoryData['leaf_paths'] as $leafPath) {
            fwrite($finalHandle, "--   " . $leafPath . "\n");
        }
        fwrite($finalHandle, "\nSTART TRANSACTION;\n\n");

        fwrite($finalHandle, "-- Create live import batch record\n");
        fwrite($finalHandle, "INSERT INTO import_batches (source_name, source_version, batch_label, notes, status, total_categories, total_sites, started_at, completed_at)\n");
        fwrite($finalHandle, "VALUES ('curlie', NULL, " . self::sql('Curlie export ' . date('Y-m-d H:i:s')) . ", " . self::sql('Generated by standalone Curlie importer') . ", 'completed', " . $categoryCount . ", " . $sitesCount . ", NOW(), NOW());\n");
        fwrite($finalHandle, "SET @import_batch_id = LAST_INSERT_ID();\n\n");

        fwrite($finalHandle, "-- Categories\n");
        $bodyReadHandle = fopen($tempBodyPath, 'rb');
        if (!$bodyReadHandle) {
            fclose($finalHandle);
            @unlink($tempBodyPath);
            throw new \RuntimeException('Failed to reopen temporary export file.');
        }
        stream_copy_to_stream($bodyReadHandle, $finalHandle);
        fclose($bodyReadHandle);

        fwrite($finalHandle, "COMMIT;\n");
        fclose($finalHandle);
        @unlink($tempBodyPath);

        return [
            'batch_id' => $batchId,
            'filename' => $filename,
            'full_path' => $fullPath,
            'categories_count' => $categoryCount,
            'sites_count' => $sitesCount,
        ];
    }

public static function writeSql(?int $batchId = null, ?string $branch = null): array
    {
        return self::generate($batchId, $branch);
    }

    private static function buildCategoryExportData(?int $batchId, ?string $branch): array
    {
        $pdo = Database::connection();

        [$categoryWhere, $categoryParams] = self::buildCategoryFilters($batchId, $branch);
        $categorySql = "
            SELECT
                sc.source_category_id,
                sc.category_name,
                sc.description_raw,
                sc.local_path_candidate AS source_local_path_candidate,
                cm.local_path_candidate AS mapping_local_path_candidate,
                cm.local_path_final
            FROM source_categories sc
            LEFT JOIN category_mapping cm
                ON cm.source_category_id = sc.source_category_id
            {$categoryWhere}
              AND sc.mapping_status = 'approved'
            ORDER BY sc.path_depth ASC, sc.full_path ASC
        ";

        $stmt = $pdo->prepare($categorySql);
        $stmt->execute($categoryParams);

        $categoriesByPath = [];
        $leafPaths = [];
        $sourceCategoryByExportPath = [];
        $duplicateExportPaths = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $exportPath = self::resolveExportPath(
                $row['source_local_path_candidate'] ?? null,
                $row['mapping_local_path_candidate'] ?? null,
                $row['local_path_final'] ?? null
            );

            if ($exportPath === '') {
                continue;
            }

            if (!isset($sourceCategoryByExportPath[$exportPath])) {
                $sourceCategoryByExportPath[$exportPath] = [];
            }

            $sourceCategoryByExportPath[$exportPath][] = (string) $row['source_category_id'];

            if (count(array_unique($sourceCategoryByExportPath[$exportPath])) > 1) {
                $duplicateExportPaths[$exportPath] = array_values(array_unique($sourceCategoryByExportPath[$exportPath]));
                continue;
            }
            $leafPaths[$exportPath] = true;

            $segments = explode('/', $exportPath);
            $build = [];

            foreach ($segments as $index => $segment) {
                $build[] = $segment;
                $path = implode('/', $build);

                if (!isset($categoriesByPath[$path])) {
                    $categoriesByPath[$path] = [
                        'path' => $path,
                        'parent_path' => $index > 0 ? implode('/', array_slice($build, 0, -1)) : null,
                        'slug' => self::slugify($segment),
                        'name' => self::titleize($segment),
                        'description' => null,
                        'source_key' => null,
                        'depth' => $index + 1,
                    ];
                }
            }

            $categoriesByPath[$exportPath]['name'] = trim((string) $row['category_name']) !== ''
                ? trim((string) $row['category_name'])
                : $categoriesByPath[$exportPath]['name'];
            $categoriesByPath[$exportPath]['description'] = $row['description_raw'];
            $categoriesByPath[$exportPath]['source_key'] = (string) $row['source_category_id'];
        }

        if (!empty($duplicateExportPaths)) {
            $samples = [];
            foreach ($duplicateExportPaths as $path => $sourceIds) {
                $samples[] = $path . ' <- source_category_id(s): ' . implode(', ', array_values(array_unique($sourceIds)));
                if (count($samples) >= 20) {
                    break;
                }
            }

            throw new \RuntimeException(
                "Export aborted due to duplicate final category paths. Sample collisions:\n- "
                . implode("\n- ", $samples)
            );
        }

        uasort($categoriesByPath, function (array $a, array $b): int {
            if ($a['depth'] === $b['depth']) {
                return strcmp($a['path'], $b['path']);
            }
            return $a['depth'] <=> $b['depth'];
        });

        return [
            'categories' => array_values($categoriesByPath),
            'categories_by_path' => $categoriesByPath,
            'leaf_paths' => array_keys($leafPaths),
        ];
    }

private static function buildData(?int $batchId, ?string $branch): array
    {
        $pdo = Database::connection();

        [$categoryWhere, $categoryParams] = self::buildCategoryFilters($batchId, $branch);
        $categorySql = "
            SELECT
                sc.source_category_id,
                sc.category_name,
                sc.description_raw,
                sc.local_path_candidate AS source_local_path_candidate,
                cm.local_path_candidate AS mapping_local_path_candidate,
                cm.local_path_final
            FROM source_categories sc
            LEFT JOIN category_mapping cm
                ON cm.source_category_id = sc.source_category_id
            {$categoryWhere}
              AND sc.mapping_status = 'approved'
            ORDER BY sc.path_depth ASC, sc.full_path ASC
        ";

        $stmt = $pdo->prepare($categorySql);
        $stmt->execute($categoryParams);
        $categoryRows = $stmt->fetchAll();

        $categoriesByPath = [];
        $leafPaths = [];

        foreach ($categoryRows as $row) {
            $exportPath = self::resolveExportPath(
                $row['source_local_path_candidate'] ?? null,
                $row['mapping_local_path_candidate'] ?? null,
                $row['local_path_final'] ?? null
            );

            if ($exportPath === '') {
                continue;
            }

            $leafPaths[$exportPath] = true;

            $segments = explode('/', $exportPath);
            $build = [];

            foreach ($segments as $index => $segment) {
                $build[] = $segment;
                $path = implode('/', $build);

                if (!isset($categoriesByPath[$path])) {
                    $categoriesByPath[$path] = [
                        'path' => $path,
                        'parent_path' => $index > 0 ? implode('/', array_slice($build, 0, -1)) : null,
                        'slug' => self::slugify($segment),
                        'name' => self::titleize($segment),
                        'description' => null,
                        'source_key' => null,
                        'depth' => $index + 1,
                    ];
                }
            }

            $categoriesByPath[$exportPath]['name'] = trim((string) $row['category_name']) !== ''
                ? trim((string) $row['category_name'])
                : $categoriesByPath[$exportPath]['name'];
            $categoriesByPath[$exportPath]['description'] = $row['description_raw'];
            $categoriesByPath[$exportPath]['source_key'] = (string) $row['source_category_id'];
        }

        uasort($categoriesByPath, function (array $a, array $b): int {
            if ($a['depth'] === $b['depth']) {
                return strcmp($a['path'], $b['path']);
            }
            return $a['depth'] <=> $b['depth'];
        });

        [$siteWhere, $siteParams] = self::buildSiteFilters($batchId, $branch);
        $siteSql = "
            SELECT
                ss.id,
                ss.source_category_id,
                ss.url,
                ss.normalized_url,
                ss.title,
                ss.description_raw,
                sc.local_path_candidate AS source_local_path_candidate,
                cm.local_path_candidate AS mapping_local_path_candidate,
                cm.local_path_final
            FROM source_sites ss
            INNER JOIN source_categories sc
                ON sc.id = ss.source_category_row_id
            LEFT JOIN category_mapping cm
                ON cm.source_category_id = ss.source_category_id
            {$siteWhere}
              AND ss.import_status = 'approved'
              AND ss.duplicate_flag = 0
              AND ss.source_category_row_id IS NOT NULL
              AND sc.mapping_status = 'approved'
            ORDER BY ss.id ASC
        ";

        $stmt = $pdo->prepare($siteSql);
        $stmt->execute($siteParams);
        $siteRows = $stmt->fetchAll();

        $sites = [];
        $seenNormalized = [];
        $usedSlugs = [];

        foreach ($siteRows as $row) {
            $categoryPath = self::resolveExportPath(
                $row['source_local_path_candidate'] ?? null,
                $row['mapping_local_path_candidate'] ?? null,
                $row['local_path_final'] ?? null
            );

            if ($categoryPath === '' || !isset($categoriesByPath[$categoryPath])) {
                continue;
            }

            $normalizedUrl = UrlNormalizer::normalize((string) (($row['normalized_url'] ?? '') ?: ($row['url'] ?? '')));
            if ($normalizedUrl === '') {
                continue;
            }

            if (isset($seenNormalized[$normalizedUrl])) {
                continue;
            }
            $seenNormalized[$normalizedUrl] = true;

            $baseSlug = self::slugify((string) $row['title']);
            if ($baseSlug === '') {
                $baseSlug = 'site-' . (int) $row['id'];
            }
            $slug = self::uniqueSlug($baseSlug, $categoryPath, $usedSlugs);

            $sites[] = [
                'id' => (int) $row['id'],
                'category_path' => $categoryPath,
                'title' => (string) $row['title'],
                'slug' => $slug,
                'url' => (string) $row['url'],
                'normalized_url' => $normalizedUrl,
                'description' => $row['description_raw'],
                'source_key' => 'curlie:' . (string) $row['source_category_id'] . ':' . (int) $row['id'],
            ];
        }

        return [
            'categories' => array_values($categoriesByPath),
            'sites' => $sites,
            'leaf_paths' => array_keys($leafPaths),
        ];
    }

    private static function uniqueSlug(string $baseSlug, string $categoryPath, array &$usedSlugs): string
    {
        $key = $categoryPath . '|' . $baseSlug;

        if (!isset($usedSlugs[$key])) {
            $usedSlugs[$key] = 1;
            return $baseSlug;
        }

        $usedSlugs[$key]++;
        return $baseSlug . '-' . $usedSlugs[$key];
    }

    private static function buildCategoryFilters(?int $batchId, ?string $branch): array
    {
        $clauses = ['WHERE 1=1'];
        $params = [];

        if ($batchId !== null) {
            $clauses[] = 'AND sc.batch_id = :batch_id';
            $params['batch_id'] = $batchId;
        }

        if ($branch !== null && $branch !== '') {
            $clauses[] = 'AND sc.top_branch = :branch';
            $params['branch'] = $branch;
        }

        return [implode(' ', $clauses), $params];
    }

    private static function buildSiteFilters(?int $batchId, ?string $branch): array
    {
        $clauses = ['WHERE 1=1'];
        $params = [];

        if ($batchId !== null) {
            $clauses[] = 'AND ss.batch_id = :batch_id';
            $params['batch_id'] = $batchId;
        }

        if ($branch !== null && $branch !== '') {
            $clauses[] = 'AND sc.top_branch = :branch';
            $params['branch'] = $branch;
        }

        return [implode(' ', $clauses), $params];
    }


    private static function resolveExportPath(?string $sourceLocalPathCandidate, ?string $mappingLocalPathCandidate, ?string $localPathFinal): string
    {
        $source = self::normalizePath($sourceLocalPathCandidate);
        $mapping = self::normalizePath($mappingLocalPathCandidate);
        $final = self::normalizePath($localPathFinal);

        // Prefer the full source-derived path candidate so exported category paths
        // remain stable and do not collapse into abbreviated tokens such as "cat".
        // Fallback to mapping fields only when the source candidate is unavailable.
        if ($source !== '') {
            return $source;
        }

        if ($final !== '') {
            return $final;
        }

        return $mapping;
    }

    private static function normalizePath(?string $path): string
    {
        $path = trim((string) $path);
        $path = trim($path, '/');
        $path = preg_replace('#/+#', '/', $path);
        return strtolower((string) $path);
    }

    private static function slugify(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim((string) $value, '-');
        return $value;
    }

    private static function titleize(string $value): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $value));
    }

    private static function sql($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return Database::connection()->quote((string) $value);
    }
}
