<?php
require_once __DIR__ . '/functions.php';

function render_iris_stylesheet_bundle(): void
{
    $stylesheets = [
        'base.css',
        'ingestion.css',
        'layout.css',
        'studio.css',
        'viewer.css',
        'charts.css',
        'tables.css',
        'form-controls.css',
        'modals.css',
        'docx-viewer.css',
        'dark-overrides.css',
    ];

    foreach ($stylesheets as $stylesheet) {
        $filePath = __DIR__ . '/../scanner/css/' . $stylesheet;
        $url = base_url('scanner/css/' . $stylesheet);
        echo '<link rel="stylesheet" href="' . e($url) . '?v=' . (int)filemtime($filePath) . '">' . PHP_EOL;
    }
}
