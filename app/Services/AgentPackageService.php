<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Facility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class AgentPackageService
{
    public const AGENT_VERSION = 'exe-1.9.2';

    public function createUnenrolledDevice(Facility $facility, string $deviceName): Device
    {
        return Device::query()->create([
            'facility_id' => $facility->getKey(),
            'uuid' => (string) Str::uuid(),
            // Jawny token startowy jest celowo odrzucany. Urządzenie otrzyma właściwy token
            // dopiero w drugim etapie bezpiecznej rejestracji.
            'token_hash' => hash('sha256', Str::random(64)),
            'name' => trim($deviceName),
            'status' => 'unknown',
            'check_interval_seconds' => 60,
            'missing_after_minutes' => config('placowka.default_missing_after_minutes', 3),
            'alert_after_minutes' => config('placowka.default_alert_after_minutes', 5),
            'is_active' => true,
        ]);
    }

    public function createForNewDevice(Facility $facility, string $deviceName): array
    {
        $plainToken = Str::random(64);
        $workDir = null;
        $zipPath = null;

        DB::beginTransaction();

        try {
            $device = Device::query()->create([
                'facility_id' => $facility->id,
                'uuid' => (string) Str::uuid(),
                'name' => trim($deviceName),
                'token_hash' => hash('sha256', $plainToken),
                'status' => 'unknown',
                'check_interval_seconds' => 60,
                'missing_after_minutes' => config('placowka.default_missing_after_minutes', 3),
                'alert_after_minutes' => config('placowka.default_alert_after_minutes', 5),
                'is_active' => true,
            ]);

            [$zipName, $zipPath, $workDir] = $this->buildPackage(
                $facility,
                $device,
                $plainToken,
                false
            );

            DB::commit();

            return [$device, $zipName];
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->cleanupPaths($workDir, $zipPath);

            throw $exception;
        }
    }

    public function regenerateForDevice(Device $device): string
    {
        $device->loadMissing('facility');

        $plainToken = Str::random(64);
        $workDir = null;
        $zipPath = null;

        try {
            [$zipName, $zipPath, $workDir] = $this->buildPackage(
                $device->facility,
                $device,
                $plainToken,
                true
            );

            DB::transaction(function () use ($device, $plainToken): void {
                $device->forceFill([
                    'token_hash' => hash('sha256', $plainToken),
                    'is_active' => true,
                    'archived_at' => null,
                    'status' => 'unknown',
                    'agent_version' => null,
                ])->save();
            });

            return $zipName;
        } catch (Throwable $exception) {
            $this->cleanupPaths($workDir, $zipPath);

            throw $exception;
        }
    }

    private function buildPackage(
        Facility $facility,
        Device $device,
        string $plainToken,
        bool $regenerated
    ): array {
        $this->ensureRequirements();
        $this->ensureSecureBuildMetadata();
        $this->ensureHttpsApplicationUrl();

        $templateDir = storage_path('app/agent-template');
        $safeName = Str::slug($facility->code.'-'.$device->name);
        $timestamp = now()->format('Ymd-His');
        $suffix = $regenerated ? '-regenerated' : '';

        $workDir = storage_path('app/agent-builds/'.$safeName.$suffix.'-'.$timestamp);
        $packageDir = storage_path('app/agent-packages');
        $zipName = 'placowka-online-agent-'.$safeName.$suffix.'-'.$timestamp.'.zip';
        $zipPath = $packageDir.'/'.$zipName;

        File::ensureDirectoryExists($workDir, 0700, true);
        File::ensureDirectoryExists($packageDir, 0700, true);

        try {
            File::copyDirectory($templateDir, $workDir);
            app(AgentPowerShellScriptNormalizer::class)->normalizeDirectory($workDir);

            if (is_dir($workDir.'/src')) {
                File::deleteDirectory($workDir.'/src');
            }

            $config = $this->config($facility, $device, $plainToken);

            File::put(
                $workDir.'/config.json',
                json_encode(
                    $config,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                )
            );

            File::put(
                $workDir.'/README_INSTALACJA.txt',
                $this->readme($facility, $device, $config, $regenerated)
            );

            $this->writeChecksums($workDir);
            $this->zipDirectory($workDir, $zipPath);

            @chmod($zipPath, 0600);

            return [$zipName, $zipPath, $workDir];
        } finally {
            if (is_dir($workDir)) {
                File::deleteDirectory($workDir);
            }
        }
    }

    private function config(Facility $facility, Device $device, string $plainToken): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return [
            'api_url' => $baseUrl.'/api/v1/heartbeat/'.$device->uuid,
            'token' => $plainToken,
            'agent_version' => self::AGENT_VERSION,
            'location_code' => $facility->code,
            'device_name' => $device->name,
            'timeout_seconds' => 8,
            'performance_profile' => 'low_impact',
            'system_interval_minutes' => 30,
            'network_interval_minutes' => 15,
            'services_interval_minutes' => 5,
            'smart_interval_minutes' => 60,
            'self_check_interval_minutes' => max(10, min(240, (int) config('placowka.agent_self_check_interval_minutes', 30))),
            'windows_update_interval_minutes' => max(60, min(10080, (int) config('placowka.windows_update_interval_minutes', 720))),
            'defender_interval_minutes' => max(30, min(1440, (int) config('placowka.defender_interval_minutes', 360))),
            'offline_queue_max_items' => max(10, min(1000, (int) config('placowka.offline_queue_max_items', 100))),
            'offline_queue_max_age_days' => max(1, min(30, (int) config('placowka.offline_queue_max_age_days', 7))),
            'offline_queue_flush_per_cycle' => max(1, min(100, (int) config('placowka.offline_queue_flush_per_cycle', 10))),
            'startup_jitter_seconds' => 15,
            'monitoring_url' => $baseUrl.'/panel/login',
            'test_urls' => [
                'https://www.google.com/generate_204',
                'https://cloudflare.com/cdn-cgi/trace',
                'https://www.msftconnecttest.com/connecttest.txt',
            ],
            'dns_test_host' => parse_url($baseUrl, PHP_URL_HOST) ?: 'monitoring.wcag-cms.pl',
            'windows_services' => app(AgentWindowsServiceConfigService::class)->forAgent(),
        ];
    }

    private function readme(
        Facility $facility,
        Device $device,
        array $config,
        bool $regenerated
    ): string {
        $lines = [
            'Placówka Online — bezpieczna paczka agenta',
            '',
            'Placówka: '.$facility->code.' — '.$facility->name,
            'Urządzenie: '.$device->name,
            'UUID: '.$device->uuid,
            'Endpoint: '.$config['api_url'],
            'Wersja: '.self::AGENT_VERSION,
            '',
            'Instalacja na Windows:',
            '1. Przenieś ZIP bezpiecznym kanałem.',
            '2. Rozpakuj ZIP.',
            '3. Uruchom PowerShell jako administrator.',
            '4. Wykonaj:',
            '   powershell.exe -ExecutionPolicy Bypass -File .\install.ps1',
            '',
            'Instalator sprawdzi sumy SHA-256 i ustawi ograniczone uprawnienia katalogu.',
            'Agent pracuje w profilu low_impact: heartbeat co minutę, a cięższe pomiary są wykonywane rzadziej. Przy braku Internetu maksymalnie 100 heartbeatów jest kolejkowanych lokalnie i wysyłanych po odzyskaniu łączności.',
            'Instalator wykonuje pełny pomiar inicjalny. W trybie ciągłym Windows Update jest kontrolowany co 12 godzin, Defender co 6 godzin, a SMART co 60 minut.',
            '',
            'Test ręczny:',
            '   C:\PlacowkaOnline\PlacowkaOnlineAgentConsole.exe',
            '',
            'Odinstalowanie:',
            '   powershell.exe -ExecutionPolicy Bypass -File .\uninstall.ps1',
            '',
            'WAŻNE: config.json zawiera token agenta. Traktuj tę paczkę jak poufną.',
            'Paczka jest przeznaczona wyłącznie dla wskazanego urządzenia.',
        ];

        if ($regenerated) {
            $lines[] = 'Po utworzeniu paczki poprzedni token urządzenia został unieważniony.';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function writeChecksums(string $workDir): void
    {
        $files = [
            'PlacowkaOnlineAgent.exe',
            'PlacowkaOnlineAgentConsole.exe',
            'config.json',
            'install.ps1',
            'uninstall.ps1',
            'test.ps1',
            'BUILD_INFO.txt',
        ];

        $lines = [];

        foreach ($files as $file) {
            $path = $workDir.'/'.$file;

            if (! is_file($path)) {
                throw new RuntimeException('Brakuje pliku wymaganego do manifestu SHA-256: '.$file);
            }

            $lines[] = hash_file('sha256', $path).' *'.$file;
        }

        File::put($workDir.'/checksums.sha256', implode(PHP_EOL, $lines).PHP_EOL);
    }

    private function ensureRequirements(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Brak rozszerzenia PHP ZipArchive.');
        }

        $templateDir = storage_path('app/agent-template');

        if (! is_dir($templateDir)) {
            throw new RuntimeException('Brak katalogu szablonu agenta: storage/app/agent-template');
        }

        foreach ([
            'PlacowkaOnlineAgent.exe',
            'PlacowkaOnlineAgentConsole.exe',
            'install.ps1',
            'uninstall.ps1',
            'test.ps1',
            'BUILD_INFO.txt',
        ] as $file) {
            if (! is_file($templateDir.'/'.$file)) {
                throw new RuntimeException('Brakuje pliku w szablonie agenta: '.$file);
            }
        }
    }

    private function ensureSecureBuildMetadata(): void
    {
        $metadataPath = storage_path('app/agent-template/BUILD_INFO.txt');
        $metadata = File::get($metadataPath);
        $minimum = (string) config('placowka.agent_minimum_go_version', '1.26.5');
        $allowInterim = (bool) config('placowka.agent_allow_interim_build', false);

        if (preg_match('/Placówka Online Agent\s+(exe-[0-9.]+)/iu', $metadata, $versionMatch) !== 1) {
            throw new RuntimeException('BUILD_INFO.txt nie zawiera rozpoznawalnej wersji agenta.');
        }

        if ($versionMatch[1] !== self::AGENT_VERSION) {
            throw new RuntimeException(
                'Szablon EXE ma wersję '.$versionMatch[1].', ale generator wymaga '.self::AGENT_VERSION
                .'. Uruchom scripts/build-agent-secure.sh przed wygenerowaniem paczki.'
            );
        }

        if (preg_match('/\bgo(\d+\.\d+\.\d+)\b/i', $metadata, $match) !== 1) {
            throw new RuntimeException('BUILD_INFO.txt nie zawiera rozpoznawalnej wersji kompilatora Go.');
        }

        if (! $allowInterim && version_compare($match[1], $minimum, '<')) {
            throw new RuntimeException(
                'Szablon agenta zbudowano Go '.$match[1].'. Wymagane jest Go '.$minimum
                .' lub nowsze. Uruchom scripts/build-agent-secure.sh przed wygenerowaniem paczki.'
            );
        }
    }

    private function ensureHttpsApplicationUrl(): void
    {
        $url = (string) config('app.url');

        if (mb_strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new RuntimeException('APP_URL musi używać HTTPS przed wygenerowaniem paczki agenta.');
        }
    }

    private function zipDirectory(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException('Nie można otworzyć pliku ZIP do zapisu.');
        }

        $source = realpath($sourceDir);

        if ($source === false) {
            throw new RuntimeException('Nie znaleziono katalogu roboczego paczki.');
        }

        $source = rtrim($source, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $filePath = $file->getRealPath();

            if ($filePath === false) {
                continue;
            }

            $relativePath = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($filePath, strlen($source))
            );

            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();

        if (! is_file($zipPath)) {
            throw new RuntimeException('Plik ZIP nie został utworzony.');
        }
    }

    private function cleanupPaths(?string $workDir, ?string $zipPath): void
    {
        if ($workDir && is_dir($workDir)) {
            File::deleteDirectory($workDir);
        }

        if ($zipPath && is_file($zipPath)) {
            File::delete($zipPath);
        }
    }
}
