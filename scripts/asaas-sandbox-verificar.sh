#!/usr/bin/env bash
# STORY-069 / ADR-010 CA-4: confirma no SANDBOX do Asaas os comportamentos dos quais o ADR depende.
# Lê a chave de ASAAS_SANDBOX_API_KEY (ambiente). Nunca imprime a chave. Só fala com api-sandbox.asaas.com.
# Não cria código de aplicação nem toca em banco; limpa o que cria (assinaturas, cobranças, cliente) ao final.
#
#   ASAAS_SANDBOX_API_KEY=... scripts/asaas-sandbox-verificar.sh [saida.log]
#   ou:  set -a; . app/.env; set +a; scripts/asaas-sandbox-verificar.sh
# Saída: progresso por passo (n/total, decorrido) e uma linha "RESULTADO <item>: ..." por verificação.
set -uo pipefail

BASE="https://api-sandbox.asaas.com/v3"   # fixo: este script nunca usa o host de produção
: "${ASAAS_SANDBOX_API_KEY:?defina ASAAS_SANDBOX_API_KEY}"
case "$ASAAS_SANDBOX_API_KEY" in
	*aact_hmlg_*) ;;
	*) echo "A chave não parece de sandbox (prefixo aact_hmlg_). Abortando." >&2; exit 2 ;;
esac
command -v jq >/dev/null || { echo "precisa de jq" >&2; exit 2; }

TOTAL=10; N=0; T0=$(date +%s)
CLIENTE=""; SUBS=(); PAGS=()
passo() { N=$((N + 1)); local d=$(($(date +%s) - T0)); echo "[$N/$TOTAL] $(( N * 100 / TOTAL ))% decorrido ${d}s :: $1"; }
res() { echo "RESULTADO $1"; }

api() { # api METODO CAMINHO [json]  -> imprime "<http>\n<corpo>"
	local m=$1 p=$2 b=${3:-}
	local args=(-sS -m 60 -X "$m" "$BASE$p" -H "access_token: $ASAAS_SANDBOX_API_KEY"
		-H "User-Agent: defonline-spike-story069" -H "Content-Type: application/json" -w '\n%{http_code}')
	[ -n "$b" ] && args+=(-d "$b")
	local out; out=$(curl "${args[@]}" 2>&1) || { echo "curl falhou"; return 1; }
	printf '%s\n%s\n' "${out##*$'\n'}" "${out%$'\n'*}"
}
http() { head -n1 <<<"$1"; }
body() { tail -n +2 <<<"$1"; }
limpar() {
	echo "Limpando dados de teste..."
	for s in ${SUBS[@]+"${SUBS[@]}"}; do api DELETE "/subscriptions/$s" >/dev/null; done
	for p in ${PAGS[@]+"${PAGS[@]}"}; do api DELETE "/payments/$p" >/dev/null; done
	[ -n "$CLIENTE" ] && api DELETE "/customers/$CLIENTE" >/dev/null
	echo "Limpeza concluída."
}
trap limpar EXIT

HOJE=$(date +%Y-%m-%d)
AMANHA=$(date -v+1d +%Y-%m-%d 2>/dev/null || date -d '+1 day' +%Y-%m-%d)
EM_UM_ANO=$(date -v+1y +%Y-%m-%d 2>/dev/null || date -d '+1 year' +%Y-%m-%d)
CARTAO='{"holderName":"Teste Sandbox","number":"4539656048392220","expiryMonth":"12","expiryYear":"2030","ccv":"123"}'
TITULAR='{"name":"Teste Sandbox","email":"sandbox@example.com","cpfCnpj":"24971563792","postalCode":"89223005","addressNumber":"277","phone":"4738010919"}'
IP="203.0.113.10"

passo "cliente de teste"
R=$(api POST /customers '{"name":"Cliente Spike 069","cpfCnpj":"24971563792","email":"sandbox@example.com","externalReference":"spike-069"}')
echo "  http $(http "$R")"; CLIENTE=$(body "$R" | jq -r '.id // empty')
[ -z "$CLIENTE" ] && { echo "sem cliente; abortando"; body "$R" | jq -c .; exit 1; }

passo "CA-4.1 cartão: tokenização e recorrência (assinatura mensal com cartão, 1ª cobrança hoje)"
R=$(api POST /creditCard/tokenizeCreditCard "$(jq -nc --arg c "$CLIENTE" --argjson cc "$CARTAO" --argjson h "$TITULAR" --arg ip "$IP" '{customer:$c,creditCard:$cc,creditCardHolderInfo:$h,remoteIp:$ip}')")
TOKEN=$(body "$R" | jq -r '.creditCardToken // empty')
res "cartao.tokenizacao: http $(http "$R"), token $([ -n "$TOKEN" ] && echo recebido || echo AUSENTE) $(body "$R" | jq -c '{creditCardNumber,creditCardBrand,errors}')"
if [ -n "$TOKEN" ]; then
	R=$(api POST /subscriptions "$(jq -nc --arg c "$CLIENTE" --arg t "$TOKEN" --arg d "$HOJE" --arg ip "$IP" '{customer:$c,billingType:"CREDIT_CARD",value:49.9,nextDueDate:$d,cycle:"MONTHLY",description:"spike 069",creditCardToken:$t,remoteIp:$ip,externalReference:"spike-069-card"}')")
	SUB=$(body "$R" | jq -r '.id // empty'); [ -n "$SUB" ] && SUBS+=("$SUB")
	res "cartao.assinatura_com_token: http $(http "$R") $(body "$R" | jq -c '{id,status,cycle,nextDueDate,errors}')"
	sleep 5
	R=$(api GET "/subscriptions/${SUB:-x}/payments")
	for p in $(body "$R" | jq -r '.data[]?.id'); do PAGS+=("$p"); done
	res "cartao.cobrancas_da_assinatura: $(body "$R" | jq -c '[.data[]? | {id,status,billingType,value,dueDate,creditCardToken:(.creditCard.creditCardToken // null) }]')"
	PCARD=$(body "$R" | jq -r '.data[0].id // empty')
fi

passo "CA-4.2 Pix avulso: expiração do QR Code (30 min?) e campos aceitos"
R=$(api POST /payments "$(jq -nc --arg c "$CLIENTE" --arg d "$AMANHA" '{customer:$c,billingType:"PIX",value:49.9,dueDate:$d,externalReference:"spike-069-pix"}')")
PX=$(body "$R" | jq -r '.id // empty'); [ -n "$PX" ] && PAGS+=("$PX")
res "pix.cobranca: http $(http "$R") $(body "$R" | jq -c '{id,status,dueDate,errors}')"
R=$(api GET "/payments/${PX:-x}/pixQrCode")
res "pix.qrcode: http $(http "$R") $(body "$R" | jq -c '{temPayload:(.payload!=null),temImagem:(.encodedImage!=null),expirationDate,errors}')"
# a doc não lista campo de expiração no POST /payments; tenta campos plausíveis e vê se são ignorados ou rejeitados
for campo in expirationDate expirationSeconds pixExpirationMinutes; do
	R=$(api POST /payments "$(jq -nc --arg c "$CLIENTE" --arg d "$AMANHA" --arg k "$campo" '{customer:$c,billingType:"PIX",value:49.9,dueDate:$d} + {($k):"30"}')")
	id=$(body "$R" | jq -r '.id // empty'); [ -n "$id" ] && PAGS+=("$id")
	Q=$(api GET "/payments/${id:-x}/pixQrCode"); res "pix.campo_$campo: http $(http "$R"); qrcode.expirationDate=$(body "$Q" | jq -r '.expirationDate // "n/d"')"
done
# o que a doc permite: a plataforma controla os 30 min; aqui só registra que o Asaas aceita excluir (cancelar) a cobrança Pix pendente
R=$(api DELETE "/payments/${PX:-x}"); res "pix.cancelar_cobranca_pendente: http $(http "$R") $(body "$R" | jq -c .)"

passo "CA-4.3 Pix em plano anual: assinatura YEARLY com Pix e cobrança Pix avulsa de 12 meses"
R=$(api POST /subscriptions "$(jq -nc --arg c "$CLIENTE" --arg d "$AMANHA" '{customer:$c,billingType:"PIX",value:399,nextDueDate:$d,cycle:"YEARLY",description:"spike 069 anual",externalReference:"spike-069-anual"}')")
SA=$(body "$R" | jq -r '.id // empty'); [ -n "$SA" ] && SUBS+=("$SA")
res "anual.assinatura_pix_YEARLY: http $(http "$R") $(body "$R" | jq -c '{id,status,cycle,billingType,errors}')"
R=$(api GET "/subscriptions/${SA:-x}/payments"); for p in $(body "$R" | jq -r '.data[]?.id'); do PAGS+=("$p"); done
res "anual.cobranca_gerada: $(body "$R" | jq -c '[.data[]? | {id,status,billingType,value,dueDate}]')"
R=$(api POST /payments "$(jq -nc --arg c "$CLIENTE" --arg d "$EM_UM_ANO" '{customer:$c,billingType:"PIX",value:399,dueDate:$d}')")
id=$(body "$R" | jq -r '.id // empty'); [ -n "$id" ] && PAGS+=("$id")
res "anual.pix_avulso_vencimento_12m: http $(http "$R") $(body "$R" | jq -c '{id,dueDate,errors}')"

passo "CA-4.4 valor mínimo por cobrança (PIX, CREDIT_CARD, UNDEFINED)"
for tipo in PIX CREDIT_CARD UNDEFINED; do
	for v in 0.01 1 4.99 5 5.01; do
		R=$(api POST /payments "$(jq -nc --arg c "$CLIENTE" --arg d "$AMANHA" --arg t "$tipo" --argjson v "$v" '{customer:$c,billingType:$t,value:$v,dueDate:$d}')")
		id=$(body "$R" | jq -r '.id // empty'); [ -n "$id" ] && PAGS+=("$id")
		res "minimo.$tipo.$v: http $(http "$R") $(body "$R" | jq -c '(.errors // [] | map(.description)) as $e | {criada:(.id!=null),erros:$e}')"
	done
done

passo "CA-4.5 estorno: cartão (cobrança avulsa à vista) e Pix"
R=$(api POST /payments "$(jq -nc --arg c "$CLIENTE" --arg t "$TOKEN" --arg d "$HOJE" --arg ip "$IP" '{customer:$c,billingType:"CREDIT_CARD",value:89.9,dueDate:$d,creditCardToken:$t,remoteIp:$ip,externalReference:"spike-069-estorno"}')")
PE=$(body "$R" | jq -r '.id // empty'); [ -n "$PE" ] && PAGS+=("$PE")
res "estorno.cartao.cobranca: http $(http "$R") $(body "$R" | jq -c '{id,status,errors}')"
sleep 3
R=$(api POST "/payments/${PE:-x}/refund" '{"value":30,"description":"spike 069 parcial"}')
res "estorno.cartao.parcial_30: http $(http "$R") $(body "$R" | jq -c '{status,refunds:[.refunds[]?|{status,value}],errors}')"
R=$(api POST "/payments/${PE:-x}/refund" '{"description":"spike 069 restante"}')
res "estorno.cartao.restante: http $(http "$R") $(body "$R" | jq -c '{status,refunds:[.refunds[]?|{status,value}],errors}')"
R=$(api POST /payments "$(jq -nc --arg c "$CLIENTE" --arg d "$AMANHA" '{customer:$c,billingType:"PIX",value:59.9,dueDate:$d}')")
PP=$(body "$R" | jq -r '.id // empty'); [ -n "$PP" ] && PAGS+=("$PP")
# confirmação manual do Pix no sandbox (a doc diz que a confirmação é manual; endpoint a confirmar)
for ep in "POST /sandbox/payment/${PP:-x}/confirm" ; do
	R=$(api ${ep%% *} "${ep#* }"); res "estorno.pix.confirmar_no_sandbox($ep): http $(http "$R") $(body "$R" | jq -c '{status,errors}')"
done
R=$(api POST "/payments/${PP:-x}/refund" '{"description":"spike 069 pix"}')
res "estorno.pix: http $(http "$R") $(body "$R" | jq -c '{status,refunds:[.refunds[]?|{status,value}],errors}')"

passo "CA-4.6 cobrança com desconto (avulsa Pix, avulsa cartão, assinatura com cartão)"
for tipo in PIX CREDIT_CARD; do
	extra='{}'; [ "$tipo" = CREDIT_CARD ] && extra=$(jq -nc --arg t "$TOKEN" --arg ip "$IP" '{creditCardToken:$t,remoteIp:$ip}')
	R=$(api POST /payments "$(jq -nc --arg c "$CLIENTE" --arg d "$HOJE" --arg t "$tipo" --argjson x "$extra" '{customer:$c,billingType:$t,value:100,dueDate:$d,discount:{value:20,type:"PERCENTAGE",dueDateLimitDays:0}} + $x')")
	id=$(body "$R" | jq -r '.id // empty'); [ -n "$id" ] && PAGS+=("$id")
	res "desconto.avulsa_$tipo: http $(http "$R") $(body "$R" | jq -c '{id,status,value,netValue,discount,errors}')"
done
R=$(api POST /subscriptions "$(jq -nc --arg c "$CLIENTE" --arg t "$TOKEN" --arg d "$AMANHA" --arg ip "$IP" '{customer:$c,billingType:"CREDIT_CARD",value:100,nextDueDate:$d,cycle:"MONTHLY",creditCardToken:$t,remoteIp:$ip,discount:{value:20,type:"PERCENTAGE",dueDateLimitDays:0}}')")
SD=$(body "$R" | jq -r '.id // empty'); [ -n "$SD" ] && SUBS+=("$SD")
res "desconto.assinatura_cartao: http $(http "$R") $(body "$R" | jq -c '{id,value,discount,errors}')"
R=$(api GET "/subscriptions/${SD:-x}/payments"); for p in $(body "$R" | jq -r '.data[]?.id'); do PAGS+=("$p"); done
res "desconto.assinatura_cobrancas: $(body "$R" | jq -c '[.data[]?|{value,discount,status}]')"
# desconto de 100% (cupom total)
R=$(api POST /payments "$(jq -nc --arg c "$CLIENTE" --arg d "$AMANHA" '{customer:$c,billingType:"PIX",value:100,dueDate:$d,discount:{value:100,type:"PERCENTAGE",dueDateLimitDays:0}}')")
id=$(body "$R" | jq -r '.id // empty'); [ -n "$id" ] && PAGS+=("$id")
res "desconto.total_100pct: http $(http "$R") $(body "$R" | jq -c '{id,value,discount,errors}')"

passo "CA-4.7 webhook: contrato de criação (authToken sem sequência numérica, sendType); sem URL pública"
R=$(api GET /webhooks); res "webhook.listagem: http $(http "$R")"
R=$(api POST /webhooks '{"name":"spike069","url":"https://example.com/asaas","email":"sandbox@example.com","enabled":false,"interrupted":false,"apiVersion":3,"authToken":"k7Qw9Zp2Xv4Lm8Rt1Yb6Nc3Hd5Js0Fg2Ua","sendType":"SEQUENTIALLY","events":["PAYMENT_RECEIVED","PAYMENT_REFUNDED"]}')
WI=$(body "$R" | jq -r '.id // empty'); res "webhook.criar_via_api: http $(http "$R") $(body "$R" | jq -c '{id,sendType,errors}')"
[ -n "$WI" ] && api DELETE "/webhooks/$WI" >/dev/null

passo "CA-4.8 Asaas Checkout hospedado (cartão nunca toca nosso servidor): Pix 30 min, cartão recorrente, limites"
CB='"callback":{"successUrl":"https://example.com/ok","cancelUrl":"https://example.com/c","expiredUrl":"https://example.com/e"}'
IT='"items":[{"name":"Plano","description":"spike","quantity":1,"value":49.9}]'
mk() { # mk rotulo json
	R=$(api POST /checkouts "$2"); id=$(body "$R" | jq -r '.id // empty'); [ -n "$id" ] && CHKS+=("$id")
	res "checkout.$1: http $(http "$R") $(body "$R" | jq -c '{id,status,minutesToExpire,errors:(.errors // null | if . then map(.description) else null end)}')"
}
CHKS=()
mk pix_30min "{\"billingTypes\":[\"PIX\"],\"chargeTypes\":[\"DETACHED\"],\"minutesToExpire\":30,$IT,$CB}"
mk pix_5min_fora_da_faixa "{\"billingTypes\":[\"PIX\"],\"chargeTypes\":[\"DETACHED\"],\"minutesToExpire\":5,$IT,$CB}"
mk cartao_recorrente "{\"billingTypes\":[\"CREDIT_CARD\"],\"chargeTypes\":[\"RECURRENT\"],\"minutesToExpire\":30,\"subscription\":{\"cycle\":\"MONTHLY\",\"nextDueDate\":\"$AMANHA 00:00:00\"},$IT,$CB}"
mk pix_recorrente_anual "{\"billingTypes\":[\"PIX\"],\"chargeTypes\":[\"RECURRENT\"],\"minutesToExpire\":30,\"subscription\":{\"cycle\":\"YEARLY\",\"nextDueDate\":\"$AMANHA 00:00:00\"},$IT,$CB}"
for c in ${CHKS[@]+"${CHKS[@]}"}; do api POST "/checkouts/$c/cancel" >/dev/null; done

passo "concluído"
echo "Fim em $(( $(date +%s) - T0 ))s."
