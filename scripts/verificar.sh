#!/usr/bin/env bash
# Testes locais. No dia a dia, rode o mínimo; a suíte longa é só para o /release.
#
#   verificar.sh                 Pint, Larastan e os testes ligados ao que mudou (segundos)
#   verificar.sh area <caminho>  Pint, Larastan e os testes indicados (tests/Feature/Cobranca ...)
#   verificar.sh pest            Pint, Larastan e todo o Pest, sem cobertura (cerca de 3 min)
#   verificar.sh dusk [arquivo]  build + Dusk, sem o grupo `lento` (a digitação tecla a tecla)
#   verificar.sh seguranca       sem pgAdmin no deploy, Gitleaks e Trivy
#   verificar.sh tudo            portão do /release: cobertura (≥80; ≥98 em app/Domain), Pennant,
#                                composer audit, Dusk completo e segurança (cerca de 12 min)
#
# Rode o Dusk ou `tudo` só quando a tela mudou de forma que os testes de feature não pegam,
# ou no release. O Dusk roda no web-test: o servidor local segue usável durante os testes.
# Precisa da stack local de pé (./up.sh): containers web, web-test, db e mailpit.
set -euo pipefail
ROOT=$(git -C "$(dirname "$0")" rev-parse --show-toplevel)
cd "$ROOT"

modo=${1:-rapido}
[ $# -gt 0 ] && shift

# O Mac em repouso congela o Docker: um portão de 12 min já levou 50.
if [ "$modo" = tudo ] && [ -z "${VERIFICAR_ACORDADO:-}" ] && command -v caffeinate >/dev/null; then
  VERIFICAR_ACORDADO=1 exec caffeinate -i "$0" "$modo" "$@"
fi

case "$modo" in
  rapido|area|pest|dusk|seguranca|tudo) ;;
  *) sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'; exit 2 ;;
esac

inicio=$SECONDS
etapa() { echo "== [$((SECONDS - inicio))s] $*"; }
falhas=()
roda() { local nome=$1; shift; etapa "$nome"; "$@" || { echo "✖ $nome FALHOU"; falhas+=("$nome"); }; }

if [ "$modo" != seguranca ]; then
  for c in web web-test db mailpit; do
    docker compose ps --status running --services 2>/dev/null | grep -qx "$c" \
      || { echo "✖ container '$c' fora do ar — rode ./up.sh"; exit 1; }
  done
fi

ex()  { docker compose exec -T web "$@"; }
ext() { docker compose exec -T web-test "$@"; }

# Testes alterados e testes que citam uma classe alterada, olhando o que ainda não foi commitado.
testes_afetados() {
  git status --porcelain --untracked-files=all | awk '{print $NF}' | grep -E '^app/.*\.php$' | while read -r f; do
    [ -f "$f" ] || continue
    case "$f" in
      app/tests/Browser/*) ;;
      app/tests/*Test.php) echo "$f" ;;
      app/app/*) grep -rlw --include='*Test.php' --exclude-dir=Browser "$(basename "$f" .php)" app/tests || true ;;
    esac
  done | sort -u | sed 's#^app/##'
}

estaticos() {
  roda "pint"     ex ./vendor/bin/pint --test
  roda "larastan" ex ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
}

# Uma rodada só mede as duas coberturas: a geral aqui e a de app/Domain lendo o mesmo relatório.
cobertura() {
  roda "pest + cobertura ≥80" ext php -d memory_limit=-1 ./vendor/bin/pest --testsuite=All --coverage --min=80 --coverage-clover=/tmp/clover.xml
  roda "cobertura app/Domain ≥98" ext php scripts/cobertura-dominio.php /tmp/clover.xml 98
}

dusk() { # dusk [argumentos do artisan dusk]
  roda "npm build" ex sh -c 'npm run build >/dev/null'
  ext sh -c 'pgrep -x chromedriver >/dev/null || (chromedriver --port=9515 --whitelisted-ips="" >/tmp/chromedriver.log 2>&1 &)'
  sleep 2
  roda "dusk" ext env APP_URL=http://localhost:8000 DUSK_DRIVER_URL=http://localhost:9515 php artisan dusk "$@"
}

case "$modo" in
  rapido)
    estaticos
    alvos=$(testes_afetados)
    if [ -n "$alvos" ]; then
      # shellcheck disable=SC2086
      roda "pest ($(wc -w <<<"$alvos" | tr -d ' ') arquivos afetados)" ext ./vendor/bin/pest $alvos
    else
      echo "nenhum teste ligado às alterações; use 'verificar.sh area <caminho>' se precisar"
    fi
    ;;
  area)
    [ $# -gt 0 ] || { echo "informe o caminho: verificar.sh area tests/Feature/Cobranca"; exit 2; }
    estaticos
    roda "pest ($*)" ext ./vendor/bin/pest "$@"
    ;;
  pest)
    estaticos
    roda "pest" ext php -d memory_limit=-1 ./vendor/bin/pest --testsuite=All
    ;;
  dusk)
    dusk --exclude-group=lento "$@"
    ;;
  tudo)
    estaticos
    cobertura
    roda "pennant overdue" ex php artisan pennant:list-overdue --fail-on-overdue
    roda "composer audit"  ex composer audit --locked
    dusk
    ;;
esac

if [ "$modo" = seguranca ] || [ "$modo" = tudo ]; then
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
