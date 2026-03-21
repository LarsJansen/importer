<?php
$batches = $batches ?? ['rows' => [], 'total' => 0, 'page' => 1, 'perPage' => 25, 'pages' => 1];
function h(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Batches</h1>
        <div class="text-muted">Import runs and their current review state.</div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Label</th>
                    <th>Status</th>
                    <th>Categories</th>
                    <th>Sites</th>
                    <th>Started</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($batches['rows'])): ?>
                    <?php foreach ($batches['rows'] as $batch): ?>
                        <tr>
                            <td><?= (int) ($batch['id'] ?? 0) ?></td>
                            <td><?= h($batch['label'] ?? '') ?></td>
                            <td><?= h($batch['status'] ?? '') ?></td>
                            <td><?= (int) ($batch['categories_imported'] ?? 0) ?></td>
                            <td><?= (int) ($batch['sites_imported'] ?? 0) ?></td>
                            <td><?= h($batch['started_at'] ?? '') ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="/batches/<?= (int) ($batch['id'] ?? 0) ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No batches found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
