@extends('legal.layout')

@section('title', 'Polityka prywatności')
@section('description', 'Polityka prywatności serwisu Placówka Online i agenta monitorującego komputery.')
@section('heading', 'Polityka prywatności')
@section('lead', 'Wyjaśniamy, jakie dane przetwarza panel i agent, w jakim celu oraz jak chronimy prywatność użytkowników i osób korzystających z monitorowanych komputerów.')

@section('content')
<section>
    <h2>1. Najważniejsze informacje</h2>
    <p><strong>{{ config('legal.service_name') }}</strong> jest projektem open source przeznaczonym do monitorowania dostępności, kondycji technicznej i podstawowego stanu bezpieczeństwa komputerów. Celem systemu jest szybsze wykrywanie awarii, nieaktualnego oprogramowania, problemów z łącznością i wybranych nieprawidłowości ochrony, aby ograniczać ryzyko przestojów i podatności na włamania.</p>
    <p>System nie gwarantuje całkowitego wyeliminowania awarii, włamań, złośliwego oprogramowania ani utraty danych. Nie zastępuje aktualizacji, programu antywirusowego, zapory, kopii zapasowych, audytów bezpieczeństwa ani prawidłowej administracji urządzeniem.</p>
</section>

<section>
    <h2>2. Kto odpowiada za dane</h2>
    <p>Administratorem danych związanych z kontami panelu, logowaniem, bezpieczeństwem serwisu i kontaktem jest <strong>{{ config('legal.controller_name') }}</strong>. Kontakt w sprawach prywatności: <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
    @if(config('legal.controller_address'))
        <p>Adres do korespondencji: {{ config('legal.controller_address') }}.</p>
    @endif
    @if(config('legal.iod_email'))
        <p>Kontakt do inspektora ochrony danych: <a href="mailto:{{ config('legal.iod_email') }}">{{ config('legal.iod_email') }}</a>.</p>
    @endif
    <p>W zakresie telemetrii komputera, która może zostać powiązana z pracownikiem lub inną osobą, administratorem danych jest co do zasady placówka lub podmiot, który zdecydował o instalacji agenta. Operator tej instalacji systemu może działać jako podmiot przetwarzający na podstawie umowy powierzenia danych.</p>
    <p>W przypadku samodzielnej instalacji projektu open source administratorem danych jest podmiot, który uruchomił i konfiguruje daną instalację.</p>
</section>

<section>
    <h2>3. Jakie dane mogą być przetwarzane</h2>
    <h3>Dane użytkowników panelu</h3>
    <ul>
        <li>imię i nazwisko lub nazwa użytkownika;</li>
        <li>adres e-mail;</li>
        <li>rola i przypisane placówki;</li>
        <li>daty logowania, adres IP, wynik logowania i identyfikatory sesji;</li>
        <li>historia istotnych operacji administracyjnych i bezpieczeństwa.</li>
    </ul>

    <h3>Dane techniczne placówki i urządzenia</h3>
    <ul>
        <li>nazwa placówki, kod placówki i dane kontaktowe osoby odpowiedzialnej;</li>
        <li>nazwa komputera, identyfikator urządzenia i wersja agenta;</li>
        <li>wersja systemu Windows, czas działania i podstawowe informacje sprzętowe;</li>
        <li>użycie procesora, pamięci RAM i przestrzeni dyskowej;</li>
        <li>stan dysków, SMART i usług Windows objętych monitoringiem;</li>
        <li>adresy IP, adres MAC, brama, DNS, nazwa karty sieciowej i podstawowe parametry połączenia;</li>
        <li>wyniki testów Internetu, DNS, bramy i połączenia z serwerem monitoringu;</li>
        <li>stan Windows Update, wymagany restart i liczba oczekujących aktualizacji;</li>
        <li>stan ochrony antywirusowej, nazwa aktywnego dostawcy, np. Microsoft Defender lub ESET, stan zapory i aktywne ostrzeżenia;</li>
        <li>czas heartbeatów, incydenty, komentarze operatorów i informacje o rozwiązaniu problemu.</li>
    </ul>

    <h3>Dane, których agent nie powinien zbierać</h3>
    <p>Standardowy agent nie jest narzędziem do nadzoru treści pracy. Nie powinien zbierać treści dokumentów, haseł, wiadomości e-mail, historii przeglądania, naciśnięć klawiszy, obrazu ekranu ani nagrań z mikrofonu lub kamery. Funkcje zdalnego dostępu, np. AnyDesk, są odrębnymi narzędziami i nie są uruchamiane automatycznie przez agenta.</p>
</section>

<section>
    <h2>4. Cele i podstawy przetwarzania</h2>
    <ul>
        <li><strong>realizacja usługi i obsługa konta</strong> — wykonanie umowy lub działania związane z jej realizacją;</li>
        <li><strong>monitorowanie dostępności i kondycji urządzeń</strong> — wykonanie umowy oraz prawnie uzasadniony interes polegający na zapewnieniu ciągłości działania i bezpieczeństwa;</li>
        <li><strong>wykrywanie incydentów, ochrona przed nadużyciami i prowadzenie logów</strong> — prawnie uzasadniony interes administratora i klientów;</li>
        <li><strong>realizacja obowiązków prawnych, księgowych lub związanych z obsługą żądań</strong> — obowiązek prawny;</li>
        <li><strong>dochodzenie lub obrona roszczeń</strong> — prawnie uzasadniony interes.</li>
    </ul>
</section>

<section>
    <h2>5. Odbiorcy danych</h2>
    <p>Dane mogą być udostępniane wyłącznie w zakresie niezbędnym do działania systemu: upoważnionym administratorom i operatorom, podmiotowi utrzymującemu serwer, dostawcy poczty elektronicznej, dostawcom kopii zapasowych, wsparciu technicznemu oraz organom publicznym, gdy wynika to z prawa.</p>
    <p>Open source oznacza dostępność kodu źródłowego na warunkach licencji, a nie publiczny dostęp do danych produkcyjnych, kont użytkowników, tokenów agentów ani infrastruktury klientów.</p>
</section>

<section>
    <h2>6. Przekazywanie danych poza EOG</h2>
    <p>Podstawowa instalacja może działać wyłącznie na serwerach w Europejskim Obszarze Gospodarczym. Jeżeli administrator instalacji korzysta z usług dostawców spoza EOG, powinien zapewnić odpowiednią podstawę prawną i zabezpieczenia wymagane przez RODO oraz poinformować o tym użytkowników.</p>
</section>

<section>
    <h2>7. Okres przechowywania</h2>
    <ul>
        <li>dane konta — przez okres aktywności konta i współpracy, a następnie przez czas niezbędny do rozliczeń, ochrony przed roszczeniami i spełnienia obowiązków prawnych;</li>
        <li>telemetria i heartbeaty — zgodnie z polityką retencji ustawioną przez administratora placówki, nie dłużej niż jest to potrzebne do monitoringu, raportowania i analizy incydentów;</li>
        <li>incydenty i dzienniki bezpieczeństwa — przez okres niezbędny do zapewnienia rozliczalności, analizy bezpieczeństwa i ochrony przed roszczeniami;</li>
        <li>kopie zapasowe — zgodnie z ustawioną retencją backupów, po czym są nadpisywane lub usuwane;</li>
        <li>dane sesji i niezbędne pliki cookie — do wygaśnięcia sesji lub okresu technicznie koniecznego.</li>
    </ul>
</section>

<section>
    <h2>8. Pliki cookie i dane techniczne przeglądarki</h2>
    <p>Serwis wykorzystuje wyłącznie pliki cookie i podobne mechanizmy niezbędne do logowania, utrzymania sesji, ochrony formularzy CSRF i bezpieczeństwa panelu. Bez nich logowanie i bezpieczne korzystanie z panelu nie byłoby możliwe.</p>
    <p>Serwis nie wykorzystuje domyślnie reklamowych ani marketingowych plików cookie. Jeżeli w przyszłości zostaną dodane narzędzia analityczne lub inne opcjonalne technologie, polityka zostanie uaktualniona, a tam, gdzie jest to wymagane, użytkownik otrzyma możliwość wyrażenia zgody.</p>
</section>

<section>
    <h2>9. Bezpieczeństwo i podpis cyfrowy agenta</h2>
    <p>Oficjalny instalator agenta jest podpisywany certyfikatem <strong>{{ config('legal.code_signing_name') }}</strong>. Podpis pozwala sprawdzić pochodzenie i integralność pliku po jego wydaniu. Nie jest jednak gwarancją, że program nie zawiera żadnych błędów lub że urządzenie nie zostanie zaatakowane.</p>
    <p>Agent powinien być pobierany wyłącznie z oficjalnego panelu lub repozytorium projektu. Przed instalacją należy sprawdzić ważność podpisu cyfrowego i — jeśli jest publikowana — sumę SHA-256.</p>
</section>

<section>
    <h2>10. Prawa osób</h2>
    <p>W zależności od podstawy i okoliczności przetwarzania osobie przysługuje prawo dostępu do danych, sprostowania, usunięcia, ograniczenia przetwarzania, przenoszenia danych, wniesienia sprzeciwu oraz wniesienia skargi do Prezesa Urzędu Ochrony Danych Osobowych.</p>
    <p>Żądania dotyczące danych przetwarzanych przez konkretną placówkę należy kierować w pierwszej kolejności do tej placówki. W sprawach dotyczących konta panelu i bezpieczeństwa serwisu można napisać na <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
</section>

<section>
    <h2>11. Zautomatyzowane decyzje</h2>
    <p>System automatycznie klasyfikuje stany techniczne i generuje alerty, ale nie podejmuje decyzji wywołujących skutki prawne wobec osób i nie prowadzi profilowania pracowników. Alert jest wskazówką techniczną i powinien zostać zweryfikowany przez administratora lub operatora.</p>
</section>

<section>
    <h2>12. Zmiany polityki</h2>
    <p>Polityka może zostać zmieniona wraz z rozwojem projektu, zmianą zakresu telemetrii lub przepisów. Aktualna wersja jest zawsze dostępna publicznie bez logowania.</p>
</section>
@endsection
