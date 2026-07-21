# Architektura

## Panel

Laravel 12 obsługuje placówki, urządzenia, operatorów, incydenty, raporty,
kopie zapasowe i bezpieczną rejestrację agentów.

## Agent

Agent Go działa jako zadanie Harmonogramu zadań Windows. Pomiary szybkie są
wykonywane co minutę, a cięższe moduły rzadziej. W przypadku braku sieci
heartbeat trafia do ograniczonej kolejki lokalnej.

## Bezpieczeństwo komunikacji

- HTTPS;
- indywidualny token urządzenia;
- jednorazowy kod rejestracyjny o krótkiej ważności;
- limity żądań i rozmiaru payloadu;
- brak tokenu w publicznym instalatorze;
- brak mechanizmu zdalnego wykonywania dowolnych poleceń.

## Dane historyczne

Opóźnione heartbeat’y są oznaczane jako dostarczone z kolejki. Nie mogą
nadpisywać bieżącego stanu urządzenia ani błędnie otwierać lub zamykać incydentów.
