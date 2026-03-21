<?php
$batch = $batch ?? [];
$stats = $stats ?? [];
function h(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Batch #<?= (int) ($batch['id'] ?? 0) ?></h1>
        <div class="text-muted"><?= h($batch['label'] ?? '') ?></div>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="/batches">Back to batches</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Categories</div><div class="display-6"><?= (int) ($stats['categories'] ?? 0) ?></div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Approved categories</div><div class="display-6"><?= (int) ($stats['approved_categories'] ?? 0) ?></div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Sites</div><div class="display-6"><?= (int) ($stats['sites'] ?? 0) ?></div></div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Ready for export</div><div class="display-6"><?= (int) ($stats['ready_export_sites'] ?? 0) ?></div></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Category review</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="/categories?batch_id=<?= (int) ($batch['id'] ?? 0) ?>">All categories</a>
                <a class="btn btn-outline-success" href="/categories?batch_id=<?= (int) ($batch['id'] ?? 0) ?>&status=approved">Approved</a>
                <a class="btn btn-outline-warning" href="/categories?batch_id=<?= (int) ($batch['id'] ?? 0) ?>&status=pending">Pending</a>
                <a class="btn btn-outline-secondary" href="/categories?batch_id=<?= (int) ($batch['id'] ?? 0) ?>&status=skipped">Skipped</a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Site review</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a class="btn btn-outline-primary" href="/sites?batch_id=<?= (int) ($batch['id'] ?? 0) ?>">All sites</a>
                <a class="btn btn-outline-success" href="/sites?batch_id=<?= (int) ($batch['id'] ?? 0) ?>&status=approved">Approved</a>
                <a class="btn btn-outline-warning" href="/sites?batch_id=<?= (int) ($batch['id'] ?? 0) ?>&status=ready_export">Ready for export</a>
                <a class="btn btn-outline-danger" href="/sites?batch_id=<?= (int) ($batch['id'] ?? 0) ?>&status=duplicates">Duplicates</a>
                <a class="btn btn-outline-secondary" href="/sites?batch_id=<?= (int) ($batch['id'] ?? 0) ?>&status=missing_category">Missing category</a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Batch details</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Status:</strong> <?= h($batch['status'] ?? '') ?></p>
                <p><strong>Started:</strong> <?= h($batch['started_at'] ?? '') ?></p>
                <p><strong>Finished:</strong> <?= h($batch['finished_at'] ?? '') ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Imported categories:</strong> <?= (int) ($batch['categories_imported'] ?? 0) ?></p>
                <p><strong>Imported sites:</strong> <?= (int) ($batch['sites_imported'] ?? 0) ?></p>
                <p><strong>Errors:</strong> <?= (int) ($batch['errors_count'] ?? 0) ?></p>
            </div>
        </div>
    </div>
</div>
