---
epic_id: EPIC-XXX
slug: nome-curto-do-epico
title: Título humano do épico
wave: WAVE-YYYY-NN
status: draft  # draft | ready | in_progress | in_review | done | abandoned
owner_role: po
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
target_completion: YYYY-MM-DD  # estimativa, não compromisso rígido
---

# EPIC-XXX — <título humano>

## Por que existimos (problema do usuário)

<Descreva o problema do usuário em 1–2 parágrafos. Foque na dor, não na solução. Cite a persona ou o job-to-be-done.>

## Resultado esperado (outcome)

<Frase única: "ao fim deste épico, <persona> consegue <ação> e percebe <valor>".>

## Métrica de sucesso (como saberemos que funcionou)

- Métrica primária: <ex: 70% dos usuários que iniciam o fluxo concluem>
- Métrica de qualidade: <ex: zero erros 5xx em produção durante a primeira semana>

## Entregável visível no fim do épico

<O que estará disponível em homologação/produção ao final. Tem que ser observável por um humano.>

- [ ] <ex: tela de login funcional acessível em https://homolog.defonline.com.br>
- [ ] <ex: usuário cadastrado consegue subir uma planilha exemplo e ver o diagnóstico>

## Fora de escopo (explicitamente)

<Liste o que poderia ser confundido como parte do épico mas NÃO está incluso. Evita escopo crescente.>

- <item fora de escopo>

## Referências da especificação

- `defonline-docs/especificacao/V2/especificacao-funcional.md` — seções <X.Y>, <X.Z>
- `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` — seções aplicáveis
- <outros documentos>

## Dependências

- **Bloqueia:** <épicos que dependem deste>
- **Bloqueado por:** <épicos/decisões que este precisa antes de iniciar>
- **Decisões arquiteturais necessárias:** <liste ADRs que precisam existir antes; se não existirem, abra spike>

## Estórias

(Preenchido durante o Fluxo B. Cada estória vira um arquivo em `stories/`.)

- [ ] STORY-XXX — <título>
- [ ] STORY-XXX — <título>
- [ ] STORY-XXX (validação) — Validação final do épico

## Validação final

Critérios em `validation/checklist.md`. Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** todas as estórias `done` + relatório de validação `approved` + funcionalidade demonstrável em homologação.

## Histórico

- YYYY-MM-DD — criado por PO
- YYYY-MM-DD — <mudança>
