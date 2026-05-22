#!/usr/bin/env bash
# Gera o par SSH dedicado ao deploy (ADR-005 §2.3 + ADR-006 §2.3).
#
# Saída:
#   ./secrets/deploy-homolog.key       (chave privada — NUNCA comitar)
#   ./secrets/deploy-homolog.key.pub   (chave pública — vai para ~/.ssh/authorized_keys do deploy@VPS)
#   ./secrets/deploy-homolog.known_hosts  (preencher depois com ssh-keyscan)
#
# Depois:
#   - Copie a pública para a VPS: ssh-copy-id -i secrets/deploy-homolog.key root@<ip>
#       OU adicione em /home/deploy/.ssh/authorized_keys manualmente.
#   - Adicione a privada como GitHub Secret DEPLOY_SSH_KEY (cat secrets/deploy-homolog.key).
#   - Gere known_hosts: ssh-keyscan -p 22 -H <ip> > secrets/deploy-homolog.known_hosts
#   - Adicione known_hosts como GitHub Secret DEPLOY_SSH_KNOWN_HOSTS.

set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p secrets
chmod 700 secrets

if [ -f secrets/deploy-homolog.key ]; then
    echo "✖ secrets/deploy-homolog.key já existe. Remova antes de regerar (operação destrutiva!)."
    exit 1
fi

ssh-keygen \
    -t ed25519 \
    -C "deploy@defonline-homolog" \
    -f secrets/deploy-homolog.key \
    -N "" \
    -q

chmod 600 secrets/deploy-homolog.key

cat <<EOF
✓ Par gerado em secrets/deploy-homolog.key{,.pub}

Próximos passos:
1. Subir a chave pública na VPS (uma vez):
     ssh-copy-id -i secrets/deploy-homolog.key.pub root@<VPS_IP>
   OU manualmente:
     cat secrets/deploy-homolog.key.pub  # copia o conteúdo
     ssh root@<VPS_IP>
       mkdir -p /home/deploy/.ssh && chmod 700 /home/deploy/.ssh
       echo "<conteúdo>" >> /home/deploy/.ssh/authorized_keys
       chmod 600 /home/deploy/.ssh/authorized_keys
       chown -R deploy:deploy /home/deploy/.ssh

2. Gerar known_hosts (depende de a VPS estar acessível):
     ssh-keyscan -p 22 -H <VPS_IP> > secrets/deploy-homolog.known_hosts

3. Adicionar no GitHub (Settings → Secrets → Actions):
     DEPLOY_SSH_KEY            = conteúdo de secrets/deploy-homolog.key (chave PRIVADA)
     DEPLOY_SSH_KNOWN_HOSTS    = conteúdo de secrets/deploy-homolog.known_hosts

⚠ secrets/ está no .gitignore (.env e .env.* já são ignorados; padrão `secrets/*` adicionado).
EOF
