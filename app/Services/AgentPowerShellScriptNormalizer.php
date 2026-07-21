<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class AgentPowerShellScriptNormalizer
{
    /**
     * Skrypty dołączane do każdej paczki agenta Windows.
     *
     * @var list<string>
     */
    private const SCRIPT_FILES = [
        'install.ps1',
        'uninstall.ps1',
        'test.ps1',
    ];

    public function normalizeDirectory(string $directory): void
    {
        foreach (self::SCRIPT_FILES as $fileName) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . $fileName;

            if (! File::isFile($path)) {
                throw new RuntimeException(
                    'Brakuje skryptu PowerShell w paczce agenta: ' . $fileName
                );
            }

            $this->normalizeFile($path);
        }
    }

    private function normalizeFile(string $path): void
    {
        $bytes = File::get($path);
        $content = $this->decodeToUtf8($bytes, basename($path));

        // Usuń znaczniki Markdown, gdyby skrypt został zapisany z bloku kodu.
        $content = preg_replace(
            '/\A```(?:powershell|pwsh)?\s*/iu',
            '',
            $content
        ) ?? $content;

        $content = preg_replace(
            '/\s*```\s*\z/u',
            '',
            $content
        ) ?? $content;

        // Usuń BOM zapisany jako znak tekstowy, znaki zero-width,
        // znaczniki kierunku tekstu, NBSP i inne niewidoczne znaki z początku.
        $content = preg_replace(
            '/\A(?:[\x{FEFF}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{00A0}]|\s)+/u',
            '',
            $content
        ) ?? $content;

        // Obsługa tzw. mojibake, np. tekstowego "ï»¿" przed pierwszą komendą.
        $content = preg_replace('/\A[^\x21-\x7E]+/u', '', $content) ?? $content;

        if ($content === '') {
            throw new RuntimeException(
                'Skrypt PowerShell jest pusty po normalizacji: ' . basename($path)
            );
        }

        // Windows PowerShell 5.1 najpewniej obsługuje UTF-8 z pojedynczym BOM.
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = str_replace("\n", "\r\n", $content);

        File::put($path, "\xEF\xBB\xBF" . $content);

        $saved = File::get($path);

        if (! str_starts_with($saved, "\xEF\xBB\xBF")) {
            throw new RuntimeException(
                'Nie udało się zapisać prawidłowego kodowania skryptu: '
                . basename($path)
            );
        }

        if (str_starts_with(substr($saved, 3), "\xEF\xBB\xBF")) {
            throw new RuntimeException(
                'Wykryto podwójny BOM w skrypcie: ' . basename($path)
            );
        }
    }

    private function decodeToUtf8(string $bytes, string $fileName): string
    {
        if (str_starts_with($bytes, "\xFF\xFE")) {
            return $this->convertEncoding(
                substr($bytes, 2),
                'UTF-16LE',
                $fileName
            );
        }

        if (str_starts_with($bytes, "\xFE\xFF")) {
            return $this->convertEncoding(
                substr($bytes, 2),
                'UTF-16BE',
                $fileName
            );
        }

        while (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        }

        if (preg_match('//u', $bytes) === 1) {
            return $bytes;
        }

        return $this->convertEncoding($bytes, 'Windows-1250', $fileName);
    }

    private function convertEncoding(
        string $content,
        string $fromEncoding,
        string $fileName
    ): string {
        $converted = iconv($fromEncoding, 'UTF-8//IGNORE', $content);

        if ($converted === false || $converted === '') {
            throw new RuntimeException(
                'Nie można przekonwertować kodowania skryptu: ' . $fileName
            );
        }

        return $converted;
    }
}
