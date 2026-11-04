---
story_id: STORY-068
slug: recomendacoes-no-backoffice
title: Backoffice — manutenção da matriz de recomendações
epic_id: EPIC-011
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: in_review
owner_agent: programador (claude-opus-5-5)
created_at: 2026-11-04
updated_at: 2026-11-04
estimated_session_size: M
---

# STORY-068 — Recomendações no backoffice

Decisão: PDR-016.

## Critérios de aceite

- **CA-1 carga:** a migration traz os 117 textos. Os de Indústria são idênticos aos que o motor usava, sem mudar os golden hashes. Comércio e Serviços vêm do Anexo F.
- **CA-2 acesso:** a tela e suas ações exigem `recomendacoes.editar`; sem ela, 404 e nenhum item no menu.
- **CA-3 tela:** filtra por segmento e indicador e mostra as 3 faixas com o valor numérico de cada uma. Os filtros travam durante a edição. Comércio e Serviços têm aviso de que ainda não aparecem em relatório.
- **CA-4 edição:** só o texto muda, com até 300 caracteres e sem texto vazio. Salvar sem mudança não grava nem audita. Toda alteração vai para `audit_logs`, com antes, depois e versão.
- **CA-5 efeito:** o próximo diagnóstico usa o texto novo. O já emitido mantém texto e versão.
- **CA-6 versão:** a versão do segmento é o mês da última edição, no horário de Brasília. A edição de um segmento não muda a versão de outro.

## Notas do agente

- 2026-11-04: implementada, commit local. Falta publicar e verificar em produção.
