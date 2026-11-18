---
story_id: STORY-105
slug: respostas-e-travamentos-do-quiz
title: "Respostas do quiz no backoffice e relatório de onde os clientes travam"
epic_id: EPIC-011
sprint_id: null
type: feature
target_role: programador
requires_design: false
status: in_review
owner_agent: claude-opus-5-5
created_at: 2026-11-18
updated_at: 2026-11-18
estimated_session_size: M
---

# STORY-105 — Respostas do quiz e travamentos

Origem: item 8 da devolutiva do PO (2026-11-18). A equipe quer consultar as respostas do quiz e saber onde os clientes erram, para simplificar e clarear as perguntas.

## Critérios de aceite

- [x] **CA-1:** A permissão nova "Ver respostas do quiz e onde os clientes travam" (`quiz.ver_respostas`) dá acesso às duas telas (404 para os demais).
- [x] **CA-2:** A tela de respostas de um diagnóstico mostra, por bloco, o enunciado e a resposta formatada (R$, dias, %, Sim/Não), os sócios com CPF mascarado e os avisos de consistência aceitos. Cada abertura é auditada.
- [x] **CA-3:** Nas fichas do usuário e da empresa (STORY-104), a data do diagnóstico leva às respostas, só para quem tem a permissão.
- [x] **CA-4:** O quiz registra o evento `quiz_travamento` para cada campo com erro ao avançar ou concluir, cada aviso de consistência mostrado e cada bloqueio. O evento guarda o id do campo ou da regra, o bloco e o id do rascunho, nunca o valor. Uma falha ao registrar não atrapalha o quiz.
- [x] **CA-5:** O relatório "Travamentos do quiz" (30, 90 dias ou 1 ano) mostra os erros por pergunta (vezes e quizzes afetados), os avisos e bloqueios por regra (mostrados e aceitos) e o funil iniciados × concluídos. O que foi feito pelo "Logar como" fica de fora.

## Notas do agente

- `App\Domain\Quiz\CatalogoPerguntas` é a fonte única de enunciado e texto auxiliar. Ele vira a base da edição pelo backoffice (STORY-106).
- "Mostrado" só conta a partir desta estória. Os avisos aceitos vêm de `quiz_payload.alertas_aceitos` e incluem diagnósticos anteriores.

## Testes

- `tests/Feature/Backoffice/RespostasETravamentosTest.php`
