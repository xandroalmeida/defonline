---
story_id: STORY-071
slug: catalogo-planos-e-pacotes-backoffice
title: "Backoffice — gestão de planos, pacotes de créditos e trial"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: in_review
owner_agent: programador
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-071 — Backoffice — gestão de planos, pacotes de créditos e trial

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

O PO exigiu que nada do catálogo fique fixo em código (PDR-020). É a base de toda a cobrança e pode ir para produção sem efeito ao usuário.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.1 a §2.3
  - `defonline-docs/especificacao/V2/arquitetura-tecnica.md` §4.1 (Plan, CreditPackage, Setting)
  - `decisions/pdr/PDR-013-permissoes-de-admin-e-glossario-no-backoffice.md`
  - `app/app/Livewire/Backoffice/Recomendacoes.php` (padrão de tela)

## O quê

Permitir que um admin crie, edite, ordene e desative planos e pacotes, e configure o trial, em tela no backoffice.

## Por quê

A equipe testa preço e oferta sem depender de deploy.

## Critérios de aceite

- [ ] **CA-1:** A carga inicial traz Básico e Pro, os pacotes Mini, Plus e Max e o trial, com os valores do PDR-020.
- [ ] **CA-2:** Tela e ações exigem a permissão nova `planos.gerenciar`; sem ela, resposta 404 e nenhum item no menu (PDR-013).
- [ ] **CA-3:** O admin cria e edita plano: nome, cota mensal, preço mensal, preço anual, ordem e ativo.
- [ ] **CA-4:** O admin cria e edita pacote: nome, quantidade de créditos, preço, validade em meses, ordem e ativo.
- [ ] **CA-5:** O admin edita o trial: ativo, dias e análises incluídas.
- [ ] **CA-6:** Valores inválidos são recusados com mensagem por campo: preço negativo, cota ou quantidade menor que 1, trial sem dias.
- [ ] **CA-7:** Quando o unitário de um pacote fica abaixo do unitário mensal de um plano ativo, a tela avisa e permite salvar.
- [ ] **CA-8:** Toda alteração vai para `audit_logs` com antes, depois e autor. Sem mudança real, nada é gravado.
- [ ] **CA-9:** Teste arquitetural impede que o código de aplicação compare com códigos de plano ou pacote específicos.

## Fora de escopo

- Excluir plano ou pacote: só desativar.
- Efeito sobre assinaturas vigentes: o preço delas é um snapshot (STORY-077).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** nenhuma
- **Bloqueia:** STORY-072, STORY-073, STORY-075, STORY-087

## Decisões já tomadas (não as reabra)

- PDR-020: catálogo em tela, trial, Asaas substituível, Termos `v2`, DRM em planos pagos e no trial, e cupons.

## Liberdade técnica

Você decide estrutura de código, tabelas, bibliotecas dentro dos ADRs e desenho dos testes. Não decide critérios de aceite nem padrões de qualidade. Se faltar uma decisão arquitetural, pare e registre em "Notas do agente".

## Definição de Pronto

- [ ] Todos os critérios de aceite têm teste e passam.
- [ ] `scripts/verificar.sh` verde.
- [ ] Commits em `main` local; push, PR e tag só com pedido do PO.
- [ ] IDR registrado se houve decisão técnica com impacto futuro.
- [ ] `index.json` e "Notas do agente" atualizados; status `in_review`.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Ao iniciar, atualize status e `owner_agent` aqui e no `index.json`. Se travar, use `status: blocked` e descreva o bloqueio.

## Notas do agente

### Decisões tomadas
- Tabelas `planos`, `pacotes_credito` e `trial_config` (linha única). O trial ficou como configuração separada, não como linha de `Plano` (a arquitetura-tecnica §4.1 o previa como plano `trial`): assim não aparece na lista de planos e nada precisa comparar `codigo = 'trial'` (CA-9). Para as próximas estórias: ler `TrialConfig::atual()`.
- `codigo` é gerado do nome na criação (slug, com sufixo se colidir) e nunca editado; é só identificador, o código não ramifica por ele.
- Preços em centavos inteiros; digitados em reais ("49,90", "1.599,00"). Sem máscara de JS.
- Aviso do CA-7 em `App\Domain\Cobranca\PrecoCredito` (produto cruzado, sem arredondamento), avisando no formulário (ao digitar) e na linha do pacote.
- Auditoria: ações `planos.plano_criado/alterado`, `planos.pacote_credito_criado/alterado`, `planos.trial_config_alterado`, com before/after só dos campos gravados; sem mudança real, nada é gravado nem auditado. Desativar é uma alteração de `ativo`.
- Teste arquitetural `tests/Unit/Arch/CatalogoCobrancaTest.php`: varre app, routes, views e config atrás de comparação, case, in_array, match ou where com códigos seed ou `->codigo`.

### Descobertas
- Dusk: `keys()` com `'{backspace}'` em array digita literal; usar `WebDriverKeys`.
- Rodar a suíte em paralelo com a de cobertura gera falhas espúrias (banco de teste compartilhado).

### Bloqueios encontrados
Nenhum.

### IDRs criados
Nenhum.

### Cobertura final
Suíte All 1306 passando; cobertura total 98,2%; Planos, PrecoCredito e os 3 modelos 100%.

### Evidências
`tests/Feature/Livewire/Backoffice/PlanosTest.php` (CA-1 a CA-8), `tests/Unit/Domain/Cobranca/PrecoCreditoTest.php` (CA-7), `tests/Unit/Arch/CatalogoCobrancaTest.php` (CA-9), `tests/Browser/PlanosBackofficeBrowserTest.php` (E2E). Pint e PHPStan limpos.
