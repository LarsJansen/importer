<?php
require __DIR__ . '/bootstrap.php';

use App\Models\ExportRun;
use App\Services\ExportBuilder;

$options = getopt('', ['batch-id::', 'branch::']);
$batchId = isset($options['batch-id']) && $options['batch-id'] !== '' ? (int) $options['batch-id'] : null;
$branch = isset($options['branch']) && $options['branch'] !== '' ? (string) $options['branch'] : null;

$result = ExportBuilder::generate($batchId, $branch);
ExportRun::create(
    $result['batch_id'] ?? $batchId,
    $result['filename'],
    $result['categories_count'],
    $result['sites_count']
);

echo "Export written to: {$result['full_path']}\n";
echo "Categories: {$result['categories_count']}\n";
echo "Sites: {$result['sites_count']}\n";
