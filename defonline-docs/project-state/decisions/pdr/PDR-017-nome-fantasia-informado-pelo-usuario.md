---
pdr_id: PDR-017
slug: nome-fantasia-informado-pelo-usuario
title: Usuário informa o nome fantasia exibido nos relatórios
status: accepted
decided_at: 2026-11-05
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-001]
related_adrs: []
related_idrs: []
---

# PDR-017 — Usuário informa o nome fantasia exibido nos relatórios

## Contexto

O PO pediu que o usuário possa informar o nome fantasia no cadastro de empresa e que os relatórios o exibam no lugar da razão social. No cadastro por CNPJ o nome fantasia vinha só da Receita, e a PDR-010 item 3 proíbe alterar dado da Receita.

## Decisão

1. No cadastro por CNPJ, depois da consulta, o nome fantasia é editado no próprio lugar, na linha da ficha. Parte do valor da Receita e só é gravado ao confirmar o cadastro.
2. Na tela da empresa, o nome é editado no próprio lugar, na linha "Nome fantasia", a qualquer momento. É assim que as empresas já cadastradas recebem o nome.
3. O valor da Receita continua imutável em `nome_fantasia`. O informado fica em `nome_fantasia_informado`, e a linha mostra "Na Receita: …" quando os dois diferem. Campo vazio ou igual ao da Receita não grava nome informado.
4. Em todas as telas e relatórios, a ordem é: nome informado, nome fantasia da Receita, razão social.
5. A capa do PDF mantém a razão social em letra menor abaixo do nome, para identificar a empresa legalmente.
6. Em CPF, o nome comercial já era digitado pelo autônomo: continua em `nome_fantasia` e passa a ser editável na tela da empresa.
7. Toda alteração na tela da empresa gera audit log sem o texto, porque em CPF o nome pode identificar a pessoa.

## Consequências

- **Exceção à PDR-010 item 4:** as observações deixam de ser o único dado livre do usuário em empresa com CNPJ. O item 3 continua valendo, porque o dado da Receita não muda.
- O relatório pode exibir um nome que não consta na Receita. A razão social na capa e o CNPJ continuam identificando a empresa.
- Diagnósticos já emitidos passam a mostrar o nome novo, porque o nome é lido da empresa e não do snapshot.
