---
pdr_id: PDR-014
slug: ncg-vendas-base-mensal-e-fim-do-ciclo-operacional
title: NCG/Vendas na base mensal e exclusão do Ciclo Operacional
status: accepted
decided_at: 2026-11-04
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-002]
related_adrs: []
related_idrs: [IDR-010]
---

# PDR-014 — NCG/Vendas na base mensal e exclusão do Ciclo Operacional

## Contexto

A EB Parcerias revisou o relatório da Drogaria Martins e apontou que o NCG/Vendas estava em bases diferentes, com resultado correto de 0,76. O sistema dividia a NCG de 91.500 pelas vendas anualizadas de 1.440.000 e gravava 0,0635, como a especificação mandava. A venda média mensal é 120.000.

A EB também sugeriu excluir o Ciclo Operacional. Ele não consta do Anexo D nem da matriz da EB; entrou como complemento na STORY-030.

## Decisão

1. NCG/Vendas = NCG / venda média mensal (Q09), em razão direta, exibida como `0,76`.
2. Faróis do NCG/Vendas: verde até 0; amarelo de 0,01 a 10,00; vermelho acima de 10,00.
3. O NCG absoluto usa o mesmo limite para separar as mensagens de patamar moderado e alto.
4. O Ciclo Operacional sai do motor e do relatório.
5. Motor 2.0.0: mudança de denominador é MAJOR (IDR-010).

## Pendência

**O limite do item 2 é provisório até a EB confirmar.** A especificação dizia "0,01–10%", e a planilha de recomendações de dez/2025 diz "0,01 a 10,00", sem o sinal de %. Foi adotada a planilha porque os relatórios antigos da EB seguem a mesma escala: a Farmácia Aurinete deu 4,4 e recebeu o texto da faixa amarela. Se a EB escolher 10%, basta trocar o limite em `config/motor/faroes-industria.php` para 0,10 e subir a versão do motor.

**Histórico do limite.** Em 04/11/2026 o limite foi para 0,10 e entrou em produção sob o motor 2.0.0. Em 05/11/2026 o PO o devolveu a 10,00, ao ver um NCG/Vendas de 6,36 receber a recomendação da faixa "acima de 10" (motor 2.0.1). Snapshots já emitidos com 0,10 não mudam.

## Consequências

- O vermelho fica raro: exige NCG acima de 10 meses de vendas. Com limite de 10%, quase toda empresa com NCG positiva ficaria vermelha.
- Diagnósticos já emitidos não mudam. O relatório continua exibindo o NCG/Vendas deles em percentual, sobre vendas de 12 meses, e deixa de mostrar o Ciclo Operacional, mesmo quando ele está no snapshot.
- Para ver o 0,76, a Drogaria Martins precisa emitir um novo diagnóstico depois da publicação.
- Altera a especificação funcional (§4.5, §6.3, Anexos D, E e F).
