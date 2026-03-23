<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\SourceCategory;

class CategoriesController
{
    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, (int) ($_GET['per_page'] ?? 50));
        $branch = (string) ($_GET['branch'] ?? '');
        $status = (string) ($_GET['status'] ?? '');
        $path = (string) ($_GET['path'] ?? '');
        $batchId = ($_GET['batch_id'] ?? '') !== '' ? (int) $_GET['batch_id'] : null;

        View::render('categories/index', [
            'categories' => SourceCategory::paginate($page, $perPage, [
                'batch_id' => $batchId,
                'branch' => $branch ?: null,
                'status' => $status ?: null,
                'path' => $path ?: null,
            ]),
            'branches' => SourceCategory::branches(),
            'selectedBranch' => $branch,
            'selectedStatus' => $status,
            'pathSearch' => $path,
            'perPage' => $perPage,
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
            'skip' => 'skipped',
            'reset' => 'pending',
        ];

        if ($ids && isset($statusMap[$action])) {
            if ($action === 'approve') {
                SourceCategory::bulkApproveBranches($ids);
            } else {
                SourceCategory::bulkUpdateStatus($ids, $statusMap[$action]);
            }
        }

        redirect('/categories?' . http_build_query(array_filter([
            'batch_id' => $_POST['batch_id'] ?? '',
            'branch' => $_POST['branch'] ?? '',
            'status' => $_POST['status'] ?? '',
            'path' => $_POST['path'] ?? '',
            'per_page' => $_POST['per_page'] ?? 50,
            'page' => $_POST['page'] ?? 1,
        ], fn ($v) => $v !== '')));
    }
}
