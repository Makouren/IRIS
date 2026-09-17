export function initStudioActions(ctx) {
  const bind = (id, approve) => document.getElementById(id)?.addEventListener('click', () => ctx.state.studioActiveRecord && ctx.api.saveStudioData?.(approve));
  bind('studioBtnSave', false); bind('studioBtnApprove', true);
}
