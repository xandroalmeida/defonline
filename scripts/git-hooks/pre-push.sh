#!/usr/bin/env bash
# DEFOnline — pre-push hook (ADR-006 §Decisão 4 — revisado 2026-08 p/ agilidade).
#
# Gate LOCAL, agora PROPORCIONAL ao diff e ENXUTO:
#  - Push que toca só infra/docs/scripts/workflows (nada em app/) → passa direto,
#    sem exigir a stack de pé. (Antes: rodava a suíte inteira até para mexer em YAML.)
#  - Push com mudança em código → gate RÁPIDO: Pint + Larastan + Pest (SEM cobertura)
#    + Pennant. Feedback local em segundos.
#  - Cobertura (≥80/≥98) e Dusk NÃO rodam mais aqui: migraram para o CI (pr.yml, jobs
#    `test-coverage` e `test-dusk`), onde rodam em paralelo e fora da sua máquina. A
#    cobertura já era duplicada com o CI; o Dusk (browser, lento/flaky) saiu do caminho
#    crítico local. O CI continua sendo o gate de merge que segura tudo isso.
#
# `git push --no-verify` continua proibido por política salvo emergência (ADR-006 §1.2).
# Pré-requisito (só quando há mudança em app/): stack `./up.sh` de pé.

set -euo pipefail

cd "$(dirname "$0")/../.."

bold() { printf '\033[1m%s\033[0m\n' "$1"; }
red()  { printf '\033[31m%s\033[0m\n' "$1"; }
grn()  { printf '\033[32m%s\033[0m\n' "$1"; }
ylw()  { printf '\033[33m%s\033[0m\n' "$1"; }

bold "▶ pre-push hook do DEFOnline"

# ── Proporcional ao diff: o que está saindo neste push? ─────────────────────
# Base = origin/main do último fetch. Sem base (repo novo) → roda o gate por segurança.
if git rev-parse --verify --quiet origin/main >/dev/null 2>&1; then
    changed="$(git diff --name-only origin/main HEAD 2>/dev/null || true)"
else
    changed="__no_base__"
fi

# Caminhos que NÃO exigem gate PHP (infra, docs, tooling, workflows, qualquer *.md).
skip_re='^(infra/|defonline-docs/|docs/|scripts/|\.github/)|(^|/)[^/]*\.md$'

needs_gate=0
if [ "$changed" = "__no_base__" ]; then
    needs_gate=1                        # sem base p/ comparar → não arrisca
elif [ -z "$changed" ]; then
    needs_gate=0                        # diff vazio (ex.: push de tag já validada) → skip
else
    while IFS= read -r f; do
        [ -z "$f" ] && continue
        if ! printf '%s\n' "$f" | grep -Eq "$skip_re"; then
            needs_gate=1                # achou arquivo fora do skip-list → roda gate
            break
        fi
    done <<< "$changed"
fi

if [ "$needs_gate" -eq 0 ]; then
    grn "  ✓ push sem mudança em código (só infra/docs/tooling) — gate PHP dispensado."
    bold "✔ pre-push verde (fast-path)."
    exit 0
fi

# ── Gate rápido (precisa da stack) ──────────────────────────────────────────
if ! docker compose ps --status running --services 2>/dev/null | grep -q '^web$'; then
    red "✖ Container 'web' não está rodando. Execute ./up.sh antes do push."
    exit 1
fi

step() {
    local name="$1"; shift
    bold "→ $name"
    if "$@"; then
        grn "  ✓ $name ok"
    else
        red "  ✖ $name FALHOU — push abortado"
        exit 1
    fi
}

step "Pint (lint)"  docker compose exec -T web ./vendor/bin/pint --test
step "Larastan"     docker compose exec -T web ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
# Pest SEM cobertura: exit code confiável (o swallow do exit code era só com o plugin
# de cobertura do Pest 4.7 + --min). A cobertura ≥80/≥98 é enforced no CI (pr.yml).
step "Pest (All, sem cobertura)"  docker compose exec -T web php -d memory_limit=-1 ./vendor/bin/pest --testsuite=All
step "Pennant overdue"  docker compose exec -T web php artisan pennant:list-overdue --fail-on-overdue

bold "✔ pre-push verde — pode dar push."
