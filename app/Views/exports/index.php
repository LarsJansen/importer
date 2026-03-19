<?php
$selectedBatchId = $selectedBatchId ?? null;
$selectedBranch = $selectedBranch ?? '';
$preview = $preview ?? ['categories' => 0, 'sites' => 0];
$runs = $runs ?? ['rows' => [], 'total' => 0, 'page' => 1, 'perPage' => 25, 'pages' => 1];
$batches = $batches ?? [];
$branches = $branches ?? [];

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$queryBase = [];
if ($selectedBatchId) {
    $queryBase['batch_id'] = $selectedBatchId;
}
if ($selectedBranch !== '') {
    $queryBase['branch'] = $selectedBranch;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Exports</h1>
        <div class="text-muted">Generate live-directory-ready SQL from approved categories and approved sites.</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Export builder</div>
            <div class="card-body">
                <form method="get" action="/exports" class="row g-2 mb-3">
                    <div class="col-12">
                        <label class="form-label">Batch</label>
                        <select name="batch_id" class="form-select">
                            <option value="">All approved batches</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?= (int) ($batch['id'] ?? 0) ?>" <?= ((int) ($selectedBatchId ?? 0) === (int) ($batch['id'] ?? 0)) ? 'selected' : '' ?>>
                                    #<?= (int) ($batch['id'] ?? 0) ?> — <?= h($batch['label'] ?? 'Batch') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Top branch</label>
                        <select name="branch" class="form-select">
                            <option value="">All branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <?php
                                $branchValue = is_array($branch)
                                    ? ($branch['top_branch'] ?? $branch['branch'] ?? '')
                                    : (string) $branch;
                                ?>
                                <option value="<?= h($branchValue) ?>" <?= ($selectedBranch === $branchValue) ? 'selected' : '' ?>>
                                    <?= h($branchValue) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-secondary" type="submit">Preview export</button>
                    </div>
                </form>

                <div class="border rounded p-3 bg-light mb-3">
                    <div class="fw-semibold mb-2">Preview</div>
                    <div>Approved categories: <strong><?= (int) ($preview['categories'] ?? 0) ?></strong></div>
                    <div>Approved sites: <strong><?= (int) ($preview['sites'] ?? 0) ?></strong></div>
                </div>

                <form method="post" action="/exports/generate">
                    <input type="hidden" name="batch_id" value="<?= h((string) ($selectedBatchId ?? '')) ?>">
                    <input type="hidden" name="branch" value="<?= h($selectedBranch) ?>">
                    <button class="btn btn-primary" type="submit" <?= (($preview['categories'] ?? 0) <= 0 && ($preview['sites'] ?? 0) <= 0) ? 'disabled' : '' ?>>
                        Generate SQL export
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Export rules</div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Exports only categories where <code>mapping_status = approved</code>.</li>
                    <li>Exports only sites where <code>import_status = approved</code>.</li>
                    <li>Skips rows flagged as duplicates and rows with missing category links.</li>
                    <li>Keeps source descriptions as-is, including HTML.</li>
                    <li>Current exporter is still the safe starter version.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Export history</span>
        <form method="get" class="d-flex align-items-center gap-2">
            <?php if ($selectedBatchId): ?>
                <input type="hidden" name="batch_id" value="<?= (int) $selectedBatchId ?>">
            <?php endif; ?>
            <?php if ($selectedBranch !== ''): ?>
                <input type="hidden" name="branch" value="<?= h($selectedBranch) ?>">
            <?php endif; ?>
            <label for="per_page" class="form-label mb-0">Per page</label>
            <select id="per_page" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ([25, 50, 100, 250] as $size): ?>
                    <option value="<?= $size ?>" <?= ((int) ($runs['perPage'] ?? 25) === $size) ? 'selected' : '' ?>><?= $size ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Created</th>
                    <th>Filename</th>
                    <th>Batch</th>
                    <th>Categories</th>
                    <th>Sites</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($runs['rows'])): ?>
                    <?php foreach ($runs['rows'] as $run): ?>
                        <tr>
                            <td><?= (int) ($run['id'] ?? 0) ?></td>
                            <td><?= h($run['created_at'] ?? '') ?></td>
                            <td><code><?= h($run['filename'] ?? '') ?></code></td>
                            <td><?= (int) ($run['batch_id'] ?? 0) ?></td>
                            <td><?= (int) ($run['categories_count'] ?? 0) ?></td>
                            <td><?= (int) ($run['sites_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No exports yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted">Page <?= (int) ($runs['page'] ?? 1) ?> of <?= (int) ($runs['pages'] ?? 1) ?></div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $prevPage = max(1, (int) ($runs['page'] ?? 1) - 1);
                $nextPage = min((int) ($runs['pages'] ?? 1), (int) ($runs['page'] ?? 1) + 1);

                $prevQuery = http_build_query(array_merge($queryBase, [
                    'page' => $prevPage,
                    'per_page' => (int) ($runs['perPage'] ?? 25),
                ]));
                $nextQuery = http_build_query(array_merge($queryBase, [
                    'page' => $nextPage,
                    'per_page' => (int) ($runs['perPage'] ?? 25),
                ]));
                ?>
                <li class="page-item <?= ((int) ($runs['page'] ?? 1) <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="/exports?<?= h($prevQuery) ?>">Previous</a>
                </li>
                <li class="page-item <?= ((int) ($runs['page'] ?? 1) >= (int) ($runs['pages'] ?? 1)) ? 'disabled' : '' ?>">
                    <a class="page-link" href="/exports?<?= h($nextQuery) ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
</div>
