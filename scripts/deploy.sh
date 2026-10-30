#!/usr/bin/env bash
#
# CoinBox deploy step for cPanel. Runs after every `git pull` (via .git/hooks/post-merge)
# or from cPanel "Deploy HEAD Commit" (via .cpanel.yml).
#
# The web server serves files from PUBLIC_DIR (a copy of ./public), so compiled assets
# must be copied there whenever they change.
#
#   PUBLIC_DIR  folder the domain serves     (default: ~/public_html/coinbox)
#   PHP         php binary to use           (default: php; e.g. /usr/local/bin/ea-php84)
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PUBLIC_DIR="${PUBLIC_DIR:-$HOME/public_html/coinbox}"
PHP="${PHP:-php}"

log() { printf '\033[32m[deploy]\033[0m %s\n' "$*"; }

if [ ! -d "$PUBLIC_DIR" ]; then
    echo "[deploy] PUBLIC_DIR $PUBLIC_DIR does not exist. Set PUBLIC_DIR=/path/to/served/folder" >&2
    exit 1
fi

if [ "$(cd "$PUBLIC_DIR" && pwd -P)" = "$(cd "$APP_DIR/public" && pwd -P)" ]; then
    log "Web root is the app's public folder; nothing to copy."
else
    log "Copying compiled assets to $PUBLIC_DIR/build"
    if command -v rsync >/dev/null 2>&1; then
        # --delete removes old hashed files so the folder doesn't grow forever
        rsync -a --delete "$APP_DIR/public/build/" "$PUBLIC_DIR/build/"
        # Other static files (icons, Filament assets, passkey script). Never overwrite the
        # server's own index.php / .htaccess, which point at this app.
        rsync -a --exclude 'index.php' --exclude '.htaccess' --exclude 'build/' --exclude 'storage' --exclude 'hot' \
            "$APP_DIR/public/" "$PUBLIC_DIR/"
    else
        rm -rf "$PUBLIC_DIR/build"
        cp -R "$APP_DIR/public/build" "$PUBLIC_DIR/build"
        (cd "$APP_DIR/public" && find . -mindepth 1 -maxdepth 1 ! -name index.php ! -name .htaccess ! -name build ! -name storage ! -name hot \
            -exec cp -R {} "$PUBLIC_DIR/" \;)
    fi
fi

cd "$APP_DIR"

if [ -f composer.lock ] && command -v composer >/dev/null 2>&1; then
    log "Installing PHP dependencies"
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --quiet
fi

log "Running database migrations"
"$PHP" artisan migrate --force

log "Refreshing caches"
"$PHP" artisan optimize:clear >/dev/null
"$PHP" artisan config:cache
"$PHP" artisan view:cache
"$PHP" artisan filament:optimize >/dev/null 2>&1 || true

log "Telling queue workers to reload the new code"
"$PHP" artisan queue:restart

log "Done. $(git -C "$APP_DIR" log -1 --pretty='%h %s' 2>/dev/null || true)"
