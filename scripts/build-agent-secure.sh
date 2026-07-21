#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
SOURCE_DIR="$PROJECT_ROOT/storage/app/agent-template/src"
TARGET_DIR="$PROJECT_ROOT/storage/app/agent-template"
REQUIRED_GO="1.26.5"

fail() {
    printf 'BŁĄD: %s\n' "$1" >&2
    exit 1
}

command -v go >/dev/null 2>&1 || fail "Nie znaleziono polecenia go."
[[ -f "$SOURCE_DIR/main.go" ]] || fail "Brak $SOURCE_DIR/main.go"

GO_VERSION_RAW="$(go env GOVERSION 2>/dev/null || true)"
GO_VERSION="${GO_VERSION_RAW#go}"
[[ -n "$GO_VERSION" ]] || fail "Nie udało się odczytać wersji Go."

if [[ "$(printf '%s\n%s\n' "$REQUIRED_GO" "$GO_VERSION" | sort -V | head -n1)" != "$REQUIRED_GO" ]]; then
    fail "Wymagane jest Go $REQUIRED_GO lub nowsze. Wykryto: $GO_VERSION_RAW"
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

printf 'Budowanie agenta przy użyciu %s...\n' "$GO_VERSION_RAW"
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 \
    go build -trimpath -ldflags='-s -w -X main.runMode=console' \
    -o "$TMP_DIR/PlacowkaOnlineAgentConsole.exe" "$SOURCE_DIR/main.go"

CGO_ENABLED=0 GOOS=windows GOARCH=amd64 \
    go build -trimpath -ldflags='-s -w -H=windowsgui -X main.runMode=service' \
    -o "$TMP_DIR/PlacowkaOnlineAgent.exe" "$SOURCE_DIR/main.go"

install -m 0640 "$TMP_DIR/PlacowkaOnlineAgent.exe" "$TARGET_DIR/PlacowkaOnlineAgent.exe"
install -m 0640 "$TMP_DIR/PlacowkaOnlineAgentConsole.exe" "$TARGET_DIR/PlacowkaOnlineAgentConsole.exe"

cat > "$TARGET_DIR/BUILD_INFO.txt" <<INFO
Placówka Online Agent exe-1.9.2

Źródło: storage/app/agent-template/src/main.go
Kompilator: $GO_VERSION_RAW
Target: windows/amd64
Data budowy UTC: $(date -u '+%Y-%m-%dT%H:%M:%SZ')

Pliki należy podpisać Authenticode przed szeroką dystrybucją produkcyjną.
INFO
chmod 0640 "$TARGET_DIR/BUILD_INFO.txt"

printf '\nSumy SHA-256:\n'
sha256sum \
    "$TARGET_DIR/PlacowkaOnlineAgent.exe" \
    "$TARGET_DIR/PlacowkaOnlineAgentConsole.exe"

printf '\nMetadane kompilatora zapisane w %s\n' "$TARGET_DIR/BUILD_INFO.txt"
printf 'Następny krok: wygenerowanie nowej paczki urządzenia, instalacja testowa i kontrola zużycia zasobów.\n'
