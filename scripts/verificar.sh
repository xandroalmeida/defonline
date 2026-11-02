#!/usr/bin/env bash
# Portão local: os mesmos checks do .github/workflows/pr.yml, rodados na máquina local
# (o CI não roda mais em push na main; o /release roda `verificar.sh tudo` antes da tag).
#
#   verificar.sh            detecta pelo diff contra a origin/main (commits e árvore de trabalho)
#   verificar.sh php        Pint, Larastan, Pest com cobertura (≥80; ≥98 em app/Domain), Pennant, composer audit
#   verificar.sh dusk       build do front + Dusk E2E (Chromium do container web)
#   verificar.sh seguranca  sem pgAdmin no deploy, Gitleaks e Trivy (via docker)
#   verificar.sh tudo       os três (o que o /release exige)
#
# Precisa da stack local de pé (./up.sh): containers web, web-test, db e mailpit.
set -euo pipefail
ROOT=$(git -C "$(dirname "$0")" rev-parse --show-toplevel)
cd "$ROOT"

php=0; dusk=0; seg=0
case "${1:-auto}" in
  php) php=1 ;;
  dusk) dusk=1 ;;
  seguranca) seg=1 ;;
  tudo) php=1; dusk=1; seg=1 ;;
  auto)
    base=$(git merge-base origin/main HEAD 2>/dev/null || git rev-parse HEAD)
    mudou=$( { git diff --name-only "$base"; git ls-files --others --exclude-standard; } | sort -u)
    grep -qE '^app/' <<<"$mudou" && { php=1; dusk=1; }
    grep -qvE '^(defonline-docs/|docs/)|\.md$' <<<"$mudou" && seg=1
    ;;
  *) sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'; exit 2 ;;
esac

if [ "$php$dusk$seg" = 000 ]; then
  echo "verificar: só docs alteradas; nada a rodar"; exit 0
fi

inicio=$SECONDS
etapa() { echo "== [$((SECONDS - inicio))s] $*"; }
falhas=()
roda() { local nome=$1; shift; etapa "$nome"; "$@" || { echo "✖ $nome FALHOU"; falhas+=("$nome"); }; }

if [ "$php$dusk" != 00 ]; then
  for s in web web-test db mailpit; do
    docker compose ps --status running --services 2>/dev/null | grep -qx "$s" \
      || { echo "✖ container '$s' fora do ar — rode ./up.sh"; exit 1; }
  done
fi

ex()  { docker compose exec -T web "$@"; }
ext() { docker compose exec -T web-test "$@"; }

if [ "$php" = 1 ]; then
  roda "pint"       ex ./vendor/bin/pint --test
  roda "larastan"   ex ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
  # pest direto (não artisan test): o wrapper engole o exit code do --min. Cobertura da suíte All
  # estoura os 128M padrão do container.
  roda "pest + cobertura ≥80" ext php -d memory_limit=-1 ./vendor/bin/pest --testsuite=All --coverage --min=80
  if [ -n "$(find app/app/Domain -name '*.php' 2>/dev/null | head -1)" ]; then
    roda "pest app/Domain ≥98" ext php -d memory_limit=-1 ./vendor/bin/pest --configuration=phpunit-domain.xml --coverage --min=98
  fi
  roda "pennant overdue" ex php artisan pennant:list-overdue --fail-on-overdue
  roda "composer audit"  ex composer audit --locked
fi

if [ "$dusk" = 1 ]; then
  roda "npm build" ex sh -c 'npm run build >/dev/null'
  ex sh -c 'pgrep -x chromedriver >/dev/null || (chromedriver --port=9515 --whitelisted-ips="" >/tmp/chromedriver.log 2>&1 &)'
  sleep 2
  roda "dusk" ex php artisan dusk
fi

if [ "$seg" = 1 ]; then
  roda "sem pgAdmin no deploy" ./scripts/check-no-pgadmin-in-deploy.sh
  roda "gitleaks" docker run --rm -v "$ROOT:/repo" -w /repo zricethezav/gitleaks:latest \
    git --no-banner --redact --config .gitleaks.toml .
  roda "trivy fs" docker run --rm -v "$ROOT:/repo:ro" -v defonline-trivy-cache:/root/.cache/trivy \
    aquasec/trivy:latest fs --quiet --scanners vuln --severity HIGH,CRITICAL --ignore-unfixed --exit-code 1 \
    --skip-dirs app/vendor --skip-dirs app/node_modules --skip-dirs app/storage /repo
fi

if [ ${#falhas[@]} -gt 0 ]; then
  etapa "FALHOU: ${falhas[*]}"; exit 1
fi
etapa "ok"
