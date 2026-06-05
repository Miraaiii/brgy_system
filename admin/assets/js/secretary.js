(function () {
  const body = document.body;
  const sidebar = document.getElementById('adminSidebar');
  const scrim = document.getElementById('sidebarScrim');
  const themeToggle = document.getElementById('adminThemeToggle') || document.getElementById('themeToggle');

  function getSavedTheme() {
    try {
      return localStorage.getItem('barangayTheme') || localStorage.getItem('residentTheme');
    } catch (error) {
      return null;
    }
  }

  function saveTheme(theme) {
    try {
      localStorage.setItem('barangayTheme', theme);
      localStorage.setItem('residentTheme', theme);
    } catch (error) {
      // Theme preference is optional when storage is unavailable.
    }
  }

  function setTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark-mode-preload', isDark);
    body.classList.toggle('dark-mode', isDark);

    if (themeToggle) {
      themeToggle.setAttribute('aria-pressed', String(isDark));
      themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
      const icon = themeToggle.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-moon', !isDark);
        icon.classList.toggle('fa-sun', isDark);
      }
    }
  }

  const savedTheme = getSavedTheme();
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  setTheme(savedTheme || (prefersDark ? 'dark' : 'light'));

  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const nextTheme = body.classList.contains('dark-mode') ? 'light' : 'dark';
      setTheme(nextTheme);
      saveTheme(nextTheme);
    });
  }

  function setSidebar(open) {
    if (!sidebar || !scrim) return;
    sidebar.classList.toggle('is-open', open);
    scrim.hidden = !open;
    body.classList.toggle('is-locked', open);
  }

  document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => setSidebar(true));
  });

  document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
    button.addEventListener('click', () => setSidebar(false));
  });

  document.querySelectorAll('[data-dropdown-toggle]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();
      const id = button.getAttribute('data-dropdown-toggle');
      const panel = id ? document.getElementById(id) : null;
      if (!panel) return;

      const willOpen = panel.hidden;
      document.querySelectorAll('.dropdown-panel').forEach((item) => {
        item.hidden = true;
      });
      document.querySelectorAll('[data-dropdown-toggle]').forEach((item) => {
        item.setAttribute('aria-expanded', 'false');
      });

      panel.hidden = !willOpen;
      button.setAttribute('aria-expanded', String(willOpen));
    });
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown-panel').forEach((panel) => {
      panel.hidden = true;
    });
    document.querySelectorAll('[data-dropdown-toggle]').forEach((button) => {
      button.setAttribute('aria-expanded', 'false');
    });
  });

  document.querySelectorAll('.dropdown-panel').forEach((panel) => {
    panel.addEventListener('click', (event) => event.stopPropagation());
  });

  document.querySelectorAll('[data-table-search]').forEach((input) => {
    const target = document.querySelector(input.getAttribute('data-table-search'));
    if (!target) return;

    input.addEventListener('input', () => {
      const query = input.value.trim().toLowerCase();
      target.querySelectorAll('tbody tr').forEach((row) => {
        const text = row.textContent.toLowerCase();
        row.hidden = query !== '' && !text.includes(query);
      });
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.getAttribute('data-confirm') || 'Continue with this action?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('form[data-disable-on-submit]').forEach((form) => {
    form.addEventListener('submit', () => {
      const submitter = form.querySelector('button[type="submit"], input[type="submit"]');
      if (submitter) {
        submitter.disabled = true;
        if (submitter.tagName === 'BUTTON') {
          submitter.dataset.originalText = submitter.innerHTML;
          submitter.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Working';
        }
      }
    });
  });
})();
