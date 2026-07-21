# Publikacja na GitHub

## 1. Utwórz repozytorium

Utwórz publiczne, puste repozytorium:

```text
atrojanowski44/placowka-online
```

Nie dodawaj z interfejsu GitHuba dodatkowego README ani licencji, ponieważ są już w pakiecie.

## 2. Kontrola lokalna

```bash
bash scripts/public-repo-audit.sh
php scripts/php-syntax-check.php
```

## 3. Pierwszy commit

```bash
git init -b main
git config user.name "Adam Trojanowski"
git config user.email "it@it-serwis.net"
git add .
git commit -m "chore: initial public open-source release"
git remote add origin git@github.com:atrojanowski44/placowka-online.git
git push -u origin main
```

## 4. Ustawienia GitHuba

Włącz:

- ochronę gałęzi `main`;
- wymagany Pull Request;
- Dependabot alerts i security updates;
- Secret scanning i push protection, gdy są dostępne;
- prywatne zgłaszanie podatności;
- Discussions, jeśli ma służyć do wsparcia.

## 5. Po publikacji

W produkcyjnym `.env` ustaw:

```env
PLACOWKA_SOURCE_CODE_URL="https://github.com/atrojanowski44/placowka-online"
PLACOWKA_OPEN_SOURCE_LICENSE="GNU AGPL-3.0-or-later"
```

Następnie wykonaj:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
