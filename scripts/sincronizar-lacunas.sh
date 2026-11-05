#!/usr/bin/env bash
# STORY-062: copia os inventários de lacunas gerados em runtime (app/storage/app/private/lacunas/,
# visível no host porque ./app é montado no container) para a versão versionada em defonline-docs/.
# Idempotente: só acrescenta linhas que ainda não existem no destino.
set -euo pipefail

raiz="$(cd "$(dirname "$0")/.." && pwd)"
origem="$raiz/app/storage/app/private/lacunas"
epics="$raiz/defonline-docs/project-state/epics"

sincroniza() {
  local src="$origem/$1" dst="$2"
  [ -f "$src" ] || { echo "sem inventário em $src (nada registrado ainda)"; return 0; }
  mkdir -p "$(dirname "$dst")"
  [ -f "$dst" ] || cp "$src" "$dst"
  local novas=0
  while IFS= read -r linha; do
    grep -qxF -- "$linha" "$dst" || { printf '%s\n' "$linha" >> "$dst"; novas=$((novas + 1)); }
  done < <(grep '^- ' "$src" || true)
  echo "$dst: +$novas linha(s)"
}

sincroniza matriz-lacunas.md "$epics/EPIC-002-diagnostico-industria/design/matriz-lacunas.md"
sincroniza drm-conteudo-lacunas.md "$epics/EPIC-010-drm-demonstracao-resultado-mensal/design/conteudo-lacunas.md"
