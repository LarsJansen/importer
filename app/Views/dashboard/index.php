<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="text-muted mb-0">Standalone local-only import lab for Curlie TSV data.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $label => $value): ?>
        <div class="col-md-4 col-xl-2">
            <div class="card stat-card shadow-sm">
                <div class="card-body">
                    <div class="text-muted text-capitalize small mb-2"><?= e(str_replace('_', ' ', $label)) ?></div>
                    <div class="display-6"><?= e((string) $value) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">Quick start</div>
    <div class="card-body">
        <ol class="mb-0">
            <li>Import <code>database/schema.sql</code> into a database named <code>importer</code>.</li>
            <li>Update <code>config/config.php</code> if your DB credentials differ.</li>
            <li>Run the CLI importer with a folder or paired TSV files.</li>
            <li>Review categories, sites, and mappings here in the UI.</li>
            <li>Generate SQL later for manual import into your live directory database.</li>
        </ol>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">Recent batches</div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
            <tr><th>ID</th><th>Label</th><th>Status</th><th>Categories</th><th>Sites</th><th>Started</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$recentBatches): ?>
                <tr><td colspan="7" class="text-muted">No batches yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentBatches as $row): ?>
                <tr>
                    <td><?= e((string) $row['id']) ?></td>
                    <td><?= e($row['label']) ?></td>
                    <td><span class="badge text-bg-secondary"><?= e($row['status']) ?></span></td>
                    <td><?= e((string) $row['categories_imported']) ?></td>
                    <td><?= e((string) $row['sites_imported']) ?></td>
                    <td><?= e((string) $row['started_at']) ?></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="/batches/<?= e((string) $row['id']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
