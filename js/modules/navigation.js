import { $ } from '../utils/helpers.js';

export function initNavigation(ctx) {
  const scannerButton = $('navScannerBtn');
  const adminButton = $('navAdminBtn');
  const scannerView = $('scannerWorkspaceView');
  const adminView = $('adminDatabaseView');
  scannerButton?.addEventListener('click', () => {
    scannerButton.classList.add('active'); adminButton?.classList.remove('active');
    if (scannerView) scannerView.style.display = 'block';
    if (adminView) adminView.style.display = 'none';
  });
  adminButton?.addEventListener('click', async () => {
    adminButton.classList.add('active'); scannerButton?.classList.remove('active');
    if (scannerView) scannerView.style.display = 'none';
    if (adminView) adminView.style.display = 'block';
    await ctx.api.renderAdminPortal();
  });
}
