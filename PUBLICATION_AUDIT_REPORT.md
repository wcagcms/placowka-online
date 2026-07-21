# Raport kontroli pakietu publicznego

**Data:** 21 lipca 2026 r.  
**Pakiet:** Placówka Online Public Repository v1.0.0

## Wykonane kontrole

- kontrola składni wszystkich plików PHP: zaliczona;
- kontrola składni skryptów Bash: zaliczona;
- walidacja JSON: zaliczona;
- walidacja YAML GitHub Actions i Issue Forms: zaliczona;
- `gofmt` dla obu programów Go: zaliczony;
- testowa cross-kompilacja głównego agenta Windows z lokalnym Go 1.23.2:
  zaliczona;
- kontrola obecności pełnej licencji AGPL: zaliczona;
- kontrola niedozwolonych plików i wzorców sekretów: zaliczona;
- brak `.env`, baz SQLite, logów, plików EXE, ZIP, PFX/P12, PEM i kluczy:
  potwierdzony.

## Kontrole do wykonania po publikacji

- `composer install` i pełne `php artisan test` w GitHub Actions;
- `npm install` oraz `npm run build` w GitHub Actions;
- pełna budowa agenta Go 1.26.5;
- budowa instalatora Inno Setup na Windows;
- weryfikacja podpisu Authenticode po otrzymaniu certyfikatu.

Pakiet nie zawiera danych produkcyjnych, binariów ani materiału klucza
prywatnego Code Signing.
