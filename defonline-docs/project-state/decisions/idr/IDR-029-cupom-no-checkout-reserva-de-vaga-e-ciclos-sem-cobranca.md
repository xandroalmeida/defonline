---
idr_id: IDR-029
slug: cupom-no-checkout-reserva-de-vaga-e-ciclos-sem-cobranca
title: Cupom no checkout — Invoice pendente reserva a vaga, desconto é retrato na assinatura e ciclo gratuito é renovação nossa
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-083)
related_story: STORY-083
related_adrs: ["ADR-010"]
related_idrs: ["IDR-019", "IDR-020", "IDR-021", "IDR-025", "IDR-026"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-029 — Cupom no checkout

## Contexto

A STORY-083 aplica cupom em créditos (Pix e cartão), assinatura com cartão, Pix anual e renovação. O ADR-010 (`proposed`, recomendações adotadas pelo orquestrador) manda enviar sempre o valor final e ativar sem cobrança quando o valor final é zero ou menor que o mínimo do provedor.

## Decisão

- **Valor final sempre nosso.** `PrecoComCupom` calcula preço cheio, desconto e valor a pagar; nada do desconto do Asaas é usado. Final abaixo do mínimo (`services.payments.valor_minimo_centavos`, R$ 5,00) ou zero = **sem cobrança**, e o desconto efetivo passa a ser o preço inteiro.
- **Reserva da vaga (CA-4).** O uso só nasce com a contratação concluída (CA-3), então a Invoice pendente e dentro do prazo, com `cupom_id`, é a reserva. `CupomDoCheckout::reavaliarTravado` trava a linha do cupom (`FOR UPDATE`) dentro da transação que cria a Invoice e conta usos + reservas de outros usuários (`Cupom::reservasDeOutros`). A reserva some quando a Invoice falha, é cancelada ou o prazo passa. Pix pago depois do prazo com a vaga já tomada ainda credita (o dinheiro entrou) e deixa `Log::warning cobranca.cupom_acima_do_limite`.
- **Uso na transação do pagamento.** `registrarUso` (insertOrIgnore, `UNIQUE (cupom, usuário)`) roda dentro da transação que marca a Invoice paga e entrega o benefício; em duas compras pagas da mesma conta com o mesmo cupom, grava um uso e alerta `cobranca.cupom_ja_usado`.
- **Sem cobrança = transação única.** Invoice paga de valor 0 (`meio = nenhum`, `provedor = interno`), uso e benefício (lote ou assinatura) juntos; sem uso gravado não há ativação. `invoices_valor_ck` passou a aceitar zero só com `meio = nenhum` e desconto positivo. A assinatura nasce com `meio_pagamento = nenhum` e sem `id_externo`.
- **Desconto é retrato na assinatura (CA-9).** `cupom_id`, `preco_cheio_centavos`, `desconto_centavos` e `desconto_periodos` (ciclos contados desde o 1º; `null` = sempre). Editar ou desativar o cupom depois não muda nada. `preco_snapshot_centavos` passa a ser o valor da PRÓXIMA cobrança (Pix, troca de cartão e telas já o liam assim). A duração vem da concessão (`ciclos_restantes`) quando o cupom é concedido.
- **Volta ao preço vigente (CA-7).** Pago o último ciclo com desconto, `valor_pendente_no_provedor` é marcado na mesma transação e `AjustarValorDaAssinatura` chama `PaymentProvider::changeSubscriptionValue` (novo na interface; Asaas: `PUT /subscriptions/{id}` com `value` e `updatePendingPayments`) fora dela; se o provedor falhar, `assinaturas:ciclos-gratuitos` (horária) repete. "Vigente" = o preço cheio contratado (o que a tela mostrou em "Depois").
- **Ciclo gratuito é renovação nossa.** `CiclosGratuitos` (comando `assinaturas:ciclos-gratuitos`) renova no vencimento, com Invoice zero e cota cheia, os ciclos em que o desconto zera a cobrança; acabado o desconto, sem cartão no provedor, encerra a assinatura (`Expirada`) e a carteira bloqueia com `desconto_acabou_sem_cartao`. `EncerrarPixVencidas` e `AvisarRenovacaoPix` pulam o ano gratuito.
- **Cadastrar cartão no meio do desconto** reaproveita `TrocarCartao`: sem assinatura antiga (`antiga_id_externo` agora anulável), valor = preço cheio e 1ª cobrança no fim do desconto. Depois do fim, o caminho é uma contratação normal a preço cheio (o cupom já foi usado).
- **Telas.** Conta com cupom concedido aplicável não vê o campo de digitar (ADR-010). O concedido vence o digitado; entre concedidos vale o de maior desconto. Na renovação Pix o desconto em curso continua e não se soma a outro; sem desconto em curso a renovação aceita cupom.
- **Aviso D-7 (CA-8).** `cupons:avisar-fim-do-desconto` (horário, um por desconto via `desconto_aviso_em`) + aviso em Meu plano.

## Alternativas consideradas

- Gravar o uso no checkout e estornar se abandonado: viola o CA-3 e deixa o cupom "consumido" por quem desistiu. Descartada.
- Contar só `cupom_usos` no limite: dois checkouts abertos levariam a mesma última vaga e o segundo pagaria um desconto que não pode dar. Descartada.
- Deixar o provedor aplicar o desconto: já descartado pelo ADR-010 4.7.

## Consequências

- Para a 086/087: `cobranca.cupom_acima_do_limite`, `cobranca.cupom_ja_usado` e `cobranca.ajuste_de_valor_falhou` são alertas novos; somas de receita devem ignorar `meio = nenhum`.
- A migration tem prefixo `2026_11_05_400083` e não `2NNNNN` porque altera constraints criadas por `2026_11_05_300080`.
- Pendente de sandbox/produção: valor de cartão alterado exige tokenização habilitada na conta (ADR-010 Q5); `updatePendingPayments` não foi exercitado contra o Asaas real.

## Como verificar

`tests/Feature/Cobranca/CupomNoCheckoutTest.php`, `tests/Domain/Cobranca/CupomNoCheckoutDominioTest.php`, `tests/Concorrencia/CupomConcorrenciaTest.php` e `tests/Browser/CupomCheckoutBrowserTest.php`.


## Junção com a troca de plano (STORY-082, IDR-026) e com o fuso de exibição (IDR-025)

- **`changeSubscriptionValue` é um só**, com o contrato da 082: `(assinatura, valor, ciclo, próximaCobrançaEm)`. O fim do desconto (`AjustarValorDaAssinatura`) passa o preço cheio, o ciclo e o `proximo_vencimento` da assinatura. No Fake, `valorDaAssinatura()` devolve `{valor, ciclo, proxima}`.
- **Regra combinada** (`DescontoNaTroca`, domínio puro):
  1. o crédito do plano atual usa o preço do ciclo em vigor, com o desconto desse ciclo (`valorDoPeriodo(periodos_pagos)`), nunca o preço cheio;
  2. o valor final nunca fica negativo (`PrecoComCupom`); desconto total no plano novo dá troca sem cobrança;
  3. o cupom vinculado à assinatura NÃO é reaplicado: nenhuma Invoice de troca leva `cupom_id`, nenhum uso novo é registrado, e o mesmo desconto é recalculado sobre o preço do plano novo;
  4. o desconto só continua se o cupom cobre o plano e o ciclo novos (`Cupom::cobre`, que ignora ativo/vigência/limite: CA-9). Troca de ciclo reinicia a contagem de ciclos, então o desconto termina nela (decisão local, a confirmar);
  5. se o desconto ainda valeria em ciclos futuros e não continua, ele termina na troca e a tela de revisão avisa (`screen-troca-desconto-termina`).
- **Downgrade agendado**: `troca_preco_centavos` é o que o provedor cobrará na renovação (com o desconto que continua); `troca_preco_cheio_centavos` e `troca_desconto_centavos` (0 = terminou) guardam o retrato que `Assinatura::aplicarRetratoDaTroca()` grava quando a renovação (cartão ou Pix) o aplica.
- **Fuso**: toda data nova passa por `DataExibicao` (teste de arquitetura `FusoDeExibicaoTest`).
