<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\Batch;
use App\Models\ExportRun;
use App\Models\SourceCategory;
use App\Services\ExportBuilder;

class ExportsController
{
    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(25, min(250, (int) ($_GET['per_page'] ?? 25)));
        $batchId = isset($_GET['batch_id']) && $_GET['batch_id'] !== '' ? (int) $_GET['batch_id'] : null;
        $branch = trim((string) ($_GET['branch'] ?? '')) ?: null;

        View::render('exports/index', [
            'exports' => ExportRun::paginate($page, $perPage),
            'preview' => ExportBuilder::preview($batchId, $branch),
            'batches' => Batch::all(),
            'branches' => SourceCategory::branches(),
            'selectedBatchId' => $batchId,
            'selectedBranch' => $branch,
            'message' => $_GET['message'] ?? null,
        ]);
    }

    public function create(): void
    {
        $batchId = isset($_POST['batch_id']) && $_POST['batch_id'] !== '' ? (int) $_POST['batch_id'] : null;
        $branch = trim((string) ($_POST['branch'] ?? '')) ?: null;

        $result = ExportBuilder::generate($batchId, $branch);
        ExportRun::create($result);

        $query = http_build_query([
            'message' => 'Export generated: ' . $result['filename'],
            'batch_id' => $batchId,
            'branch' => $branch,
        ]);
        redirect('/exports?' . $query);
    }
}
