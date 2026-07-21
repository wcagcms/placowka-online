# Kopie zapasowe i odtwarzanie

Kopie aplikacji są przechowywane poza katalogiem publicznym i nie mogą trafiać
do repozytorium.

```bash
php artisan placowka:backup --retention=14
php artisan placowka:backup-verify --limit=7
```

Przed odtworzeniem:

1. zatrzymaj zapisy lub włącz tryb konserwacji;
2. wykonaj dodatkową kopię bieżącego stanu;
3. sprawdź SHA-256 archiwum;
4. odtwórz bazę w środowisku testowym;
5. dopiero po weryfikacji wykonaj odtworzenie produkcyjne.

Po odtworzeniu zmień tokeny, gdy istnieje podejrzenie ich ujawnienia.
