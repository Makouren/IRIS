<?php
/**
 * Purpose: Shared PHP include for asset bundles application behavior.
 * Included by: scanner/index.php and admin pages that use the shared styles.
 * Inputs/outputs: Reads the ordered CSS filenames; emits versioned <link> tags.
 * Dependencies: includes/functions.php for base_url() and e().
 * Load order: Keep dark-overrides.css last so its overrides follow the base rules.
 */
require_once __DIR__ . '/../functions.php';

/**
 * Emit the ordered stylesheet links with cache-busting versions.
 *
 * @return void
 * @side-effects Writes stylesheet link elements to the response.
 */
function render_iris_stylesheet_bundle(): void
{
    // File modification times invalidate browser caches; order preserves the CSS cascade.
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
        $filePath = __DIR__ . '/../../scanner/css/' . $stylesheet;
        $url = base_url('scanner/css/' . $stylesheet);
        echo '<link rel="stylesheet" href="' . e($url) . '?v=' . (int)filemtime($filePath) . '">' . PHP_EOL;
    }
}
