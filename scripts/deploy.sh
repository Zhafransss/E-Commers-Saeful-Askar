#!/usr/bin/env bash
#
# Release on the shared hosting (branch `deployment`): run from anywhere on the server with
#
#   bash scripts/deploy.sh
#
# It takes the newest commit from GitHub, installs what composer.lock asks for, runs new migrations, and
# clears the caches. The built CSS/JS (public/build) comes with the commit, since the server has no Node.
# Nothing is touched that Git does not track: .env, storage/, vendor/ (rebuilt by composer).
set -euo pipefail

cd "$(dirname "$0")/.."
BRANCH=deployment

echo "== Mengambil kode terbaru dari GitHub"
git fetch origin "$BRANCH"

# A file edited by hand on the server would be lost or would block the update: say so instead of guessing.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo
    echo "Ada file di server yang berbeda dari Git (kemungkinan diedit manual atau terupload lewat File Manager):"
    git status --short --untracked-files=no
    echo
    echo "Kalau file-file itu memang boleh diganti dengan versi GitHub, jalankan: git checkout -- ."
    echo "Lalu ulangi: bash scripts/deploy.sh"
    exit 1
fi

BEFORE=$(git rev-parse --short HEAD)
git merge --ff-only "origin/$BRANCH"
AFTER=$(git rev-parse --short HEAD)

echo "== Memasang paket PHP (composer.lock)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "== Migrasi database (hanya yang baru)"
php artisan migrate --force

echo "== Membersihkan cache"
php artisan optimize:clear

echo
if [ "$BEFORE" = "$AFTER" ]; then
    echo "Selesai. Kode sudah yang terbaru: $(git log -1 --oneline)"
else
    echo "Selesai. $BEFORE -> $AFTER: $(git log -1 --oneline)"
fi
