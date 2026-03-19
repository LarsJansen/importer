<?php
require __DIR__ . '/bootstrap.php';

use App\Models\ExportRun;
use App\Services\ExportBuilder;

$result = ExportBuilder::writeSql();
ExportRun::create(null, $result['filename'], 0, 0);

echo "Export written to: {$result['full_path']}\n";
