(() => {
    'use strict';

    const normalize = (value) => String(value ?? '')
        .toLocaleLowerCase('pl-PL')
        .trim();

    const initMonitoringCenter = () => {
        const root = document.querySelector('[data-monitoring-center]');

        if (!root) {
            return;
        }

        const snapshotUrl = root.dataset.snapshotUrl;
        const container = document.getElementById('monitoring-snapshot');
        const refreshButton = document.getElementById('monitoring-refresh-button');
        const autoRefresh = document.getElementById('monitoring-auto-refresh');
        const refreshState = document.getElementById('monitoring-refresh-state');
        const generatedAt = document.getElementById('monitoring-generated-at');
        const liveMessage = document.getElementById('monitoring-live-message');
        const searchInput = root.querySelector('[data-monitoring-search]');
        const statusFilter = root.querySelector('[data-monitoring-status-filter]');
        const resultLabel = document.getElementById('monitoring-filter-result');

        if (!snapshotUrl || !container || !refreshButton || !autoRefresh) {
            return;
        }

        let refreshing = false;

        const applyMonitoringFilters = () => {
            const query = normalize(searchInput?.value);
            const state = statusFilter?.value ?? 'all';
            const cards = Array.from(container.querySelectorAll('[data-monitoring-facility-card]'));
            let visible = 0;

            cards.forEach((card) => {
                const matchesQuery = !query || normalize(card.dataset.search).includes(query);
                const matchesState = state === 'all' || card.dataset.status === state;
                const show = matchesQuery && matchesState;

                card.hidden = !show;

                if (show) {
                    visible += 1;
                }
            });

            const emptyState = container.querySelector('[data-monitoring-empty-filter]');

            if (emptyState) {
                emptyState.hidden = cards.length === 0 || visible > 0;
            }

            if (resultLabel) {
                if (cards.length === 0) {
                    resultLabel.textContent = '';
                } else {
                    resultLabel.textContent = `Widoczne placówki: ${visible} z ${cards.length}.`;
                }
            }
        };

        const setButtonState = (active) => {
            refreshButton.disabled = active;
            refreshButton.textContent = active ? 'Odświeżanie…' : 'Odśwież dane';
        };

        const refreshSnapshot = async (announce = false) => {
            if (refreshing) {
                return;
            }

            refreshing = true;
            setButtonState(true);

            try {
                const response = await fetch(snapshotUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                if (typeof data.html !== 'string') {
                    throw new Error('Nieprawidłowa odpowiedź serwera.');
                }

                container.innerHTML = data.html;

                if (generatedAt) {
                    generatedAt.dateTime = data.generated_at;
                    generatedAt.textContent = data.generated_at_label;
                }

                applyMonitoringFilters();

                if (announce && liveMessage) {
                    liveMessage.textContent = `Dane centrum monitoringu odświeżono o ${data.generated_at_label}.`;
                }
            } catch (error) {
                if (liveMessage) {
                    liveMessage.textContent = 'Nie udało się odświeżyć danych centrum monitoringu. Spróbuj ponownie.';
                }
            } finally {
                refreshing = false;
                setButtonState(false);
            }
        };

        refreshButton.addEventListener('click', () => refreshSnapshot(true));

        searchInput?.addEventListener('input', applyMonitoringFilters);
        statusFilter?.addEventListener('change', applyMonitoringFilters);

        autoRefresh.addEventListener('change', () => {
            if (refreshState) {
                refreshState.textContent = autoRefresh.checked
                    ? 'Włączone co 30 sekund'
                    : 'Wyłączone';
            }
        });

        window.setInterval(() => {
            if (autoRefresh.checked && document.visibilityState === 'visible') {
                refreshSnapshot(false);
            }
        }, 30000);

        applyMonitoringFilters();
    };

    const initReportDirectory = () => {
        const root = document.querySelector('[data-report-directory]');

        if (!root) {
            return;
        }

        const searchInput = root.querySelector('[data-report-search]');
        const stateFilter = root.querySelector('[data-report-state-filter]');
        const resultLabel = root.querySelector('[data-report-result]');
        const cards = Array.from(root.querySelectorAll('[data-report-card]'));
        const emptyState = root.querySelector('[data-report-empty]');

        const applyReportFilters = () => {
            const query = normalize(searchInput?.value);
            const state = stateFilter?.value ?? 'all';
            let visible = 0;

            cards.forEach((card) => {
                const matchesQuery = !query || normalize(card.dataset.search).includes(query);
                const matchesState = state === 'all' || card.dataset.state === state;
                const show = matchesQuery && matchesState;

                card.hidden = !show;

                if (show) {
                    visible += 1;
                }
            });

            if (emptyState) {
                emptyState.hidden = cards.length === 0 || visible > 0;
            }

            if (resultLabel) {
                resultLabel.textContent = cards.length > 0
                    ? `Widoczne raporty: ${visible} z ${cards.length}.`
                    : '';
            }
        };

        searchInput?.addEventListener('input', applyReportFilters);
        stateFilter?.addEventListener('change', applyReportFilters);
        applyReportFilters();
    };


    const initReportPrinting = () => {
        const buttons = Array.from(document.querySelectorAll('[data-print-report]'));

        if (buttons.length === 0) {
            return;
        }

        let previousTitle = document.title;

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                previousTitle = document.title;

                const printTitle = button.dataset.printTitle?.trim();
                if (printTitle) {
                    document.title = printTitle;
                }

                window.print();
            });
        });

        window.addEventListener('afterprint', () => {
            document.title = previousTitle;
        });
    };

    const init = () => {
        initMonitoringCenter();
        initReportDirectory();
        initReportPrinting();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
