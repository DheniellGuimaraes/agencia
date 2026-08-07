#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT_DIR/mercado-pago-agencia-privilege"
DIST_DIR="$ROOT_DIR/dist"
ZIP_FILE="$DIST_DIR/mercado-pago-agencia-privilege.zip"

if [[ ! -d "$PLUGIN_DIR" ]]; then
  echo "Plugin directory not found: $PLUGIN_DIR" >&2
  exit 1
fi

mkdir -p "$DIST_DIR"
rm -f "$ZIP_FILE"
(
  cd "$ROOT_DIR"
  zip -qr "$ZIP_FILE" mercado-pago-agencia-privilege \
    -x 'mercado-pago-agencia-privilege/tests/*' \
    -x 'mercado-pago-agencia-privilege/docs/auditoria-tecnica-3.7.0.md'
)
echo "Built $ZIP_FILE"
