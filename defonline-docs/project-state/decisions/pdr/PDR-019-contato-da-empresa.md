---
pdr_id: PDR-019
slug: contato-da-empresa
title: E-mail e telefone/WhatsApp da empresa, opcionais
status: accepted
decided_at: 2026-11-05
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-001]
related_adrs: []
related_idrs: []
---

# PDR-019 — E-mail e telefone/WhatsApp da empresa

## Contexto

O PO pediu que o cadastro de empresa colete e-mail e telefone/WhatsApp, opcionais. A ficha da Receita usada no cadastro não traz esses dados.

## Decisão

1. Os dois campos entram nos dois caminhos de cadastro: CNPJ, depois da consulta, e CPF. Nenhum é obrigatório.
2. O e-mail é validado no formato. O telefone segue a regra do cadastro de usuário (DDD + 8 ou 9 dígitos) e é gravado só com dígitos.
3. Na tela da empresa, os dois podem ser vistos e alterados a qualquer momento. É assim que as empresas já cadastradas recebem o dado.
4. O audit log registra quais campos mudaram, sem os valores. Eventos de produto não levam o contato.

## Consequências

- **Exceção à PDR-010 item 4:** o contato é mais um dado livre do usuário em empresa com CNPJ. O item 3 continua valendo, porque o dado da Receita não muda.
- Por enquanto o produto só guarda o dado: não há envio de mensagem para esse contato. Usá-lo para comunicação exige decisão própria sobre consentimento.
- Os dados não são verificados, e o mesmo contato pode constar em mais de uma empresa.
