<?php
$categories = $categories ?? ['rows' => [], 'total' => 0, 'page' => 1, 'perPage' => 50, 'pages' => 1];
$branches = $branches ?? [];
$selectedBranch = $selectedBranch ?? '';
$selectedStatus = $selectedStatus ?? '';
$pathSearch = $pathSearch ?? '';
$perPage = $perPage ?? 50;

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Source categories</h1>
        <div class="text-muted">Approve, skip, and review imported category branches.</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
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
                    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'skipped' => 'Skipped'] as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Path contains</label>
                <input type="text" name="path" class="form-control" value="<?= h($pathSearch) ?>">
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
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<form method="post" action="/categories/bulk">
    <input type="hidden" name="branch" value="<?= h($selectedBranch) ?>">
    <input type="hidden" name="status" value="<?= h($selectedStatus) ?>">
    <input type="hidden" name="path" value="<?= h($pathSearch) ?>">
    <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
    <input type="hidden" name="page" value="<?= (int) ($categories['page'] ?? 1) ?>">

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" data-check-all="categories"></th>
                        <th>ID</th>
                        <th>Source Category ID</th>
                        <th>Full Path</th>
                        <th>Entries</th>
                        <th>Depth</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($categories['rows'])): ?>
                        <?php foreach ($categories['rows'] as $row): ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?= (int) $row['id'] ?>" data-group="categories"></td>
                                <td><?= (int) $row['id'] ?></td>
                                <td><?= h($row['source_category_id'] ?? '') ?></td>
                                <td><code class="small-path"><?= h($row['full_path'] ?? '') ?></code></td>
                                <td><?= (int) ($row['entry_count'] ?? 0) ?></td>
                                <td><?= (int) ($row['path_depth'] ?? 0) ?></td>
                                <td><?= h($row['mapping_status'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No categories found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card-body border-top">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-success" type="submit" name="action" value="approve">Approve selected</button>
                <button class="btn btn-warning" type="submit" name="action" value="skip">Skip selected</button>
                <button class="btn btn-outline-secondary" type="submit" name="action" value="reset">Reset to pending</button>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center">
            <div class="text-muted">
                Showing <?= count($categories['rows'] ?? []) ?> of <?= (int) ($categories['total'] ?? 0) ?> categories.
            </div>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $prevDisabled = ((int) ($categories['page'] ?? 1) <= 1) ? 'disabled' : '';
                $nextDisabled = ((int) ($categories['page'] ?? 1) >= (int) ($categories['pages'] ?? 1)) ? 'disabled' : '';

                $prevQuery = http_build_query(array_filter([
                    'branch' => $selectedBranch,
                    'status' => $selectedStatus,
                    'path' => $pathSearch,
                    'per_page' => $perPage,
                    'page' => max(1, (int) ($categories['page'] ?? 1) - 1),
                ], fn($v) => $v !== ''));

                $nextQuery = http_build_query(array_filter([
                    'branch' => $selectedBranch,
                    'status' => $selectedStatus,
                    'path' => $pathSearch,
                    'per_page' => $perPage,
                    'page' => min((int) ($categories['pages'] ?? 1), (int) ($categories['page'] ?? 1) + 1),
                ], fn($v) => $v !== ''));
                ?>
                <li class="page-item <?= $prevDisabled ?>">
                    <a class="page-link" href="/categories?<?= h($prevQuery) ?>">Previous</a>
                </li>
                <li class="page-item <?= $nextDisabled ?>">
                    <a class="page-link" href="/categories?<?= h($nextQuery) ?>">Next</a>
                </li>
            </ul>
        </div>
    </div>
</form>
