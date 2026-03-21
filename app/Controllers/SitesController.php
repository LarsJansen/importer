<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\SourceCategory;
use App\Models\SourceSite;

class SitesController
{
    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, (int) ($_GET['per_page'] ?? 50));
        $status = (string) ($_GET['status'] ?? '');
        $branch = (string) ($_GET['branch'] ?? '');
        $path = (string) ($_GET['path'] ?? '');
        $url = (string) ($_GET['url'] ?? '');
        $batchId = ($_GET['batch_id'] ?? '') !== '' ? (int) $_GET['batch_id'] : null;

        View::render('sites/index', [
            'sites' => SourceSite::paginate($page, $perPage, [
                'batch_id' => $batchId,
                'status' => $status ?: null,
                'branch' => $branch ?: null,
                'path' => $path ?: null,
                'url' => $url ?: null,
            ]),
            'branches' => SourceCategory::branches(),
            'selectedStatus' => $status,
            'selectedBranch' => $branch,
            'pathSearch' => $path,
            'urlSearch' => $url,
            'perPage' => $perPage,
            'counts' => SourceSite::counts($batchId),
            'selectedBatchId' => $batchId,
        ]);
    }

    public function bulkUpdate(): void
    {
        $action = (string) ($_POST['action'] ?? '');
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $ids = array_values(array_filter($ids));

        $statusMap = [
            'approve' => 'approved',
            'reject' => 'rejected',
            'reset' => 'ready',
        ];

        if ($ids && isset($statusMap[$action])) {
            SourceSite::bulkUpdateStatus($ids, $statusMap[$action]);
        }

        redirect('/sites?' . http_build_query(array_filter([
            'batch_id' => $_POST['batch_id'] ?? '',
            'status' => $_POST['status'] ?? '',
            'branch' => $_POST['branch'] ?? '',
            'path' => $_POST['path'] ?? '',
            'url' => $_POST['url'] ?? '',
            'per_page' => $_POST['per_page'] ?? 50,
            'page' => $_POST['page'] ?? 1,
        ], fn ($v) => $v !== '')));
    }
}
