<?php
require __DIR__ . '/bootstrap.php';

use App\Core\Database;

$config = app_config();
$pdo = Database::connection();
$batchId = null;

$categories = $pdo->query("SELECT * FROM category_mapping WHERE mapping_status IN ('pending', 'mapped', 'approved') ORDER BY source_full_path ASC")->fetchAll();
$sites = $pdo->query("SELECT ss.*, cm.local_path_final, cm.local_path_candidate FROM source_sites ss
    LEFT JOIN category_mapping cm ON cm.source_category_id = ss.source_category_id
    WHERE ss.import_status = 'ready'
    ORDER BY ss.id ASC")->fetchAll();

$filename = 'curlie_export_' . date('Ymd_His') . '.sql';
$fullPath = rtrim($config['paths']['exports'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

$fh = fopen($fullPath, 'wb');
if (!$fh) {
    fwrite(STDERR, "Failed to write export file.\n");
    exit(1);
}

fwrite($fh, "-- Curlie importer export\n");
fwrite($fh, "-- Generated at " . date('Y-m-d H:i:s') . "\n\n");
fwrite($fh, "-- Review carefully before importing into the live directory database.\n");
fwrite($fh, "-- This starter export emits review-friendly comments rather than final schema-specific INSERTs.\n\n");

fwrite($fh, "-- CATEGORY CANDIDATES\n");
foreach ($categories as $row) {
    $path = $row['local_path_final'] ?: $row['local_path_candidate'];
    $comment = sprintf(
        "-- source_category_id=%s | source_path=%s | local_path=%s | status=%s\n",
        $row['source_category_id'],
        $row['source_full_path'],
        $path,
        $row['mapping_status']
    );
    fwrite($fh, $comment);
}

fwrite($fh, "\n-- SITE CANDIDATES\n");
foreach ($sites as $row) {
    $path = $row['local_path_final'] ?: $row['local_path_candidate'] ?: 'UNMAPPED';
    $comment = sprintf(
        "-- url=%s | title=%s | category_path=%s | source_category_id=%s\n",
        $row['url'],
        str_replace(["\r", "\n"], ' ', $row['title']),
        $path,
        $row['source_category_id']
    );
    fwrite($fh, $comment);
}

fclose($fh);

$insert = $pdo->prepare('INSERT INTO export_runs (batch_id, filename, categories_count, sites_count, created_at) VALUES (:batch_id, :filename, :categories_count, :sites_count, NOW())');
$insert->execute([
    'batch_id' => $batchId,
    'filename' => $filename,
    'categories_count' => count($categories),
    'sites_count' => count($sites),
]);

echo "Export written to: {$fullPath}\n";
exit(0);
