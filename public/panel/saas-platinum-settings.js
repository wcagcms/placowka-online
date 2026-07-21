(() => {
    'use strict';

    const normalizeText = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pl');

    const setupServiceFilters = () => {
        const search = document.querySelector('[data-service-search]');
        const filter = document.querySelector('[data-service-filter]');
        const cards = Array.from(document.querySelectorAll('[data-service-card]'));
        const count = document.querySelector('[data-service-visible-count]');
        const empty = document.querySelector('[data-filter-empty]');

        if (!search || !filter || cards.length === 0) {
            return;
        }

        const applyFilters = () => {
            const query = normalizeText(search.value.trim());
            const selected = filter.value;
            let visible = 0;

            cards.forEach((card) => {
                const haystack = normalizeText(card.dataset.search);
                const monitored = card.dataset.monitored === '1';
                const alert = card.dataset.alert === '1';

                const matchesSearch = query === '' || haystack.includes(query);
                const matchesFilter = selected === 'all'
                    || (selected === 'monitored' && monitored)
                    || (selected === 'alerts' && alert)
                    || (selected === 'disabled' && !monitored);

                const shouldShow = matchesSearch && matchesFilter;
                card.hidden = !shouldShow;

                if (shouldShow) {
                    visible += 1;
                }
            });

            if (count) {
                count.textContent = String(visible);
            }

            if (empty) {
                empty.hidden = visible !== 0;
            }
        };

        search.addEventListener('input', applyFilters);
        filter.addEventListener('change', applyFilters);
        applyFilters();
    };

    const setupDeleteConfirmation = () => {
        document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const message = form.dataset.confirmMessage || 'Czy na pewno wykonać tę operację?';

                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    };

    const setupSettingsPreview = () => {
        const form = document.querySelector('[data-settings-form]');

        if (!form) {
            return;
        }

        const saveBar = document.querySelector('[data-save-bar]');
        const saveState = document.querySelector('[data-save-state]');
        const systemNameInput = document.querySelector('[data-system-name-input]');
        const systemNamePreview = document.querySelector('[data-system-name-preview]');
        const alertToggle = document.querySelector('[data-alert-toggle]');
        const alertSummary = document.querySelector('[data-alert-summary]');
        const alertPreview = document.querySelector('[data-alert-preview]');

        const markDirty = () => {
            if (saveBar) {
                saveBar.classList.add('is-dirty');
            }

            if (saveState) {
                saveState.textContent = 'Masz niezapisane zmiany';
            }
        };

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);

        if (systemNameInput && systemNamePreview) {
            systemNameInput.addEventListener('input', () => {
                systemNamePreview.textContent = systemNameInput.value.trim() || 'Placówka Online';
            });
        }

        if (alertToggle) {
            const updateAlertState = () => {
                const enabled = alertToggle.checked;

                if (alertSummary) {
                    alertSummary.textContent = enabled ? 'Włączone' : 'Wyłączone';
                }

                if (alertPreview) {
                    alertPreview.textContent = enabled ? 'Aktywne' : 'Wyłączone';
                }
            };

            alertToggle.addEventListener('change', updateAlertState);
            updateAlertState();
        }

        const summaryMap = {
            missing: document.querySelector('[data-missing-summary]'),
            interval: document.querySelector('[data-interval-summary]'),
            retention: document.querySelector('[data-retention-summary]'),
        };

        form.querySelectorAll('[data-summary-target]').forEach((input) => {
            input.addEventListener('input', () => {
                const target = summaryMap[input.dataset.summaryTarget];

                if (target) {
                    target.textContent = input.value || '0';
                }
            });
        });

        form.addEventListener('submit', () => {
            if (saveState) {
                saveState.textContent = 'Zapisywanie ustawień…';
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        setupServiceFilters();
        setupDeleteConfirmation();
        setupSettingsPreview();
    });
})();
