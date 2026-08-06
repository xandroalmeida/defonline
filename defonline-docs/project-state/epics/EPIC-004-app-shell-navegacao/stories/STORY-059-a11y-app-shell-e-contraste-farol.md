---
story_id: STORY-059
slug: a11y-app-shell-e-contraste-farol
title: "Acessibilidade — nome acessível no toggle de conta, drawer inerte e contraste dos pills de farol"
epic_id: EPIC-004
sprint_id: SPRINT-2026-W32
type: bugfix
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: S
origem: "Escalação da STORY-040, decidida no PDR-006 §1"
---

# STORY-059 — Acessibilidade do app shell e contraste do farol

## Contexto

A `STORY-040` entregou os destaques clicáveis do Resumo Executivo com todos os seus critérios verdes, mas o gate de acessibilidade da página inteira reprovou por três achados que **não são dela**: dois nasceram no app shell do EPIC-004 e um está nos tokens do design system.

O `PDR-006` §1 decidiu fechar a `STORY-040` e trazer a dívida para cá, com dono e título próprios — em vez de deixá-la escondida dentro de uma estória cujo nome não fala dela.

- Épico: `epics/EPIC-004-app-shell-navegacao/epic.md`
- Ler antes: `decisions/pdr/PDR-006-escalacoes-sprint-w32.md` §1; as notas finais da `STORY-040`, que trazem o relatório do gate com os pesos de cada achado; `especificacao/V2/design-system.md`; `decisions/idr/IDR-008-*` (tokens)

## O quê

Corrigir os três achados de acessibilidade para que a página do relatório de diagnóstico passe no gate.

## Por quê

O farol é o elemento visual mais importante do relatório, e a especificação exige explicitamente que ele não dependa só de cor. Contraste insuficiente ataca justamente o usuário que já tem dificuldade de distinguir as cores — o mesmo que a exigência de cor + ícone pretendia proteger.

Os dois achados de app shell afetam qualquer pessoa que navegue por teclado ou leitor de tela, em **todas** as páginas autenticadas, não só nesta.

## Critérios de aceite

- [ ] **CA-1 (Nome acessível no toggle de conta):** o botão de conta do cabeçalho tem nome acessível. Hoje é só ícone. O gate deixa de acusar `button-name`.
- [ ] **CA-2 (Drawer fechado não recebe foco):** com o drawer de navegação fechado, seus filhos saem da ordem de tabulação e da árvore de acessibilidade. O gate deixa de acusar `aria-hidden-focus`. Navegar por teclado com o drawer fechado nunca leva o foco para dentro dele.
- [ ] **CA-3 (Contraste dos pills de farol):** os pills `Verde`, `Amarelo` e `Vermelho` e o badge de versão atingem contraste ≥ 4.5:1. **A solução é decisão do Designer**, não do programador — pode exigir ajuste de token, e ajuste de token afeta o produto inteiro.
- [ ] **CA-4 (Sem hex literal):** qualquer mudança de cor acontece nos tokens, nunca em hex literal no Blade — `IDR-008`. O teste arquitetural de tokens continua verde.
- [ ] **CA-5 (Semântica do farol preservada):** o farol continua comunicando por cor **e** ícone **e** texto. Corrigir contraste não pode simplificar o componente.
- [ ] **CA-6 (Gate passa):** a página do relatório de diagnóstico atinge a pontuação exigida no gate de acessibilidade. Evidência: relatório antes e depois.
- [ ] **CA-7 (Sem regressão visual):** o farol continua legível e distinguível nas três cores, em desktop e mobile. Evidência: captura das três variantes.
- [ ] **CA-8 (Testes):** teste cobrindo nome acessível do toggle e ausência de foco no drawer fechado. Se houver ajuste de token, o teste arquitetural cobre a ausência de hex literal.

## Fora de escopo

- Auditoria de acessibilidade de outras páginas. Esta estória fecha três achados nomeados, não abre uma frente.
- Reescrever o componente de farol.
- Qualquer coisa do EPIC-010.

## Dependências

- **Bloqueada por:** nada. CA-3 precisa do Designer, mas CA-1 e CA-2 podem andar antes.
- **Bloqueia:** nada. A `STORY-040` fechou sem esperar por esta.

## Decisões já tomadas (não as reabra)

- `PDR-006` §1 — a dívida é desta estória, não da 040; e o gate **não** é reinterpretado como "escopo da estória".
- `IDR-008` — tokens em `tokens.css`, sem hex literal.
- Farol comunica por cor, ícone e texto.

## Definição de Pronto

- [ ] CA-1 a CA-8 passam.
- [ ] Pipeline verde; deploy em homologação verificado; gate reexecutado em homologação.
- [ ] `index.json` atualizado; notas do agente preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Se o CA-3 exigir mudança de token que afete outros componentes, **pare e registre** — mudança de design system é decisão do Designer, com DDR.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### Cobertura final
