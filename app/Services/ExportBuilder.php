<?php
namespace App\Services;

use App\Core\Database;

class ExportBuilder
{
    public static function writeSql(?int $batchId = null, ?string $branch = null): array
    {
        $config = require __DIR__ . '/../../config/config.php';

        $filename = 'curlie_export_' . date('Ymd_His') . '.sql';
        $fullPath = rtrim($config['paths']['exports'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        $fh = fopen($fullPath, 'wb');
        fwrite($fh, "-- Export placeholder (Phase 3 initial)\n");
        fwrite($fh, "-- Replace with full builder in next step\n");
        fclose($fh);

        return [
            'filename' => $filename,
            'full_path' => $fullPath,
            'categories_count' => 0,
            'sites_count' => 0,
        ];
    }
}
