# Instalacja serwera

## Wymagania

- Linux z serwerem WWW;
- PHP 8.4 z rozszerzeniami wymaganymi przez Laravel;
- Composer 2;
- MySQL/MariaDB albo SQLite do testów;
- Node.js 22 i npm;
- cron uruchamiający scheduler co minutę;
- HTTPS w środowisku produkcyjnym.

## Instalacja

```bash
git clone https://github.com/wcagcms/placowka-online.git
cd placowka-online
cp .env.example .env
composer install --no-interaction
php artisan key:generate
```

Skonfiguruj bazę i pocztę w `.env`, następnie:

```bash
php artisan migrate --force
php artisan placowka:create-admin admin@example.org --name="Administrator"
npm install
npm run build
php artisan optimize
```

## Scheduler

```cron
* * * * * cd /sciezka/placowka-online && php artisan schedule:run >> /dev/null 2>&1
```

Kontrola:

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan route:list
```

DocumentRoot serwera WWW musi wskazywać katalog `public/`.
