@extends('legal.layout')

@section('title', 'Informacja RODO')
@section('description', 'Klauzula informacyjna RODO dla użytkowników panelu i osób korzystających z monitorowanych komputerów.')
@section('heading', 'Informacja RODO')
@section('lead', 'Klauzula informacyjna dla użytkowników panelu oraz osób, których mogą dotyczyć dane techniczne pochodzące z monitorowanych urządzeń.')

@section('content')
<section>
    <h2>A. Użytkownicy panelu administracyjnego</h2>

    <h3>1. Administrator danych</h3>
    <p>Administratorem danych związanych z utworzeniem konta, logowaniem i korzystaniem z panelu jest <strong>{{ config('legal.controller_name') }}</strong>. Kontakt: <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
    @if(config('legal.controller_address'))
        <p>Adres do korespondencji: {{ config('legal.controller_address') }}.</p>
    @endif
    @if(config('legal.iod_email'))
        <p>Inspektor ochrony danych: <a href="mailto:{{ config('legal.iod_email') }}">{{ config('legal.iod_email') }}</a>.</p>
    @endif

    <h3>2. Cele i podstawy prawne</h3>
    <ul>
        <li>utworzenie i obsługa konta, uwierzytelnianie oraz realizacja usługi — art. 6 ust. 1 lit. b RODO;</li>
        <li>ochrona panelu, zapobieganie nadużyciom, prowadzenie dziennika bezpieczeństwa i ustalanie odpowiedzialności za działania — art. 6 ust. 1 lit. f RODO;</li>
        <li>realizacja obowiązków prawnych — art. 6 ust. 1 lit. c RODO;</li>
        <li>ustalenie, dochodzenie lub obrona roszczeń — art. 6 ust. 1 lit. f RODO.</li>
    </ul>

    <h3>3. Zakres danych</h3>
    <p>Imię i nazwisko lub nazwa konta, adres e-mail, rola, przypisane placówki, adres IP, daty i wyniki logowania, identyfikatory sesji, historia działań administracyjnych i bezpieczeństwa.</p>

    <h3>4. Odbiorcy</h3>
    <p>Upoważnieni administratorzy, hosting i utrzymanie serwera, dostawca poczty, podmioty zapewniające kopie zapasowe i wsparcie techniczne oraz organy uprawnione na podstawie prawa.</p>

    <h3>5. Okres przetwarzania</h3>
    <p>Dane są przetwarzane przez czas aktywności konta i współpracy, a następnie przez okres niezbędny do rozliczalności, bezpieczeństwa, obsługi roszczeń i obowiązków prawnych. Logi są przechowywane zgodnie z polityką retencji administratora.</p>

    <h3>6. Dobrowolność podania danych</h3>
    <p>Podanie danych wymaganych do konta jest dobrowolne, ale niezbędne do uzyskania dostępu do panelu. Brak tych danych uniemożliwia utworzenie lub używanie konta.</p>
</section>

<section>
    <h2>B. Dane z monitorowanych komputerów</h2>

    <h3>1. Administrator i podmiot przetwarzający</h3>
    <p>Administratorem danych pochodzących z urządzenia jest co do zasady placówka, pracodawca lub inny podmiot, który zdecydował o instalacji agenta i określił cele monitoringu. Operator serwisu może przetwarzać te dane na polecenie administratora jako podmiot przetwarzający. Jeżeli dane mogą dotyczyć osób fizycznych, relacja powinna być uregulowana umową powierzenia przetwarzania danych zgodną z art. 28 RODO.</p>

    <h3>2. Źródło i kategorie danych</h3>
    <p>Dane są pozyskiwane automatycznie z monitorowanego komputera: identyfikatory techniczne, nazwa komputera, adres IP i MAC, parametry sieci, system operacyjny, użycie zasobów, stan dysków, Windows Update, program antywirusowy, zapora, wybrane usługi Windows, heartbeaty i incydenty.</p>

    <h3>3. Cel monitoringu</h3>
    <p>Zapewnienie ciągłości działania, wykrywanie awarii, problemów z Internetem i konfiguracją, identyfikowanie nieaktualnego oprogramowania oraz ograniczanie ryzyka podatności na włamania. Monitoring nie powinien służyć do kontroli treści pracy ani obserwowania zachowania pracownika.</p>

    <h3>4. Podstawa prawna</h3>
    <p>Podstawę określa administrator danej placówki, najczęściej jest nią realizacja umowy, obowiązek prawny albo prawnie uzasadniony interes polegający na ochronie infrastruktury, zapewnieniu bezpieczeństwa i ciągłości działania. Placówka powinna samodzielnie ocenić podstawę prawną, zakres monitoringu, obowiązek poinformowania pracowników i zgodność z przepisami prawa pracy.</p>

    <h3>5. Okres przetwarzania</h3>
    <p>Telemetria jest przechowywana zgodnie z retencją ustaloną przez administratora placówki, nie dłużej niż wymaga tego analiza bieżącego stanu, raportowanie, obsługa incydentów, rozliczalność lub obrona roszczeń.</p>
</section>

<section>
    <h2>C. Prawa osoby, której dane dotyczą</h2>
    <p>Osobie może przysługiwać prawo dostępu do danych, uzyskania kopii, sprostowania, usunięcia, ograniczenia przetwarzania, przenoszenia danych oraz wniesienia sprzeciwu. Zakres poszczególnych praw zależy od podstawy prawnej i celu przetwarzania.</p>
    <p>Osoba ma prawo wnieść skargę do Prezesa Urzędu Ochrony Danych Osobowych.</p>
</section>

<section>
    <h2>D. Przekazywanie poza EOG i profilowanie</h2>
    <p>Dane nie są domyślnie przekazywane poza Europejski Obszar Gospodarczy. Jeżeli administrator korzysta z dostawcy spoza EOG, musi zapewnić podstawę prawną i odpowiednie zabezpieczenia.</p>
    <p>System nie podejmuje wobec osób decyzji opartych wyłącznie na zautomatyzowanym przetwarzaniu, które wywołują skutki prawne lub podobnie istotnie wpływają na osobę. Oceny techniczne i Health Score są wsparciem dla administratora, a nie oceną pracownika.</p>
</section>

<section>
    <h2>E. Kontakt i realizacja praw</h2>
    <p>W sprawach dotyczących danych z konkretnej placówki należy skontaktować się z administratorem tej placówki. W sprawach kont panelu i działania serwisu: <a href="mailto:{{ config('legal.contact_email') }}">{{ config('legal.contact_email') }}</a>.</p>
</section>
@endsection
