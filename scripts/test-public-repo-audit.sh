#!/usr/bin/env bash

set -Eeuo pipefail

REPOSITORY_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || {
    echo "BŁĄD: test musi zostać uruchomiony z repozytorium Git." >&2
    exit 2
}

AUDIT_SCRIPT="$REPOSITORY_ROOT/scripts/public-repo-audit.sh"

if [[ ! -f "$AUDIT_SCRIPT" ]]; then
    echo "BŁĄD: brak skryptu $AUDIT_SCRIPT" >&2
    exit 2
fi

TEMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TEMP_DIR"' EXIT

TEST_REPOSITORY="$TEMP_DIR/repository"

mkdir -p "$TEST_REPOSITORY/scripts"
cd "$TEST_REPOSITORY"

git init -q
git config user.name "Repository Audit Test"
git config user.email "repository-audit@example.invalid"

cp "$AUDIT_SCRIPT" scripts/public-repo-audit.sh
chmod +x scripts/public-repo-audit.sh

printf '%s\n' '# Test repository' > README.md

git add README.md scripts/public-repo-audit.sh
git commit -q -m "test: initialize temporary repository"

expect_success() {
    local description="$1"

    if ! bash scripts/public-repo-audit.sh >audit-output.txt 2>&1; then
        echo "BŁĄD TESTU: $description" >&2
        cat audit-output.txt >&2
        exit 1
    fi

    echo "OK: $description"
}

expect_failure() {
    local description="$1"

    if bash scripts/public-repo-audit.sh >audit-output.txt 2>&1; then
        echo "BŁĄD TESTU: audyt powinien zakończyć się błędem: $description" >&2
        cat audit-output.txt >&2
        exit 1
    fi

    echo "OK: $description"
}

#
# Test 1:
# Pliki utworzone lokalnie, ale nieśledzone przez Git,
# nie mogą powodować fałszywego alarmu.
#

printf '%s\n' 'APP_ENV=testing' > .env

mkdir -p database
touch database/database.sqlite

expect_success \
    "nieśledzone .env i database.sqlite są ignorowane"

#
# Test 2:
# Ten sam .env po dodaniu do indeksu Git musi zostać wykryty.
#

git add -f .env

expect_failure \
    "śledzony plik .env jest blokowany"

git reset -q HEAD -- .env
rm -f .env

#
# Test 3:
# Sekret zapisany w zwykłym śledzonym pliku musi zostać wykryty.
#

printf '%s\n' \
    'github_pat_TEST012345678901234567890123456789' \
    > leaked-secret.txt

git add leaked-secret.txt

expect_failure \
    "potencjalny token w śledzonym pliku jest blokowany"

echo
echo "Wszystkie testy audytu repozytorium zakończyły się powodzeniem."