---
idr_id: IDR-026
slug: troca-de-plano-upgrade-imediato-e-downgrade-agendado
title: Troca de plano — "superior" pelo preço, diferença em checkout avulso, downgrade agendado na assinatura
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-082)
related_story: STORY-082
related_adrs: ["ADR-010"]
related_idrs: ["IDR-019", "IDR-020", "IDR-021"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-026 — Troca de plano

## Contexto

A STORY-082 troca de plano e de ciclo: upgrade imediato e proporcional, downgrade no ciclo seguinte (Termos). O catálogo é configurável, então nenhuma regra pode citar plano por código (PDR-020, teste de arquitetura da STORY-071). O ADR-010 (`proposed`, recomendações adotadas) manda cobrar a diferença por checkout avulso e ajustar o valor da assinatura no provedor; abaixo de R$ 5,00 aplica-se sem cobrança.

## Decisão

- **"Superior" é o preço por mês** do ciclo de destino contra o que o usuário paga hoje (comparação em cruz, sem divisão); empate só sobe se a cota for maior (`CalculoDeTroca`, domínio puro). Mensal e anual entram na mesma regra (pergunta 2 da spec adotada). **Anual para mensal nunca é imediato**: o crédito de um ano não usado não vira dinheiro (fora de escopo), então é agendado.
- **Upgrade no mesmo ciclo:** paga `round(novo × restante/total) − round(atual × restante/total)`, proporção em segundos do período em curso, nunca negativa. **Upgrade com troca de ciclo (mensal para anual):** o ano novo começa hoje pelo preço cheio menos o crédito do que restava; `data_inicio` e `periodos_pagos` são reancorados.
- **A diferença é uma `Invoice` `tipo = troca_de_plano`** (avulsa, cartão, ou Pix se o plano anual foi pago com Pix: o QR Code da diferença, resposta à pergunta 1), com o retrato do plano novo e `periodo_base` (= `periodos_pagos` no momento). O plano só muda no `cobranca.paga` conferido no provedor (`ConfirmarAssinatura` → `TrocarDePlano::aplicarPaga`); recusado, vencido ou cancelado, nada muda (CA-2). Se o ciclo mudou entre abrir e pagar (`periodo_base` diferente, assinatura que deixou de estar ativa), o dinheiro entrou mas a troca não vale: `Log::error cobranca.troca_paga_sem_efeito` para devolução manual (STORY-087), e a tela de retorno diz isso.
- **Cota:** o que já foi gasto no ciclo continua gasto; `restante = max(0, cota nova − gastas)`. Com ciclo novo, cota cheia.
- **Provedor:** `PaymentProvider::changeSubscriptionValue(ref, valor, ciclo, próximaCobrança)` (Asaas: `PUT /subscriptions/{id}` com `value`, `cycle`, `nextDueDate`, `updatePendingPayments`). No upgrade, o ajuste vem depois de o plano valer; se o provedor falhar, `assinaturas.ajuste_provedor_pendente` e a rotina horária `assinaturas:ajustar-no-provedor` repetem até conseguir (mesmo desenho de `assinaturas:cancelar-antigas`). Plano sem recorrência no provedor (Pix anual) não chama.
- **Downgrade agendado em `assinaturas.troca_*`** (`troca_plano_id`, `troca_ciclo`, `troca_preco_centavos`, `troca_agendada_em`, tudo-ou-nada por `CHECK`). O valor no provedor já passa ao novo no agendamento, para a próxima cobrança sair certa; "Desfazer" o devolve (se o provedor falhar, nada muda do nosso lado). A renovação (`ConfirmarAssinatura::renovar` / `renovarPorPix`) aplica plano, ciclo, cota e preço e zera `troca_*`; com ciclo novo, o período nasce no fim do atual. A renovação por Pix (`IniciarRenovacaoPix`) e os e-mails de aviso já falam do plano e do valor agendados; a troca de cartão (`TrocarCartao`) abre o checkout recorrente com o valor agendado.
- **Quem pode:** assinatura paga `ativa`. Em atraso ("Atualize o cartão antes de trocar de plano"), cancelada (reative antes), sem plano, em personificação e plano desativado como destino (CA-5) são negados com motivo estável (`TrocaNegada`). O plano atual desativado segue valendo. Plano pago com Pix só troca para o anual.
- **Rotas:** `plano.trocar` = `/planos/trocar/{plano}/{ciclo}` (revisão; a lista é `/planos`, que já mostra "Trocar") e `plano.trocar.retorno`. Cobrança desligada = 404 (CA-6).

## Alternativas consideradas

- **Trocar de plano no provedor criando nova assinatura:** exigiria o cartão no nosso servidor ou novo checkout recorrente (atrito igual ao da troca de cartão). Descartada: `PUT` do valor basta.
- **Aplicar o downgrade por rotina no dia do vencimento:** corrida com a cobrança que o provedor já gera. Descartada: o valor já está certo no provedor desde o agendamento.
- **Guardar a diferença como crédito em conta:** fora de escopo da estória.

## Consequências

- Para 087 (estorno): a diferença paga é uma `Invoice` própria, `tipo = troca_de_plano`; "sem efeito" e "pagamento da assinatura antiga" têm alerta.
- `Arrependimento` usa `data_inicio`: a troca de ciclo a reinicia (efeito conhecido; refinar na 087 se o PO quiser que o prazo de 7 dias conte do contrato original).
- Pendente de produção (não observável no sandbox sem pagar, ADR-010 Q5): o Asaas aceitar `value`/`cycle` num `PUT` de assinatura de cartão e aplicar `updatePendingPayments` à cobrança do próximo ciclo. Até lá, não ligar a cobrança em produção sem esse teste.

## Como verificar

`tests/Domain/Cobranca/CalculoDeTrocaTest.php`, `tests/Feature/Cobranca/TrocaDePlanoTest.php`, `tests/Feature/Livewire/TrocarPlanoTest.php`, `tests/Browser/TrocaDePlanoBrowserTest.php`.
