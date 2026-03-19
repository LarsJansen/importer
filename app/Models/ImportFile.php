<?php
namespace App\Models;

use App\Core\Database;

class ImportFile
{
    public static function create(array $data): int
    {
        $sql = 'INSERT INTO import_files (batch_id, file_type, filename, file_path, rows_read, rows_imported, rows_skipped, created_at)
                VALUES (:batch_id, :file_type, :filename, :file_path, :rows_read, :rows_imported, :rows_skipped, NOW())';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public static function byBatch(int $batchId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM import_files WHERE batch_id = :batch_id ORDER BY id ASC');
        $stmt->execute(['batch_id' => $batchId]);
        return $stmt->fetchAll();
    }
}
