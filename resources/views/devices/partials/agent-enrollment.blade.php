@php
    $plainEnrollmentCode = session('agent_enrollment_device_id') === $device->id
        ? session('agent_enrollment_plain_code')
        : null;
    $plainEnrollmentExpiresAt = session('agent_enrollment_device_id') === $device->id
        ? session('agent_enrollment_expires_at')
        : null;
    $activeEnrollmentCode = $device->enrollmentCodes
        ->first(fn ($code) => $code->isAvailable());
@endphp

<section class="card padded po-enrollment-card" aria-labelledby="agent-enrollment-title">
    <div class="po-enrollment-header">
        <div>
            <p class="po-enrollment-eyebrow">Bezpieczna instalacja</p>
            <h2 id="agent-enrollment-title">Instalator PlacówkaOnlineSetup.exe</h2>
            <p>
                Jeden stały instalator, bez tokenu urządzenia i bez skryptów PowerShell.
                Kod jest ważny 15 minut i może zostać wykorzystany tylko raz.
            </p>
        </div>
        <span class="po-enrollment-status {{ $installerAvailable ? 'is-ready' : 'is-missing' }}">
            {{ $installerAvailable ? 'INSTALATOR GOTOWY' : 'BRAK INSTALATORA' }}
        </span>
    </div>

    @error('enrollment')
        <div class="notice danger" role="alert">{{ $message }}</div>
    @enderror

    @if($plainEnrollmentCode)
        <div class="po-enrollment-code-panel" data-enrollment-code-panel data-expires-at="{{ $plainEnrollmentExpiresAt }}">
            <div>
                <span class="po-enrollment-code-label">Jednorazowy kod urządzenia</span>
                <strong class="po-enrollment-code" data-enrollment-code>{{ $plainEnrollmentCode }}</strong>
                <p>
                    Kod jest pokazany tylko teraz. Nie jest przechowywany w bazie w jawnej postaci.
                </p>
            </div>
            <div class="po-enrollment-code-actions">
                <button class="btn secondary" type="button" data-copy-enrollment-code>
                    Kopiuj kod
                </button>
                <span class="po-enrollment-countdown" data-enrollment-countdown aria-live="polite">
                    Ważność: 15:00
                </span>
            </div>
        </div>
    @endif

    <div class="po-enrollment-grid">
        <article class="po-enrollment-step">
            <span class="po-enrollment-step-number">1</span>
            <div>
                <h3>Pobierz stały instalator</h3>
                <p>Ten sam plik jest używany dla wszystkich placówek. Nie zawiera kodu ani tokenu.</p>
                @if($installerAvailable)
                    <a class="btn secondary" href="{{ route('agent-installer.download') }}">
                        Pobierz PlacowkaOnlineSetup.exe
                    </a>
                    @if($installerSha256)
                        <small class="po-enrollment-hash">SHA-256: <code>{{ $installerSha256 }}</code></small>
                    @endif
                @else
                    <span class="po-enrollment-muted">Najpierw zbuduj i opublikuj instalator zgodnie z README pakietu.</span>
                @endif
            </div>
        </article>

        <article class="po-enrollment-step">
            <span class="po-enrollment-step-number">2</span>
            <div>
                <h3>Wygeneruj kod dla tego urządzenia</h3>
                <p>Nowy kod automatycznie unieważni poprzedni niewykorzystany kod.</p>
                <form method="post" action="{{ route('devices.enrollment-codes.store', $device) }}">
                    @csrf
                    <button class="btn" type="submit">Wygeneruj kod ważny 15 minut</button>
                </form>
            </div>
        </article>

        <article class="po-enrollment-step">
            <span class="po-enrollment-step-number">3</span>
            <div>
                <h3>Uruchom instalator na właściwym komputerze</h3>
                <p>
                    Instalator poprosi o kod, rozpocznie sesję, skopiuje agenta, a dopiero potem pobierze token przez HTTPS.
                </p>
            </div>
        </article>
    </div>

    <div class="po-enrollment-meta-grid">
        <div>
            <span>Kod</span>
            <strong>Jedno użycie</strong>
        </div>
        <div>
            <span>Ważność kodu</span>
            <strong>15 minut</strong>
        </div>
        <div>
            <span>Token w instalatorze</span>
            <strong>Nie</strong>
        </div>
        <div>
            <span>PowerShell</span>
            <strong>Nie</strong>
        </div>
    </div>

    @if($device->enrollmentCodes->isNotEmpty())
        <details class="po-enrollment-history">
            <summary>Ostatnie kody i sesje instalacyjne</summary>
            <div class="po-enrollment-history-list">
                @foreach($device->enrollmentCodes as $code)
                    @php
                        $codeState = $code->used_at
                            ? 'Wykorzystany'
                            : ($code->revoked_at
                                ? 'Unieważniony'
                                : ($code->claimed_at
                                    ? 'Rozpoczęto instalację'
                                    : ($code->expires_at->isPast() ? 'Wygasł' : 'Aktywny')));
                    @endphp
                    <div class="po-enrollment-history-row">
                        <div>
                            <strong>{{ $code->code_label }}</strong>
                            <span>{{ $codeState }}</span>
                        </div>
                        <div>
                            <time datetime="{{ $code->created_at?->toIso8601String() }}">
                                {{ $code->created_at?->timezone('Europe/Warsaw')->format('d.m.Y H:i') }}
                            </time>
                            @if($code->isAvailable())
                                <form method="post"
                                      action="{{ route('devices.enrollment-codes.revoke', [$device, $code]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="po-enrollment-text-button" type="submit">Unieważnij</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</section>
