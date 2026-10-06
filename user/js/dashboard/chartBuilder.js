/**
 * Purpose: Observatory dashboard browser logic for chart builder; loaded by the user dashboard.
 */
import * as IRISChartBuilder from '../../../scanner/js/modules/chartEngine.js?v=rank-axis-render-sync-20261004';
        window.IRISChartBuilder = IRISChartBuilder;

export function formatDoughnutLabel(displayName, params) {
    return `${displayName}: ${params.percent}%`;
}
