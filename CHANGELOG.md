# Historia zmian

Format opiera się na Keep a Changelog, a wersje projektu stosują Semantic Versioning.

## [Unreleased]

## [0.2.0] — 2026-07-22

- przeniesiono oficjalne repozytorium do organizacji `wcagcms`;
- ustawiono adres `https://github.com/wcagcms/placowka-online`;
- zaktualizowano agenta do `exe-1.9.3`;
- zaktualizowano instalator do `1.0.6`;
- poprawiono sondę Microsoft NCSI z HTTPS na HTTP;
- dodano serwerową normalizację wyniku sond Internetu;
- dodano stabilizację incydentów: otwarcie po 3 kolejnych błędach i
  zamknięcie po 5 kolejnych poprawnych pomiarach;
- rozszerzono historię heartbeatów o informacje o liczbie i błędach sond.

## [0.1.0] — 2026-07-21

Pierwszy publiczny pakiet repozytorium. Obejmuje panel Laravel, źródła agenta
`exe-1.9.2`, definicję instalatora `1.0.5`, Secure Enrollment, telemetrię,
incydenty, kopie zapasowe, Windows Update, ochronę antywirusową i dokumenty
publiczne.
