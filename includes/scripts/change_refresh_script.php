<?php
/**
 * Purpose: Shared PHP include for change refresh script application behavior.
 * Included by: Admin footer, office upload, and user dashboard pages.
 * Inputs/outputs: Reads session role and optional irisChangeRefreshView; emits one script tag.
 * Dependencies: scanner/js/ui/changeRefresh.js and api/change_signal.php.
 * Load order: Set irisChangeRefreshView before including; defer lets page markup finish loading.
 */
?>
<script src="<?= e(base_url('scanner/js/ui/changeRefresh.js')) ?>?v=<?= (int)filemtime(__DIR__ . '/../../scanner/js/ui/changeRefresh.js') ?>" data-iris-change-refresh data-endpoint="<?= e(base_url('api/change_signal.php')) ?>" data-role="<?= e($_SESSION['role'] ?? '') ?>" data-view="<?= e($irisChangeRefreshView ?? '') ?>" defer></script>