#!/usr/bin/env bash
# DEV LOCAL: expõe só POST /webhooks/pagamentos por um túnel Cloudflare e cadastra o webhook
# "DEFOnline Local" no Asaas sandbox. A URL muda a cada subida; o script atualiza o cadastro.
# A conta de sandbox é compartilhada: este script só toca no webhook com esse nome.
#
#   scripts/tunel-webhook.sh up      sobe o túnel e cria/atualiza o webhook
#   scripts/tunel-webhook.sh down    apaga o webhook e derruba o túnel
#   scripts/tunel-webhook.sh status  mostra URL e estado no Asaas
set -euo pipefail
cd "$(dirname "$0")/.."

NOME="DEFOnline Local"
BASE="https://api-sandbox.asaas.com/v3"
DC=(docker compose -f docker-compose.yml -f infra/tunel-webhook/docker-compose.yml)
EVENTOS='["PAYMENT_CREATED","PAYMENT_CONFIRMED","PAYMENT_RECEIVED","PAYMENT_OVERDUE","PAYMENT_REFUNDED","PAYMENT_PARTIALLY_REFUNDED","PAYMENT_REFUND_DENIED","PAYMENT_DELETED","PAYMENT_RESTORED","PAYMENT_CHARGEBACK_REQUESTED","PAYMENT_CHARGEBACK_DISPUTE","PAYMENT_AWAITING_CHARGEBACK_REVERSAL","PAYMENT_CREDIT_CARD_CAPTURE_REFUSED","PAYMENT_REPROVED_BY_RISK_ANALYSIS","CHECKOUT_PAID","CHECKOUT_EXPIRED","CHECKOUT_CANCELED"]'

valor() { # lê uma variável do app/.env sem imprimir nem executar o arquivo
	sed -nE "s/^$1=['\"]?([^'\"]*)['\"]?\$/\1/p" app/.env | tail -n1
}
CHAVE=$(valor ASAAS_SANDBOX_API_KEY)
TOKEN=$(valor ASAAS_WEBHOOK_TOKEN)
case "$CHAVE" in *aact_hmlg_*) ;; *) echo "ASAAS_SANDBOX_API_KEY ausente ou não é de sandbox" >&2; exit 2 ;; esac

api() { curl -sS -m 30 -X "$1" "$BASE$2" -H "access_token: $CHAVE" -H "User-Agent: defonline-local" -H "Content-Type: application/json" ${3:+-d "$3"}; }
webhook_id() { api GET "/webhooks?limit=100" | jq -r --arg n "$NOME" '[.data[]? | select(.name == $n) | .id][0] // empty'; }
url_tunel() { "${DC[@]}" logs webhook-tunnel 2>&1 | grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' | tail -n1 || true; }

case "${1:-}" in
up)
	[ "${#TOKEN}" -ge 32 ] || { echo "ASAAS_WEBHOOK_TOKEN ausente em app/.env" >&2; exit 2; }
	echo "[1/3] subindo túnel"
	"${DC[@]}" up -d --force-recreate webhook-gate webhook-tunnel 2>&1 | tail -n2
	echo "[2/3] aguardando URL pública (até 60s)"
	URL=""
	for i in $(seq 1 30); do URL=$(url_tunel); [ -n "$URL" ] && break; sleep 2; done
	[ -n "$URL" ] || { echo "túnel não informou URL" >&2; exit 1; }
	echo "      $URL: aguardando responder (até 90s; o Asaas recusa URL que ainda não resolve)"
	for i in $(seq 1 45); do [ "$(curl -s -m 5 -o /dev/null -w '%{http_code}' "$URL/")" = 404 ] && break; sleep 2; done
	echo "[3/3] cadastrando webhook \"$NOME\""
	CORPO=$(jq -nc --arg n "$NOME" --arg u "$URL/webhooks/pagamentos" --arg t "$TOKEN" --argjson e "$EVENTOS" \
		'{name:$n,url:$u,email:"sandbox@example.com",enabled:true,interrupted:false,apiVersion:3,authToken:$t,sendType:"SEQUENTIALLY",events:$e}')
	ID=$(webhook_id)
	if [ -n "$ID" ]; then R=$(api PUT "/webhooks/$ID" "$CORPO"); else R=$(api POST /webhooks "$CORPO"); fi
	echo "$R" | jq -c '{id,name,url,enabled,errors}'
	echo "$R" | jq -e '.id' >/dev/null
	;;
down)
	ID=$(webhook_id)
	[ -n "$ID" ] && api DELETE "/webhooks/$ID" | jq -c '{deleted}'
	"${DC[@]}" rm -sf webhook-tunnel webhook-gate 2>&1 | tail -n1
	;;
status)
	echo "túnel: $(url_tunel)"
	api GET "/webhooks?limit=100" | jq -c --arg n "$NOME" '.data[]? | select(.name == $n) | {id,url,enabled,interrupted,penalizedAt}'
	;;
*)
	sed -n 2,8p "$0"; exit 2 ;;
esac
