#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_PATH="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
MIN_GO_VERSION="${PLACOWKA_AGENT_MINIMUM_GO_VERSION:-1.26.5}"
SETUP_VERSION="${PLACOWKA_AGENT_SETUP_VERSION:-1.0.5}"
AGENT_VERSION="exe-1.9.2"

version_ge() {
    [ "$(printf '%s\n%s\n' "$2" "$1" | sort -V | head -n1)" = "$2" ]
}

cd "$PROJECT_PATH"

command -v go >/dev/null 2>&1 || {
    echo "BŁĄD: Brak kompilatora Go." >&2
    exit 1
}

GO_VERSION="$(go env GOVERSION | sed 's/^go//')"
if ! version_ge "$GO_VERSION" "$MIN_GO_VERSION"; then
    echo "BŁĄD: Go $GO_VERSION jest zbyt stare. Wymagane: $MIN_GO_VERSION lub nowsze." >&2
    exit 1
fi

TEMPLATE_DIR="storage/app/agent-template"
INSTALLER_DIR="storage/app/agent-installer"
BUILD_DIR="$INSTALLER_DIR/build"
INPUT_DIR="$INSTALLER_DIR/inno/build-input"
SOURCE_DIR="$INSTALLER_DIR/src/enroll"

for file in \
    "$TEMPLATE_DIR/PlacowkaOnlineAgent.exe" \
    "$TEMPLATE_DIR/PlacowkaOnlineAgentConsole.exe" \
    "$TEMPLATE_DIR/BUILD_INFO.txt" \
    "$SOURCE_DIR/main.go" \
    "$SOURCE_DIR/go.mod" \
    "$INSTALLER_DIR/inno/PlacowkaOnlineSetup.iss"; do
    [ -f "$file" ] || {
        echo "BŁĄD: Brakuje pliku: $file" >&2
        exit 1
    }
done

if ! grep -Eq "Kompilator: go(1\.26\.[5-9]|1\.2[7-9]\.|[2-9]\.)" "$TEMPLATE_DIR/BUILD_INFO.txt"; then
    echo "BŁĄD: BUILD_INFO.txt nie potwierdza agenta zbudowanego Go 1.26.5 lub nowszym." >&2
    echo "Najpierw uruchom scripts/build-agent-secure.sh." >&2
    exit 1
fi

rm -rf "$BUILD_DIR" "$INPUT_DIR"
mkdir -p "$BUILD_DIR" "$INPUT_DIR"

(
    cd "$SOURCE_DIR"
    CGO_ENABLED=0 GOOS=windows GOARCH=amd64 \
        go build \
        -trimpath \
        -ldflags "-s -w -X main.buildVersion=$SETUP_VERSION" \
        -o "$PROJECT_PATH/$BUILD_DIR/PlacowkaOnlineEnroll.exe" \
        .
)

cp "$TEMPLATE_DIR/PlacowkaOnlineAgent.exe" "$INPUT_DIR/"
cp "$TEMPLATE_DIR/PlacowkaOnlineAgentConsole.exe" "$INPUT_DIR/"
cp "$TEMPLATE_DIR/BUILD_INFO.txt" "$INPUT_DIR/"
cp "$BUILD_DIR/PlacowkaOnlineEnroll.exe" "$INPUT_DIR/"

cat > "$INSTALLER_DIR/inno/BUILD_WINDOWS.cmd" <<'CMD'
@echo off
setlocal EnableExtensions
cd /d "%~dp0"

set "ISCC="
if exist "%ProgramFiles%\Inno Setup 7\ISCC.exe" set "ISCC=%ProgramFiles%\Inno Setup 7\ISCC.exe"
if exist "%ProgramFiles(x86)%\Inno Setup 7\ISCC.exe" set "ISCC=%ProgramFiles(x86)%\Inno Setup 7\ISCC.exe"
if exist "%ProgramFiles%\Inno Setup 6\ISCC.exe" set "ISCC=%ProgramFiles%\Inno Setup 6\ISCC.exe"
if exist "%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe" set "ISCC=%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe"

if not defined ISCC (
  echo BLAD: Nie znaleziono ISCC.exe. Zainstaluj Inno Setup 6.7 lub nowsze.
  exit /b 1
)

"%ISCC%" "PlacowkaOnlineSetup.iss"
if errorlevel 1 exit /b %errorlevel%

echo.
echo Gotowe: %CD%\output\PlacowkaOnlineSetup.exe
certutil -hashfile "%CD%\output\PlacowkaOnlineSetup.exe" SHA256
endlocal
CMD

cat > "$INSTALLER_DIR/inno/README_BUILD.txt" <<EOF
Placówka Online — budowa stałego instalatora

1. Ten katalog zawiera gotowe pliki build-input utworzone na serwerze Go $GO_VERSION.
2. Skopiuj cały katalog inno na zabezpieczony komputer Windows.
3. Zainstaluj Inno Setup 6.7 lub nowsze.
4. Uruchom BUILD_WINDOWS.cmd.
5. Podpisz output\\PlacowkaOnlineSetup.exe certyfikatem Authenticode.
6. Prześlij podpisany plik na serwer i uruchom:
   scripts/publish-agent-installer.sh /ścieżka/PlacowkaOnlineSetup.exe

Wersja instalatora: $SETUP_VERSION
Wersja agenta: $AGENT_VERSION
EOF

ARCHIVE="$BUILD_DIR/PlacowkaOnlineInstaller-BuildInput-$SETUP_VERSION.zip"
(
    cd "$INSTALLER_DIR"
    zip -qr "$PROJECT_PATH/$ARCHIVE" inno
)

sha256sum "$BUILD_DIR/PlacowkaOnlineEnroll.exe" "$ARCHIVE" > "$BUILD_DIR/SHA256SUMS.txt"

echo "Gotowe."
echo "Moduł Go: $BUILD_DIR/PlacowkaOnlineEnroll.exe"
echo "Paczka do kompilacji Inno Setup: $ARCHIVE"
echo "Go: $GO_VERSION"
