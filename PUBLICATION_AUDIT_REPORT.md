# Raport kontroli publikacyjnej

**Data:** 22 lipca 2026 r.  
**Repozytorium:** https://github.com/wcagcms/placowka-online  
**Wydanie:** `v0.2.0`  
**Agent:** `exe-1.9.3`  
**Instalator:** `1.0.6`

## Zakres kontroli

- brak `.env`, baz danych, logów i kopii produkcyjnych;
- brak tokenów urządzeń, kluczy prywatnych i materiałów certyfikatu;
- brak gotowych plików EXE, MSI, PFX i P12;
- publiczny kod agenta i definicja instalatora są dostępne;
- wszystkie odwołania wskazują organizację `wcagcms`;
- domyślna sonda Microsoft używa HTTP;
- dokumentacja odpowiada agentowi `exe-1.9.3`;
- licencja projektu: `GNU AGPL-3.0-or-later`.

## Wynik

Pakiet jest przygotowany do publikacji po wykonaniu lokalnych kontroli:

```bash
bash scripts/public-repo-audit.sh
php scripts/php-syntax-check.php
```
