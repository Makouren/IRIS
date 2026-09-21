import { $ } from '../utils/helpers.js';

export function initNavigation(ctx) {
  const scannerButton = $('navScannerBtn');
  const scannerView = $('scannerWorkspaceView');
  const adminView = $('adminDatabaseView');

  const showReviewWorkspace = async () => {
    scannerButton?.classList.add('active');
    if (scannerView) scannerView.style.display = 'block';
    if (adminView) adminView.style.display = 'none';
    scannerView?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  scannerButton?.addEventListener('click', event => {
    event.preventDefault();
    void showReviewWorkspace();
  });

  ctx.api.openReviewStudio = async recordId => {
    await showReviewWorkspace();
    if (adminView) {
      adminView.style.display = 'block';
      adminView.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (recordId) {
        const records = await ctx.dbManager.getAllRecords();
        ctx.state.studioActiveRecord = records.find(record => record.id === recordId) || ctx.state.studioActiveRecord;
      }
      await ctx.api.renderAdminPortal();
    }
  };
}
