#!/usr/bin/env bash
# Run once on the server: makes every `git pull` run scripts/deploy.sh automatically.
set -euo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HOOK="$APP_DIR/.git/hooks/post-merge"

cat > "$HOOK" <<HOOK
#!/usr/bin/env bash
# Installed by scripts/install-deploy-hook.sh
PUBLIC_DIR="\${PUBLIC_DIR:-${PUBLIC_DIR:-\$HOME/public_html/coinbox}}" PHP="\${PHP:-${PHP:-php}}" "$APP_DIR/scripts/deploy.sh"
HOOK
chmod +x "$HOOK"
echo "Installed $HOOK. Every 'git pull' now runs scripts/deploy.sh."
