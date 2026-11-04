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
2. Faróis do NCG/Vendas: verde até 0; amarelo até 0,10 (10%); vermelho acima de 0,10.
3. O NCG absoluto usa o mesmo limite para separar as mensagens de patamar moderado e alto.
4. O Ciclo Operacional sai do motor e do relatório.
5. Motor 2.0.0: mudança de denominador é MAJOR (IDR-010).

## Pendência

**O limite do item 2 é provisório até a EB confirmar.** A especificação diz "0,01–10%", e a planilha de recomendações de dez/2025 diz "0,01 a 10,00", sem o sinal de %. O PO adotou 10% (0,10). Se a EB definir outro valor, basta trocar o limite em `config/motor/faroes-industria.php` e subir a versão do motor.

## Consequências

- O vermelho fica frequente: basta a NCG passar de 10% de um mês de vendas, cerca de 3 dias. A Drogaria Martins (0,76) fica vermelha, com a mensagem de NCG alto.
- Diagnósticos já emitidos não mudam. O relatório continua exibindo o NCG/Vendas deles em percentual, sobre vendas de 12 meses, e deixa de mostrar o Ciclo Operacional, mesmo quando ele está no snapshot.
- Para ver o 0,76, a Drogaria Martins precisa emitir um novo diagnóstico depois da publicação.
- Altera a especificação funcional (§4.5, §6.3, Anexos D, E e F).
