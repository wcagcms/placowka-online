@extends('legal.layout')

@section('title', 'Regulamin')
@section('description', 'Regulamin korzystania z serwisu Placówka Online i instalacji agenta monitorującego.')
@section('heading', 'Regulamin korzystania z serwisu')
@section('lead', 'Zasady korzystania z panelu Placówka Online, instalowania agenta oraz odpowiedzialności administratorów i operatorów.')

@section('content')
<section>
    <h2>§ 1. Postanowienia ogólne</h2>
    <ol>
        <li>Regulamin określa zasady korzystania z serwisu <strong>{{ config('legal.service_name') }}</strong>, panelu administracyjnego, API i agenta instalowanego na komputerach.</li>
        <li>Usługodawcą i operatorem tej instalacji jest <strong>{{ config('legal.controller_name') }}</strong>, kontakt: <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</li>
        @if(config('legal.controller_address'))
            <li>Adres do korespondencji usługodawcy: {{ config('legal.controller_address') }}.</li>
        @endif
        <li>Regulamin jest dostępny publicznie bez konieczności logowania oraz może zostać zapisany lub wydrukowany.</li>
    </ol>
</section>

<section>
    <h2>§ 2. Charakter projektu open source</h2>
    <ol>
        <li>Placówka Online jest projektem open source. Kod może być analizowany, używany i modyfikowany na zasadach {{ config('legal.open_source_license') }}.</li>
        <li>Jeżeli skonfigurowano publiczny adres repozytorium, jest on dostępny w stopce dokumentów prawnych.</li>
        <li>Udostępnienie kodu źródłowego nie oznacza publicznego dostępu do serwera produkcyjnego, danych klientów, tokenów agentów, haseł, kopii zapasowych ani logów.</li>
        <li>Licencja open source na kod oraz zasady korzystania z utrzymywanej instancji serwisu są odrębnymi zagadnieniami. Bezpłatny kod nie oznacza obowiązku bezpłatnego hostingu, wsparcia lub administracji.</li>
    </ol>
</section>

<section>
    <h2>§ 3. Cel i zakres usługi</h2>
    <ol>
        <li>System służy do monitorowania dostępności i kondycji technicznej komputerów oraz wybranych elementów bezpieczeństwa.</li>
        <li>Monitoring może obejmować m.in. heartbeaty, łączność z Internetem, DNS i bramę, CPU, RAM, dyski, SMART, system Windows, wybrane usługi, Windows Update, program antywirusowy, zaporę, incydenty i raporty.</li>
        <li>Celem jest szybsze wykrywanie awarii i ograniczanie podatności na włamania przez wskazywanie problemów wymagających reakcji.</li>
        <li>System nie jest programem antywirusowym, systemem EDR, zaporą, kopią zapasową ani gwarancją bezpieczeństwa lub ciągłości działania.</li>
        <li>Alerty i Health Score są ocenami technicznymi i wymagają interpretacji przez administratora.</li>
    </ol>
</section>

<section>
    <h2>§ 4. Wymagania techniczne</h2>
    <ol>
        <li>Do korzystania z panelu potrzebne są aktualna przeglądarka internetowa, połączenie HTTPS i obsługa niezbędnych plików cookie.</li>
        <li>Instalacja agenta wymaga obsługiwanego systemu Windows, uprawnień administratora i dostępu do serwera monitoringu.</li>
        <li>Niektóre pomiary mogą zależeć od wersji Windows, dostępności usług systemowych, uprawnień oraz konfiguracji programu antywirusowego i zapory.</li>
    </ol>
</section>

<section>
    <h2>§ 5. Konta i uprawnienia</h2>
    <ol>
        <li>Dostęp do panelu jest przeznaczony wyłącznie dla upoważnionych administratorów i operatorów.</li>
        <li>Użytkownik ma obowiązek chronić hasło, nie udostępniać konta innym osobom i niezwłocznie zgłaszać podejrzenie przejęcia dostępu.</li>
        <li>Operator może widzieć tylko placówki i funkcje przypisane przez administratora.</li>
        <li>Istotne operacje mogą być rejestrowane w dzienniku bezpieczeństwa.</li>
        <li>Operator serwisu może czasowo zablokować konto w przypadku nadużycia, naruszenia bezpieczeństwa albo zakończenia współpracy.</li>
    </ol>
</section>

<section>
    <h2>§ 6. Instalacja i działanie agenta</h2>
    <ol>
        <li>Agent może być instalowany wyłącznie za zgodą właściciela urządzenia lub osoby uprawnionej do zarządzania nim.</li>
        <li>Zabronione jest instalowanie agenta na cudzym komputerze bez wiedzy i upoważnienia administratora tego urządzenia.</li>
        <li>Instalacja odbywa się przy użyciu jednorazowego kodu lub innego mechanizmu autoryzacji przewidzianego w panelu.</li>
        <li>Agent powinien działać w profilu o ograniczonym wpływie na wydajność. Niektóre pełne pomiary mogą chwilowo zwiększyć użycie zasobów.</li>
        <li>Agent nie zapewnia zdalnego sterowania komputerem. Ewentualne połączenie przez AnyDesk lub inne narzędzie odbywa się odrębnie i wymaga właściwego upoważnienia.</li>
    </ol>
</section>

<section>
    <h2>§ 7. Podpis cyfrowy i integralność</h2>
    <ol>
        <li>Oficjalny instalator agenta jest podpisywany certyfikatem <strong>{{ config('legal.code_signing_name') }}</strong>.</li>
        <li>Podpis cyfrowy umożliwia weryfikację wydawcy i wykrycie modyfikacji pliku po jego podpisaniu.</li>
        <li>Użytkownik powinien pobierać instalator wyłącznie z oficjalnego panelu lub repozytorium projektu i sprawdzić, czy podpis jest ważny.</li>
        <li>Sam podpis nie gwarantuje braku błędów, podatności ani szkodliwych zmian w środowisku urządzenia.</li>
    </ol>
</section>

<section>
    <h2>§ 8. Obowiązki placówki i administratora urządzenia</h2>
    <ol>
        <li>Placówka odpowiada za legalność instalacji agenta, właściwe poinformowanie pracowników i ustalenie podstawy prawnej przetwarzania danych.</li>
        <li>Placówka określa, które komputery i usługi mogą być monitorowane, oraz dba o aktualność danych kontaktowych.</li>
        <li>Administrator powinien reagować na incydenty, aktualizować system i agentów, wykonywać kopie zapasowe oraz stosować niezależne zabezpieczenia.</li>
        <li>Jeżeli placówka korzysta z zewnętrznego antywirusa, np. ESET, powinna ustawić właściwą politykę ochrony urządzenia w panelu.</li>
        <li>Nie wolno używać systemu do niezgodnego z prawem nadzoru pracowników, pozyskiwania treści ich komunikacji ani obchodzenia zabezpieczeń.</li>
    </ol>
</section>

<section>
    <h2>§ 9. Dane i prywatność</h2>
    <ol>
        <li>Zasady przetwarzania danych opisują Polityka prywatności i Informacja RODO.</li>
        <li>Jeżeli operator instalacji przetwarza dane w imieniu placówki, strony powinny zawrzeć umowę powierzenia przetwarzania zgodną z art. 28 RODO.</li>
        <li>Użytkownik nie może wprowadzać do systemu danych zbędnych, bezprawnych ani naruszających prawa innych osób.</li>
    </ol>
</section>

<section>
    <h2>§ 10. Niedozwolone działania</h2>
    <p>Zabronione są w szczególności:</p>
    <ul>
        <li>próby uzyskania nieuprawnionego dostępu do kont, API, tokenów lub urządzeń;</li>
        <li>instalacja agenta bez upoważnienia;</li>
        <li>modyfikowanie instalatora i przedstawianie zmodyfikowanej wersji jako oficjalnej;</li>
        <li>dostarczanie treści bezprawnych, złośliwego kodu lub danych naruszających prawa osób trzecich;</li>
        <li>celowe przeciążanie serwisu, omijanie limitów i zakłócanie monitoringu;</li>
        <li>wykorzystywanie systemu do kontroli treści pracy lub inwigilacji.</li>
    </ul>
</section>

<section>
    <h2>§ 11. Dostępność i odpowiedzialność</h2>
    <ol>
        <li>Projekt jest rozwijany w miarę dostępnych zasobów. Możliwe są przerwy techniczne, błędy, opóźnienia heartbeatów lub niekompletne dane.</li>
        <li>Operator dokłada należytej staranności, ale nie gwarantuje nieprzerwanej dostępności ani wykrycia każdego zagrożenia lub awarii.</li>
        <li>Odpowiedzialność za decyzje techniczne podjęte na podstawie alertów spoczywa na osobie uprawnionej, która powinna zweryfikować stan urządzenia.</li>
        <li>Ograniczenia odpowiedzialności nie dotyczą sytuacji, w których odpowiedzialności nie można wyłączyć na mocy bezwzględnie obowiązującego prawa.</li>
    </ol>
</section>

<section>
    <h2>§ 12. Zgłoszenia i reklamacje</h2>
    <ol>
        <li>Problemy techniczne, bezpieczeństwa i reklamacje można zgłaszać na adres <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</li>
        <li>Zgłoszenie powinno zawierać opis problemu, nazwę placówki lub urządzenia, przybliżony czas zdarzenia i dane umożliwiające kontakt. Nie należy przesyłać hasła ani tokenów.</li>
        <li>Odpowiedź jest udzielana w rozsądnym terminie, zależnym od rodzaju i pilności problemu.</li>
    </ol>
</section>

<section>
    <h2>§ 13. Zakończenie korzystania</h2>
    <ol>
        <li>Użytkownik może zakończyć korzystanie z panelu przez kontakt z administratorem i wniosek o dezaktywację konta.</li>
        <li>Agent może zostać odinstalowany przez uprawnionego administratora urządzenia.</li>
        <li>Po zakończeniu współpracy dane są usuwane lub archiwizowane zgodnie z obowiązkami prawnymi, retencją i zasadami ochrony roszczeń.</li>
    </ol>
</section>

<section>
    <h2>§ 14. Zmiany regulaminu</h2>
    <ol>
        <li>Regulamin może zostać zmieniony z powodu rozwoju funkcji, zmian bezpieczeństwa, modelu świadczenia usługi lub przepisów.</li>
        <li>Aktualna wersja jest publikowana pod stałym publicznym adresem i dostępna bez logowania.</li>
        <li>W sprawach nieuregulowanych zastosowanie mają przepisy prawa polskiego i prawa Unii Europejskiej.</li>
    </ol>
</section>
@endsection
