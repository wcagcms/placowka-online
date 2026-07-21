<?php

namespace App\Services;

class AgentVersionService
{
    /** @return array{installed:?string,latest:string,status:string,label:string,message:string,is_current:bool} */
    public function describe(?string $installedVersion): array
    {
        $installed = $this->normalizeLabel($installedVersion);
        $latest = $this->normalizeLabel((string) config('placowka.agent_latest_version', 'exe-1.8.0'))
            ?: 'exe-1.8.0';

        if ($installed === null) {
            return [
                'installed' => null,
                'latest' => $latest,
                'status' => 'missing',
                'label' => 'Brak danych',
                'message' => 'Agent nie przesłał informacji o swojej wersji.',
                'is_current' => false,
            ];
        }

        $installedSemver = $this->extractSemver($installed);
        $latestSemver = $this->extractSemver($latest);

        if ($installedSemver === null || $latestSemver === null) {
            return [
                'installed' => $installed,
                'latest' => $latest,
                'status' => 'unknown',
                'label' => 'Wersja nierozpoznana',
                'message' => 'Nie udało się wiarygodnie porównać wersji agenta.',
                'is_current' => false,
            ];
        }

        $comparison = version_compare($installedSemver, $latestSemver);

        if ($comparison === 0) {
            return [
                'installed' => $installed,
                'latest' => $latest,
                'status' => 'current',
                'label' => 'Aktualny',
                'message' => 'Agent korzysta z najnowszej wersji.',
                'is_current' => true,
            ];
        }

        if ($comparison < 0) {
            return [
                'installed' => $installed,
                'latest' => $latest,
                'status' => 'outdated',
                'label' => 'Wymaga aktualizacji',
                'message' => 'Dostępna jest nowsza wersja agenta.',
                'is_current' => false,
            ];
        }

        return [
            'installed' => $installed,
            'latest' => $latest,
            'status' => 'ahead',
            'label' => 'Wersja nowsza',
            'message' => 'Agent raportuje wersję nowszą niż wersja oznaczona w systemie jako produkcyjna.',
            'is_current' => false,
        ];
    }

    private function normalizeLabel(?string $version): ?string
    {
        $version = trim((string) $version);

        return $version !== '' ? mb_substr($version, 0, 100) : null;
    }

    private function extractSemver(string $version): ?string
    {
        if (! preg_match('/(?<!\d)(\d+)\.(\d+)\.(\d+)(?!\d)/', $version, $matches)) {
            return null;
        }

        return $matches[1].'.'.$matches[2].'.'.$matches[3];
    }
}
