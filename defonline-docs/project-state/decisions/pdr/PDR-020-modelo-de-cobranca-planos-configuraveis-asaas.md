---
pdr_id: PDR-020
slug: modelo-de-cobranca-planos-configuraveis-asaas
title: Cobrança com planos geridos em tela, trial de 7 dias, cupons e Asaas como provedor substituível
status: accepted
decided_at: 2026-11-05
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-005, EPIC-010, EPIC-011]
related_adrs: []
related_idrs: []
---

# PDR-020 — Cobrança com planos geridos em tela, trial de 7 dias, cupons e Asaas como provedor substituível

## Contexto

A spec (§2) já define planos, créditos e políticas comerciais, mas nada foi implementado. Quatro pontos travavam o EPIC-005: trial em `[DECIDIR]`, gateway `[A DEFINIR]`, Termo de Adesão revisado por advogado como bloqueio e a condição do PDR-003 de medir a adesão ao DRM antes de construir a cobrança. Além disso, os preços dos créditos contradiziam a regra da própria spec e tornavam a compra avulsa mais vantajosa que o Plano Básico.

## Opções consideradas

### Opção 1 — Destravar a cobrança agora, com catálogo configurável (escolhida)
- Prós: inicia a comercialização sem esperar terceiros; preço e oferta mudam sem deploy.
- Contras: cobra com Termo sem revisão jurídica e sem dados de adesão ao DRM.

### Opção 2 — Status quo: esperar advogado e medição
- Consequência: EPIC-005 segue bloqueado por prazo que não controlamos.

## Decisão

> **Optamos pela Opção 1.**

1. **Catálogo em tela.** Planos e pacotes de créditos são criados e editados no backoffice. Nenhum preço, cota, plano ou pacote fica fixo em código; o código não ramifica por plano específico.
2. **Trial ligado:** 7 dias com 1 análise. Encerra o `[DECIDIR]` 6.10. Dias e análises são configuráveis.
3. **Provedor: Asaas**, atrás de uma abstração que permita trocar de provedor sem mexer nas regras de negócio.
4. **Termos.** A cobrança não espera a revisão por advogado, que continua pendente e deixa de ser bloqueio. Termo de Adesão e Política de Privacidade passam à versão `v2`, sem faixa de placeholder, com as regras comerciais da spec §2 e as premissas do NRF §8. O aceite da `v2` é registrado no cadastro e no checkout. Os Termos e o rodapé identificam a EB Parcerias Ltda (CNPJ 28.261.669/0001-56, Fortaleza/CE), com dados conferidos na base pública da Receita.
5. **Medição sem bloqueio.** A STORY-056 continua necessária e alimenta a calibragem de preço, mas não condiciona o EPIC-005. Altera a condição do PDR-003.
6. **Novos valores de seed dos créditos** (referência; a tela manda):

| Pacote | Créditos | Preço | Unitário |
|---|---|---|---|
| Mini | 1 | R$ 89,90 | R$ 89,90 |
| Plus | 5 | R$ 349,90 | R$ 69,98 |
| Max | 10 | R$ 599,90 | R$ 59,99 |

Os planos mantêm os valores atuais: Básico R$ 49,90/mês ou R$ 399/ano; Pro R$ 199/mês ou R$ 1.599/ano. A tela avisa, sem impedir, quando o unitário de um pacote fica abaixo do unitário mensal de um plano ativo.

7. **DRM incluído em todos os planos pagos e no trial.** Lançar no DRM não consome cota. Quem só tem créditos não tem o DRM. Sem plano ou trial ativo, não há novos lançamentos, mas os já feitos continuam visíveis. O paywall entra no EPIC-005; o EPIC-010 segue sem ele.
8. **Cupons de desconto.** O admin cria cupons de desconto parcial ou total em tela (spec §2.5).
9. **Usuários do beta recebem cupom.** Desconto e duração são definidos pelo admin ao criar o cupom. O cupom é concedido às contas do beta e aplicado automaticamente no checkout.

## Justificativa

O preço é hipótese e precisa mudar sem deploy; a mesma lógica vale para o provedor, que pode deixar de ser competitivo. Com os novos seeds, o unitário de todo pacote supera os R$ 49,90 do Básico. Antes, R$ 19,90 por crédito deixava 4 análises por ano muito abaixo dos R$ 399 do plano anual.

## Consequências

### Positivas
- EPIC-005 sem bloqueio externo.
- Oferta ajustável pela equipe, incluindo vender ou não o Pro (flag `ativo`).

### Negativas / trade-offs aceitos
- Risco jurídico aceito de cobrar com Termo não revisado.
- Preço definido sem dados de adesão ao DRM.

### Para o time técnico
- ADR a produzir: abstração do provedor de pagamento e integração Asaas.
- Impacto em épicos: EPIC-005 sai de bloqueado, passa a incluir a tela de catálogo e a permissão de backoffice correspondente.

## Pendências

- **Revisão por advogado (adiada pelo PO):** priorizar a condição do arrependimento e o teto de responsabilidade, que vêm do NRF §8.1 e podem não valer em relação de consumo.
- **Novo aceite das contas existentes (adiado pelo PO):** o NRF §8.3 pede novo aceite no próximo login, mecanismo que não existe. Nesta decisão, o novo aceite ocorre no checkout, e as contas existentes que não contratarem ficam com o aceite da `v1`.

## Sinais de revisão

- Queixa, chargeback ou questionamento jurídico ligado aos termos da contratação: antecipar a revisão por advogado.
- Conversão trial → pago baixa ou abuso de trial: rever o formato do trial.
- Taxa de 2º lançamento do DRM baixa (STORY-056): o problema é de produto, rever a oferta antes do preço.
