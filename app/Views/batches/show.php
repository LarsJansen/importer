<?php if (!$batch): ?>
    <div class="alert alert-danger">Batch not found.</div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Batch #<?= e((string) $batch['id']) ?></h1>
        <a class="btn btn-outline-secondary" href="/batches">Back</a>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Label</div><div><?= e($batch['label']) ?></div></div></div></div>
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Status</div><div><?= e($batch['status']) ?></div></div></div></div>
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Categories</div><div><?= e((string) $batch['categories_imported']) ?></div></div></div></div>
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Sites</div><div><?= e((string) $batch['sites_imported']) ?></div></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Started</div><div><?= e((string) $batch['started_at']) ?></div></div></div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">Files in this batch</div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>ID</th><th>Type</th><th>Filename</th><th>Rows read</th><th>Imported</th><th>Skipped</th></tr></thead>
                <tbody>
                <?php if (!$files): ?>
                    <tr><td colspan="6" class="text-muted">No file tracking rows recorded.</td></tr>
                <?php endif; ?>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?= e((string) $file['id']) ?></td>
                        <td><?= e($file['file_type']) ?></td>
                        <td><code><?= e($file['filename']) ?></code></td>
                        <td><?= e((string) $file['rows_read']) ?></td>
                        <td><?= e((string) $file['rows_imported']) ?></td>
                        <td><?= e((string) $file['rows_skipped']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
