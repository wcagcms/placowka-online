@extends('layouts.panel')

@section('title', 'Agent wygenerowany — Placówka Online')
@section('eyebrow', 'Paczka agenta')
@section('page_title', 'Agent EXE został wygenerowany')
@section('page_lead', 'Pobierz ZIP i zainstaluj agenta na komputerze w placówce.')

@section('page_actions')
    <a class="btn secondary" href="{{ route('facilities.show', ['facility' => $facility->id]) }}">Przejdź do placówki</a>
    <a class="btn secondary" href="{{ route('dashboard') }}">Panel główny</a>
@endsection

@section('content')
    <section class="card padded">
        <p>
            <strong>Placówka:</strong>
            {{ $facility->code }} — {{ $facility->name }}
        </p>

        <p>
            <strong>Urządzenie:</strong>
            {{ $device->name }}
        </p>

        <p>
            <strong>UUID:</strong>
            <code>{{ $device->uuid }}</code>
        </p>

        <div class="actions">
            <a class="btn" href="{{ route('agent-packages.download', ['zipName' => $zipName]) }}">
                Pobierz paczkę ZIP agenta
            </a>

            <a class="btn secondary" href="{{ route('devices.edit', ['device' => $device->id]) }}">
                Zarządzaj urządzeniem
            </a>
        </div>

        <div class="notice warn" style="margin-top:18px;">
            <strong>Ważne:</strong>
            paczka ZIP zawiera <code>config.json</code> z tokenem agenta.
            Traktuj ją jak hasło. Po instalacji w placówce nie udostępniaj jej publicznie.
        </div>

        <p class="muted">
            Instalacja na Windows: rozpakuj ZIP, uruchom PowerShell jako administrator i wykonaj
            <code>powershell.exe -ExecutionPolicy Bypass -File .\install.ps1</code>.
        </p>
    </section>
@endsection
