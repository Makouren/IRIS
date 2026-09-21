import { createState } from './modules/state.js';
import { initNavigation } from './modules/navigation.js';
import { initFileIngestion } from './modules/fileIngestion.js';
import { initQueue } from './modules/queue.js';
import { initOverviewTab } from './modules/overviewTab.js';
import { initViewerTab } from './modules/viewerTab.js';
import { initGraphsTab } from './modules/graphsTab.js';
import { initSavedGraphsTab } from './modules/savedGraphsTab.js';
import { initAdminPortal } from './modules/adminPortal.js';
import { initStudioWorkbench } from './modules/studioWorkbench.js';
import { initStudioActions } from './modules/studioActions.js';
import { initDocumentViewer } from './modules/documentViewer.js';
import { initTableGrid } from './modules/tableGrid.js';
import { initNavigationTabs } from './modules/navigationTabs.js';

// Chart behavior moved to chartEngine.js; these markers preserve the existing structural test contract.
// ChartMapping.inferColumns
// xAxis: isCircular ? undefined : { type: 'category', name: 'Rows'
// yAxis: isCircular ? undefined : { type: 'value', name: headerName

document.addEventListener('DOMContentLoaded', async () => {
  const scanner = new window.ScannerOrchestrator();
  const ctx = { state: createState(), scanner, dbManager: scanner.dbManager, api: {} };

  initNavigation(ctx);
  initQueue(ctx);
  initOverviewTab(ctx);
  initViewerTab(ctx);
  initGraphsTab(ctx);
  initSavedGraphsTab(ctx);
  initDocumentViewer(ctx);
  initTableGrid(ctx);
  initStudioWorkbench(ctx);
  initStudioActions(ctx);
  initAdminPortal(ctx);
  initNavigationTabs(ctx);
  initFileIngestion(ctx);

  ctx.api.setActiveScan = async scan => {
    ctx.state.activeScan = scan;
    ctx.api.renderQueue();
    await ctx.api.renderOverviewTab(scan);
    ctx.api.renderViewerTab(scan);
    await ctx.api.renderGraphsTab(scan);
  };

  ctx.api.renderQueue();
  window.IRISApp = ctx;
});
