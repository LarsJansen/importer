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
        $perPage = max(25, min(250, (int) ($_GET['per_page'] ?? 50)));
        $status = trim((string) ($_GET['status'] ?? ''));
        $branch = trim((string) ($_GET['branch'] ?? ''));
        $path = trim((string) ($_GET['path'] ?? ''));
        $url = trim((string) ($_GET['url'] ?? ''));

        View::render('sites/index', [
            'result' => SourceSite::paginate($page, $perPage, [
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
            'status' => $_POST['status'] ?? '',
            'branch' => $_POST['branch'] ?? '',
            'path' => $_POST['path'] ?? '',
            'url' => $_POST['url'] ?? '',
            'per_page' => $_POST['per_page'] ?? 50,
            'page' => $_POST['page'] ?? 1,
        ], fn ($v) => $v !== '')));
    }
}
