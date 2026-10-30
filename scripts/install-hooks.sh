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
mkdir -p "$HOOKS_DIR"

# pre-push: gates locais (ADR-006 §Decisão 4). pre-commit + post-*: segredos cifrados (scripts/segredos.sh).
for HOOK in pre-push pre-commit post-checkout post-merge post-rewrite; do
    SOURCE="scripts/git-hooks/${HOOK}.sh"
    [ -f "$SOURCE" ] || continue
    chmod +x "$SOURCE"
    ln -sf "$(pwd)/$SOURCE" "$HOOKS_DIR/$HOOK"
    echo "✓ git hook '$HOOK' instalado em $HOOKS_DIR/$HOOK → $SOURCE"
done
