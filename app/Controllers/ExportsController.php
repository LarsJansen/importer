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
        $perPage = max(1, (int) ($_GET['per_page'] ?? 25));
        $batchId = ($_GET['batch_id'] ?? '') !== '' ? (int) $_GET['batch_id'] : null;
        $branch = trim((string) ($_GET['branch'] ?? ''));

        View::render('exports/index', [
            'runs' => ExportRun::paginate($page, $perPage),
            'preview' => ExportBuilder::preview($batchId, $branch !== '' ? $branch : null),
            'batches' => Batch::paginate(1, 250)['rows'],
            'branches' => SourceCategory::branches(),
            'selectedBatchId' => $batchId,
            'selectedBranch' => $branch,
            'message' => $_GET['message'] ?? null,
        ]);
    }

    public function generate(): void
    {
        $batchId = ($_POST['batch_id'] ?? '') !== '' ? (int) $_POST['batch_id'] : null;
        $branch = trim((string) ($_POST['branch'] ?? ''));
        $branch = $branch !== '' ? $branch : null;

        $result = ExportBuilder::generate($batchId, $branch);
        ExportRun::create(
            $result['batch_id'] ?? $batchId,
            $result['filename'],
            $result['categories_count'],
            $result['sites_count']
        );

        $query = http_build_query([
            'message' => 'Export generated: ' . $result['filename'],
            'batch_id' => $batchId,
            'branch' => $branch,
        ]);

        header('Location: /exports?' . $query);
        exit;
    }
}
