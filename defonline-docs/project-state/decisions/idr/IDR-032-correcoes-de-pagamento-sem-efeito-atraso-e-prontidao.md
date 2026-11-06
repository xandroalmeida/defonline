---
idr_id: IDR-032
slug: correcoes-de-pagamento-sem-efeito-atraso-e-prontidao
title: Pagamento sem efeito vai para a devolução, atraso mantém o acesso e estorno pendente tira o crédito do consumo
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-092)
related_story: STORY-092
related_adrs: ["ADR-010"]
related_idrs: ["IDR-019", "IDR-020", "IDR-021", "IDR-027"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-032 — Correções da rodada 1 de validação (STORY-092)

## Contexto

A STORY-087 reprovou o EPIC-005. Esta IDR registra as escolhas da STORY-092 (D-08, D-11a, D-12). As STORY-093 e 094 ficaram para depois da junção e podem acrescentar seções aqui.

## Decisão

- **Pagamento confirmado sem efeito = Invoice `paga` + `devolucao_motivo` + alerta + auditoria**, num ponto só (`PagamentoSemEfeito::registrar`). Motivos novos além dos de IDR-027: `troca_sem_efeito` (diferença de upgrade paga depois de a base mudar), `renovacao_repetida` (segundo QR Code da renovação anual) e `checkout_cancelado` (o provedor confirma o pagamento de um checkout que já estava `cancelada` aqui; antes a Invoice final ficava `ignorada` em silêncio). Nada é concedido. O CHECK de `invoices.devolucao_motivo` foi ampliado (migration `2026_11_05_300092`).
- **"A base mudou" na diferença do upgrade:** a Invoice `troca_de_plano` agora guarda `plano_origem_id` e `ciclo_origem` (de onde a diferença foi calculada). Pagar a diferença só aplica se o plano, o ciclo e o `periodo_base` da assinatura ainda são esses; duas diferenças da mesma base aplicam a primeira e devolvem a segunda. Invoice antiga, sem o retrato, vale pelo `periodo_base`.
- **Renovação Pix anual:** a Invoice da renovação guarda `periodo_base`; o ano só avança se a assinatura ainda está nesse período.
- **Assinatura não nasce sobre período vigente:** `Carteira::concederCota` trata como vigente também a `cancelada` cujo `proximo_vencimento` ainda não passou (o mesmo critério de `IniciarAssinatura::assinaturaVigente`). O segundo checkout pago vira `assinatura_duplicada`.
- **Checkout novo do mesmo item cancela o anterior no provedor** (`AbrirCheckoutDaInvoice::cancelarAnteriores`): mesmo Usuário, tipo, renovação, meio, provedor, pacote ou plano e ciclo, e assinatura. Provedor que não deixa cancelar vira `Log::warning cobranca.checkout_anterior_nao_cancelado` e não impede o novo; o estado local do antigo só muda pelo webhook (marcar `cancelada` aqui tornaria o pagamento em voo um estado final ignorado).
- **Estorno em `aguardando_autorizacao`/`em_andamento` tira o lote do consumo** (`LoteCredito::semEstornoEmCurso`, usado pela carteira e por Meu plano). Negado, o admin do provedor desfaz a marca (`ReembolsarCobranca::negar`, com auditoria e alerta) e o lote volta. **A ligação do evento de estorno negado a `negar()` é da STORY-088 (CA-4).**
- **Atraso mantém o acesso** (espec §3.7, Termo cl. 7): `RegrasConsumo::daAcesso` aceita `em_atraso` sem olhar o vencimento; análises usam a cota que sobrou e o Raio X segue. O bloqueio vem quando a régua encerra o ciclo (`expirada`). Sem cota, `em_atraso` continua sendo o motivo do bloqueio (o que destrava é o cartão). O motivo `EmAtraso` ganhou o par "aviso": `AvisoDeAtraso`, na Home.

## Alternativas consideradas

- **Marcar `cancelada` o checkout anterior assim que o provedor aceita o cancelamento:** pagamento em voo viraria estado final e seria descartado. Descartada em favor do webhook.
- **Comparar só o plano de destino para detectar a segunda diferença paga:** não pegaria upgrade de ciclo (mensal para anual) com plano igual. Descartada pelo retrato de origem.

## Como verificar

`tests/Feature/Cobranca/PagamentoSemEfeitoEEstornoPendenteTest.php` (E4, E4b, E12, E13, E17, E21), `tests/Domain/Cobranca/AvisoDeAtrasoTest.php`, `tests/Domain/Cobranca/RegrasConsumoTest.php`.
