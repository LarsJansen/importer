<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Exports</h1>
        <div class="text-muted">Generate live-directory-ready SQL from approved categories and approved sites.</div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

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
                                <option value="<?= (int) $batch['id'] ?>" <?= $selectedBatchId === (int) $batch['id'] ? 'selected' : '' ?>>#<?= (int) $batch['id'] ?> — <?= e($batch['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Top branch</label>
                        <select name="branch" class="form-select">
                            <option value="">All branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= e($branch['top_branch']) ?>" <?= $selectedBranch === $branch['top_branch'] ? 'selected' : '' ?>><?= e($branch['top_branch']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-secondary" type="submit">Preview export</button>
                    </div>
                </form>

                <div class="border rounded p-3 bg-light mb-3">
                    <div class="fw-semibold mb-2">Preview</div>
                    <div>Approved categories: <strong><?= number_format((int) $preview['categories']) ?></strong></div>
                    <div>Approved sites: <strong><?= number_format((int) $preview['sites']) ?></strong></div>
                </div>

                <form method="post" action="/exports/create">
                    <input type="hidden" name="batch_id" value="<?= e((string) ($selectedBatchId ?? '')) ?>">
                    <input type="hidden" name="branch" value="<?= e((string) ($selectedBranch ?? '')) ?>">
                    <button class="btn btn-primary" type="submit" <?= ((int) $preview['categories'] === 0 || (int) $preview['sites'] === 0) ? 'disabled' : '' ?>>Generate SQL export</button>
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
                    <li>Uses <code>NOT EXISTS</code> checks in SQL to avoid duplicate category paths and duplicate normalized URLs.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Export history</span>
        <form method="get" class="d-flex align-items-center gap-2">
            <input type="hidden" name="batch_id" value="<?= e((string) ($selectedBatchId ?? '')) ?>">
            <input type="hidden" name="branch" value="<?= e((string) ($selectedBranch ?? '')) ?>">
            <label for="per_page" class="form-label mb-0">Per page</label>
            <select id="per_page" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ([25, 50, 100, 250] as $size): ?>
                    <option value="<?= $size ?>" <?= (int) $exports['perPage'] === $size ? 'selected' : '' ?>><?= $size ?></option>
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
                <?php foreach ($exports['rows'] as $row): ?>
                    <tr>
                        <td><?= (int) $row['id'] ?></td>
                        <td><?= e($row['created_at']) ?></td>
                        <td><?= e($row['filename']) ?></td>
                        <td><?= $row['batch_label'] ? e($row['batch_label']) : 'All/filtered' ?></td>
                        <td><?= number_format((int) $row['categories_count']) ?></td>
                        <td><?= number_format((int) $row['sites_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$exports['rows']): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No exports yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted">Page <?= (int) $exports['page'] ?> of <?= (int) $exports['pages'] ?></div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $exports['page'] <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(paginate_url(['page' => max(1, $exports['page'] - 1)])) ?>">Previous</a></li>
                <li class="page-item <?= $exports['page'] >= $exports['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(paginate_url(['page' => min($exports['pages'], $exports['page'] + 1)])) ?>">Next</a></li>
            </ul>
        </nav>
    </div>
</div>
