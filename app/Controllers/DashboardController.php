<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\Batch;
use App\Models\SourceCategory;
use App\Models\SourceSite;

class DashboardController
{
    public function index(): void
    {
        $batchPage = Batch::paginate(1, 10);

        View::render('dashboard/index', [
            'stats' => [
                'batches' => $batchPage['total'],
                'categories' => SourceCategory::count(),
                'sites' => SourceSite::count(),
                'duplicates' => SourceSite::duplicateCount(),
                'unmapped_categories' => SourceCategory::unmappedCount(),
                'missing_category_links' => SourceSite::missingCategoryCount(),
            ],
            'recentBatches' => $batchPage['rows'],
        ]);
    }
}