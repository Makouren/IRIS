/**
 * Purpose: Scanner browser logic for portal navigation; loaded by the Scanner page.
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
  const passwordModal = document.getElementById('passwordChangeModal');
  const passwordForm = passwordModal?.querySelector('[data-password-change-form]');
  const passwordStatus = passwordModal?.querySelector('[data-password-change-status]');
  const officeThemeToggle = document.getElementById('officeThemeToggle');
  const officeIsDark = () => document.documentElement.classList.contains('dark');
  const themeObserver = new MutationObserver(() => {
    requestAnimationFrame(() => requestAnimationFrame(() => {
      document.querySelectorAll('[_echarts_instance_]').forEach(element => {
        const bounds = element.getBoundingClientRect();
        if (bounds.width <= 0 || bounds.height <= 0) return;
        window.echarts?.getInstanceByDom(element)?.resize?.();
      });
    }));
  });
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
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

  if (passwordModal && passwordForm && passwordStatus) {
    const setPasswordModalOpen = open => {
      passwordModal.classList.toggle('hidden', !open);
      passwordModal.classList.toggle('flex', open);
      passwordModal.setAttribute('aria-hidden', String(!open));
      if (open) passwordForm.querySelector('[name="current_password"]')?.focus();
    };
    const showPasswordStatus = (message, isError = false) => {
      passwordStatus.textContent = message;
      passwordStatus.className = `mb-4 rounded-lg p-3 text-sm ${isError
        ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200'
        : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'}`;
    };

    document.querySelectorAll('[data-password-change-open]').forEach(button => {
      button.addEventListener('click', () => {
        passwordStatus.classList.add('hidden');
        setPasswordModalOpen(true);
      });
    });
    passwordModal.querySelectorAll('[data-password-change-close]').forEach(button => {
      button.addEventListener('click', () => setPasswordModalOpen(false));
    });
    passwordModal.addEventListener('click', event => {
      if (event.target === passwordModal) setPasswordModalOpen(false);
    });
    passwordForm.addEventListener('submit', async event => {
      event.preventDefault();
      const submitButton = passwordForm.querySelector('[type="submit"]');
      submitButton.disabled = true;
      try {
        const response = await fetch(passwordForm.dataset.api, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-Token': passwordForm.dataset.csrf || ''
          },
          body: JSON.stringify(Object.fromEntries(new FormData(passwordForm)))
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Unable to update your password.');
        passwordForm.reset();
        showPasswordStatus(result.message || 'Password updated.');
      } catch (error) {
        showPasswordStatus(error.message, true);
      } finally {
        submitButton.disabled = false;
      }
    });
  }

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && passwordModal && !passwordModal.classList.contains('hidden')) {
      passwordModal.classList.add('hidden');
      passwordModal.classList.remove('flex');
      passwordModal.setAttribute('aria-hidden', 'true');
    }
  });

  if (!toggle || !menu) return;

  const setMenuOpen = open => {
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    menu.classList.toggle('is-open', open);
    if (profileToggle && profileMenu && window.matchMedia('(max-width: 1024px)').matches) {
      const hasProfileActions = Boolean(profileMenu.querySelector('ul a, ul button, form'));
      const showProfileActions = open && hasProfileActions;
      profileToggle.setAttribute('aria-expanded', String(showProfileActions));
      profileMenu.classList.toggle('hidden', !showProfileActions);
    }
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
