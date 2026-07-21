@extends('layouts.panel')

@section('title', 'Dodaj urządzenie — Placówka Online')
@section('eyebrow', 'Nowe urządzenie')
@section('page_title', 'Dodaj urządzenie')
@section('page_lead', $facility->code . ' — ' . $facility->name)

@section('page_actions')
    <a class="btn secondary" href="{{ route('facilities.show', ['facility' => $facility->id]) }}">Powrót</a>
@endsection

@section('content')
    <section class="card padded">
        <form class="form-grid" method="post" action="{{ route('facilities.devices.store', ['facility' => $facility->id]) }}">
            @csrf

            <div class="field">
                <label for="device_name">Nazwa urządzenia</label>
                <input id="device_name" name="device_name" value="{{ old('device_name', 'Komputer sekretariat') }}" required>
                <div class="hint">Przykłady: Komputer sekretariat, Komputer dyrektora lub Serwer. Po dodaniu wygenerujesz kod do stałego instalatora.</div>
            </div>

            <div class="actions">
                <button class="btn" type="submit">Dodaj urządzenie</button>
                <a class="btn secondary" href="{{ route('facilities.show', ['facility' => $facility->id]) }}">Anuluj</a>
            </div>
        </form>
    </section>
@endsection
