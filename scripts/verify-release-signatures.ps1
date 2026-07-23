[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$BuildInputDirectory,

    [Parameter(Mandatory = $true)]
    [string]$InstallerPath,

    [string]$ExpectedPublisher = ''
)

$ErrorActionPreference = 'Stop'

$files = @(
    (Join-Path $BuildInputDirectory 'PlacowkaOnlineAgent.exe'),
    (Join-Path $BuildInputDirectory 'PlacowkaOnlineAgentConsole.exe'),
    (Join-Path $BuildInputDirectory 'PlacowkaOnlineEnroll.exe'),
    $InstallerPath
)

$results = foreach ($file in $files) {
    if (-not (Test-Path -LiteralPath $file -PathType Leaf)) {
        throw "Brak wymaganego pliku wydania: $file"
    }

    $signature = Get-AuthenticodeSignature -LiteralPath $file
    $hash = Get-FileHash -LiteralPath $file -Algorithm SHA256
    $publisher = if ($signature.SignerCertificate) {
        $signature.SignerCertificate.Subject
    } else {
        ''
    }

    if ($signature.Status -ne 'Valid') {
        throw "Nieprawidłowy podpis Authenticode: $file ($($signature.Status))"
    }

    if ($ExpectedPublisher -and $publisher -notlike "*$ExpectedPublisher*") {
        throw "Nieoczekiwany wydawca pliku $file: $publisher"
    }

    [pscustomobject]@{
        File       = (Resolve-Path -LiteralPath $file).Path
        Status     = $signature.Status
        Publisher  = $publisher
        Thumbprint = $signature.SignerCertificate.Thumbprint
        SHA256     = $hash.Hash
    }
}

$results | Format-Table -AutoSize
$results
