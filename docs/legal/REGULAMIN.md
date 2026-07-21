# Regulamin korzystania z serwisu

**Wersja:** 1.0  
**Obowiązuje od:** 19 lipca 2026 r.

## § 1. Postanowienia ogólne

1. Regulamin określa zasady korzystania z serwisu **Placówka Online**, panelu administracyjnego, API i agenta instalowanego na komputerach.
2. Usługodawcą i operatorem tej instalacji jest **Adam Trojanowski**, kontakt: [it@it-serwis.net](mailto:it@it-serwis.net).
3. Regulamin jest dostępny publicznie bez konieczności logowania oraz może zostać zapisany lub wydrukowany.

## § 2. Charakter projektu open source

1. Placówka Online jest projektem open source. Kod może być analizowany, używany i modyfikowany na zasadach licencji open source wskazanej w repozytorium projektu.
2. Jeżeli skonfigurowano publiczny adres repozytorium, jest on dostępny w stopce dokumentów prawnych.
3. Udostępnienie kodu źródłowego nie oznacza publicznego dostępu do serwera produkcyjnego, danych klientów, tokenów agentów, haseł, kopii zapasowych ani logów.
4. Licencja open source na kod oraz zasady korzystania z utrzymywanej instancji serwisu są odrębnymi zagadnieniami. Bezpłatny kod nie oznacza obowiązku bezpłatnego hostingu, wsparcia lub administracji.

## § 3. Cel i zakres usługi

1. System służy do monitorowania dostępności i kondycji technicznej komputerów oraz wybranych elementów bezpieczeństwa.
2. Monitoring może obejmować m.in. heartbeaty, łączność z Internetem, DNS i bramę, CPU, RAM, dyski, SMART, system Windows, wybrane usługi, Windows Update, program antywirusowy, zaporę, incydenty i raporty.
3. Celem jest szybsze wykrywanie awarii i ograniczanie podatności na włamania przez wskazywanie problemów wymagających reakcji.
4. System nie jest programem antywirusowym, systemem EDR, zaporą, kopią zapasową ani gwarancją bezpieczeństwa lub ciągłości działania.
5. Alerty i Health Score są ocenami technicznymi i wymagają interpretacji przez administratora.

## § 4. Wymagania techniczne

1. Do korzystania z panelu potrzebne są aktualna przeglądarka internetowa, połączenie HTTPS i obsługa niezbędnych plików cookie.
2. Instalacja agenta wymaga obsługiwanego systemu Windows, uprawnień administratora i dostępu do serwera monitoringu.
3. Niektóre pomiary mogą zależeć od wersji Windows, dostępności usług systemowych, uprawnień oraz konfiguracji programu antywirusowego i zapory.

## § 5. Konta i uprawnienia

1. Dostęp do panelu jest przeznaczony wyłącznie dla upoważnionych administratorów i operatorów.
2. Użytkownik ma obowiązek chronić hasło, nie udostępniać konta innym osobom i niezwłocznie zgłaszać podejrzenie przejęcia dostępu.
3. Operator może widzieć tylko placówki i funkcje przypisane przez administratora.
4. Istotne operacje mogą być rejestrowane w dzienniku bezpieczeństwa.
5. Operator serwisu może czasowo zablokować konto w przypadku nadużycia, naruszenia bezpieczeństwa albo zakończenia współpracy.

## § 6. Instalacja i działanie agenta

1. Agent może być instalowany wyłącznie za zgodą właściciela urządzenia lub osoby uprawnionej do zarządzania nim.
2. Zabronione jest instalowanie agenta na cudzym komputerze bez wiedzy i upoważnienia administratora tego urządzenia.
3. Instalacja odbywa się przy użyciu jednorazowego kodu lub innego mechanizmu autoryzacji przewidzianego w panelu.
4. Agent powinien działać w profilu o ograniczonym wpływie na wydajność. Niektóre pełne pomiary mogą chwilowo zwiększyć użycie zasobów.
5. Agent nie zapewnia zdalnego sterowania komputerem. Ewentualne połączenie przez AnyDesk lub inne narzędzie odbywa się odrębnie i wymaga właściwego upoważnienia.

## § 7. Podpis cyfrowy i integralność

1. Oficjalny instalator agenta jest podpisywany certyfikatem **Open Source Code Signing**.
2. Podpis cyfrowy umożliwia weryfikację wydawcy i wykrycie modyfikacji pliku po jego podpisaniu.
3. Użytkownik powinien pobierać instalator wyłącznie z oficjalnego panelu lub repozytorium projektu i sprawdzić, czy podpis jest ważny.
4. Sam podpis nie gwarantuje braku błędów, podatności ani szkodliwych zmian w środowisku urządzenia.

## § 8. Obowiązki placówki i administratora urządzenia

1. Placówka odpowiada za legalność instalacji agenta, właściwe poinformowanie pracowników i ustalenie podstawy prawnej przetwarzania danych.
2. Placówka określa, które komputery i usługi mogą być monitorowane, oraz dba o aktualność danych kontaktowych.
3. Administrator powinien reagować na incydenty, aktualizować system i agentów, wykonywać kopie zapasowe oraz stosować niezależne zabezpieczenia.
4. Jeżeli placówka korzysta z zewnętrznego antywirusa, np. ESET, powinna ustawić właściwą politykę ochrony urządzenia w panelu.
5. Nie wolno używać systemu do niezgodnego z prawem nadzoru pracowników, pozyskiwania treści ich komunikacji ani obchodzenia zabezpieczeń.

## § 9. Dane i prywatność

1. Zasady przetwarzania danych opisują Polityka prywatności i Informacja RODO.
2. Jeżeli operator instalacji przetwarza dane w imieniu placówki, strony powinny zawrzeć umowę powierzenia przetwarzania zgodną z art. 28 RODO.
3. Użytkownik nie może wprowadzać do systemu danych zbędnych, bezprawnych ani naruszających prawa innych osób.

## § 10. Niedozwolone działania

Zabronione są w szczególności:

- próby uzyskania nieuprawnionego dostępu do kont, API, tokenów lub urządzeń;
- instalacja agenta bez upoważnienia;
- modyfikowanie instalatora i przedstawianie zmodyfikowanej wersji jako oficjalnej;
- dostarczanie treści bezprawnych, złośliwego kodu lub danych naruszających prawa osób trzecich;
- celowe przeciążanie serwisu, omijanie limitów i zakłócanie monitoringu;
- wykorzystywanie systemu do kontroli treści pracy lub inwigilacji.

## § 11. Dostępność i odpowiedzialność

1. Projekt jest rozwijany w miarę dostępnych zasobów. Możliwe są przerwy techniczne, błędy, opóźnienia heartbeatów lub niekompletne dane.
2. Operator dokłada należytej staranności, ale nie gwarantuje nieprzerwanej dostępności ani wykrycia każdego zagrożenia lub awarii.
3. Odpowiedzialność za decyzje techniczne podjęte na podstawie alertów spoczywa na osobie uprawnionej, która powinna zweryfikować stan urządzenia.
4. Ograniczenia odpowiedzialności nie dotyczą sytuacji, w których odpowiedzialności nie można wyłączyć na mocy bezwzględnie obowiązującego prawa.

## § 12. Zgłoszenia i reklamacje

1. Problemy techniczne, bezpieczeństwa i reklamacje można zgłaszać na adres [it@it-serwis.net](mailto:it@it-serwis.net).
2. Zgłoszenie powinno zawierać opis problemu, nazwę placówki lub urządzenia, przybliżony czas zdarzenia i dane umożliwiające kontakt. Nie należy przesyłać hasła ani tokenów.
3. Odpowiedź jest udzielana w rozsądnym terminie, zależnym od rodzaju i pilności problemu.

## § 13. Zakończenie korzystania

1. Użytkownik może zakończyć korzystanie z panelu przez kontakt z administratorem i wniosek o dezaktywację konta.
2. Agent może zostać odinstalowany przez uprawnionego administratora urządzenia.
3. Po zakończeniu współpracy dane są usuwane lub archiwizowane zgodnie z obowiązkami prawnymi, retencją i zasadami ochrony roszczeń.

## § 14. Zmiany regulaminu

1. Regulamin może zostać zmieniony z powodu rozwoju funkcji, zmian bezpieczeństwa, modelu świadczenia usługi lub przepisów.
2. Aktualna wersja jest publikowana pod stałym publicznym adresem i dostępna bez logowania.
3. W sprawach nieuregulowanych zastosowanie mają przepisy prawa polskiego i prawa Unii Europejskiej.
