<?php
$sites = $sites ?? ['rows' => [], 'total' => 0, 'page' => 1, 'perPage' => 50, 'pages' => 1];
$branches = $branches ?? [];
$selectedStatus = $selectedStatus ?? '';
$selectedBranch = $selectedBranch ?? '';
$pathSearch = $pathSearch ?? '';
$urlSearch = $urlSearch ?? '';
$perPage = $perPage ?? 50;
$selectedBatchId = $selectedBatchId ?? null;
$counts = $counts ?? ['total'=>0,'ready'=>0,'approved'=>0,'rejected'=>0,'invalid'=>0,'duplicates'=>0,'missing_category'=>0,'ready_export'=>0];
function h(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function statusFilterLink(string $label, string $statusValue, int $count, ?int $batchId, string $selectedStatus, string $selectedBranch, string $pathSearch, string $urlSearch, int $perPage): string
{
    $query = array_filter([
        'batch_id' => $batchId,
        'status' => $statusValue,
        'branch' => $selectedBranch,
        'path' => $pathSearch,
        'url' => $urlSearch,
        'per_page' => $perPage,
    ], fn($v) => $v !== '' && $v !== null);

    $active = $selectedStatus === $statusValue || ($statusValue === '' && $selectedStatus === '');
    $class = $active ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline-secondary';

    return '<a class="' . $class . '" href="/sites?' . h(http_build_query($query)) . '">' . h($label) . ' <span class="badge text-bg-light">' . $count . '</span></a>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Source sites</h1>
        <div class="text-muted">Filter, review, and bulk-approve sites before export.</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2">
        <?= statusFilterLink('All sites', '', (int) $counts['total'], $selectedBatchId, $selectedStatus, $selectedBranch, $pathSearch, $urlSearch, (int) $perPage) ?>
        <?= statusFilterLink('Ready', 'ready', (int) $counts['ready'], $selectedBatchId, $selectedStatus, $selectedBranch, $pathSearch, $urlSearch, (int) $perPage) ?>
        <?= statusFilterLink('Approved', 'approved', (int) $counts['approved'], $selectedBatchId, $selectedStatus, $selectedBranch, $pathSearch, $urlSearch, (int) $perPage) ?>
        <?= statusFilterLink('Ready for export', 'ready_export', (int) $counts['ready_export'], $selectedBatchId, $selectedStatus, $selectedBranch, $pathSearch, $urlSearch, (int) $perPage) ?>
        <?= statusFilterLink('Rejected', 'rejected', (int) $counts['rejected'], $selectedBatchId, $selectedStatus, $selectedBranch, $pathSearch, $urlSearch, (int) $perPage) ?>
        <?= statusFilterLink('Invalid', 'invalid', (int) $counts['invalid'], $selectedBatchId, $selectedStatus, $selectedBranch, $pathSearch, $urlSearch, (int) $perPage) ?>
        <?= statusFilterLink('Missing category', 'missing_category', (int) $counts['missing_category'], $selectedBatchId, $selectedStatus, $selectedBranch, $pathSearch, $urlSearch, (int) $perPage) ?>
        <?= statusFilterLink('Duplicates', 'duplicates', (int) $counts['duplicates'], $selectedBatchId, $selectedStatus, $selectedBranch, $pathSearch, $urlSearch, (int) $perPage) ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <?php if ($selectedBatchId): ?>
                <input type="hidden" name="batch_id" value="<?= (int) $selectedBatchId ?>">
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label">Top branch</label>
                <select name="branch" class="form-select">
                    <option value="">All top branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <?php $branchValue = is_array($branch) ? ($branch['top_branch'] ?? '') : (string) $branch; ?>
                        <option value="<?= h($branchValue) ?>" <?= $selectedBranch === $branchValue ? 'selected' : '' ?>><?= h($branchValue) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    <?php foreach ([
                        'ready' => 'Ready',
                        'approved' => 'Approved',
                        'ready_export' => 'Ready for export',
                        'rejected' => 'Rejected',
                        'invalid' => 'Invalid',
                        'missing_category' => 'Missing category',
                        'duplicates' => 'Duplicates',
                    ] as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Category path</label>
                <input type="text" name="path" class="form-control" value="<?= h($pathSearch) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">URL or title</label>
                <input type="text" name="url" class="form-control" value="<?= h($urlSearch) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">Per page</label>
                <select name="per_page" class="form-select">
                    <?php foreach ([25, 50, 100, 250] as $size): ?>
                        <option value="<?= $size ?>" <?= (int) $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Go</button>
            </div>
        </form>
    </div>
</div>

<form method="post" action="/sites/bulk">
    <input type="hidden" name="batch_id" value="<?= (int) ($selectedBatchId ?? 0) ?>">
    <input type="hidden" name="status" value="<?= h($selectedStatus) ?>">
    <input type="hidden" name="branch" value="<?= h($selectedBranch) ?>">
    <input type="hidden" name="path" value="<?= h($pathSearch) ?>">
    <input type="hidden" name="url" value="<?= h($urlSearch) ?>">
    <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
    <input type="hidden" name="page" value="<?= (int) ($sites['page'] ?? 1) ?>">

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" data-check-all="sites"></th>
                        <th>ID</th>
                        <th>URL</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($sites['rows'])): ?>
                        <?php foreach ($sites['rows'] as $row): ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?= (int) $row['id'] ?>" data-group="sites"></td>
                                <td><?= (int) $row['id'] ?></td>
                                <td>
                                    <div><a href="<?= h($row['url'] ?? '') ?>" target="_blank" rel="noreferrer"><?= h($row['url'] ?? '') ?></a></div>
                                    <?php if (!empty($row['duplicate_flag'])): ?><div><span class="badge text-bg-warning">duplicate</span></div><?php endif; ?>
                                </td>
                                <td><?= h($row['title'] ?? '') ?></td>
                                <td><code class="small-path"><?= h($row['full_path'] ?? '') ?></code></td>
                                <td><?= h($row['import_status'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No sites found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card-body border-top">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-success" type="submit" name="action" value="approve">Approve selected</button>
                <button class="btn btn-danger" type="submit" name="action" value="reject">Reject selected</button>
                <button class="btn btn-outline-secondary" type="submit" name="action" value="reset">Reset to ready</button>
            </div>
        </div>
    </div>
</form>
