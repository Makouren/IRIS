/**
 * Purpose: Control shared portal menus, theme switching, and password-change modal behavior.
 * Loaded by: Shared portal header/footer on admin, office, and Observatory pages.
 * Inputs/outputs: Reads portal DOM hooks and form data; updates menus and submits password changes.
 * Dependencies: api/change_password.php and server-provided CSRF/form data attributes.
 * Load order: Load after the shared portal markup.
 */
(() => {
  const toggle = document.getElementById('portalNavToggle');
  const menu = document.getElementById('portalNavMenu');
  const profileToggle = document.getElementById('user-menu-button');
  const profileMenu = document.getElementById('user-dropdown');
  const officeThemeToggle = document.getElementById('officeThemeToggle');
  const officeIsDark = () => document.documentElement.classList.contains('dark');
  const setOfficeTheme = dark => {
    document.documentElement.classList.toggle('dark', dark);
    const mode = dark ? 'dark' : 'light';
    localStorage.setItem('color-theme', mode);
    localStorage.setItem('iris-theme', mode);
    officeThemeToggle?.setAttribute('aria-pressed', String(dark));
    officeThemeToggle?.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
    officeThemeToggle?.setAttribute('title', dark ? 'Switch to light theme' : 'Switch to dark theme');
    document.getElementById('office-theme-dark-icon')?.classList.toggle('hidden', dark);
    document.getElementById('office-theme-light-icon')?.classList.toggle('hidden', !dark);
  };

  if (officeThemeToggle) {
    setOfficeTheme(officeIsDark());
    officeThemeToggle.addEventListener('click', () => setOfficeTheme(!officeIsDark()));
  }

  if (profileToggle && profileMenu) {
    const setProfileMenuOpen = open => {
      profileToggle.setAttribute('aria-expanded', String(open));
      profileMenu.classList.toggle('hidden', !open);
    };

    profileToggle.addEventListener('click', () => {
      setProfileMenuOpen(profileToggle.getAttribute('aria-expanded') !== 'true');
    });
    document.addEventListener('pointerdown', event => {
      if (profileToggle.getAttribute('aria-expanded') === 'true' && !profileMenu.contains(event.target) && !profileToggle.contains(event.target)) {
        setProfileMenuOpen(false);
      }
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && profileToggle.getAttribute('aria-expanded') === 'true') {
        setProfileMenuOpen(false);
        profileToggle.focus();
      }
    });
    profileMenu.addEventListener('click', event => {
      if (event.target.closest('a, button')) setProfileMenuOpen(false);
    });
  }

  if (!toggle || !menu) return;

  const setMenuOpen = open => {
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    menu.classList.toggle('is-open', open);
  };

  toggle.addEventListener('click', () => {
    setMenuOpen(toggle.getAttribute('aria-expanded') !== 'true');
  });
  document.addEventListener('pointerdown', event => {
    if (toggle.getAttribute('aria-expanded') === 'true' && !menu.contains(event.target) && !toggle.contains(event.target)) setMenuOpen(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
      setMenuOpen(false);
      toggle.focus();
    }
  });
  document.addEventListener('focusin', event => {
    if (toggle.getAttribute('aria-expanded') === 'true' && !menu.contains(event.target) && event.target !== toggle) setMenuOpen(false);
  });
  menu.addEventListener('click', event => {
    if (event.target.closest('a') && !event.target.closest('#user-menu-button')) setMenuOpen(false);
  });
  window.addEventListener('resize', () => {
    if (window.matchMedia('(min-width: 1025px)').matches) setMenuOpen(false);
  });
})();
