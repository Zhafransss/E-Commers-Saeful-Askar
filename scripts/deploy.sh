#!/usr/bin/env bash
#
# Release on the shared hosting (branch `deployment`): run from anywhere on the server with
#
#   bash scripts/deploy.sh
#
# It takes the newest commit from GitHub, installs what composer.lock asks for, runs new migrations, and
# clears the caches, and copies public/ to DEPLOY_WEB_ROOT when .env sets one. The built CSS/JS (public/build)
# comes with the commit, since the server has no Node.
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

# Composer: the system one, or composer.phar in the home folder (hosts without a global composer).
if command -v composer >/dev/null 2>&1; then
    COMPOSER="composer"
elif [ -f "$HOME/composer.phar" ]; then
    COMPOSER="php $HOME/composer.phar"
else
    COMPOSER=""
fi

if [ -n "$COMPOSER" ]; then
    echo "== Memasang paket PHP (composer.lock)"
    # The scripts are skipped (hosts often block proc_open); package:discover is the only one that matters here.
    $COMPOSER install --no-dev --optimize-autoloader --no-interaction --no-scripts
    php artisan package:discover --ansi
elif [ "$BEFORE" != "$AFTER" ] && ! git diff --quiet "$BEFORE" "$AFTER" -- composer.lock; then
    echo
    echo "composer.lock berubah, tetapi composer tidak ada di server, jadi folder vendor tidak bisa diperbarui."
    echo "Pasang composer.phar di folder home (lihat docs/deployment-shared-hosting.md), lalu ulangi: bash scripts/deploy.sh"
    exit 1
else
    echo "== Composer tidak ada; composer.lock tidak berubah, jadi vendor/ dipakai apa adanya"
fi

echo "== Migrasi database (hanya yang baru)"
php artisan migrate --force

echo "== Membersihkan cache"
php artisan optimize:clear

# Hosting whose document root cannot be set to public/ (cPanel on Rumahweb): the site's folder holds copies of
# the public files. DEPLOY_WEB_ROOT in .env names that folder; the copy follows every release so the built
# CSS/JS (public/build) is the new one. index.php and .htaccess there are set up once by hand and left alone.
WEB_ROOT=$(grep -E '^DEPLOY_WEB_ROOT=' .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'" || true)
if [ -n "$WEB_ROOT" ]; then
    echo "== Menyalin public/ ke $WEB_ROOT"
    mkdir -p "$WEB_ROOT"
    rm -rf "$WEB_ROOT/build" "$WEB_ROOT/images"
    cp -a public/build public/images "$WEB_ROOT/"
    cp -a public/favicon* public/apple-touch-icon.png "$WEB_ROOT/"
fi

echo
if [ "$BEFORE" = "$AFTER" ]; then
    echo "Selesai. Kode sudah yang terbaru: $(git log -1 --oneline)"
else
    echo "Selesai. $BEFORE -> $AFTER: $(git log -1 --oneline)"
fi
