@extends('layouts.panel')

@section('title', 'Zarządzaj urządzeniem — Placówka Online')
@section('eyebrow', 'Urządzenie')
@section('page_title', 'Zarządzaj urządzeniem')
@section('page_lead', $device->facility->code . ' — ' . $device->facility->name . ' / ' . $device->name)

@section('page_actions')
    <a class="btn secondary" href="{{ route('facilities.show', ['facility' => $device->facility->id]) }}">Powrót do placówki</a>
    <a class="btn secondary" href="{{ route('devices.heartbeats', ['device' => $device->id]) }}">Heartbeat</a>
@endsection


@push('head')
<link rel="stylesheet" href="{{ asset('panel/saas-platinum-enrollment.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('panel/saas-platinum-enrollment.js') }}" defer></script>
@endpush

@section('content')
    <div class="grid two">
        <section class="card padded">
            <h2>Edycja danych</h2>

            <form class="form-grid" method="post" action="{{ route('devices.update', ['device' => $device->id]) }}">
                @csrf
                @method('PATCH')

                <div class="field">
                    <label for="name">Nazwa urządzenia</label>
                    <input id="name" name="name" value="{{ old('name', $device->name) }}" required>
                </div>

                <div class="field">
                    <label for="notes">Notatki</label>
                    <textarea id="notes" name="notes">{{ old('notes', $device->notes) }}</textarea>
                </div>

                <fieldset class="field">
                    <legend>Ochrona antywirusowa</legend>

                    <label for="antivirus_policy">Wymagana polityka ochrony</label>
                    <select id="antivirus_policy" name="antivirus_policy" required>
                        <option value="auto" @selected(old('antivirus_policy', $device->antivirus_policy ?? 'auto') === 'auto')>
                            Automatycznie wykryj aktywny antywirus
                        </option>
                        <option value="microsoft_defender" @selected(old('antivirus_policy', $device->antivirus_policy ?? 'auto') === 'microsoft_defender')>
                            Wymagaj Microsoft Defender
                        </option>
                        <option value="third_party" @selected(old('antivirus_policy', $device->antivirus_policy ?? 'auto') === 'third_party')>
                            Wymagaj zewnętrznego programu antywirusowego
                        </option>
                    </select>

                    <label for="expected_antivirus_provider">Oczekiwany dostawca zewnętrzny</label>
                    <input id="expected_antivirus_provider"
                           name="expected_antivirus_provider"
                           value="{{ old('expected_antivirus_provider', $device->expected_antivirus_provider) }}"
                           maxlength="120"
                           placeholder="np. ESET">

                    <p class="help">
                        Dla komputera z ESET wybierz „Wymagaj zewnętrznego programu antywirusowego”
                        i wpisz <strong>ESET</strong>. System zamknie alert Defendera dopiero wtedy,
                        gdy agent potwierdzi aktywny produkt ESET w Windows Security Center.
                    </p>

                    @php
                        $detectedProducts = collect(data_get($device->defender_status, 'antivirus_products', []));
                    @endphp

                    @if($detectedProducts->isNotEmpty())
                        <div class="notice" style="margin-top:12px;">
                            <strong>Produkty wykryte przez ostatni pomiar:</strong>
                            <ul>
                                @foreach($detectedProducts as $product)
                                    <li>
                                        {{ data_get($product, 'display_name', 'Nieznany produkt') }}
                                        — {{ data_get($product, 'enabled') === true ? 'aktywny' : 'nieaktywny' }}
                                        @if(data_get($product, 'up_to_date') === false)
                                            , wymaga aktualizacji
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </fieldset>

                <button class="btn" type="submit">Zapisz zmiany</button>
            </form>
        </section>

        <aside class="card padded">
            <h2>Status urządzenia</h2>

            <ul class="info-list">
                <li><strong>Status:</strong> {{ $device->status }}</li>
                <li><strong>Aktywne:</strong> {{ $device->is_active ? 'tak' : 'nie' }}</li>
                <li>
                    <strong>Archiwum:</strong>
                    @if($device->archived_at)
                        tak, od {{ $device->archived_at->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') }}
                    @else
                        nie
                    @endif
                </li>
                <li><strong>UUID:</strong><br><code>{{ $device->uuid }}</code></li>
                <li>
                    <strong>Ostatni raport:</strong><br>
                    {{ $device->last_seen_at ? $device->last_seen_at->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') : 'brak danych' }}
                </li>
                <li><strong>Wersja agenta:</strong> {{ $device->agent_version ?: 'brak danych' }}</li>
            </ul>
        </aside>
    </div>

    @include('devices.partials.agent-enrollment')

    <section class="card padded">
        <h2>Operacje urządzenia</h2>

        <div class="actions">
            @if($device->is_active)
                <form class="inline" method="post" action="{{ route('devices.deactivate', ['device' => $device->id]) }}">
                    @csrf
                    <button class="btn warn" type="submit">Dezaktywuj urządzenie</button>
                </form>
            @else
                <form class="inline" method="post" action="{{ route('devices.activate', ['device' => $device->id]) }}">
                    @csrf
                    <button class="btn ok" type="submit">Aktywuj urządzenie</button>
                </form>
            @endif

            @if($legacyPackagesEnabled)
                <form class="inline" method="post" action="{{ route('devices.regenerate-package', ['device' => $device->id]) }}">
                    @csrf
                    <button class="btn secondary" type="submit">Awaryjna paczka ZIP</button>
                </form>
            @endif

            <form class="inline" method="post" action="{{ route('devices.archive', ['device' => $device->id]) }}" onsubmit="return confirm('Czy na pewno zarchiwizować to urządzenie? Historia pomiarów zostanie zachowana.');">
                @csrf
                <button class="btn danger" type="submit">Archiwizuj urządzenie</button>
            </form>
        </div>

        @if($legacyPackagesEnabled)
            <div class="notice warn" style="margin-top:18px;">
                <strong>Tryb awaryjny:</strong>
                stara paczka ZIP nadal zawiera indywidualny token urządzenia i powinna być używana wyłącznie do czasu
                potwierdzenia działania stałego instalatora. Po testach ustaw
                <code>PLACOWKA_LEGACY_AGENT_PACKAGES_ENABLED=false</code>.
            </div>
        @endif
    </section>
@endsection
