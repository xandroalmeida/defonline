---
story_id: STORY-059
slug: a11y-app-shell-e-contraste-farol
title: "Acessibilidade — nome acessível no toggle de conta, drawer inerte, contraste dos pills de farol, alvo de toque do help e borda de input"
epic_id: EPIC-004
sprint_id: SPRINT-2026-W32
type: bugfix
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-06
estimated_session_size: M
origem: "Escalação da STORY-040, decidida no PDR-006 §1; ampliada com 2 achados da STORY-046 pelo PDR-008 §3"
---

# STORY-059 — Acessibilidade do app shell e contraste do farol

## Contexto

A `STORY-040` entregou os destaques clicáveis do Resumo Executivo com todos os seus critérios verdes, mas o gate de acessibilidade da página inteira reprovou por três achados que **não são dela**: dois nasceram no app shell do EPIC-004 e um está nos tokens do design system.

O `PDR-006` §1 decidiu fechar a `STORY-040` e trazer a dívida para cá, com dono e título próprios — em vez de deixá-la escondida dentro de uma estória cujo nome não fala dela.

**Ampliada em 2026-08-06 (`PDR-008` §3).** O Designer achou mais dois problemas de acessibilidade **já em produção** ao especificar as telas do DRM (`STORY-046`), pelo mesmo motivo: não nasceram da estória dele e não cabiam no EPIC-010. Viraram o CA-9 e o CA-10. O do `<x-help>` é o mais urgente dos dois: está no Quiz hoje e o formulário do DRM usa `<x-help>` em dezenas de campos — entregar a `STORY-047` antes de corrigir multiplica o defeito.

- Épico: `epics/EPIC-004-app-shell-navegacao/epic.md`
- Ler antes: `decisions/pdr/PDR-006-escalacoes-sprint-w32.md` §1; as notas finais da `STORY-040`, que trazem o relatório do gate com os pesos de cada achado; `especificacao/V2/design-system.md`; `decisions/idr/IDR-008-*` (tokens)

## O quê

Corrigir os **cinco** achados de acessibilidade nomeados: os três que reprovam o gate da página do relatório e os dois herdados de produção que o Designer levantou na `STORY-046`.

## Por quê

O farol é o elemento visual mais importante do relatório, e a especificação exige explicitamente que ele não dependa só de cor. Contraste insuficiente ataca justamente o usuário que já tem dificuldade de distinguir as cores — o mesmo que a exigência de cor + ícone pretendia proteger.

Os dois achados de app shell afetam qualquer pessoa que navegue por teclado ou leitor de tela, em **todas** as páginas autenticadas, não só nesta. Os dois achados novos são da mesma natureza: alvo de toque e borda de campo não são detalhe estético — são o que torna o produto operável para quem tem tremor, baixa visão ou dedo grande num celular pequeno.

## Critérios de aceite

- [ ] **CA-1 (Nome acessível no toggle de conta):** o botão de conta do cabeçalho tem nome acessível. Hoje é só ícone. O gate deixa de acusar `button-name`.
- [ ] **CA-2 (Drawer fechado não recebe foco):** com o drawer de navegação fechado, seus filhos saem da ordem de tabulação e da árvore de acessibilidade. O gate deixa de acusar `aria-hidden-focus`. Navegar por teclado com o drawer fechado nunca leva o foco para dentro dele.
- [ ] **CA-3 (Contraste dos pills de farol):** os pills `Verde`, `Amarelo` e `Vermelho` e o badge de versão atingem contraste ≥ 4.5:1. **A solução é decisão do Designer**, não do programador — pode exigir ajuste de token, e ajuste de token afeta o produto inteiro.
- [ ] **CA-4 (Sem hex literal):** qualquer mudança de cor acontece nos tokens, nunca em hex literal no Blade — `IDR-008`. O teste arquitetural de tokens continua verde.
- [ ] **CA-5 (Semântica do farol preservada):** o farol continua comunicando por cor **e** ícone **e** texto. Corrigir contraste não pode simplificar o componente.
- [ ] **CA-6 (Gate passa):** a página do relatório de diagnóstico atinge a pontuação exigida no gate de acessibilidade. Evidência: relatório antes e depois.
- [ ] **CA-7 (Sem regressão visual):** o farol continua legível e distinguível nas três cores, em desktop e mobile. Evidência: captura das três variantes.
- [ ] **CA-8 (Testes):** teste cobrindo nome acessível do toggle e ausência de foco no drawer fechado. Se houver ajuste de token, o teste arquitetural cobre a ausência de hex literal.
- [ ] **CA-9 (Alvo de toque do `<x-help>`):** `.help__trigger` mede hoje **18×18px**, contra o piso de **44×44px** (`accessibility-basics` §7). A área de toque atinge 44×44px **sem aumentar o círculo visível** — padding transparente, não ícone maior. Vale em todas as telas que já usam o componente, o Quiz inclusive. **É bug, não decisão de design:** não precisa de DDR.
- [ ] **CA-10 (Contraste da borda de input) — depende de DDR do Designer:** `--color-input` (#E3E8EE) sobre `--color-surface` (#FFFFFF) mede **1,23:1**, contra os **3:1** que a WCAG 2.1 §1.4.11 pede para o limite de um componente interativo — e a borda é o que identifica um campo como campo. Atinge `.input` e `.input-affix`, ou seja, **todas as telas de formulário do produto**. Como o CA-3, **a solução é do Designer e exige DDR próprio**: mexer no token da fundação reavalia telas em produção. Se o DDR não estiver pronto quando os demais CAs fecharem, **entregue os outros e deixe este explicitamente aberto nas notas** — não segure a estória inteira por ele.

## Fora de escopo

- Auditoria de acessibilidade de outras páginas. Esta estória fecha **cinco achados nomeados**, não abre uma frente.
- Reescrever o componente de farol.
- Qualquer coisa do EPIC-010.

## Dependências

- **Bloqueada por:** nada. O CA-3 e o CA-10 precisam do Designer; CA-1, CA-2 e CA-9 andam antes e sem esperar.
- **Deveria vir antes da `STORY-047`:** não é bloqueio formal, mas o CA-9 corrige um defeito que o formulário do DRM multiplicaria por dezenas de campos.
- **Bloqueia:** nada. A `STORY-040` fechou sem esperar por esta.

## Decisões já tomadas (não as reabra)

- `PDR-006` §1 — a dívida é desta estória, não da 040; e o gate **não** é reinterpretado como "escopo da estória".
- `PDR-008` §3 — os dois achados da `STORY-046` moram aqui, e o de borda de input exige DDR antes de virar código.
- `IDR-008` — tokens em `tokens.css`, sem hex literal.
- Farol comunica por cor, ícone e texto.

## Definição de Pronto

- [ ] CA-1 a CA-9 passam. O **CA-10 pode ficar aberto** se o DDR do Designer não estiver pronto — registrado nas notas, não escondido.
- [ ] Pipeline verde; deploy em homologação verificado; gate reexecutado em homologação.
- [ ] `index.json` atualizado; notas do agente preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Se o CA-3 ou o CA-10 exigirem mudança de token que afete outros componentes, **pare e registre** — mudança de design system é decisão do Designer, com DDR. Evidência numérica dos dois achados novos está em `design/system/tokens.md` §*Dívidas de acessibilidade*.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### Cobertura final
