---
story_id: STORY-059
slug: a11y-app-shell-e-contraste-farol
title: "Acessibilidade — nome acessível no toggle de conta, drawer inerte, contraste dos pills de farol, alvo de toque do help e borda de input"
epic_id: EPIC-004
sprint_id: SPRINT-2026-W32
type: bugfix
target_role: programador
requires_design: true
status: in_review
owner_agent: programador + designer (agente Claude — sessão W32 fila 4.1)
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

- [x] **CA-1 (Nome acessível no toggle de conta):** o botão de conta do cabeçalho tem nome acessível. Hoje é só ícone. O gate deixa de acusar `button-name`.
- [x] **CA-2 (Drawer fechado não recebe foco):** com o drawer de navegação fechado, seus filhos saem da ordem de tabulação e da árvore de acessibilidade. O gate deixa de acusar `aria-hidden-focus`. Navegar por teclado com o drawer fechado nunca leva o foco para dentro dele.
- [x] **CA-3 (Contraste dos pills de farol):** os pills `Verde`, `Amarelo` e `Vermelho` e o badge de versão atingem contraste ≥ 4.5:1. **A solução é decisão do Designer**, não do programador — pode exigir ajuste de token, e ajuste de token afeta o produto inteiro.
- [x] **CA-4 (Sem hex literal):** qualquer mudança de cor acontece nos tokens, nunca em hex literal no Blade — `IDR-008`. O teste arquitetural de tokens continua verde.
- [x] **CA-5 (Semântica do farol preservada):** o farol continua comunicando por cor **e** ícone **e** texto. Corrigir contraste não pode simplificar o componente.
- [x] **CA-6 (Gate passa):** a página do relatório de diagnóstico atinge a pontuação exigida no gate de acessibilidade. Evidência: relatório antes e depois.
- [x] **CA-7 (Sem regressão visual):** o farol continua legível e distinguível nas três cores, em desktop e mobile. Evidência: captura das três variantes.
- [x] **CA-8 (Testes):** teste cobrindo nome acessível do toggle e ausência de foco no drawer fechado. Se houver ajuste de token, o teste arquitetural cobre a ausência de hex literal.
- [x] **CA-9 (Alvo de toque do `<x-help>`):** `.help__trigger` mede hoje **18×18px**, contra o piso de **44×44px** (`accessibility-basics` §7). A área de toque atinge 44×44px **sem aumentar o círculo visível** — padding transparente, não ícone maior. Vale em todas as telas que já usam o componente, o Quiz inclusive. **É bug, não decisão de design:** não precisa de DDR.
- [x] **CA-10 (Contraste da borda de input) — depende de DDR do Designer:** `--color-input` (#E3E8EE) sobre `--color-surface` (#FFFFFF) mede **1,23:1**, contra os **3:1** que a WCAG 2.1 §1.4.11 pede para o limite de um componente interativo — e a borda é o que identifica um campo como campo. Atinge `.input` e `.input-affix`, ou seja, **todas as telas de formulário do produto**. Como o CA-3, **a solução é do Designer e exige DDR próprio**: mexer no token da fundação reavalia telas em produção. Se o DDR não estiver pronto quando os demais CAs fecharem, **entregue os outros e deixe este explicitamente aberto nas notas** — não segure a estória inteira por ele.

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

- [x] CA-1 a CA-9 passam. **O CA-10 não ficou aberto:** o `DDR-007` foi ratificado pelo PO em 2026-08-06 e aplicado no mesmo ciclo.
- [x] Suíte completa verde local (949 Pest + 35 Dusk); Pint e PHPStan limpos. Gate de acessibilidade reexecutado **em ambiente local**: 100 (Lighthouse 12.8.2). **Deploy e gate em homologação pendentes** — dependem de tag, que só o PO cria (ver "Bloqueios encontrados").
- [x] `index.json` atualizado; notas do agente preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Se o CA-3 ou o CA-10 exigirem mudança de token que afete outros componentes, **pare e registre** — mudança de design system é decisão do Designer, com DDR. Evidência numérica dos dois achados novos está em `design/system/tokens.md` §*Dívidas de acessibilidade*.

## Notas do agente

### Decisões tomadas

**Os cinco achados e o que cada um virou:**

| CA | Achado | Correção |
|---|---|---|
| CA-1 | `button-name` no toggle de conta | `aria-label="Menu da conta de <primeiro nome>"` no botão. Os três filhos eram inúteis como nome: a inicial e o chevron são `aria-hidden`, e a saudação some abaixo de 640px |
| CA-2 | `aria-hidden-focus` no drawer | `inert` + `aria-hidden`, aplicados por `sincronizarInerte()` a partir de `navFechado` (estreito **e** fechado) |
| CA-3 | Contraste dos pills e do badge | `DDR-006` — tinta separada do pigmento, e fundo do pill opaco |
| CA-9 | `<x-help>` com alvo de 18px | Pseudo-elemento transparente de 44×44px centrado no botão |
| CA-10 | Borda de campo a 1,23:1 | `DDR-007` — `--color-input` para `#7C8798` |

**1. O CA-2 tinha um segundo defeito, espelhado, que a estória não nomeava.** O binding era `:aria-hidden="navOpen ? 'false' : 'true'"`, e `navOpen` é false **também no desktop**, onde a navegação é sidebar fixa e visível. Ou seja: além de deixar o drawer fechado focável no celular, o app escondia do leitor de tela, em toda página autenticada, uma navegação que estava ali à vista. Corrigir só o lado acusado pelo gate teria deixado o outro em pé. `navFechado` (estreito **e** fechado, via `matchMedia` no mesmo breakpoint do CSS) responde à pergunta certa; há teste de browser para os dois lados.

**2. `inert` e `aria-hidden` andam juntos, e nenhum dos dois sozinho resolve.** `aria-hidden` esconde do leitor de tela e **deixa o Tab entrar** — é exatamente essa combinação que o gate chama de `aria-hidden-focus`. `inert` tira do foco. Aplicar os dois é o que fecha o CA-2 de verdade.

**3. CA-3 — `DDR-006`, ratificado pelo PO.** Dois tokens por farol: cor viva pinta (fundo, borda), tinta `-texto` escreve (texto, ícone). Escolhida contra escurecer o token único, que levaria o fundo junto — o amarelo viraria marrom — e escureceria sem necessidade a borda do card do Resumo Executivo e a faixa de alerta do Quiz, que consomem os mesmos tokens e não têm problema de contraste. A decisão mora em **token** porque o `tokens.md` registra que a `STORY-054` herda o slot de farol do DRM sem renegociar: token propaga sozinho, conserto no componente teria que ser refeito lá. O badge "Versão MVP" usa `--color-tertiary-hover`, que já existia — sem token novo.

**4. CA-10 — `DDR-007`, ratificado e aplicado no mesmo ciclo.** A estória autorizava deixá-lo aberto; o PO decidiu aplicar agora, e a razão de sequência pesou: a `STORY-047` traz um formulário de 38 campos, e entrar com a borda invisível multiplicaria o defeito. `--color-input` foi de `#E3E8EE` para `#7C8798` e deixou de ser sinônimo de `--color-border` — os dois tokens eram idênticos desde o DS v1, o que fazia a distinção parecer decorativa. **Trade-off assumido e declarado:** cadastro, login, nova empresa e o Quiz inteiro ficam visualmente mais densos, e o produto se afasta um passo da leveza Stripe do DS v1. É mudança visível; pede conferência do PO em homologação, não só teste verde.

**5. O DS canônico subiu para v1.1.** Regra do `tokens.md`: token novo entra em `tokens.css` **e** na especificação, com bump lá. Quatro tokens novos (tintas) + um valor alterado (`--color-input`).

### Descobertas

**O gate achou um caso que a conta em papel não pegava — e ele mudou a regra, não só o valor.** Aplicadas as tintas, o Lighthouse subiu de 85 para **96** e **um único nó** continuou reprovando: o pill dentro do cabeçalho do card do Resumo Executivo, a 4,2:1. O token estava certo; a composição é que não era. O fundo do pill era translúcido (`/10`) e aquele cabeçalho **já é** `bg-farol-verde/8` — os dois tingimentos somavam, e o mesmo componente media 4,76:1 na tabela e 4,2:1 ali dentro.

A correção foi trocar alpha por `color-mix(... , var(--color-surface))`: **cor opaca, idêntica em qualquer superfície**. A regra que fica, registrada no `DDR-006` e no `tokens.md`, vale além do farol: **componente com fundo tingido usa cor opaca; contraste de componente não pode depender do que estiver atrás dele.** Gate final: **100**.

De quebra, as classes saíram do utilitário arbitrário do Tailwind para `.farol--<cor>` no `app.css` — o que elimina a dependência do scanner do Tailwind enxergar a string literal inteira, fragilidade que o próprio docblock do componente documentava.

**O badge "Versão MVP" violava o `IDR-008` e ninguém tinha visto.** Ele carregava `style` inline com três `rgba(99, 91, 255, …)` literais. O teste arquitetural não pegava porque procura **hex**, e `rgba()` passa por baixo. Virou `.pill--versao` no `app.css`, com tokens. O teste continua cego a `rgba` literal — vale como achado para o PO: é um furo do gate, não desta estória.

**O `<x-help>` não precisou de JavaScript nem de mudança de layout.** O pseudo-elemento fica fora do fluxo, então nada se desloca, e não tem conteúdo — a área ampliada não acrescenta nada à árvore de acessibilidade, só ao hit-testing. O teste de browser mede os dois lados: acerta o botão a 20px do centro, **não** acerta a 40px, que era o risco real de exagerar e roubar toque do campo vizinho.

### Bloqueios encontrados

**Deploy e gate em homologação não executados — falta de mandato, não de trabalho.** Mesma situação da `STORY-060`: o deploy nasce do push de uma tag `rc`, ato do PO. O gate foi rodado **em ambiente local**, com Lighthouse 12.8.2 (a mesma versão da medição de 85 da `STORY-040`), contra a página real do relatório com sessão autenticada — resultado **100**, categoria `accessibility`, zero auditorias reprovadas. Falta reexecutar em homologação depois do release.

Nada mais bloqueou. Os dois DDRs foram levados ao PO e ratificados na mesma sessão, então o CA-10 não precisou ficar aberto.

### Cobertura final

- Suíte completa: **949 testes Pest** (2.932 asserções) + **35 testes Dusk** (244 asserções), verdes.
- Pint: 247 arquivos, limpo. PHPStan: sem erros.
- Gate de acessibilidade (Lighthouse 12.8.2, categoria `accessibility`, página do relatório autenticada, **local**): **100** — era 85 em homologação antes da estória.
- Testes novos: 10 em `Feature/Shell/A11yAppShellTest.php` (CA-1 a CA-5), 9 em `Unit/Arch/ContrasteTokensTest.php` (CA-3, CA-9, CA-10 — razão de contraste **calculada** a partir de `tokens.css`, não comparada com string), 5 em `Browser/A11yShellBrowserTest.php` (CA-2 e CA-9 em browser real), 1 em `Browser/EvidenciaFarolBrowserTest.php` (CA-7).
- Evidência visual (CA-7): `app/tests/Browser/screenshots/story-059-farol-desktop.png`, `story-059-farol-mobile.png` e `story-059-farol-vermelho-desktop.png` — gitignored.
