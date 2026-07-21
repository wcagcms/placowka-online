# Polityka prywatności

**Wersja:** 1.0  
**Obowiązuje od:** 19 lipca 2026 r.

## 1. Najważniejsze informacje

**Placówka Online** jest projektem open source przeznaczonym do monitorowania dostępności, kondycji technicznej i podstawowego stanu bezpieczeństwa komputerów. Celem systemu jest szybsze wykrywanie awarii, nieaktualnego oprogramowania, problemów z łącznością i wybranych nieprawidłowości ochrony, aby ograniczać ryzyko przestojów i podatności na włamania.

System nie gwarantuje całkowitego wyeliminowania awarii, włamań, złośliwego oprogramowania ani utraty danych. Nie zastępuje aktualizacji, programu antywirusowego, zapory, kopii zapasowych, audytów bezpieczeństwa ani prawidłowej administracji urządzeniem.

## 2. Kto odpowiada za dane

Administratorem danych związanych z kontami panelu, logowaniem, bezpieczeństwem serwisu i kontaktem jest **Adam Trojanowski**. Kontakt w sprawach prywatności: [it@it-serwis.net](mailto:it@it-serwis.net).

W zakresie telemetrii komputera, która może zostać powiązana z pracownikiem lub inną osobą, administratorem danych jest co do zasady placówka lub podmiot, który zdecydował o instalacji agenta. Operator tej instalacji systemu może działać jako podmiot przetwarzający na podstawie umowy powierzenia danych.

W przypadku samodzielnej instalacji projektu open source administratorem danych jest podmiot, który uruchomił i konfiguruje daną instalację.

## 3. Jakie dane mogą być przetwarzane

### Dane użytkowników panelu

- imię i nazwisko lub nazwa użytkownika;
- adres e-mail;
- rola i przypisane placówki;
- daty logowania, adres IP, wynik logowania i identyfikatory sesji;
- historia istotnych operacji administracyjnych i bezpieczeństwa.

### Dane techniczne placówki i urządzenia

- nazwa placówki, kod placówki i dane kontaktowe osoby odpowiedzialnej;
- nazwa komputera, identyfikator urządzenia i wersja agenta;
- wersja systemu Windows, czas działania i podstawowe informacje sprzętowe;
- użycie procesora, pamięci RAM i przestrzeni dyskowej;
- stan dysków, SMART i usług Windows objętych monitoringiem;
- adresy IP, adres MAC, brama, DNS, nazwa karty sieciowej i podstawowe parametry połączenia;
- wyniki testów Internetu, DNS, bramy i połączenia z serwerem monitoringu;
- stan Windows Update, wymagany restart i liczba oczekujących aktualizacji;
- stan ochrony antywirusowej, nazwa aktywnego dostawcy, np. Microsoft Defender lub ESET, stan zapory i aktywne ostrzeżenia;
- czas heartbeatów, incydenty, komentarze operatorów i informacje o rozwiązaniu problemu.

### Dane, których agent nie powinien zbierać

Standardowy agent nie jest narzędziem do nadzoru treści pracy. Nie powinien zbierać treści dokumentów, haseł, wiadomości e-mail, historii przeglądania, naciśnięć klawiszy, obrazu ekranu ani nagrań z mikrofonu lub kamery. Funkcje zdalnego dostępu, np. AnyDesk, są odrębnymi narzędziami i nie są uruchamiane automatycznie przez agenta.

## 4. Cele i podstawy przetwarzania

- **realizacja usługi i obsługa konta** — wykonanie umowy lub działania związane z jej realizacją;
- **monitorowanie dostępności i kondycji urządzeń** — wykonanie umowy oraz prawnie uzasadniony interes polegający na zapewnieniu ciągłości działania i bezpieczeństwa;
- **wykrywanie incydentów, ochrona przed nadużyciami i prowadzenie logów** — prawnie uzasadniony interes administratora i klientów;
- **realizacja obowiązków prawnych, księgowych lub związanych z obsługą żądań** — obowiązek prawny;
- **dochodzenie lub obrona roszczeń** — prawnie uzasadniony interes.

## 5. Odbiorcy danych

Dane mogą być udostępniane wyłącznie w zakresie niezbędnym do działania systemu: upoważnionym administratorom i operatorom, podmiotowi utrzymującemu serwer, dostawcy poczty elektronicznej, dostawcom kopii zapasowych, wsparciu technicznemu oraz organom publicznym, gdy wynika to z prawa.

Open source oznacza dostępność kodu źródłowego na warunkach licencji, a nie publiczny dostęp do danych produkcyjnych, kont użytkowników, tokenów agentów ani infrastruktury klientów.

## 6. Przekazywanie danych poza EOG

Podstawowa instalacja może działać wyłącznie na serwerach w Europejskim Obszarze Gospodarczym. Jeżeli administrator instalacji korzysta z usług dostawców spoza EOG, powinien zapewnić odpowiednią podstawę prawną i zabezpieczenia wymagane przez RODO oraz poinformować o tym użytkowników.

## 7. Okres przechowywania

- dane konta — przez okres aktywności konta i współpracy, a następnie przez czas niezbędny do rozliczeń, ochrony przed roszczeniami i spełnienia obowiązków prawnych;
- telemetria i heartbeaty — zgodnie z polityką retencji ustawioną przez administratora placówki, nie dłużej niż jest to potrzebne do monitoringu, raportowania i analizy incydentów;
- incydenty i dzienniki bezpieczeństwa — przez okres niezbędny do zapewnienia rozliczalności, analizy bezpieczeństwa i ochrony przed roszczeniami;
- kopie zapasowe — zgodnie z ustawioną retencją backupów, po czym są nadpisywane lub usuwane;
- dane sesji i niezbędne pliki cookie — do wygaśnięcia sesji lub okresu technicznie koniecznego.

## 8. Pliki cookie i dane techniczne przeglądarki

Serwis wykorzystuje wyłącznie pliki cookie i podobne mechanizmy niezbędne do logowania, utrzymania sesji, ochrony formularzy CSRF i bezpieczeństwa panelu. Bez nich logowanie i bezpieczne korzystanie z panelu nie byłoby możliwe.

Serwis nie wykorzystuje domyślnie reklamowych ani marketingowych plików cookie. Jeżeli w przyszłości zostaną dodane narzędzia analityczne lub inne opcjonalne technologie, polityka zostanie uaktualniona, a tam, gdzie jest to wymagane, użytkownik otrzyma możliwość wyrażenia zgody.

## 9. Bezpieczeństwo i podpis cyfrowy agenta

Oficjalny instalator agenta jest podpisywany certyfikatem **Open Source Code Signing**. Podpis pozwala sprawdzić pochodzenie i integralność pliku po jego wydaniu. Nie jest jednak gwarancją, że program nie zawiera żadnych błędów lub że urządzenie nie zostanie zaatakowane.

Agent powinien być pobierany wyłącznie z oficjalnego panelu lub repozytorium projektu. Przed instalacją należy sprawdzić ważność podpisu cyfrowego i — jeśli jest publikowana — sumę SHA-256.

## 10. Prawa osób

W zależności od podstawy i okoliczności przetwarzania osobie przysługuje prawo dostępu do danych, sprostowania, usunięcia, ograniczenia przetwarzania, przenoszenia danych, wniesienia sprzeciwu oraz wniesienia skargi do Prezesa Urzędu Ochrony Danych Osobowych.

Żądania dotyczące danych przetwarzanych przez konkretną placówkę należy kierować w pierwszej kolejności do tej placówki. W sprawach dotyczących konta panelu i bezpieczeństwa serwisu można napisać na [it@it-serwis.net](mailto:it@it-serwis.net).

## 11. Zautomatyzowane decyzje

System automatycznie klasyfikuje stany techniczne i generuje alerty, ale nie podejmuje decyzji wywołujących skutki prawne wobec osób i nie prowadzi profilowania pracowników. Alert jest wskazówką techniczną i powinien zostać zweryfikowany przez administratora lub operatora.

## 12. Zmiany polityki

Polityka może zostać zmieniona wraz z rozwojem projektu, zmianą zakresu telemetrii lub przepisów. Aktualna wersja jest zawsze dostępna publicznie bez logowania.
