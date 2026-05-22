#!/usr/bin/env bash
# Instala os git hooks do projeto (ADR-006 §Decisão 4).
# Idempotente — pode rodar quantas vezes precisar.
#
# Roda automaticamente via Composer `post-install-cmd` em `app/composer.json`.
# Também pode ser invocado manualmente: `./scripts/install-hooks.sh`.

set -euo pipefail

cd "$(dirname "$0")/.."

# Em CI ou container sem git repo, não falha — só pula.
if ! git rev-parse --git-dir >/dev/null 2>&1; then
    echo "→ install-hooks: não é um git repo, pulando."
    exit 0
fi

HOOKS_DIR="$(git rev-parse --git-path hooks)"
SOURCE="scripts/git-hooks/pre-push.sh"

if [ ! -f "$SOURCE" ]; then
    echo "→ install-hooks: $SOURCE não encontrado, pulando."
    exit 0
fi

mkdir -p "$HOOKS_DIR"
ln -sf "$(pwd)/$SOURCE" "$HOOKS_DIR/pre-push"
chmod +x "$SOURCE"

echo "✓ git hook 'pre-push' instalado em $HOOKS_DIR/pre-push → $SOURCE"
