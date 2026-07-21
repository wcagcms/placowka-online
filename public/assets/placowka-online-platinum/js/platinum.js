(() => {
  'use strict';

  const body = document.body;
  const menuButton = document.getElementById('menuButton');
  const sidebar = document.getElementById('sidebar');

  const setMenuState = (isOpen) => {
    body.classList.toggle('nav-open', isOpen);
    if (menuButton) {
      menuButton.setAttribute('aria-expanded', String(isOpen));
      menuButton.setAttribute('aria-label', isOpen ? 'Zamknij menu' : 'Otwórz menu');
    }
  };

  menuButton?.addEventListener('click', () => {
    setMenuState(!body.classList.contains('nav-open'));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !body.classList.contains('nav-open')) return;
    setMenuState(false);
    menuButton?.focus();
  });

  document.addEventListener('click', (event) => {
    if (!body.classList.contains('nav-open') || !sidebar || !menuButton) return;
    if (!sidebar.contains(event.target) && !menuButton.contains(event.target)) setMenuState(false);
  });

  document.querySelectorAll('[data-close-mobile-nav]').forEach((link) => {
    link.addEventListener('click', () => setMenuState(false));
  });

})();
