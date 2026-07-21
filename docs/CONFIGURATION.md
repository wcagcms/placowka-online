# Konfiguracja

Wszystkie sekrety przechowuj wyłącznie w `.env`, nigdy w Git.

Najważniejsze grupy zmiennych:

- `APP_*` — środowisko i adres aplikacji;
- `DB_*` — baza danych;
- `MAIL_*` — wysyłka powiadomień;
- `PLACOWKA_*` — progi monitoringu, agent, retencja i kopie;
- `PLACOWKA_LEGAL_*` — dane publicznych dokumentów;
- `PLACOWKA_SOURCE_CODE_URL` — publiczny adres odpowiadającego kodu źródłowego.

Kompletny, bezpieczny wzór znajduje się w `.env.example`.

Po zmianie konfiguracji produkcyjnej:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
