# Współtworzenie projektu

Dziękujemy za zainteresowanie Placówka Online.

## Zanim zaczniesz

1. Nie publikuj danych placówek, tokenów agentów, logów, baz danych ani
   materiałów certyfikatu.
2. Luka bezpieczeństwa powinna zostać zgłoszona zgodnie z SECURITY.md.
3. Większą zmianę funkcjonalną najpierw opisz w Issue typu „Propozycja”.
4. Zachowuj zgodność z WCAG 2.2 AA i istniejącym wyglądem SaaS Platinum.

## Środowisko

- PHP 8.4;
- Laravel 12;
- Node.js 22;
- Go 1.26.5 lub nowszy dla agenta;
- MySQL/MariaDB lub SQLite do testów.

## Kontrole przed Pull Request

```bash
php scripts/php-syntax-check.php
php artisan test
npm run build
bash scripts/public-repo-audit.sh
```

Dla zmian agenta:

```bash
export PATH="$HOME/.local/go1.26.5/bin:$PATH"
gofmt -w storage/app/agent-template/src/main.go
bash scripts/build-agent-secure.sh "$PWD"
```

## Pull Request

PR powinien zawierać opis problemu, zakres zmiany, sposób testu, wpływ na bazę,
agent, instalator, `.env`, Composer i npm. Nie łącz zmian niezwiązanych ze sobą.

## Licencja wkładu

Przekazując wkład, zgadzasz się na jego udostępnienie na licencji
AGPL-3.0-or-later, takiej jak reszta projektu.
