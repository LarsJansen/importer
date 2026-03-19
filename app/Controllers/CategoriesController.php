<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\SourceCategory;

class CategoriesController
{
    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(25, min(250, (int) ($_GET['per_page'] ?? 50)));
        $branch = trim((string) ($_GET['branch'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $path = trim((string) ($_GET['path'] ?? ''));

        View::render('categories/index', [
            'result' => SourceCategory::paginate($page, $perPage, [
                'branch' => $branch ?: null,
                'status' => $status ?: null,
                'path' => $path ?: null,
            ]),
            'branches' => SourceCategory::branches(),
            'selectedBranch' => $branch,
            'selectedStatus' => $status,
            'pathSearch' => $path,
            'perPage' => $perPage,
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
            SourceCategory::bulkUpdateStatus($ids, $statusMap[$action]);
        }

        redirect('/categories?' . http_build_query(array_filter([
            'branch' => $_POST['branch'] ?? '',
            'status' => $_POST['status'] ?? '',
            'path' => $_POST['path'] ?? '',
            'per_page' => $_POST['per_page'] ?? 50,
            'page' => $_POST['page'] ?? 1,
        ], fn ($v) => $v !== '')));
    }
}
