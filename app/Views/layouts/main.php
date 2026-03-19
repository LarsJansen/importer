<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($config['app_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .stat-card { min-height: 120px; }
        code.small-path { font-size: .85rem; white-space: normal; word-break: break-word; }
        .sticky-actions { position: sticky; bottom: 0; z-index: 5; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="/">Curlie Importer Lab</a>
        <div class="navbar-nav">
            <a class="nav-link" href="/">Dashboard</a>
            <a class="nav-link" href="/batches">Batches</a>
            <a class="nav-link" href="/categories">Categories</a>
            <a class="nav-link" href="/sites">Sites</a>
            <a class="nav-link" href="/mappings">Mappings</a>
            <a class="nav-link" href="/exports">Exports</a>
        </div>
    </div>
</nav>
<div class="container pb-5">
    <?php require $viewFile; ?>
</div>
<script>
document.querySelectorAll('[data-check-all]').forEach(function(master) {
    master.addEventListener('change', function() {
        var target = master.getAttribute('data-check-all');
        document.querySelectorAll('input[name="ids[]"][data-group="' + target + '"]').forEach(function(box) {
            box.checked = master.checked;
        });
    });
});
</script>
</body>
</html>
