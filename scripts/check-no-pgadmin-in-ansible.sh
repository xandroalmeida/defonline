#!/usr/bin/env bash
# STORY-009 CA-10 — regressão arquitetural.
#
# PhpPgAdmin (e equivalentes: pgAdmin4, Adminer, DbGate, ...) é DEV LOCAL APENAS.
# Nunca pode aparecer em playbook/role/inventário Ansible que rode contra os
# inventários `homolog` ou `production`. Ver ADR-005 §1.1, §6, §7.5 e a STORY-009.
#
# A pasta `inventories/dev/` (se um dia existir) é exceção explícita.
#
# Falha (exit != 0) se encontrar qualquer ocorrência fora da exceção.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ANSIBLE_DIR="${ROOT}/infra/ansible"

if [ ! -d "${ANSIBLE_DIR}" ]; then
    echo "OK — ${ANSIBLE_DIR} não existe ainda; nada a verificar."
    exit 0
fi

PATTERN='pgadmin\|phppgadmin\|adminer\|dbgate'

# Procura case-insensitive, excluindo a pasta de exceção inventories/dev/.
MATCHES="$(grep -RinE --exclude-dir='dev' "${PATTERN}" "${ANSIBLE_DIR}" 2>/dev/null || true)"

if [ -n "${MATCHES}" ]; then
    echo "❌ STORY-009 CA-10 — ferramenta administrativa de banco detectada em código Ansible:"
    echo ""
    echo "${MATCHES}"
    echo ""
    echo "PhpPgAdmin / pgAdmin / Adminer / DbGate são DEV LOCAL APENAS."
    echo "Não podem ser instanciados em playbooks que rodem contra homol/prod."
    echo "Ver ADR-005 §1.1, §6, §7.5 e STORY-009."
    exit 1
fi

echo "✅ STORY-009 CA-10 — nenhuma referência a pgadmin/phppgadmin/adminer/dbgate em ${ANSIBLE_DIR}."
