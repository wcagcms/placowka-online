# Kontrola ustawień bezpieczeństwa GitHub

Po opublikowaniu wydania sprawdź w organizacji `wcagcms` i repozytorium `placowka-online`:

- [ ] organizacja wymaga 2FA od członków i współpracowników;
- [ ] `main` jest chronione rulesetem;
- [ ] force push i usuwanie `main` są zabronione;
- [ ] scalenie wymaga Pull Request i zaliczonych testów CI;
- [ ] Dependabot alerts są włączone;
- [ ] Dependabot security updates są włączone;
- [ ] Secret scanning jest włączone;
- [ ] Push protection jest włączone;
- [ ] Private vulnerability reporting jest włączone;
- [ ] workflow ma tylko minimalne `permissions`;
- [ ] pliki wykonywalne wydania mają ważny podpis Authenticode;
- [ ] sumy SHA-256 wydania są opublikowane.
