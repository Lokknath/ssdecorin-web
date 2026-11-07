#!/usr/bin/env bash
# ======================================================================
# deploy-milesweb.sh — Automated Deployment to MilesWeb for ssdecorin.com
# Reads credentials from site/.env or accepts environment variables
# ======================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# 1. Load credentials from .env
ENV_FILE="$SCRIPT_DIR/.env"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Error: .env file not found at $ENV_FILE"
  exit 1
fi

# Extract milesweb_ssh and milesweb_pass from .env safely
SSH_CMD=$(grep -E "^milesweb_ssh=" "$ENV_FILE" | cut -d'=' -f2- | tr -d '\r"' || true)
SSH_PASS=$(grep -E "^milesweb_pass=" "$ENV_FILE" | cut -d'=' -f2- | tr -d '\r"' || true)
REMOTE_PATH=$(grep -E "^REMOTE_PATH=" "$ENV_FILE" | cut -d'=' -f2- | tr -d '\r"' || echo "site")

if [[ -z "$SSH_CMD" || -z "$SSH_PASS" ]]; then
  echo "Error: milesweb_ssh or milesweb_pass not set in $ENV_FILE"
  exit 1
fi

# Parse SSH_CMD (e.g., 'ssh -p 22 nsquare_1@45.199.139.26')
PORT="22"
if [[ "$SSH_CMD" =~ -p[[:space:]]+([0-9]+) ]]; then
  PORT="${BASH_REMATCH[1]}"
fi

USER_HOST=$(echo "$SSH_CMD" | awk '{print $NF}')
USER=$(echo "$USER_HOST" | cut -d'@' -f1)
HOST=$(echo "$USER_HOST" | cut -d'@' -f2)

echo "=========================================================="
echo "  Deploying Sai Sreenivasa Decorin -> MilesWeb"
echo "  Target Domain:  ssdecorin.com"
echo "  Host:           $HOST"
echo "  User:           $USER"
echo "  Port:           $PORT"
echo "  Remote Path:    $REMOTE_PATH"
echo "=========================================================="

# 2. Package backup ZIP
echo "📦 Packaging milesweb-deploy.zip archive..."
rm -f milesweb-deploy.zip
zip -rq milesweb-deploy.zip . \
  -x ".git/*" \
  -x ".env*" \
  -x ".DS_Store" \
  -x "logs/*" \
  -x "milesweb-deploy.zip" \
  -x "deploy-milesweb.sh"

echo "✓ Created milesweb-deploy.zip ($(du -h milesweb-deploy.zip | cut -f1))"

# 3. Rsync files using expect
echo "🚀 Syncing files to MilesWeb server via SSH..."
/usr/bin/expect <<EOF
set timeout 60
spawn rsync -avz --delete -e "ssh -p $PORT -o StrictHostKeyChecking=no" \
  --exclude ".env*" \
  --exclude ".git" \
  --exclude ".DS_Store" \
  --exclude "logs" \
  --exclude "deploy-milesweb.sh" \
  ./ "$USER@$HOST:$REMOTE_PATH/"
expect {
  "password:" {
    send "$SSH_PASS\r"
    exp_continue
  }
  eof
}
catch wait result
exit [lindex \$result 3]
EOF

echo "✓ Rsync sync complete!"

# 4. Verify remote files
echo "🔍 Verifying remote deployment..."
/usr/bin/expect <<EOF
set timeout 15
spawn ssh -p $PORT -o StrictHostKeyChecking=no "$USER@$HOST" "ls -la $REMOTE_PATH | head -n 15"
expect {
  "password:" {
    send "$SSH_PASS\r"
    exp_continue
  }
  eof
}
EOF

echo ""
echo "=========================================================="
echo "  🎉 Deployment successfully published to MilesWeb!"
echo "  Site URL: https://ssdecorin.com"
echo "=========================================================="
