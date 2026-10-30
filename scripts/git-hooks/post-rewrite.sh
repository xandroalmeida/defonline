#!/bin/sh
# Após pull/checkout/rebase: decifra secrets/ atualizados nos arquivos locais (nunca sobrescreve edição local).
bash "$(git rev-parse --show-toplevel)/scripts/segredos.sh" pull --hook || true
