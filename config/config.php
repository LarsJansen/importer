<?php
return [
    'app_name' => 'Curlie Importer Lab',
    'base_url' => 'http://importer',
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'importer',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'paths' => [
        'storage' => __DIR__ . '/../storage',
        'exports' => __DIR__ . '/../storage/exports',
        'logs' => __DIR__ . '/../storage/logs',
    ],
];
