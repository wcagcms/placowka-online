# Publikacja na GitHub

## Oficjalne repozytorium

```text
https://github.com/wcagcms/placowka-online
```

Adres do operacji Git przez HTTPS:

```text
https://github.com/wcagcms/placowka-online.git
```

Adres SSH:

```text
git@github.com:wcagcms/placowka-online.git
```

## 1. Kontrola przed wysłaniem

```bash
bash scripts/public-repo-audit.sh
php scripts/php-syntax-check.php
git status --short
```

Repozytorium nie może zawierać `.env`, baz, logów, tokenów, kluczy,
certyfikatów, kopii zapasowych ani produkcyjnych plików EXE.

## 2. Pierwsza publikacja pustego repozytorium

```bash
git init -b main
git config user.name "Adam Trojanowski"
git config user.email "it@it-serwis.net"
git add .
git commit -m "chore: initial public open-source release"
git remote add origin https://github.com/wcagcms/placowka-online.git
git push -u origin main
```

Przy logowaniu przez SSH zamień adres `origin` na:

```bash
git remote set-url origin git@github.com:wcagcms/placowka-online.git
```

## 3. Aktualizacja istniejącego lokalnego repozytorium

```bash
git remote set-url origin https://github.com/wcagcms/placowka-online.git
git remote -v
git add .
git commit -m "release: publish 0.2.0 with agent 1.9.3"
git tag -a v0.2.0 -m "Placówka Online 0.2.0"
git push origin main
git push origin v0.2.0
```

## 4. Ustawienia repozytorium w organizacji `wcagcms`

Ustaw opis:

```text
Open-source monitoring komputerów Windows dla JST i placówek publicznych.
```

Ustaw stronę projektu:

```text
https://monitoring.wcag-cms.pl
```

Zalecane tematy:

```text
laravel windows monitoring public-sector cybersecurity incident-management open-source poland
```

Włącz:

- ochronę gałęzi `main`;
- Pull Request przed scaleniem;
- Dependabot alerts i security updates;
- Secret scanning i push protection, gdy plan organizacji je udostępnia;
- prywatne zgłaszanie podatności;
- Issues;
- Discussions, jeśli mają służyć do wsparcia.

## 5. Produkcyjny link do źródeł

W produkcyjnym `.env` ustaw:

```env
PLACOWKA_SOURCE_CODE_URL="https://github.com/wcagcms/placowka-online"
PLACOWKA_OPEN_SOURCE_LICENSE="GNU AGPL-3.0-or-later"
```

Następnie:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 6. GitHub Release

Dla wydania `v0.2.0` opublikuj:

- kod źródłowy generowany przez GitHub;
- `SHA256SUMS.txt`;
- opis zmian;
- informacje o wersji agenta `exe-1.9.3`;
- informacje o instalatorze `1.0.6`;
- podpisane pliki wykonywalne dopiero po uzyskaniu i zweryfikowaniu certyfikatu.
