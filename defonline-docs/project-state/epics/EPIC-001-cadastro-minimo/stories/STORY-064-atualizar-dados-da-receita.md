---
story_id: STORY-064
slug: atualizar-dados-da-receita
title: "Atualizar dados da Receita" na tela da empresa — leva as empresas legadas para a ficha completa
epic_id: EPIC-001
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-11-02
updated_at: 2026-11-02
estimated_session_size: M
---

# STORY-064 — "Atualizar dados da Receita"

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause.

## Contexto

O PDR-010 torna os dados da RFB imutáveis. A única forma de mudá-los é reconsultar a Receita. As empresas cadastradas antes da STORY-063, manuais ou com o enriquecimento antigo de 7 campos, não têm a ficha completa. A decisão foi não fazer reconsulta em lote: cada empresa é atualizada sob demanda, pela ação desta estória.

- Ler ANTES: `defonline-docs/project-state/decisions/pdr/PDR-010-cadastro-empresa-cnpj-first-dados-rfb-imutaveis.md` e `defonline-docs/project-state/epics/EPIC-001-cadastro-minimo/stories/STORY-063-cadastro-empresa-cnpj-first-ficha-rfb.md` (ficha, mensagens e modelo de dados já entregues).

## O quê

Adicionar em `/empresas/{id}`, para empresas com CNPJ, a ação "Atualizar dados da Receita". Ela reconsulta a RFB e substitui a ficha inteira, mantendo as observações do usuário.

## Por quê

Essa ação leva a base legada para a regra nova sem job em lote e sem estourar o rate-limit do provedor. Também é como uma empresa nova acompanha mudanças na Receita, como uma exclusão do Simples ou um sócio novo.

## Critérios de aceite

- [ ] **CA-1.** Em `/empresas/{id}` de uma empresa com CNPJ existe a ação "Atualizar dados da Receita", com a data da última consulta ("Dados da Receita de dd/mm/aaaa"). Empresa com CPF não mostra a ação.
- [ ] **CA-2.** Uma empresa legada (`fonte_enriquecimento = 'manual'` com CNPJ, ou sem a ficha completa) mostra um aviso: "Estes dados não foram confirmados na Receita. Atualize para ver a ficha completa." Os campos digitados antes continuam exibidos até a atualização, sem ser editáveis.
- [ ] **CA-3.** Uma atualização bem-sucedida substitui **toda** a ficha (incluindo CNAEs secundários, endereço, sócios e regime), passa a empresa para `fonte_enriquecimento = 'rfb'`, renova o carimbo e **preserva as observações**. Gera audit log `empresa.rfb_atualizada` com a lista de campos que mudaram (nomes, sem valores PII).
- [ ] **CA-4.** Uma falha mantém a ficha anterior intacta e mostra as mensagens do CA-7 da STORY-063.
- [ ] **CA-5.** Atualizações repetidas da mesma empresa em menos de 5 minutos usam o cache da consulta (IDR-004) e não geram nova chamada ao provedor. A tela informa que os dados já estão atualizados.
- [ ] **CA-6.** Diagnósticos já emitidos não mudam: o relatório antigo continua mostrando os dados da empresa de quando foi gerado (verifique se isso já vale hoje e registre em "Notas do agente"; se não valer, pare e escale ao PO).
- [ ] **CA-7.** Teste E2E: empresa legada manual → aviso → atualizar → ficha completa com as observações preservadas.

## Fora de escopo

Reconsulta em lote ou agendada; notificar o usuário de mudanças na Receita; editar dados RFB (PDR-010).

## Padrões de qualidade exigidos

Segue `defonline-docs/skills/po/references/quality-standards.md`: cobertura ≥ 80% (≥ 98% no domínio), E2E do CA-7, PII mascarada em log e evento.

## Dependências

- **Bloqueada por:** STORY-063.
- **Design:** posição e estados da ação na tela da empresa (spec do Designer, junto com o da STORY-063).

## Decisões já tomadas (não as reabra)

PDR-010 (itens 3 e 7), IDR-004 (cache e rate-limit), ADR-003 (audit).

## Definição de Pronto (DoD)

- [ ] CAs passam; pre-push e CI verdes; deploy em homologação verificado atualizando uma empresa legada real.
- [ ] `index.json` atualizado; "Notas do agente" preenchidas.

## Protocolo do agente (obrigatório)

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
### Links de evidência
