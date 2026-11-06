---
epic_id: EPIC-005
slug: cobranca-e-planos
title: Cobrança e planos — trial, assinatura, créditos e cupons com catálogo configurável
wave: WAVE-2026-02
status: ready
owner_role: po
created_at: 2026-11-05
updated_at: 2026-11-05
related_pdr: [PDR-020, PDR-003]
---

# EPIC-005 — Cobrança e planos

## Por que existimos (problema do usuário)

O DEFOnline entrega valor, mas não tem como receber por ele. Roberto não consegue contratar nada, e a equipe não tem como testar preço e oferta. Sem receita, o produto não se sustenta além do beta.

## Resultado esperado (outcome)

Ao fim deste épico, Roberto experimenta o produto no trial, assina um plano ou compra créditos com Pix ou cartão e cancela sozinho, enquanto a equipe ajusta planos, pacotes, trial e cupons em tela, sem deploy.

## Métrica de sucesso

- **Métrica primária:** conversão trial → pago em 30 dias. Hipótese inicial: 10%, sem histórico, a recalibrar após 60 dias de venda.
- **Métricas de apoio:** conclusão do checkout e churn mensal.
- **Métrica de qualidade:** zero cobrança duplicada, crédito indevido ou acesso liberado sem pagamento. Falha de pagamento abaixo de 10% por hora.

## Entregável visível no fim do épico

- [ ] Admin gerencia planos, pacotes, trial e cupons em tela.
- [ ] Usuário ativa o trial, assina plano e compra créditos no sandbox do Asaas.
- [ ] Usuário vê, altera e cancela o plano em Meu plano.
- [ ] Análises e o Raio X do Negócio respeitam plano, créditos e trial.
- [ ] Usuários do beta recebem cupom concedido pelo admin.
- [ ] Com o interruptor desligado, produção segue igual para o usuário.

## Decisões de detalhamento

- **Interruptor de cobrança:** toda a cobrança é publicada desligada. Assim, as estórias chegam à `main` aos poucos, sem bloquear os usuários do beta. Ligar é decisão do PO após a validação.
- **"Logar como":** o admin não contrata, paga, cancela nem reembolsa enquanto assume a conta de um usuário.
- **Canal do arrependimento:** e-mail `eb@ebparcerias.com`, o mesmo dos Termos, sem botão de reembolso.
- **Pagamento:** cartão e Pix no Checkout hospedado do Asaas (ADR-010, `proposed`), adotado provisoriamente até a revisão do PO.
- **Nota fiscal:** emissão manual no início, com controle no backoffice (spec §2.4).

## Fora de escopo (explicitamente)

- Emissão automática de nota fiscal.
- Revisão dos Termos por advogado e novo aceite no login (adiados no PDR-020).
- Fluxos dedicados de contador. O plano Pro pode ser vendido, mas o DPA por empresa (NRF §7.2) segue pendente.
- Hotsite de vendas (EPIC-009) e backoffice financeiro completo (roadmap §6.1).

## Referências da especificação

- `defonline-docs/especificacao/V2/especificacao-funcional.md` — §2, §3.4 e §4.3
- `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` — §3.2, §6 e §8
- `defonline-docs/especificacao/V2/arquitetura-tecnica.md` — §4.1
- `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`

## Dependências

- **Bloqueado por:** nenhum épico para começar. A STORY-084 depende da STORY-047 (EPIC-010). A validação final depende dela.
- **Decisões arquiteturais necessárias:** ADR-010 (STORY-069).
- **Pré-requisitos do PO:** conta no Asaas em nome da EB Parcerias, com chaves de sandbox para a STORY-069 e de produção antes de ligar a cobrança.

## Sequência sugerida

1. **Base, sem depender do provedor:** STORY-071, 072, 073.
2. **Decisões:** STORY-069 e 070, em paralelo com a base.
3. **Experimentar e escolher:** STORY-074 e 075.
4. **Primeiro pagamento:** STORY-076, depois STORY-077.
5. **Ciclo de vida:** STORY-078 a 082.
6. **Benefícios e acesso:** STORY-083 e 084.
7. **Operação e fechamento:** STORY-085, 086 e 087.

## Estórias

- [ ] STORY-069 — Spike — abstração do provedor de pagamento e integração Asaas
- [ ] STORY-070 — Design das telas de cobrança do usuário
- [ ] STORY-071 — Backoffice — gestão de planos, pacotes de créditos e trial
- [ ] STORY-072 — Backoffice — criação e concessão de cupons
- [ ] STORY-073 — Carteira, interruptor de cobrança e bloqueio de análise
- [ ] STORY-074 — Trial gratuito
- [ ] STORY-075 — Página de planos e preços
- [ ] STORY-076 — Compra de créditos com Pix — primeira cobrança real
- [ ] STORY-077 — Assinatura de plano com cartão recorrente
- [ ] STORY-078 — Compra de créditos com cartão
- [ ] STORY-079 — Renovação e falha de cobrança
- [ ] STORY-080 — Pix no plano anual
- [ ] STORY-081 — Meu plano, troca de cartão e cancelamento
- [ ] STORY-082 — Upgrade e downgrade de plano
- [ ] STORY-083 — Cupom no checkout e benefício do beta
- [ ] STORY-084 — Acesso ao DRM por plano ou trial
- [ ] STORY-085 — Backoffice — assinaturas, reembolso e nota fiscal
- [ ] STORY-086 — Eventos e alertas de cobrança
- [ ] STORY-087 — Validação final do EPIC-005

- [ ] STORY-088 — Correção — recorrência duplicada e cobrança na Invoice certa
- [ ] STORY-089 — Correção — data do provedor sem deslocar o ciclo
- [ ] STORY-090 — Correção — valor da recorrência após troca de plano, cartão e cupom
- [ ] STORY-091 — Correção — cota do anual cancelado, cota contratada e upgrade proporcional
- [ ] STORY-092 — Correção — pagamento sem efeito, estorno pendente e acesso em atraso
- [ ] STORY-093 — Correção — prontidão para deploy
- [ ] STORY-094 — Correção — Termos v2 alinhados ao sistema

## Validação final

Critérios em `validation/checklist.md`. Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** todas as estórias `done` e relatório de validação `approved`. O lançamento é o PO ligar o interruptor.

## Histórico

- 2026-11-05 — criado e detalhado pelo PO a partir do PDR-020.
- 2026-11-05 — validação reprovada na rodada 1 (21 defeitos); estórias de correção STORY-088 a 094.
