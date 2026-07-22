# Checklista pierwszej publikacji GitHub

## Tożsamość

- [ ] utworzono publiczne repozytorium `wcagcms/placowka-online`;
- [ ] opis wskazuje nieodpłatny projekt dla JST;
- [ ] ustawiono stronę `https://monitoring.wcag-cms.pl`;
- [ ] licencja GitHub jest rozpoznawana jako AGPL-3.0-or-later.

## Bezpieczeństwo

- [ ] brak `.env`, baz, logów, tokenów, haseł i kluczy;
- [ ] brak plików PFX/P12/PEM/KEY/CER/CRT;
- [ ] brak EXE, paczek agentów i kopii produkcyjnych;
- [ ] uruchomiono `scripts/public-repo-audit.sh`;
- [ ] włączono GitHub Secret Scanning i Dependabot;
- [ ] włączono ochronę głównej gałęzi i wymagany PR.

## Kod źródłowy i AGPL

- [ ] panel logowania i stopka zawierają publiczny link „Kod źródłowy”;
- [ ] `PLACOWKA_SOURCE_CODE_URL` wskazuje repozytorium;
- [ ] każde wydanie binarne wskazuje odpowiadający tag;
- [ ] dostępny jest pełny kod potrzebny do zbudowania instalatora.

## Certum

- [ ] publiczny README opisuje projekt i autora;
- [ ] dostępna jest licencja;
- [ ] źródła agenta i instalatora są jawne;
- [ ] dokumenty w `docs/certum` są uzupełnione datą i adresem repozytorium;
- [ ] certyfikat nie jest jeszcze opisywany jako aktywny, dopóki podpis nie ma
  statusu `Valid`.

## Aktualność wydania

- [ ] agent w repozytorium ma wersję `exe-1.9.3`;
- [ ] instalator ma wersję `1.0.6`;
- [ ] domyślna sonda Microsoft używa `http://www.msftconnecttest.com/connecttest.txt`;
- [ ] tag `v0.2.0` wskazuje commit wykorzystany do budowania wydania.
