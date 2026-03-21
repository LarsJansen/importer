<?php
require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Batch;
use App\Services\UrlNormalizer;

$options = getopt('', ['categories::', 'sites::', 'dir::']);

$categoryFiles = [];
$siteFiles = [];

if (!empty($options['dir'])) {
    $dir = rtrim($options['dir'], DIRECTORY_SEPARATOR);
    if (!is_dir($dir)) {
        fwrite(STDERR, "Directory not found: {$dir}\n");
        exit(1);
    }

    foreach (glob($dir . DIRECTORY_SEPARATOR . '*-s.tsv') as $file) {
        $categoryFiles[] = $file;
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*-c.tsv') as $file) {
        $siteFiles[] = $file;
    }
}

if (!empty($options['categories'])) {
    $categoryFiles = array_merge($categoryFiles, array_map('trim', explode(',', $options['categories'])));
}
if (!empty($options['sites'])) {
    $siteFiles = array_merge($siteFiles, array_map('trim', explode(',', $options['sites'])));
}

$categoryFiles = array_values(array_unique(array_filter($categoryFiles)));
$siteFiles = array_values(array_unique(array_filter($siteFiles)));

if (empty($categoryFiles) && empty($siteFiles)) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php scripts/import_curlie_tsv.php --dir=\"C:\\path\\to\\curlie-data\"\n");
    fwrite(STDERR, "  php scripts/import_curlie_tsv.php --categories=\"C:\\path\\rdf-Society-s.tsv\" --sites=\"C:\\path\\rdf-Society-c.tsv\"\n");
    exit(1);
}

$pdo = Database::connection();

$label = 'Curlie TSV Import ' . date('Y-m-d H:i:s');
$batchId = Batch::create([
    'source_name' => 'curlie',
    'label' => $label,
    'status' => 'running',
    'notes' => null,
]);

echo "Starting batch #{$batchId}: {$label}\n";

$counts = [
    'categories_imported' => 0,
    'sites_imported' => 0,
    'errors_count' => 0,
];

try {
    foreach ($categoryFiles as $file) {
        echo "Importing categories from {$file}\n";
        [$imported, $skipped] = importCategoriesFile($pdo, $batchId, $file);
        $counts['categories_imported'] += $imported;
        echo "  Imported {$imported} category rows, skipped {$skipped}\n";
    }

    rebuildCategoryMappings($pdo, $batchId);

    foreach ($siteFiles as $file) {
        echo "Importing sites from {$file}\n";
        [$imported, $skipped] = importSitesFile($pdo, $batchId, $file);
        $counts['sites_imported'] += $imported;
        echo "  Imported {$imported} site rows, skipped {$skipped}\n";
    }

    Batch::complete($batchId, $counts, 'completed');
    echo "Done. Categories: {$counts['categories_imported']}, Sites: {$counts['sites_imported']}, Errors: {$counts['errors_count']}\n";
} catch (\Throwable $e) {
    $counts['errors_count']++;
    Batch::complete($batchId, $counts, 'failed');
    fwrite(STDERR, "Import failed: " . $e->getMessage() . "\n");
    exit(1);
}

function importCategoriesFile(\PDO $pdo, int $batchId, string $file): array
{
    if (!is_file($file)) {
        throw new \RuntimeException("Category file not found: {$file}");
    }

    $handle = fopen($file, 'rb');
    if (!$handle) {
        throw new \RuntimeException("Could not open category file: {$file}");
    }

    $imported = 0;
    $skipped = 0;

    $sql = "INSERT INTO source_categories
        (batch_id, source_category_id, full_path, category_name, parent_path, path_depth, entry_count, description_raw, geo_raw, geo_lat, geo_lng, top_branch, local_path_candidate, mapping_status, created_at, updated_at)
        VALUES
        (:batch_id, :source_category_id, :full_path, :category_name, :parent_path, :path_depth, :entry_count, :description_raw, :geo_raw, :geo_lat, :geo_lng, :top_branch, :local_path_candidate, :mapping_status, NOW(), NOW())";
    $stmt = $pdo->prepare($sql);

    while (($row = fgetcsv($handle, 0, "\t")) !== false) {
        if ($row === [null] || count($row) < 2) {
            $skipped++;
            continue;
        }

        $sourceCategoryId = trim((string)($row[0] ?? ''));
        $fullPath = trim((string)($row[1] ?? ''));
        if ($sourceCategoryId === '' || $fullPath === '') {
            $skipped++;
            continue;
        }

        $entryCount = (int) trim((string)($row[2] ?? '0'));
        $description = (string)($row[3] ?? null);
        $geoRaw = trim((string)($row[4] ?? ''));
        [$geoLat, $geoLng] = parseGeo($geoRaw);

        $parts = array_values(array_filter(array_map('trim', explode('/', $fullPath)), 'strlen'));
        $categoryName = $parts ? end($parts) : $fullPath;
        $parentPath = count($parts) > 1 ? implode('/', array_slice($parts, 0, -1)) : null;
        $pathDepth = count($parts);
        $topBranch = $parts[0] ?? null;
        $localPathCandidate = strtolower(implode('/', array_map('slugifyPathPart', $parts)));

        $stmt->execute([
            'batch_id' => $batchId,
            'source_category_id' => $sourceCategoryId,
            'full_path' => $fullPath,
            'category_name' => $categoryName,
            'parent_path' => $parentPath,
            'path_depth' => $pathDepth,
            'entry_count' => $entryCount,
            'description_raw' => $description !== '' ? $description : null,
            'geo_raw' => $geoRaw !== '' ? $geoRaw : null,
            'geo_lat' => $geoLat,
            'geo_lng' => $geoLng,
            'top_branch' => $topBranch,
            'local_path_candidate' => $localPathCandidate !== '' ? $localPathCandidate : null,
            'mapping_status' => 'pending',
        ]);

        $imported++;
    }

    fclose($handle);
    return [$imported, $skipped];
}

function rebuildCategoryMappings(\PDO $pdo, int $batchId): void
{
    $sql = "INSERT INTO category_mapping
        (source_category_id, source_full_path, local_path_candidate, local_path_final, mapping_status, notes, created_at, updated_at)
        SELECT sc.source_category_id, sc.full_path, sc.local_path_candidate, NULL, sc.mapping_status, NULL, NOW(), NOW()
        FROM source_categories sc
        LEFT JOIN category_mapping cm ON cm.source_category_id = sc.source_category_id
        WHERE sc.batch_id = :batch_id
          AND cm.id IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['batch_id' => $batchId]);
}

function importSitesFile(\PDO $pdo, int $batchId, string $file): array
{
    if (!is_file($file)) {
        throw new \RuntimeException("Site file not found: {$file}");
    }

    $handle = fopen($file, 'rb');
    if (!$handle) {
        throw new \RuntimeException("Could not open site file: {$file}");
    }

    $imported = 0;
    $skipped = 0;

    $findCategoryStmt = $pdo->prepare(
        "SELECT id
         FROM source_categories
         WHERE batch_id = :batch_id AND source_category_id = :source_category_id
         ORDER BY id DESC
         LIMIT 1"
    );

    $findDupStmt = $pdo->prepare(
        "SELECT id
         FROM source_sites
         WHERE normalized_url = :normalized_url
         LIMIT 1"
    );

    $insertStmt = $pdo->prepare(
        "INSERT INTO source_sites
        (batch_id, source_category_id, source_category_row_id, url, normalized_url, title, description_raw, http_scheme, import_status, duplicate_flag, notes, created_at, updated_at)
        VALUES
        (:batch_id, :source_category_id, :source_category_row_id, :url, :normalized_url, :title, :description_raw, :http_scheme, :import_status, :duplicate_flag, :notes, NOW(), NOW())"
    );

    while (($row = fgetcsv($handle, 0, "\t")) !== false) {
        if ($row === [null] || count($row) < 4) {
            $skipped++;
            continue;
        }

        $url = trim((string)($row[0] ?? ''));
        $title = trim((string)($row[1] ?? ''));
        $description = (string)($row[2] ?? null);
        $sourceCategoryId = trim((string)($row[3] ?? ''));

        if ($url === '' || $sourceCategoryId === '') {
            $skipped++;
            continue;
        }

        $normalizedUrl = UrlNormalizer::normalize($url);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $scheme = $scheme ? strtolower((string)$scheme) : null;

        $findCategoryStmt->execute([
            'batch_id' => $batchId,
            'source_category_id' => $sourceCategoryId,
        ]);
        $sourceCategoryRowId = $findCategoryStmt->fetchColumn();
        $sourceCategoryRowId = $sourceCategoryRowId ? (int)$sourceCategoryRowId : null;

        $duplicateFlag = 0;
        if ($normalizedUrl !== '') {
            $findDupStmt->execute(['normalized_url' => $normalizedUrl]);
            $existingId = $findDupStmt->fetchColumn();
            if ($existingId) {
                $duplicateFlag = 1;
            }
        }

        $insertStmt->execute([
            'batch_id' => $batchId,
            'source_category_id' => $sourceCategoryId,
            'source_category_row_id' => $sourceCategoryRowId,
            'url' => $url,
            'normalized_url' => $normalizedUrl !== '' ? $normalizedUrl : $url,
            'title' => $title !== '' ? $title : $url,
            'description_raw' => $description !== '' ? $description : null,
            'http_scheme' => $scheme,
            'import_status' => 'pending',
            'duplicate_flag' => $duplicateFlag,
            'notes' => null,
        ]);

        $imported++;
    }

    fclose($handle);
    return [$imported, $skipped];
}

function parseGeo(string $geoRaw): array
{
    if ($geoRaw === '') {
        return [null, null];
    }

    $parts = preg_split('/[\s,]+/', trim($geoRaw));
    if (!$parts || count($parts) < 2) {
        return [null, null];
    }

    $lat = is_numeric($parts[0]) ? (float)$parts[0] : null;
    $lng = is_numeric($parts[1]) ? (float)$parts[1] : null;

    return [$lat, $lng];
}

function slugifyPathPart(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
    return trim((string)$value, '-');
}
