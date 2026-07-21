# Weryfikacja repozytorium i wydania

1. Otwórz publiczne repozytorium.
2. Sprawdź plik LICENSE i identyfikator AGPL-3.0-or-later.
3. Sprawdź źródło agenta w `storage/app/agent-template/src/main.go`.
4. Sprawdź źródło enrollment helper w
   `storage/app/agent-installer/src/enroll/main.go`.
5. Sprawdź definicję instalatora w
   `storage/app/agent-installer/inno/PlacowkaOnlineSetup.iss`.
6. Sprawdź skrypty `scripts/build-agent-*.sh`.
7. Porównaj wersję Git tagu z wersją instalatora i sumą SHA-256.
8. Zweryfikuj podpis poleceniem `Get-AuthenticodeSignature`.
