<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\Mapping;

class MappingsController
{
    public function index(): void
    {
        Mapping::syncFromCategories();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(25, min(250, (int) ($_GET['per_page'] ?? 50)));
        View::render('mappings/index', ['result' => Mapping::paginate($page, $perPage), 'perPage' => $perPage]);
    }
}
