#!/usr/bin/env bash
# Gera o par GPG dedicado a backup off-site (ADR-005 §4.2).
#
# Modelo escolhido: cifra SIMÉTRICA com passphrase em arquivo (não par RSA).
# Justificativa: simétrico AES256 + passphrase forte = sem gerenciamento de keyring.
# O playbook backup.yml usa `gpg --symmetric --passphrase-file ~/.backup-gpg-pass`.
#
# Este script só gera a passphrase forte que vai para o Ansible Vault.

set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p secrets
chmod 700 secrets

if [ -f secrets/backup-gpg-passphrase ]; then
    echo "✖ secrets/backup-gpg-passphrase já existe. Remova antes de regerar."
    exit 1
fi

openssl rand -base64 48 > secrets/backup-gpg-passphrase
chmod 600 secrets/backup-gpg-passphrase

cat <<EOF
✓ Passphrase forte gerada em secrets/backup-gpg-passphrase

PROTOCOLO:
1. Guarde uma cópia desta passphrase em um cofre fora do projeto
   (1Password / Bitwarden / pen drive físico em local seguro).
   Se perder a passphrase, os backups B2 são irrecuperáveis (são bytes opacos
   para a Backblaze).

2. Coloque o valor em infra/ansible/inventories/homolog/group_vars/vault.yml:
     vault_backup_gpg_passphrase: $(cat secrets/backup-gpg-passphrase)

3. Cifre o vault:
     cd infra/ansible
     ansible-vault encrypt inventories/homolog/group_vars/vault.yml

4. ⚠ secrets/backup-gpg-passphrase nunca é comitado.
EOF
