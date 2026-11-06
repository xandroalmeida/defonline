---
story_id: STORY-090
slug: correcao-valor-no-provedor
title: "Correção — valor da recorrência após troca de plano, cartão e cupom"
epic_id: EPIC-005
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: done
owner_agent: claude-sonnet-5-5 (pista B)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-090 — Correção — valor da recorrência após troca de plano, cartão e cupom

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-06, D-15, D-20. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [x] **CA-1:** A troca de cartão usa o valor e o ciclo vigentes da assinatura no momento em que o checkout novo é confirmado. Upgrade feito depois de abrir a troca não deixa o preço antigo: o valor é corrigido no provedor, com repetição se ele falhar (E5).
- [x] **CA-2:** A renovação com valor diferente do esperado não passa só com `warning`: gera alerta da equipe, e a divergência aparece na tela Financeiro.
- [x] **CA-3:** Plano gratuito por cupom que recebe upgrade pago: a tela promete o que vai acontecer, e o usuário consegue cadastrar cartão antes do fim do desconto. A assinatura não expira sem saída (E23).
- [x] **CA-4:** Downgrade agendado é aplicado também em renovação de ciclo gratuito (E24).

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente

**Lido:** estória, `validation/report.md` (D-06, D-15, D-20), IDR-019, 021, 026, 029, exploratório (E5, E23, E24).

**Feito:**
- **CA-1** (E5): `trocas_de_cartao` guarda `valor_centavos` e `ciclo` na abertura. Em `TrocarCartao::confirmar`, se o alvo vigente (`TrocarDePlano::alvoNoProvedor`: troca agendada e ciclo gratuito considerados) difere, marca `ajuste_provedor_pendente`, corrige no provedor fora da transação e, se falhar, `assinaturas:ajustar-no-provedor` repete. `ajustarNoProvedor` agora usa o mesmo alvo (antes ignorava o downgrade agendado). Testes: `CorrecaoValorNoProvedorTest` (E5, falha com repetição, sem mudança = sem chamada, downgrade agendado).
- **CA-2**: renovação com valor diferente vira `Log::error cobranca.valor_da_renovacao_divergente` (chega ao canal da equipe pelo `AlertaDeCobranca`), a Invoice guarda `valor_esperado_centavos` e o Financeiro mostra o cartão "Renovações com valor diferente do contratado" e a marca na linha da cobrança. A renovação segue valendo o cobrado. Testes: `CorrecaoValorNoProvedorTest` e `AssinaturaComCartaoTest` (o teste do warning virou o do erro). Limitação: a lista do Financeiro não tem "baixa"; mostra as últimas ocorrências.
- **CA-3** (E23): o plano sem cartão no provedor que perde o desconto numa troca agora (a) diz na revisão da troca que nada será cobrado sem cartão e até quando cadastrar, (b) pode cadastrar o cartão (`TrocarCartao::iniciar` não exige mais desconto; a 1ª cobrança é no fim do ciclo pago), (c) Meu plano avisa, oferece "Cadastrar cartão" e, expirado, o link de recontratar. **Desvio do exploratório:** o E23 afirmava `Ativa` no vencimento sem cartão; entendi "não expira sem saída" como saída (cartão/aviso/recontratar), não como renovar de graça. Sem cartão o plano expira como o CA-8 da 083 manda. Pergunta ao PO se queria outra coisa.
- **CA-4** (E24): `CiclosGratuitos` aplica a troca agendada (plano, ciclo, cota, preço, retrato do desconto) na renovação gratuita, e decide a gratuidade pelo desconto do plano novo. Teste: E24.

Migration `2026_11_05_500090`.
