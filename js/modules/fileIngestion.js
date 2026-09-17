import { $ , all } from '../utils/helpers.js';

export function initFileIngestion(ctx) {
  const dropzone = $('dropzone'); const input = $('fileInput'); const browse = $('btnBrowse');
  const progressCard = $('progressCard'); const workspace = $('workspaceGrid');
  const status = $('progressStatus'); const percent = $('progressPercent'); const fill = $('progressFill');
  const updateProgress = (text, value) => { if (status) status.textContent = text; if (percent) percent.textContent = `${value}%`; if (fill) fill.style.width = `${value}%`; };
  const handleFiles = async (files) => {
    if (!files?.length) return;
    if (progressCard) progressCard.style.display = 'block'; if (workspace) workspace.style.display = 'grid';
    for (let index = 0; index < files.length; index += 1) {
      const file = files[index];
      try {
        updateProgress(`Scanning ${file.name} (${index + 1}/${files.length})...`, 10);
        const result = await ctx.scanner.scanFile(file, progress => updateProgress(progress.status, progress.progress));
        ctx.state.queue.unshift(result); ctx.api.renderQueue(); await ctx.api.setActiveScan(result);
      } catch (error) { console.error('Scan Error:', error); alert(`Failed to scan file ${file.name}: ${error.message}`); }
    }
    setTimeout(() => { if (progressCard) progressCard.style.display = 'none'; updateProgress('Scan complete!', 100); }, 800);
  };
  ctx.api.handleFiles = handleFiles; ctx.api.updateProgress = updateProgress;
  browse?.addEventListener('click', () => input?.click());
  input?.addEventListener('change', event => handleFiles(Array.from(event.target.files || [])));
  ['dragenter', 'dragover'].forEach(name => dropzone?.addEventListener(name, event => { event.preventDefault(); dropzone.classList.add('dragover'); }));
  ['dragleave', 'drop'].forEach(name => dropzone?.addEventListener(name, event => { event.preventDefault(); dropzone.classList.remove('dragover'); }));
  dropzone?.addEventListener('drop', event => handleFiles(Array.from(event.dataTransfer?.files || [])));
  all('.sample-btn').forEach(button => button.addEventListener('click', () => {
    const type = button.getAttribute('data-sample');
    const generator = window.SampleGenerator; let file = null;
    if (type === 'payroll') file = generator?.createSampleExcelFile();
    if (type === 'contract') file = generator?.createSampleDocxFile();
    if (type === 'pdf') file = generator?.createSamplePdfFile();
    if (file) handleFiles([file]);
  }));
}
