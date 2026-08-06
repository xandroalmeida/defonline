---
story_id: STORY-061
slug: gate-de-tokens-cego-a-cor-nao-hex
title: "Gate de tokens — fechar o furo que deixa `rgba()` e `hsl()` passarem por baixo do IDR-008"
epic_id: EPIC-004
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-08-06
updated_at: 2026-08-06
estimated_session_size: S
origem: "Achado da STORY-059, aprovado pelo PO em 2026-08-06 para virar estória"
---

# STORY-061 — O gate de tokens é cego a cor que não seja hex

## Contexto

O `IDR-008` diz que hex literal de cor só existe em `tokens.css`, e o `DesignTokensTest` (STORY-019 CA-6) é o gate que faz valer. Ele funciona — e tem um buraco: **procura só por `#RRGGBB`**.

Descoberto na `STORY-059`: o badge "Versão MVP" do relatório de Diagnóstico carregava `style` inline com três `rgba(99, 91, 255, …)` — a cor do `--color-tertiary` escrita à mão, em produção, passando pelo gate sem um arranhão. O badge foi corrigido na própria `STORY-059`; **o furo do gate não**, porque nenhum critério de aceite daquela estória pedia isso e ampliar escopo em cima de achado é como estória vira sprint.

O risco não é hipotético: foi assim que o drift aconteceu uma vez.

- Épico: `epics/EPIC-004-app-shell-navegacao/epic.md`
- Ler antes: `decisions/idr/IDR-008-*`; `app/tests/Unit/Arch/DesignTokensTest.php`; notas finais da `STORY-059`

## O quê

Estender o gate para reprovar cor literal em qualquer notação, não só hexadecimal.

## Por quê

Um gate que pega uma notação e ignora as outras dá **falsa segurança**, que é pior do que gate nenhum: o time confia que a paleta está protegida e ela não está. Trocar um token deveria propagar num arquivo; com `rgba()` espalhado, vira busca-e-substitui — exatamente o que o `IDR-008` existe para impedir.

## Critérios de aceite

- [ ] **CA-1 (Notações cobertas):** o gate reprova `rgb()`, `rgba()`, `hsl()`, `hsla()` e hex de 3 e 8 dígitos (`#abc`, `#RRGGBBAA`) em Blade e CSS fora de `tokens.css`. Hoje só `#RRGGBB` é pego.
- [ ] **CA-2 (Sem falso positivo):** `color-mix(in srgb, var(--token) 10%, var(--token))` **não** é acusado — é composição de token, não cor literal, e é o padrão adotado pelo `DDR-006` no pill de farol. `currentColor`, `transparent` e `inherit` idem.
- [ ] **CA-3 (Exclusões preservadas):** as exclusões documentadas continuam valendo — `tokens.css`, `layouts/legal.blade.php` e o template de e-mail transacional, que precisa de literal porque cliente de e-mail ignora CSS var.
- [ ] **CA-4 (Prova do furo):** teste que falha com o markup exato que passava antes (o `style` inline do badge, preservado como fixture). Sem isso não há prova de que o furo fechou.
- [ ] **CA-5 (Varredura do que já existe):** rodar o gate novo sobre o código atual e **relatar** o que ele acusa. Se houver ocorrência legítima, ela vira exclusão documentada — não silenciamento do gate.
- [ ] **CA-6 (Mensagem útil):** a mensagem de falha diz o arquivo, a cor encontrada e o token equivalente quando houver. Gate que só diz "falhou" custa 20 minutos de quem topa com ele.

## Fora de escopo

- Auditar cores em imagens, SVG externo ou dependências.
- Rever a paleta.

## Dependências

Nenhuma.

## Decisões já tomadas (não as reabra)

- `IDR-008` — tokens em `tokens.css`, sem literal fora dele.
- `DDR-006` — `color-mix` com token é padrão aprovado, não exceção a tolerar.

## Definição de Pronto

- [ ] CA-1 a CA-6 passam.
- [ ] Suíte completa verde; pipeline verde.
- [ ] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Se o CA-5 revelar muitas ocorrências legítimas, **pare e relate antes de decidir** — uma lista de exclusões longa é sinal de que a regra precisa de revisão do Designer, não de mais exceções.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### Cobertura final
