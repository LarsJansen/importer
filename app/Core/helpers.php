<?php
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../../config/config.php';
    }
    return $config;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function paginate_url(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    $query = http_build_query($params);
    return strtok($_SERVER['REQUEST_URI'] ?? '', '?') . ($query ? '?' . $query : '');
}
