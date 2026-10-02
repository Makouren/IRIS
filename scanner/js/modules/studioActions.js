export function initStudioActions(ctx) {
  let pending = false;
  ['studioBtnSave', 'studioBtnApprove'].forEach(id => {
    const button = document.getElementById(id);
    if (button) button.disabled = true;
  });
  const bind = (id, approve) => document.getElementById(id)?.addEventListener('click', async () => {
    if (pending) return;
    const record = ctx.state.studioActiveRecord;
    if (!record) {
      alert('Select a record before saving or publishing.');
      return;
    }
    const category = document.getElementById('studioDocTypeInput');
    if (!category) return;
    category.setCustomValidity(category.value.trim() ? '' : 'Classification category is required.');
    if (!category.reportValidity()) return;
    const status = document.getElementById('studioStatusSelect');
    if (!status) return;
    status.setCustomValidity(['Pending Review', 'Approved', 'Needs Revision'].includes(status.value) ? '' : 'Choose a valid publication status.');
    if (!status.reportValidity()) return;
    const buttons = ['studioBtnSave', 'studioBtnApprove'].map(buttonId => document.getElementById(buttonId)).filter(Boolean);
    pending = true;
    buttons.forEach(button => { button.disabled = true; });
    try {
      await ctx.api.saveStudioData?.(approve);
    } catch (error) {
      alert(`Unable to ${approve ? 'publish' : 'save graph'}: ${error.message || error}`);
    } finally {
      pending = false;
      buttons.forEach(button => { button.disabled = false; });
    }
  });
  bind('studioBtnSave', false); bind('studioBtnApprove', true);
}
