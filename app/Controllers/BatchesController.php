<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\Batch;
use App\Models\ImportFile;

class BatchesController
{
    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(25, min(250, (int) ($_GET['per_page'] ?? 50)));
        View::render('batches/index', ['result' => Batch::paginate($page, $perPage), 'perPage' => $perPage]);
    }

    public function show(int $id): void
    {
        View::render('batches/show', [
            'batch' => Batch::find($id),
            'files' => ImportFile::byBatch($id),
        ]);
    }
}
