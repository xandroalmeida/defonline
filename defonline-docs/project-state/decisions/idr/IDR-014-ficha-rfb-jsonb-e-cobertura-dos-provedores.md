---
idr_id: IDR-014
slug: ficha-rfb-jsonb-e-cobertura-dos-provedores
title: Ficha RFB em jsonb, proteção por propriedades Locked e o que cada provedor entrega
status: accepted
decided_at: 2026-11-02
decided_by: programador (claude-opus-5-5)
related_stories: [STORY-063]
related_pdrs: [PDR-010]
related_adrs: [ADR-003]
related_idrs: [IDR-004, IDR-005]
---

# IDR-014 — Ficha RFB em jsonb, proteção por propriedades Locked e o que cada provedor entrega

## Contexto

A STORY-063 (PDR-010) pede a ficha completa da RFB, que precisa ser persistida, nunca editável pelo usuário e substituída inteira quando a empresa for reconsultada (STORY-064).

## Decisões

1. **Persistência em uma coluna `empresas_analisadas.ficha_rfb` (jsonb).** A ficha é lida e trocada sempre inteira, e nada no sistema consulta por sócio ou CNAE secundário hoje. Tabelas filhas (sócios, atividades) dariam mais migrações e joins sem nenhum consumidor. Os campos-núcleo (`razao_social`, `cnae`, `municipio`, `uf`, `situacao_cadastral`, `data_fundacao`) continuam em colunas próprias, porque a listagem, o quiz e o relatório já leem deles. Se surgir consulta por sócio/CNAE (ex.: busca de carteira do contador), aí vale normalizar.
2. **Objetos de valor em `app/Domain/Rfb`** (`FichaRfb`, `Atividade`, `Endereco`, `Socio`, `OpcaoRegime`, `DataRfb`), com datas como string ISO `Y-m-d`, sem Carbon nem fuso. O `RfbCnpjResult` ganhou `ficha` com default vazio, então os construtores existentes não mudaram e o cache antigo (sem `ficha`) desserializa como ficha vazia.
3. **Imutabilidade garantida no servidor.** O resultado da consulta fica em propriedades Livewire `#[Locked]` (`resultadoRfb`, `cnpjConsultado`). "Confirmar" grava só a partir delas, e qualquer tentativa do cliente de alterá-las lança `CannotUpdateLockedPropertyException`. Não existe rota de edição. As observações são o único campo gravável (componente `Empresa\Observacoes`, que resolve a empresa pelo Global Scope).

## Cobertura dos provedores (conferida contra respostas reais em 2026-11-02)

| Campo | cnpja (Open API) | receitaws |
|---|---|---|
| Natureza jurídica, porte, capital, data da situação | sim | sim |
| CNAE principal + secundários com descrição | sim | sim |
| Endereço completo (logradouro, número, complemento, bairro, CEP) | sim | sim |
| Sócios: nome e qualificação | sim | sim |
| Sócios: documento (mascarado pela RFB) e data de entrada | sim | **não** |
| Simples/SIMEI: optante e data de opção | sim | sim |
| Simples/SIMEI: data de exclusão | **derivada** do `until` do último período em `history` | sim |

Os campos marcados como "não" saem como ausentes e aparecem na tela como "Não informado pela Receita" (STORY-063 CA-3).

**Pendência de verificação:** o formato de `history` do cnpja (`[{from, until}]`) foi inferido; na amostra real (Banco do Brasil) a lista veio vazia. Na primeira consulta real de uma empresa excluída do Simples, conferir se a data de exclusão aparece. Se não aparecer, ajustar `CnpjaRfbCnpjClient::regime()`.

## Consequências

- O cadastro por CNPJ depende do provedor estar no ar (PDR-010 item 5). O rate-limit de 3 RPM do Open API da cnpja segue valendo, e o cache de 5 min (IDR-004) evita reconsultar o mesmo CNPJ na sequência consulta → confirmar.
- O mock gera fichas determinísticas pelos dígitos 3–5 do CNPJ (regime, nº de CNAEs secundários, nº de sócios; 3º dígito `9` = BAIXADA). Ver docblock de `MockRfbCnpjClient`.
