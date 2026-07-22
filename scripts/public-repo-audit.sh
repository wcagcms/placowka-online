#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    echo "BŁĄD: skrypt musi zostać uruchomiony wewnątrz repozytorium Git." >&2
    exit 2
}

cd "$ROOT_DIR"

declare -a VIOLATIONS=()
TRACKED_FILES_COUNT=0

add_violation() {
    VIOLATIONS+=("$1")
}

is_allowed_env_template() {
    local filename="$1"

    case "$filename" in
        .env.example \
        | .env.example.* \
        | .env.dist \
        | .env.*.example \
        | .env.*.dist)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

echo "Audyt publicznego repozytorium..."
echo "Zakres: wyłącznie pliki śledzone przez Git."

#
# 1. Kontrola nazw plików śledzonych przez Git
#

while IFS= read -r -d '' file; do
    TRACKED_FILES_COUNT=$((TRACKED_FILES_COUNT + 1))

    filename="${file##*/}"
    lowercase_filename="${filename,,}"

    #
    # Prawdziwe pliki środowiskowe są zabronione.
    # Dozwolone są wyłącznie szablony .example i .dist.
    #
    if [[ "$filename" == ".env" || "$filename" == .env.* ]]; then
        if ! is_allowed_env_template "$filename"; then
            add_violation "Zabroniony plik środowiskowy: $file"
        fi
    fi

    #
    # Lokalne bazy SQLite nie powinny być publikowane.
    #
    case "$lowercase_filename" in
        *.sqlite | *.sqlite3)
            add_violation "Zabroniony plik bazy danych: $file"
            ;;
    esac

    #
    # Prywatne magazyny kluczy i certyfikatów.
    #
    case "$lowercase_filename" in
        *.key | *.p12 | *.pfx | *.jks | *.keystore)
            add_violation "Potencjalny prywatny klucz lub magazyn certyfikatów: $file"
            ;;

        id_rsa | id_dsa | id_ecdsa | id_ed25519)
            add_violation "Potencjalny prywatny klucz SSH: $file"
            ;;
    esac
done < <(git ls-files --cached -z)

#
# 2. Kontrola zawartości plików śledzonych przez Git
#
# git grep sprawdza wyłącznie pliki należące do repozytorium.
# Skrypt audytu i jego test są wyłączone, ponieważ zawierają
# wzorce sekretów jako dane kontrolne.
#

scan_pattern() {
    local description="$1"
    local pattern="$2"
    local matches=""
    local status=0

    matches="$(
        git grep \
            -I \
            -n \
            -E \
            -e "$pattern" \
            -- \
            . \
            ':(exclude)scripts/public-repo-audit.sh' \
            ':(exclude)scripts/test-public-repo-audit.sh' \
            2>/dev/null
    )" || status=$?

    case "$status" in
        0)
            add_violation "$description:"
            while IFS= read -r match; do
                [[ -n "$match" ]] && add_violation "  $match"
            done <<< "$matches"
            ;;

        1)
            # Brak dopasowań — prawidłowy wynik.
            ;;

        *)
            echo "BŁĄD: git grep zakończył się kodem $status." >&2
            exit "$status"
            ;;
    esac
}

scan_pattern \
    "Wykryto prywatny klucz kryptograficzny" \
    '-----BEGIN (RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----'

scan_pattern \
    "Wykryto potencjalny token GitHub" \
    '(github_pat_[A-Za-z0-9_]{20,}|gh[pousr]_[A-Za-z0-9_]{20,})'

scan_pattern \
    "Wykryto potencjalny klucz AWS Access Key ID" \
    'AKIA[0-9A-Z]{16}'

scan_pattern \
    "Wykryto ustawiony klucz Laravel APP_KEY" \
    'APP_KEY=base64:[A-Za-z0-9+/]{40,}={0,2}'

#
# 3. Wynik
#

if (( ${#VIOLATIONS[@]} > 0 )); then
    echo
    echo "BŁĄD: audyt publicznego repozytorium wykrył problemy:"
    echo

    for violation in "${VIOLATIONS[@]}"; do
        printf ' - %s\n' "$violation"
    done

    echo
    echo "Usuń wskazane dane z indeksu Git i historii przed publikacją."
    exit 1
fi

echo
echo "OK: nie wykryto zabronionych plików ani oczywistych sekretów."
echo "Sprawdzono plików śledzonych przez Git: $TRACKED_FILES_COUNT"