import { $ } from '../utils/helpers.js';

export function initNavigation(ctx) {
  const scannerButton = $('navScannerBtn');
  const archivesButton = $('navArchivesBtn');
  const scannerView = $('scannerWorkspaceView');
  const adminView = $('adminDatabaseView');

  const showReviewWorkspace = async () => {
    scannerButton?.classList.add('active');
    archivesButton?.classList.remove('active');
    if (scannerView) scannerView.style.display = 'block';
    if (adminView) adminView.style.display = 'none';
  };

  scannerButton?.addEventListener('click', event => {
    event.preventDefault();
    showReviewWorkspace();
  });

  archivesButton?.addEventListener('click', event => {
    event.preventDefault();
    scannerButton?.classList.remove('active');
    archivesButton.classList.add('active');
    if (scannerView) scannerView.style.display = 'block';
    if (adminView) adminView.style.display = 'block';
    document.querySelector('[data-admin-tab="adminFileArchivesPanel"]')?.click();
  });

  ctx.api.openReviewStudio = async (recordId) => {
    if (window.location.pathname.includes('/admin/')) {
      const param = recordId ? `?record_id=${encodeURIComponent(recordId)}` : '';
      window.location.href = `review_editor.php${param}`;
      return;
    }
    await showReviewWorkspace();
    archivesButton?.classList.remove('active');
    scannerView?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (adminView) {
      adminView.style.display = 'block';
      await ctx.api.renderAdminPortal();
    }
  };
}
