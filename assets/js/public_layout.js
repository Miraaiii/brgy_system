document.addEventListener('DOMContentLoaded', () => {
  const nav = document.getElementById('mainNav');
  const navToggle = document.getElementById('navToggle');
  const mobileMenu = document.getElementById('mobileMenu');
  const backToTop = document.getElementById('backToTop');

  const syncScrolled = () => {
    if (nav) {
      nav.classList.toggle('scrolled', window.scrollY > 36);
    }
    if (backToTop) {
      backToTop.classList.toggle('visible', window.scrollY > 420);
    }
  };

  syncScrolled();
  window.addEventListener('scroll', syncScrolled, { passive: true });

  if (navToggle && mobileMenu) {
    navToggle.addEventListener('click', () => {
      const isOpen = mobileMenu.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', String(isOpen));
      navToggle.innerHTML = isOpen
        ? '<i class="bi bi-x-lg" aria-hidden="true"></i>'
        : '<i class="bi bi-list" aria-hidden="true"></i>';
    });

    mobileMenu.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        mobileMenu.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.innerHTML = '<i class="bi bi-list" aria-hidden="true"></i>';
      });
    });
  }

  if (backToTop) {
    backToTop.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
});
