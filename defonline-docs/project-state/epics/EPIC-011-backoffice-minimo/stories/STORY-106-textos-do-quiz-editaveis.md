---
story_id: STORY-106
slug: textos-do-quiz-editaveis
title: "Textos das perguntas do quiz editáveis no backoffice"
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

# STORY-106 — Textos do quiz editáveis

Origem: item 8 da devolutiva do PO (2026-11-18). A equipe quer melhorar continuamente o texto das perguntas, para simplificar e clarear a leitura, sem depender de deploy. O PO decidiu entregar junto com a STORY-104 e a STORY-105.

## Critérios de aceite

- [x] **CA-1:** A tela "Textos do quiz" (permissão nova `quiz.editar_textos`) lista, por bloco, o enunciado, o texto auxiliar e a ajuda (?) de cada pergunta e marca as editadas.
- [x] **CA-2:** Ao editar, a equipe vê uma prévia de como o campo aparece no quiz, inclusive a ajuda com **negrito**. Há limites de tamanho e o enunciado não pode ficar vazio.
- [x] **CA-3:** O quiz passa a mostrar o texto salvo para o cliente. As respostas e o relatório de travamentos (STORY-105) usam o mesmo texto.
- [x] **CA-4:** "Restaurar original" volta ao texto do código. Salvar um texto igual ao original não guarda edição.
- [x] **CA-5:** Cada alteração e cada restauração é auditada (antes e depois), e o histórico aparece na edição.
- [x] **CA-6:** Mudar o texto não muda o cálculo, as validações nem os diagnósticos já feitos.

## Notas do agente

- `quiz_textos` guarda só o que foi editado. Os originais continuam em `CatalogoPerguntas` e `config/quiz/help-industria.php`. `App\Support\TextosQuiz` junta os dois, fica em cache até a próxima edição e é lido uma vez por requisição.
- A auditoria grava o campo (Q08, socios) em `context.campo`, porque `subject_id` é uuid.
- Fora do escopo: os textos fixos do quiz (títulos de bloco, o aviso "Esta versão atende apenas Indústria", nomes dos campos nas mensagens de erro).

## Testes

- `tests/Feature/Backoffice/TextosQuizTest.php`
- `tests/Browser/BackofficeConsultaQuizBrowserTest.php` (busca por CPF → ficha → empresa → respostas → editar texto → cliente vê no quiz)
