# Proces wydania

1. Zaktualizuj CHANGELOG i numery wersji.
2. Uruchom testy PHP, JS i Go.
3. Uruchom `bash scripts/public-repo-audit.sh`.
4. Zbuduj agenta i instalator z czystego tagu.
5. Sprawdź pliki programem antywirusowym.
6. Podpisz pliki certyfikatem Open Source Code Signing ze znacznikiem czasu.
7. Zweryfikuj Authenticode.
8. Wygeneruj `SHA256SUMS.txt`.
9. Utwórz podpisany tag Git.
10. Opublikuj GitHub Release z kodem źródłowym i sumami.
11. Dopiero potem opublikuj instalator w panelu.

Nigdy nie publikuj PFX/P12, hasła do certyfikatu ani danych urządzeń.
