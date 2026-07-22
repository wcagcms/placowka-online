#!/usr/bin/env bash
set -Eeuo pipefail

REMOTE="${1:-https://github.com/wcagcms/placowka-online.git}"

bash scripts/public-repo-audit.sh
php scripts/php-syntax-check.php

[[ ! -d .git ]] && git init -b main

git config user.name "${GIT_AUTHOR_NAME:-Adam Trojanowski}"
git config user.email "${GIT_AUTHOR_EMAIL:-it@it-serwis.net}"

git add .

if git diff --cached --quiet; then
    echo "Brak nowych zmian do zatwierdzenia."
else
    git commit -m "chore: initial public open-source release"
fi

if git remote get-url origin >/dev/null 2>&1; then
    git remote set-url origin "$REMOTE"
else
    git remote add origin "$REMOTE"
fi

echo "Origin: $(git remote get-url origin)"
echo "Sprawdź commit, a następnie wykonaj: git push -u origin main"
