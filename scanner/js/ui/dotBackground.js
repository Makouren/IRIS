/**
 * Purpose: Animate the dashboard dot background toward pointer movement.
 * Loaded by: Pages that render #dashboard-dot-background.
 * Inputs/outputs: Reads pointer and media-query state; updates background element styles.
 * Dependencies: DOM, requestAnimationFrame, and matchMedia.
 * Load order: Load after the background element; motion tracking respects reduced-motion settings.
 */
(() => {
  const background = document.getElementById('dashboard-dot-background');
  if (!background) return;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  let targetX = window.innerWidth / 2;
  let targetY = window.innerHeight / 2;
  let currentX = targetX;
  let currentY = targetY;
  let frame = 0;
  let tracking = false;
  background.style.setProperty('--mx', `${currentX}px`);
  background.style.setProperty('--my', `${currentY}px`);

  const easeTowardPointer = () => {
    currentX += (targetX - currentX) * 0.14;
    currentY += (targetY - currentY) * 0.14;
    background.style.setProperty('--mx', `${currentX}px`);
    background.style.setProperty('--my', `${currentY}px`);
    if (Math.abs(targetX - currentX) > 0.35 || Math.abs(targetY - currentY) > 0.35) {
      frame = requestAnimationFrame(easeTowardPointer);
    } else {
      frame = 0;
    }
  };

  const onMouseMove = event => {
    targetX = event.clientX;
    targetY = event.clientY;
    if (!frame) frame = requestAnimationFrame(easeTowardPointer);
  };

  const syncTracking = () => {
    const shouldTrack = !reducedMotion.matches && finePointer.matches;
    if (shouldTrack && !tracking) {
      window.addEventListener('mousemove', onMouseMove, { passive: true });
      tracking = true;
    } else if (!shouldTrack && tracking) {
      window.removeEventListener('mousemove', onMouseMove);
      if (frame) cancelAnimationFrame(frame);
      frame = 0;
      tracking = false;
    }
  };

  reducedMotion.addEventListener?.('change', syncTracking);
  finePointer.addEventListener?.('change', syncTracking);
  syncTracking();
})();
