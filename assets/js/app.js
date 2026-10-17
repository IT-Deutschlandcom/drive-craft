(() => {
  'use strict';
  const root = document.documentElement;
  const themeButton = document.querySelector('[data-theme-toggle]');
  const updateThemeButton = () => {
    if (!themeButton) return;
    const dark = root.dataset.bsTheme === 'dark';
    themeButton.textContent = dark ? '☀' : '☾';
    themeButton.setAttribute('aria-label', dark ? 'Hellen Modus aktivieren' : 'Dunklen Modus aktivieren');
  };
  updateThemeButton();
  themeButton?.addEventListener('click', () => {
    const theme = root.dataset.bsTheme === 'dark' ? 'light' : 'dark';
    root.dataset.bsTheme = theme;
    try { localStorage.setItem('dc-theme', theme); } catch (_) { /* Storage may be disabled. */ }
    updateThemeButton();
  });
  const menu = document.querySelector('.main-nav');
  document.querySelector('[data-menu-toggle]')?.addEventListener('click', (event) => {
    const open = menu.classList.toggle('open');
    event.currentTarget.setAttribute('aria-expanded', String(open));
  });
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if ('IntersectionObserver' in window && !reducedMotion) {
    root.classList.add('motion-ready');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08 });
    document.querySelectorAll('.reveal').forEach((element) => observer.observe(element));
  }
  const topButton = document.querySelector('[data-back-top]');
  window.addEventListener('scroll', () => topButton?.classList.toggle('visible', scrollY > 600), { passive: true });
  topButton?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reducedMotion ? 'instant' : 'smooth' }));
  document.querySelectorAll('[data-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      const group = button.closest('[data-filter-group]');
      group.querySelectorAll('[data-filter]').forEach((item) => {
        const active = item === button;
        item.classList.toggle('active', active);
        item.setAttribute('aria-pressed', String(active));
      });
      document.querySelectorAll('[data-category]').forEach((card) => {
        card.hidden = button.dataset.filter !== '*' && card.dataset.category !== button.dataset.filter;
      });
    });
  });
  const lightbox = document.querySelector('#gallery-lightbox');
  if (lightbox && typeof lightbox.showModal === 'function') {
    document.querySelectorAll('[data-lightbox]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        lightbox.querySelector('img').src = link.href;
        lightbox.querySelector('img').alt = link.dataset.title || '';
        lightbox.querySelector('h2').textContent = link.dataset.title || '';
        lightbox.showModal();
      });
    });
    lightbox.querySelector('[data-close]')?.addEventListener('click', () => lightbox.close());
    lightbox.addEventListener('click', (event) => { if (event.target === lightbox) lightbox.close(); });
  }
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
  });
  document.querySelectorAll('form[data-once]').forEach((form) => {
    form.addEventListener('submit', () => {
      if (!form.checkValidity()) return;
      const button = form.querySelector('[type="submit"]');
      if (button) { button.disabled = true; button.textContent = 'Wird verarbeitet …'; }
    });
  });
})();
