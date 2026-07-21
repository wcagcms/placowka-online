(() => {
    'use strict';

    const body = document.body;
    const button = document.getElementById('panelMenuButton');
    const sidebar = document.getElementById('panelSidebar');
    const overlay = document.querySelector('[data-panel-overlay]');

    const setMenuState = (open) => {
        body.classList.toggle('po-nav-open', open);

        if (button) {
            button.setAttribute('aria-expanded', String(open));
            button.setAttribute('aria-label', open ? 'Zamknij menu' : 'Otwórz menu');
        }

        if (overlay) {
            overlay.hidden = !open;
        }
    };

    button?.addEventListener('click', () => {
        setMenuState(!body.classList.contains('po-nav-open'));
    });

    overlay?.addEventListener('click', () => setMenuState(false));

    document.querySelectorAll('[data-close-panel-nav]').forEach((link) => {
        link.addEventListener('click', () => setMenuState(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !body.classList.contains('po-nav-open')) {
            return;
        }

        setMenuState(false);
        button?.focus();
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1080) {
            setMenuState(false);
        }
    });
})();
