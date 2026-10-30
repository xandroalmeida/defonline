#!/usr/bin/env bash
# STORY-009 CA-10 — regressão arquitetural.
#
# PhpPgAdmin (e equivalentes: pgAdmin4, Adminer, DbGate, ...) é DEV LOCAL APENAS.
# Nunca pode aparecer no compose que vai para o servidor (`infra/zalk/`, ADR-009).
# O `docker-compose.yml` da raiz é o de desenvolvimento e fica de fora.
#
# Falha (exit != 0) se encontrar qualquer ocorrência.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEPLOY_DIR="${ROOT}/infra/zalk"

PATTERN='pgadmin|phppgadmin|adminer|dbgate'

MATCHES="$(grep -RinE "${PATTERN}" "${DEPLOY_DIR}" 2>/dev/null || true)"

if [ -n "${MATCHES}" ]; then
    echo "❌ STORY-009 CA-10 — ferramenta administrativa de banco detectada no compose do servidor:"
    echo ""
    echo "${MATCHES}"
    echo ""
    echo "PhpPgAdmin / pgAdmin / Adminer / DbGate são DEV LOCAL APENAS. Ver STORY-009 e ADR-009."
    exit 1
fi

echo "✅ STORY-009 CA-10 — nenhuma referência a pgadmin/phppgadmin/adminer/dbgate em ${DEPLOY_DIR}."
