<?php
require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Models\Batch;
use App\Models\ImportFile;
use App\Models\SourceCategory;
use App\Models\SourceSite;

function normalize_url(string $url): string
{
    $url = trim($url);
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return $url;
    }

    $scheme = strtolower($parts['scheme'] ?? 'http');
    $host = strtolower($parts['host']);
    $path = $parts['path'] ?? '/';
    $path = $path === '' ? '/' : $path;
    $path = preg_replace('#/+#', '/', $path);
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $port = isset($parts['port']) ? (int) $parts['port'] : null;
    $defaultPort = ($scheme === 'https') ? 443 : 80;
    $portPart = ($port && $port !== $defaultPort) ? ':' . $port : '';

    return $scheme . '://' . $host . $portPart . $path . $query;
}

function slugify_segment(string $segment): string
{
    $segment = trim($segment);
    $segment = html_entity_decode($segment, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $segment = strtolower($segment);
    $segment = preg_replace('/[^a-z0-9]+/i', '-', $segment);
    $segment = trim($segment, '-');
    return $segment === '' ? 'untitled' : $segment;
}

function build_local_path_candidate(string $fullPath): string
{
    $segments = array_filter(array_map('trim', explode('/', $fullPath)), fn($v) => $v !== '');
    $segments = array_map('slugify_segment', $segments);
    return implode('/', $segments);
}

function parse_geo(?string $geoRaw): array
{
    $geoRaw = trim((string) $geoRaw);
    if ($geoRaw === '') {
        return [null, null];
    }

    $parts = preg_split('/\s+|,/', $geoRaw);
    $parts = array_values(array_filter($parts, fn($v) => $v !== ''));
    if (count($parts) < 2) {
        return [null, null];
    }

    return [is_numeric($parts[0]) ? (float) $parts[0] : null, is_numeric($parts[1]) ? (float) $parts[1] : null];
}

function parse_args(array $argv): array
{
    $args = [];
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', $arg, 2);
            $key = ltrim($parts[0], '-');
            $args[$key] = $parts[1] ?? true;
        }
    }
    return $args;
}

$args = parse_args($argv);
$dir = $args['dir'] ?? null;
$categoriesFile = $args['categories'] ?? null;
$sitesFile = $args['sites'] ?? null;
$label = $args['label'] ?? null;

if (!$dir && (!$categoriesFile || !$sitesFile)) {
    echo "Usage:\n";
    echo "  php scripts/import_curlie_tsv.php --dir=\"C:\\path\\to\\folder\"\n";
    echo "  php scripts/import_curlie_tsv.php --categories=\"C:\\path\\rdf-Society-s.tsv\" --sites=\"C:\\path\\rdf-Society-c.tsv\"\n";
    exit(1);
}

$categoryFiles = [];
$siteFiles = [];

if ($dir) {
    $dir = rtrim($dir, DIRECTORY_SEPARATOR);
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*-s.tsv') as $file) {
        $categoryFiles[] = $file;
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*-c.tsv') as $file) {
        $siteFiles[] = $file;
    }
} else {
    $categoryFiles[] = $categoriesFile;
    $siteFiles[] = $sitesFile;
}

sort($categoryFiles);
sort($siteFiles);

$label = $label ?: ('Curlie TSV Import ' . date('Y-m-d H:i:s'));
$batchId = Batch::create([
    'source_name' => 'curlie',
    'label' => $label,
    'status' => 'running',
    'notes' => $dir ? ('Imported from folder: ' . $dir) : 'Imported from explicit file pair(s).',
]);

echo "Starting batch #{$batchId}: {$label}\n";
$pdo = Database::connection();
$errors = 0;
$totalCategories = 0;
$totalSites = 0;
$categoryLookup = [];
$normalizedSeen = [];

try {
    foreach ($categoryFiles as $file) {
        echo "Importing categories from {$file}\n";
        $handle = fopen($file, 'rb');
        if (!$handle) {
            throw new RuntimeException("Failed to open category file: {$file}");
        }

        $rowsRead = 0;
        $rowsImported = 0;
        $rowsSkipped = 0;

        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            $rowsRead++;
            $row = array_pad($row, 5, '');
            [$sourceCategoryId, $fullPath, $entryCount, $descriptionRaw, $geoRaw] = $row;
            $sourceCategoryId = trim((string) $sourceCategoryId);
            $fullPath = trim((string) $fullPath);
            $entryCount = trim((string) $entryCount);

            if ($sourceCategoryId === '' || $fullPath === '') {
                $rowsSkipped++;
                continue;
            }

            $segments = array_values(array_filter(array_map('trim', explode('/', $fullPath))));
            $categoryName = end($segments) ?: $fullPath;
            $parentPath = count($segments) > 1 ? implode('/', array_slice($segments, 0, -1)) : null;
            $pathDepth = count($segments) ?: 1;
            $topBranch = $segments[0] ?? null;
            [$geoLat, $geoLng] = parse_geo($geoRaw);
            $mappingStatus = 'pending';
            $localPathCandidate = build_local_path_candidate($fullPath);

            try {
                SourceCategory::insert([
                    'batch_id' => $batchId,
                    'source_category_id' => (int) $sourceCategoryId,
                    'full_path' => $fullPath,
                    'category_name' => $categoryName,
                    'parent_path' => $parentPath,
                    'path_depth' => $pathDepth,
                    'entry_count' => is_numeric($entryCount) ? (int) $entryCount : 0,
                    'description_raw' => $descriptionRaw !== '' ? $descriptionRaw : null,
                    'geo_raw' => trim((string) $geoRaw) !== '' ? trim((string) $geoRaw) : null,
                    'geo_lat' => $geoLat,
                    'geo_lng' => $geoLng,
                    'top_branch' => $topBranch,
                    'local_path_candidate' => $localPathCandidate,
                    'mapping_status' => $mappingStatus,
                ]);
                $categoryLookup[(int) $sourceCategoryId] = (int) $pdo->lastInsertId();
                $rowsImported++;
                $totalCategories++;
            } catch (Throwable $e) {
                $rowsSkipped++;
                $errors++;
            }
        }

        fclose($handle);
        ImportFile::create([
            'batch_id' => $batchId,
            'file_type' => 'categories',
            'filename' => basename($file),
            'file_path' => $file,
            'rows_read' => $rowsRead,
            'rows_imported' => $rowsImported,
            'rows_skipped' => $rowsSkipped,
        ]);

        echo "  Imported {$rowsImported} category rows, skipped {$rowsSkipped}\n";
    }

    if (!$categoryLookup) {
        $stmt = $pdo->prepare('SELECT id, source_category_id FROM source_categories WHERE batch_id = :batch_id');
        $stmt->execute(['batch_id' => $batchId]);
        foreach ($stmt->fetchAll() as $row) {
            $categoryLookup[(int) $row['source_category_id']] = (int) $row['id'];
        }
    }

    foreach ($siteFiles as $file) {
        echo "Importing sites from {$file}\n";
        $handle = fopen($file, 'rb');
        if (!$handle) {
            throw new RuntimeException("Failed to open site file: {$file}");
        }

        $rowsRead = 0;
        $rowsImported = 0;
        $rowsSkipped = 0;

        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            $rowsRead++;
            $row = array_pad($row, 4, '');
            [$url, $title, $descriptionRaw, $sourceCategoryId] = $row;
            $url = trim((string) $url);
            $title = trim((string) $title);
            $sourceCategoryId = trim((string) $sourceCategoryId);

            if ($url === '' || $title === '' || $sourceCategoryId === '') {
                $rowsSkipped++;
                continue;
            }

            $normalizedUrl = normalize_url($url);
            $parts = parse_url($url);
            $isValidUrl = filter_var($url, FILTER_VALIDATE_URL) !== false;
            $categoryRowId = $categoryLookup[(int) $sourceCategoryId] ?? null;
            $importStatus = 'ready';
            $notes = null;

            if (!$isValidUrl) {
                $importStatus = 'invalid';
                $notes = 'Invalid URL';
            } elseif ($categoryRowId === null) {
                $importStatus = 'missing_category';
                $notes = 'Category ID not found in imported categories';
            }

            $duplicateFlag = 0;
            if (isset($normalizedSeen[$normalizedUrl])) {
                $duplicateFlag = 1;
            }
            $normalizedSeen[$normalizedUrl] = true;

            try {
                SourceSite::insert([
                    'batch_id' => $batchId,
                    'source_category_id' => (int) $sourceCategoryId,
                    'source_category_row_id' => $categoryRowId,
                    'url' => $url,
                    'normalized_url' => $normalizedUrl,
                    'title' => $title,
                    'description_raw' => $descriptionRaw !== '' ? $descriptionRaw : null,
                    'http_scheme' => $parts['scheme'] ?? null,
                    'import_status' => $importStatus,
                    'duplicate_flag' => $duplicateFlag,
                    'notes' => $notes,
                ]);
                $rowsImported++;
                $totalSites++;
            } catch (Throwable $e) {
                $rowsSkipped++;
                $errors++;
            }
        }

        fclose($handle);
        ImportFile::create([
            'batch_id' => $batchId,
            'file_type' => 'sites',
            'filename' => basename($file),
            'file_path' => $file,
            'rows_read' => $rowsRead,
            'rows_imported' => $rowsImported,
            'rows_skipped' => $rowsSkipped,
        ]);

        echo "  Imported {$rowsImported} site rows, skipped {$rowsSkipped}\n";
    }

    Batch::complete($batchId, [
        'categories_imported' => $totalCategories,
        'sites_imported' => $totalSites,
        'errors_count' => $errors,
    ]);

    echo "Done. Categories: {$totalCategories}, Sites: {$totalSites}, Errors: {$errors}\n";
    exit(0);
} catch (Throwable $e) {
    Batch::complete($batchId, [
        'categories_imported' => $totalCategories,
        'sites_imported' => $totalSites,
        'errors_count' => $errors + 1,
    ], 'failed');

    fwrite(STDERR, "Import failed: " . $e->getMessage() . "\n");
    exit(1);
}
