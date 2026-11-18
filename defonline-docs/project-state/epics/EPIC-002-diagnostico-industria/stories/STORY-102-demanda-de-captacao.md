---
story_id: STORY-102
slug: demanda-de-captacao
title: "Demanda de captação: avisos à equipe e ao cliente, prazo de 5 dias úteis"
epic_id: EPIC-002
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

# STORY-102 — Demanda de captação

Origem: pedido do PO em 2026-11-18 (item 5 da devolutiva: mensagem de retorno em 5 dias úteis no bloco 4). Decisão em `decisions/pdr/PDR-021-demanda-de-captacao.md`.

## Critérios de aceite

- [x] **CA-1:** Com Q17 = sim, o bloco 4 mostra a autorização de contato já marcada e, enquanto marcada, o aviso de retorno em até 5 dias úteis.
- [x] **CA-2:** Concluir o diagnóstico com Q17 = sim registra uma demanda (diagnóstico, usuário, empresa, valor a captar e a autorização). Com Q17 = não, nada é registrado.
- [x] **CA-3:** Com a autorização, a equipe recebe um e-mail com a empresa, o documento, o contato do usuário e da empresa, os valores do bloco 4, os nomes dos sócios (sem CPF) e o prazo. Isso só acontece se houver e-mail cadastrado no backoffice.
- [x] **CA-4:** Com a autorização, o cliente recebe e-mail de confirmação com o prazo, e o alerta da equipe sai só com o id da demanda.
- [x] **CA-5:** Sem a autorização, nenhum aviso sai e o resultado não promete prazo.
- [x] **CA-6:** O resultado do diagnóstico confirma o recebimento e o prazo quando o contato foi autorizado.
- [x] **CA-7:** O backoffice tem a tela Captação (permissão própria): o e-mail da equipe, que pode ficar em branco e é auditado, e a lista das demandas com contato, valor, autorização e data limite.
- [x] **CA-8:** Uma falha ao registrar a demanda não impede o diagnóstico. Uma nova tentativa do aviso não repete e-mail já enviado.

## Fora de escopo

- Fila com status e responsável.
- Feriados no cálculo do prazo.

## Testes

- `tests/Domain/Captacao/PrazoRetornoTest.php`
- `tests/Feature/Captacao/DemandaCaptacaoQuizTest.php`
- `tests/Feature/Captacao/AvisarDemandaCaptacaoTest.php`
- `tests/Feature/Captacao/BackofficeCaptacaoTest.php`
- `tests/Browser/DemandaCaptacaoBrowserTest.php`

## Notas do agente

- A autorização fica no rascunho (`autoriza_contato_captacao`), fora do payload canônico do motor.
- Os avisos saem em job (`AvisarDemandaCaptacao`) despachado depois do commit. `equipe_avisada_em` e `cliente_avisado_em` impedem que um retry repita o envio.
