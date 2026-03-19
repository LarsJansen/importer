<?php $pages = $result['pages']; ?>
<h1 class="h3 mb-3">Import batches</h1>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-2">
        <select name="per_page" class="form-select">
            <?php foreach ([25,50,100,250] as $size): ?>
                <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> per page</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid"><button class="btn btn-primary">Apply</button></div>
</form>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
            <tr><th>ID</th><th>Source</th><th>Label</th><th>Status</th><th>Categories</th><th>Sites</th><th>Errors</th><th>Started</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$result['rows']): ?>
                <tr><td colspan="9" class="text-muted">No import batches yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($result['rows'] as $row): ?>
                <tr>
                    <td><?= e((string) $row['id']) ?></td>
                    <td><?= e($row['source_name']) ?></td>
                    <td><?= e($row['label']) ?></td>
                    <td><?= e($row['status']) ?></td>
                    <td><?= e((string) $row['categories_imported']) ?></td>
                    <td><?= e((string) $row['sites_imported']) ?></td>
                    <td><?= e((string) $row['errors_count']) ?></td>
                    <td><?= e((string) $row['started_at']) ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="/batches/<?= e((string) $row['id']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted mt-2 mb-0">Showing <?= e((string) count($result['rows'])) ?> of <?= e((string) $result['total']) ?> batches.</p>
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
