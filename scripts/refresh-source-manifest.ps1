[CmdletBinding()]
param(
    [string]$Root = ''
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

function Get-PortableRelativePath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$BasePath,

        [Parameter(Mandatory = $true)]
        [string]$TargetPath
    )

    $baseFullPath = [System.IO.Path]::GetFullPath($BasePath).TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar
    $targetFullPath = [System.IO.Path]::GetFullPath($TargetPath)

    $baseUri = New-Object System.Uri($baseFullPath)
    $targetUri = New-Object System.Uri($targetFullPath)

    return [System.Uri]::UnescapeDataString(
        $baseUri.MakeRelativeUri($targetUri).ToString()
    ).Replace('\', '/')
}

if ([string]::IsNullOrWhiteSpace($Root)) {
    $scriptDirectory = ''

    if (-not [string]::IsNullOrWhiteSpace($PSScriptRoot)) {
        $scriptDirectory = $PSScriptRoot
    }
    elseif (
        $null -ne $MyInvocation.MyCommand -and
        -not [string]::IsNullOrWhiteSpace($MyInvocation.MyCommand.Path)
    ) {
        $scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
    }

    if ([string]::IsNullOrWhiteSpace($scriptDirectory)) {
        throw 'Cannot determine the script directory. Run with the -Root parameter.'
    }

    $Root = Split-Path -Parent $scriptDirectory
}

$rootPath = (Resolve-Path -LiteralPath $Root).Path
$outputPath = Join-Path $rootPath 'SOURCE_MANIFEST.sha256'

$excludedDirectoryNames = @('.git', 'vendor', 'node_modules')
$excludedRelativePaths = @(
    '.env',
    'SOURCE_MANIFEST.sha256',
    'database/database.sqlite'
)

$manifestLines = @(
    Get-ChildItem -LiteralPath $rootPath -File -Recurse | Where-Object {
        $relative = Get-PortableRelativePath -BasePath $rootPath -TargetPath $_.FullName
        $segments = $relative.Split('/')
        $hasExcludedDirectory = $false

        foreach ($segment in $segments) {
            if ($excludedDirectoryNames -contains $segment) {
                $hasExcludedDirectory = $true
                break
            }
        }

        (-not $hasExcludedDirectory) -and
        ($excludedRelativePaths -notcontains $relative) -and
        (-not $relative.EndsWith('.log', [System.StringComparison]::OrdinalIgnoreCase))
    } | Sort-Object FullName | ForEach-Object {
        $relative = Get-PortableRelativePath -BasePath $rootPath -TargetPath $_.FullName
        $hash = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        '{0}  {1}' -f $hash, $relative
    }
)

$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllLines($outputPath, $manifestLines, $utf8WithoutBom)

Write-Host ('Updated: {0}' -f $outputPath)
