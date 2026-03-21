<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\Batch;

class BatchesController
{
    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, (int) ($_GET['per_page'] ?? 25));

        View::render('batches/index', [
            'batches' => Batch::paginate($page, $perPage),
        ]);
    }

    public function show(int $id): void
    {
        $batch = Batch::find($id);
        if (!$batch) {
            http_response_code(404);
            echo 'Batch not found';
            return;
        }

        View::render('batches/show', [
            'batch' => $batch,
            'stats' => Batch::stats($id),
        ]);
    }
}
