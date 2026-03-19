<?php $pages = $result['pages']; ?>
<h1 class="h3 mb-3">Category mappings</h1>
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
        <table class="table table-striped table-sm mb-0">
            <thead><tr><th>ID</th><th>Source Category ID</th><th>Source Path</th><th>Candidate</th><th>Final</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$result['rows']): ?>
                <tr><td colspan="6" class="text-muted">No mapping rows yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($result['rows'] as $row): ?>
                <tr>
                    <td><?= e((string) $row['id']) ?></td>
                    <td><?= e((string) $row['source_category_id']) ?></td>
                    <td><?= e($row['source_full_path']) ?></td>
                    <td><code class="small-path"><?= e($row['local_path_candidate']) ?></code></td>
                    <td><code class="small-path"><?= e($row['local_path_final']) ?></code></td>
                    <td><?= e($row['mapping_status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted mt-2 mb-0">Showing <?= e((string) count($result['rows'])) ?> of <?= e((string) $result['total']) ?> mappings.</p>
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
