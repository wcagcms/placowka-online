@extends('layouts.panel')

@section('title', 'Zarządzaj placówką — Placówka Online')
@section('eyebrow', 'Placówka')
@section('page_title', 'Zarządzaj placówką')
@section('page_lead', $facility->code . ' — ' . $facility->name)

@section('page_actions')
    <a class="btn secondary" href="{{ route('facilities.show', ['facility' => $facility->id]) }}">Szczegóły placówki</a>
    <a class="btn secondary" href="{{ route('dashboard') }}">Panel główny</a>
@endsection

@section('content')
    <section class="stats five" aria-label="Statystyki placówki">
        <div class="stat"><span>Urządzenia</span><strong>{{ $stats['devices_total'] }}</strong></div>
        <div class="stat"><span>Aktywne</span><strong>{{ $stats['devices_active'] }}</strong></div>
        <div class="stat"><span>Nieaktywne</span><strong>{{ $stats['devices_inactive'] }}</strong></div>
        <div class="stat"><span>Archiwalne</span><strong>{{ $stats['devices_archived'] }}</strong></div>
        <div class="stat"><span>Aktywne awarie</span><strong>{{ $stats['open_incidents'] }}</strong></div>
    </section>

    <div class="grid two">
        <section class="card padded">
            <h2>Dane placówki</h2>

            <form class="form-grid" method="post" action="{{ route('facilities.update', ['facility' => $facility->id]) }}">
                @csrf
                @method('PATCH')

                <div class="field">
                    <label for="code">Kod placówki</label>
                    <input id="code" name="code" value="{{ old('code', $facility->code) }}" required>
                </div>

                <div class="field">
                    <label for="name">Nazwa placówki</label>
                    <input id="name" name="name" value="{{ old('name', $facility->name) }}" required>
                </div>

                <div class="field">
                    <label for="address">Adres</label>
                    <textarea id="address" name="address">{{ old('address', $facility->address) }}</textarea>
                </div>

                <div class="field">
                    <label for="contact_email">E-mail kontaktowy</label>
                    <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $facility->contact_email) }}">
                </div>

                <button class="btn" type="submit">Zapisz dane placówki</button>
            </form>
        </section>

        <aside class="card padded">
            <h2>Status placówki</h2>

            <ul class="info-list">
                <li><strong>Status:</strong> {{ $facility->is_active ? 'aktywna' : 'nieaktywna' }}</li>
                <li><strong>ID:</strong> {{ $facility->id }}</li>
                <li><strong>Kod:</strong> {{ $facility->code }}</li>
                <li><strong>E-mail:</strong> {{ $facility->contact_email ?: 'brak' }}</li>
                <li><strong>Utworzono:</strong> {{ $facility->created_at?->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') }}</li>
                <li><strong>Aktualizacja:</strong> {{ $facility->updated_at?->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') }}</li>
            </ul>

            <div class="actions" style="margin-top:18px;">
                @if($facility->is_active)
                    <form class="inline" method="post" action="{{ route('facilities.deactivate', ['facility' => $facility->id]) }}" onsubmit="return confirm('Czy na pewno dezaktywować placówkę? Aktywne urządzenia też zostaną dezaktywowane.');">
                        @csrf
                        <button class="btn danger" type="submit">Dezaktywuj placówkę</button>
                    </form>
                @else
                    <form class="inline" method="post" action="{{ route('facilities.activate', ['facility' => $facility->id]) }}">
                        @csrf
                        <button class="btn ok" type="submit">Aktywuj placówkę</button>
                    </form>
                @endif
            </div>

            <div class="notice warn" style="margin-top:18px;">
                Dezaktywacja placówki dezaktywuje niezarchiwizowane urządzenia, żeby system nie zgłaszał fałszywych awarii.
                Historia heartbeatów i awarii zostaje zachowana.
            </div>
        </aside>
    </div>

    <section class="card padded">
        <h2>Urządzenia w placówce</h2>

        @php
            $activeDevices = $facility->devices->filter(fn($device) => $device->archived_at === null);
            $archivedDevices = $facility->devices->filter(fn($device) => $device->archived_at !== null);
        @endphp

        <h3>Aktywne i nieaktywne</h3>

        @if($activeDevices->count())
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Nazwa</th>
                        <th>Status</th>
                        <th>Aktywne</th>
                        <th>Ostatni raport</th>
                        <th>Akcje</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($activeDevices as $device)
                        <tr>
                            <td data-label="Nazwa">{{ $device->name }}</td>
                            <td data-label="Status">{{ $device->status }}</td>
                            <td data-label="Aktywne">{{ $device->is_active ? 'tak' : 'nie' }}</td>
                            <td data-label="Ostatni raport">
                                {{ $device->last_seen_at ? $device->last_seen_at->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') : 'brak danych' }}
                            </td>
                            <td data-label="Akcje">
                                <a class="btn small secondary" href="{{ route('devices.edit', ['device' => $device->id]) }}">Zarządzaj</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted">Brak aktywnych lub nieaktywnych urządzeń.</p>
        @endif

        <h3>Archiwalne</h3>

        @if($archivedDevices->count())
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Nazwa</th>
                        <th>Status</th>
                        <th>Zarchiwizowano</th>
                        <th>Ostatni raport</th>
                        <th>Akcje</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($archivedDevices as $device)
                        <tr>
                            <td data-label="Nazwa">{{ $device->name }}</td>
                            <td data-label="Status">{{ $device->status }}</td>
                            <td data-label="Zarchiwizowano">{{ $device->archived_at?->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') }}</td>
                            <td data-label="Ostatni raport">
                                {{ $device->last_seen_at ? $device->last_seen_at->timezone('Europe/Warsaw')->format('Y-m-d H:i:s') : 'brak danych' }}
                            </td>
                            <td data-label="Akcje">
                                <a class="btn small secondary" href="{{ route('devices.edit', ['device' => $device->id]) }}">Zarządzaj</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted">Brak urządzeń archiwalnych.</p>
        @endif
    </section>
@endsection
