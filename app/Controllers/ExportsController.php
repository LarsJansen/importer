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

        $preview = ['categories' => 0, 'sites' => 0];
        if (method_exists(ExportBuilder::class, 'preview')) {
            $preview = ExportBuilder::preview($batchId, $branch !== '' ? $branch : null);
        }

        $branches = [];
        if (method_exists(SourceCategory::class, 'branches')) {
            $branches = SourceCategory::branches();
        }

        View::render('exports/index', [
            'runs' => ExportRun::paginate($page, $perPage),
            'batches' => Batch::paginate(1, 250)['rows'],
            'branches' => $branches,
            'preview' => $preview,
            'selectedBatchId' => $batchId,
            'selectedBranch' => $branch,
        ]);
    }

    public function generate(): void
    {
        $batchId = ($_POST['batch_id'] ?? '') !== '' ? (int) $_POST['batch_id'] : null;
        $branch = trim((string) ($_POST['branch'] ?? ''));

        $result = ExportBuilder::writeSql($batchId, $branch !== '' ? $branch : null);
        ExportRun::create($batchId, $result['filename'], (int) ($result['categories_count'] ?? 0), (int) ($result['sites_count'] ?? 0));

        header('Location: /exports');
        exit;
    }
}
