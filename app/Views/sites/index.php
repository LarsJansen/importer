<?php $pages = $result['pages']; ?>
<h1 class="h3 mb-3">Source sites</h1>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">All sites</option>
            <option value="ready" <?= $selectedStatus === 'ready' ? 'selected' : '' ?>>Ready</option>
            <option value="approved" <?= $selectedStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $selectedStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="invalid" <?= $selectedStatus === 'invalid' ? 'selected' : '' ?>>Invalid</option>
            <option value="missing_category" <?= $selectedStatus === 'missing_category' ? 'selected' : '' ?>>Missing category</option>
            <option value="duplicates" <?= $selectedStatus === 'duplicates' ? 'selected' : '' ?>>Duplicates</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="branch" class="form-select">
            <option value="">All top branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= e($branch['top_branch']) ?>" <?= $selectedBranch === $branch['top_branch'] ? 'selected' : '' ?>><?= e($branch['top_branch']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <input type="text" name="path" value="<?= e($pathSearch) ?>" class="form-control" placeholder="Category path contains...">
    </div>
    <div class="col-md-3">
        <input type="text" name="url" value="<?= e($urlSearch) ?>" class="form-control" placeholder="URL or title contains...">
    </div>
    <div class="col-md-1">
        <select name="per_page" class="form-select">
            <?php foreach ([25,50,100,250] as $size): ?>
                <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-1 d-grid"><button class="btn btn-primary">Go</button></div>
</form>
<form method="post" action="/sites/bulk">
    <input type="hidden" name="status" value="<?= e($selectedStatus) ?>">
    <input type="hidden" name="branch" value="<?= e($selectedBranch) ?>">
    <input type="hidden" name="path" value="<?= e($pathSearch) ?>">
    <input type="hidden" name="url" value="<?= e($urlSearch) ?>">
    <input type="hidden" name="per_page" value="<?= e((string) $perPage) ?>">
    <input type="hidden" name="page" value="<?= e((string) $result['page']) ?>">
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped table-sm mb-0 align-middle">
                <thead><tr><th><input type="checkbox" data-check-all="sites"></th><th>ID</th><th>URL</th><th>Title</th><th>Category</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$result['rows']): ?>
                    <tr><td colspan="6" class="text-muted">No sites found.</td></tr>
                <?php endif; ?>
                <?php foreach ($result['rows'] as $row): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= e((string) $row['id']) ?>" data-group="sites"></td>
                        <td><?= e((string) $row['id']) ?></td>
                        <td><a href="<?= e($row['url']) ?>" target="_blank"><?= e($row['url']) ?></a></td>
                        <td>
                            <div><?= e($row['title']) ?></div>
                            <code class="small-path"><?= e($row['normalized_url']) ?></code>
                        </td>
                        <td><?= e($row['full_path'] ?? '') ?></td>
                        <td>
                            <span class="badge text-bg-secondary"><?= e($row['import_status']) ?></span>
                            <?php if ((int) $row['duplicate_flag'] === 1): ?>
                                <span class="badge text-bg-warning">duplicate</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="bg-white border-top p-3 d-flex flex-wrap gap-2 justify-content-between sticky-actions">
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" name="action" value="approve">Approve selected</button>
                <button class="btn btn-danger btn-sm" name="action" value="reject">Reject selected</button>
                <button class="btn btn-outline-secondary btn-sm" name="action" value="reset">Reset to ready</button>
            </div>
            <div class="text-muted small align-self-center">Showing <?= e((string) count($result['rows'])) ?> of <?= e((string) $result['total']) ?> sites.</div>
        </div>
    </div>
</form>
<?php if ($pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm flex-wrap">
        <li class="page-item <?= $result['page'] <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(paginate_url(['page' => max(1, $result['page'] - 1)])) ?>">Previous</a></li>
        <?php for ($p = max(1, $result['page'] - 2); $p <= min($pages, $result['page'] + 2); $p++): ?>
            <li class="page-item <?= $p === $result['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e(paginate_url(['page' => $p])) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $result['page'] >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(paginate_url(['page' => min($pages, $result['page'] + 1)])) ?>">Next</a></li>
    </ul>
</nav>
<?php endif; ?>
