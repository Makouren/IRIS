import { $ } from '../utils/helpers.js';

export function initNavigation(ctx) {
  const scannerButton = $('navScannerBtn');
  const scannerView = $('scannerWorkspaceView');
  const adminView = $('adminDatabaseView');

  const showReviewWorkspace = async () => {
    scannerButton?.classList.add('active');
    if (scannerView) scannerView.style.display = 'block';
    if (adminView) adminView.style.display = 'none';
  };

  scannerButton?.addEventListener('click', () => { showReviewWorkspace(); });

  ctx.api.openReviewStudio = async () => {
    await showReviewWorkspace();
    if (adminView) {
      adminView.style.display = 'block';
      adminView.scrollIntoView({ behavior: 'smooth', block: 'start' });
      await ctx.api.renderAdminPortal();
    }
  };
}
