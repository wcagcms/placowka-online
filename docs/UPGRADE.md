# Aktualizacja

```bash
git fetch --tags
git checkout <tag>
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize
```

Przed aktualizacją wykonaj kopię bazy i plików. Nowe wersje agenta wdrażaj
najpierw na jednym urządzeniu testowym. Samo opublikowanie nowego instalatora nie
aktualizuje automatycznie istniejących agentów.
