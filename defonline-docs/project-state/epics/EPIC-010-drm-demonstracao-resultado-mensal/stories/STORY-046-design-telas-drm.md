---
story_id: STORY-046
slug: design-telas-drm
title: "Specs de tela do módulo DRM — lançamento, demonstração do mês e série histórica"
epic_id: EPIC-010
type: implementation
target_role: designer
requires_design: true
status: done
owner_agent: "designer (claude-opus-5)"
created_at: 2026-08-04
updated_at: 2026-08-06
closed_at: 2026-08-06
approved_by: Alexandro
estimated_session_size: L
design_screen_ids: [SCREEN-STORY-046-lancamento-mensal, SCREEN-STORY-046-demonstracao-mes, SCREEN-STORY-046-serie-historica]
---

# STORY-046 — Specs de tela do módulo DRM

> **Para o agente que vai executar:** você é o **Designer**. Esta estória roda em paralelo à STORY-045 (spike do Arquiteto) e precisa estar `ready` antes da STORY-047 começar.

## Contexto

Este módulo tem um problema de desenho maior que o problema de código. São até 36 campos monetários por mês, doze vezes por ano, preenchidos por alguém que não gosta de planilha e que está fazendo isso no celular, à noite, depois de fechar a loja. **Se a tela de lançamento for uma lista de 36 campos, o módulo morre no segundo mês** — e a métrica primária do épico (2º lançamento em 45 dias) é justamente o detector desse fracasso.

O PDR-003 já fixou os três mecanismos anti-atrito. Seu trabalho é fazê-los funcionar visualmente.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` (integral); `epics/EPIC-010-*/design/estrutura-drm.md`; `decisions/pdr/PDR-003-*.md`; `especificacao/V2/design-system.md`; `decisions/idr/IDR-008-*` (tokens)
- Referência viva: telas do Quiz e do relatório de Diagnóstico já em produção — reaproveite padrões, não invente dialeto novo.

## O quê

Produzir **três specs de tela** usando `defonline-docs/skills/designer/templates/screen-spec.md`, mais os DDRs que as decisões exigirem.

### Tela 1 — Lançamento mensal

O centro do problema. Precisa resolver, visualmente:

- **Escolha de modo por grupo** (Anexo K §K.5). Como o usuário entende, em 3 segundos, que pode informar só o total de "despesas administrativas" em vez de 21 itens — e o que ele perde ao fazer isso. A escolha não pode parecer um atalho preguiçoso; é uma opção legítima.
- **Pré-preenchimento com o mês anterior.** Como um campo "repetido do mês passado" se distingue de um campo confirmado, sem poluir a tela com badges em 30 campos. Como o usuário confirma tudo de uma vez.
- **Progresso e retomada.** O usuário vai sair no meio. Onde ele estava, quanto falta, como volta.
- **Adaptação por setor** — Serviços vê menos campos, e isso deve parecer natural, não incompleto.
- **Alertas e bloqueios** (§K.6), diferenciando o que impede o fechamento do que só pede confirmação.

### Tela 2 — Demonstração do Mês

Saída principal. Precisa carregar simultaneamente **valor nominal e percentual sobre a receita** — o pedido literal do Key User — sem virar planilha.

- Hierarquia visual entre linhas totalizadoras (L1, L3, L5, L7, L9, L11) e subtotais (L6a, L6b, L8a, L8b). Ver `design/estrutura-drm.md` §1.
- Onde os três números que importam ficam: **quanto vendeu**, **quanto sobrou antes da retirada**, **quanto sobrou depois**. O usuário deve encontrá-los sem ler a tabela inteira.
- Estado de indisponibilidade (mês sem faturamento): percentuais ausentes, valores nominais presentes, motivo explicado sem parecer erro do sistema.
- Estado de prejuízo. É informação, não falha — e não pode parecer que o app está brigando com o usuário.
- Espaço reservado para farol e leitura (STORY-054), sem que a tela pareça incompleta antes deles.
- Mobile-first: tabela densa em desktop, cards empilhados em mobile — mesmo padrão do relatório de Diagnóstico.

### Tela 3 — Série histórica

- Lista de competências, com estados distintos: lançada, retificada, **não lançada** (≠ sem movimento), rascunho em aberto.
- Variação vs. mês anterior e vs. média móvel, incluindo os estados de indisponibilidade (`design/estrutura-drm.md` §6).
- Marcação de mês atípico (alerta A4) legível como explicação, não como advertência.
- Entrada para lançar o mês faltante e para retificar um mês fechado.
- Sem gráfico obrigatório no MVP. Se propuser um, justifique o ganho de leitura no spec.

## Por quê

Conecta com as duas primeiras métricas do épico: recorrência (2º lançamento) e atrito (≥70% de conclusão, ≤5 min do 2º mês em diante). Nenhuma das duas é ganha no backend.

## Critérios de aceite

- [x] **CA-1:** Três specs publicados em `epics/EPIC-010-*/design/`, seguindo o template, com mobile e desktop.
- [x] **CA-2:** O spec de lançamento apresenta **solução explícita para cada um dos três mecanismos anti-atrito** do PDR-003, com o raciocínio de por que aquela solução reduz esforço percebido.
- [x] **CA-3:** A Demonstração do Mês exibe nominal e percentual em cada linha, com hierarquia visual clara entre os três níveis.
- [x] **CA-4:** Todos os estados de indisponibilidade e alerta do Anexo K §K.6 e §K.7 estão especificados — nenhum estado fica "a definir na implementação".
- [x] **CA-5:** Só componentes e tokens do design system existente. Componente novo exige justificativa no spec e entra no DS. **Nenhum hex literal** (IDR-008).
- [x] **CA-6:** Acessibilidade AA: contraste ≥ 4.5:1, alvo de toque ≥ 44×44px, farol nunca só por cor, navegação por teclado no formulário longo, ordem de tabulação coerente. *(Com duas ressalvas de produção declaradas nas Notas — não criadas por esta estória.)*
- [x] **CA-7:** DDR registrado para cada decisão de design com efeito além destas três telas.
- [ ] **CA-8:** Specs revisados com o PO antes de `ready`. **← único CA em aberto; depende do PO.**

## Fora de escopo

- Implementar qualquer coisa.
- Telas de exportação (STORY-055) e de retificação além do ponto de entrada (STORY-050).
- Redação final de rótulos e textos de ajuda — é o pacote editorial da EBC (STORY-052). Use texto provisório e marque como tal.

## Padrões de qualidade exigidos

`defonline-docs/skills/designer/SKILL.md` e `references/`. O entregável é o spec, não código.

## Dependências

- **Bloqueada por:** nada. Roda em paralelo à STORY-045.
- **Bloqueia:** STORY-047, STORY-049, STORY-051.

## Decisões já tomadas (não as reabra)

- PDR-003, decisões 1, 4, 5 e 6.
- Anexo K — quais campos existem e quando aparecem.
- `design/estrutura-drm.md` — quais linhas a demonstração tem e em que ordem.
- IDR-008 — tokens em `tokens.css`.

## Definição de Pronto

- [ ] CA-1 a CA-8 atendidos. *(CA-1 a CA-7 ✅; CA-8 depende da revisão do PO.)*
- [x] `index.json` atualizado (seção `design.screens`).
- [x] Notas do agente preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

> Executada em 2026-08-04 pelo Designer (`claude-opus-5`). **Entregue e aguardando revisão do PO (CA-8).**
> Specs em `status: draft` e DDRs em `status: proposed` — sobem para `ready`/`accepted` **só com aprovação humana explícita**. A invariante 12 do índice exige spec `ready` antes de a `STORY-047` codificar UI, então essa aprovação é o que destrava o Checkpoint 1.

### Entregáveis

| Artefato | Caminho |
|---|---|
| Tela 1 — Lançamento mensal | `epics/EPIC-010-*/design/STORY-046-lancamento-mensal.md` |
| Tela 2 — Demonstração do Mês | `epics/EPIC-010-*/design/STORY-046-demonstracao-mes.md` |
| Tela 3 — Série histórica | `epics/EPIC-010-*/design/STORY-046-serie-historica.md` |
| DDR-001 a DDR-005 | `decisions/ddr/` |
| Design System vivo (inicializado) | `design/system/` — README, tokens, components, patterns, voice-and-tone |

### Decisões tomadas

**Os três mecanismos anti-atrito (CA-2).**

1. **Modo agregado — o número de campos é o argumento** (DDR-002). Seletor de duas opções no cabeçalho de cada bloco de grupo, com o custo declarado **na própria opção**: `Informar o total · 1 campo` contra `Detalhar por item · 21 campos`. O custo é contado **depois** da supressão por setor, o que resolve a adaptação por setor de graça — Serviços lê "2 campos" onde Indústria lê "4", e ninguém precisa explicar a ausência. Rótulos factuais, nunca "Rápido/Completo": o Anexo K §K.5 proíbe culpabilizar quem escolhe agregado, e adjetivo de valor faria exatamente isso. **Por que reduz esforço percebido:** o usuário decide o tamanho do bloco antes de ver 21 campos, e decide com o número na frente em vez de com uma intuição.

2. **Pré-preenchimento — a marca some ao conferir, e o silêncio é o progresso** (DDR-003). Marcar 21 campos com badge é ruído que ninguém lê. A inversão: o usuário não precisa saber o que veio de julho — precisa saber **o que ainda não olhou**. Legenda mínima (`veio de julho`) que **desaparece ao editar ou confirmar**; o que resta marcado é exatamente o que falta. **Por que reduz esforço percebido:** a tela converge para o silêncio conforme o trabalho avança, então o progresso fica visível sem nenhum indicador adicional — e o caminho de 2 toques do mês sem alterações (`Conferir tudo` → conferência → `Fechar`) ataca a meta de ≤5 min. Ele desemboca na **conferência**, nunca no fechamento: confirmar em massa não pode ser o último ato.

3. **Rascunho e retomada — o bloco é o ponto de corte** (DDR-001). Wizard de 6 blocos, salvo ao concluir cada um. A retomada nomeia o bloco (`você parou em Despesas administrativas`), não o número. **Por que reduz esforço percebido:** "onde eu parei" passa a ter resposta exata em vez de aproximada, e o rodapé permanente (*"pode sair e voltar depois"*) torna o abandono seguro — abandono seguro é pré-requisito de retomada.

**Decisões estruturais.**

- **Nenhum dialeto novo de formulário.** O padrão do Quiz em produção foi promovido a DDR-001 e virou o padrão único do produto. Os três blocos inline quase idênticos do `quiz.blade.php` (retomada, alertas cruzados, bloqueios) foram consolidados no componente `aviso-inline`, com a regra que faltava: **a ausência de botão de seguir em frente é o que faz de um bloqueio um bloqueio.**
- **Nenhum diálogo modal introduzido.** A confirmação destrutiva de troca de modo é bloco inline — o DS não tem modal, e um formulário longo em celular não precisa de foco-trap onde um bloco resolve.
- **Cards por trecho da conta, não por linha** (DDR-004). O CA-9 da `STORY-049` pede "cards empilhados no mobile, como o relatório de Diagnóstico". Copiar literalmente quebraria a aritmética: os 13 indicadores do Diagnóstico são objetos independentes, mas as linhas da demonstração são elos de uma conta — uma borda entre `(–) Custo` e `= Lucro Bruto` corta a subtração que o usuário precisa ver. Cortar por trecho (cada card fecha num totalizador) entrega cards empilhados, reduz 17 cards a 5 e preserva a conta.
- **Prejuízo sem cor** (DDR-004, item 6). Sinal aritmético + troca de verbo no destaque (`SOBROU` → `FALTOU`) + `pill` neutro. Vermelho no produto já significa erro (`destructive`) ou farol (`farol-vermelho`, que é interpretação e é da `STORY-054`); prejuízo não é nenhum dos dois.
- **Farol e leitura são ausentes, não vazios.** Coluna com cabeçalho e sem conteúdo é literalmente o que "parecer incompleto" significa. Antes da `STORY-054` a tabela tem 3 colunas; depois, o farol entra como badge dentro da célula de rótulo e a leitura num card acima — **nenhum dos dois muda a grade**.
- **Sem gráfico na série histórica**, com justificativa e gatilho de reabertura no spec §5. Razão decisiva: a série tem lacunas por desenho, e um gráfico de linha **desenha uma reta onde não há dado** — é a "queda fantasma" do `estrutura-drm` §4 em forma visual, no produto que proibiu `COALESCE(…, 0)` no banco justamente para evitá-la.
- **Variações sem cor** na série. Queda de custo é boa, queda de lucro é ruim: a direção não tem semântica fixa, e verde/vermelho estariam errados metade do tempo.

### Descobertas

1. **`--color-neutral` como fundo de input já significa `disabled`** (`app.css`). Isso derrubou a solução mais óbvia para marcar campo herdado — 21 campos editáveis pareceriam bloqueados, o pior mal-entendido possível num formulário. Foi o que levou à solução textual do DDR-003.

2. **Duas combinações de token reprovam AA por margem fina, e são fáceis de usar por engano.** `tertiary` sobre `neutral` = **4,45:1**; `destructive` sobre `neutral` = **4,45:1** (ambas passam sobre branco, com 4,70:1). Consequência concreta no `seletor-modo`: a opção selecionada usa `tertiary` **só** em borda e marcador, com o rótulo em `primary`. Registrado em `design/system/tokens.md` como restrição vinculante.

3. **A garantia "ausência ≠ zero" do `ADR-007` não tinha correspondente na tela.** O modelo gastou espinha de meses na leitura e proibição de `COALESCE` para torná-la impossível de violar no banco — mas uma garantia que não chega ao pixel não é garantia, porque a tela é a única superfície onde o usuário encontra o dado. Daí o DDR-005 e os **quatro canais simultâneos** (borda tracejada, ausência de área de valor, rótulo textual, presença de CTA) que separam mês não lançado de mês zerado. Nenhum deles é cor.

4. **Herança parcial é estado frequente, não caso de borda.** Grupo agregado no mês anterior e detalhado agora ⇒ os itens não têm de onde herdar, e o total **não pode** ser distribuído entre eles (`ADR-007`). Estava fora de todos os documentos. Desenhado no spec §4.6: campos vazios, sem marca, com o total anterior exibido como referência de ordem de grandeza — é o máximo que se pode dizer sem inventar a distribuição.

5. **A contagem de campos.** A estória cita "36 campos" duas vezes; o Anexo K **v1.1** (pós-`PDR-004`, que aprovou D41/D42) fixa **38**. Não é divergência de escopo — **36 é o número correto para o setor Serviços** (Indústria 38, Comércio 37, Serviços 36), pela supressão de D04/D05. Os specs usam 38 como teto e declaram o número por setor onde ele importa.

6. **Zero tokens novos.** Cinco situações pareciam pedir cor nova (herança, prejuízo, mês atípico, variação, indisponibilidade) e nenhuma precisou. O padrão que emergiu: **quando a informação precisava de um canal, o canal certo era texto** — o que resolve acessibilidade e economia de token de uma vez.

### Bloqueios encontrados

Nenhum. As três telas foram especificadas integralmente.

Duas restrições contornadas sem escalar:

- **Sem sync prévio com o Programador** (`references/collaboration-with-developer.md` prevê um): a `STORY-047` ainda não tem `owner_agent`. As restrições técnicas vieram do `ADR-007` e da leitura do código do Quiz em produção. Os specs declaram as premissas para que o sync aconteça na abertura da `STORY-047`, não depois.
- **Invariante 9 do índice, leitura literal.** Ela pede entrada em `design.screens[]` com `story == story.id` para cada estória com `requires_design`. Como as três specs nasceram de uma estória de design dedicada (`STORY-046`) e não do modelo paralelo que o `PDR-002` assumiu, as entradas têm `story: STORY-046` + `implemented_by: STORY-047/049/051`, e as estórias consumidoras receberam `design_screen_id` (invariante 10 ✅). A intenção protetiva da invariante 9 está atendida; **a leitura literal, não.** Não editei o esquema — é do PO. Item 5 da escalação abaixo. *(Observação: a invariante 9 já está frouxa hoje — `STORY-044` está `done` com `requires_design: true` e sem entrada.)*

### Escalado ao PO — 5 itens

| # | Item | Por quê é do PO, não meu |
|---|---|---|
| 1 | **Default do modo dos grupos no 1º lançamento.** Proponho `detalhado`, pelo argumento da irreversibilidade: quem chega sem histórico não sabe o que perde ao agregar, e agregar é irreversível para aquele mês. **Contra-argumento honesto:** 38 campos no primeiro contato é o pior momento possível para o atrito, e o 1º lançamento é a porta de entrada do módulo | É escolha de produto entre atrito e granularidade. Custo de implementação idêntico nos dois casos |
| 2 | **`<x-help>` tem alvo de toque de 18×18px**, contra o piso de 44px. Está em produção no Quiz e seria herdado pelo DRM, que usa `<x-help>` em dezenas de campos — multiplicando o problema. É **bug**, não decisão. Sugiro anexar à `STORY-059`, que já é a estória de dívida de acessibilidade | Não é criado por esta estória e não cabe no `EPIC-010` |
| 3 | **Borda de input mede 1,23:1**, contra os 3:1 da WCAG 1.4.11 para limite de componente interativo. Atinge todas as telas de formulário do produto. Corrigir mexe num token da fundação e **exige DDR próprio**. Sugiro avaliar junto com a `STORY-059` | Idem |
| 4 | **Proposta ao Arquiteto / `STORY-048`:** acrescentar `tipo` (`total\|deducao\|subtotal\|item`) e `destaque` (bool) às linhas do snapshot. Hoje duas regras da Tela 2 (quais linhas são os três destaques; onde um card de trecho fecha) dependem de configuração porque o snapshot não diz o suficiente. **O motor não foi construído**, então o custo agora é ~zero; depois seria mudança em snapshot imutável — o beco do `IDR-013`. **A tela não depende disso**: a regra por configuração está especificada e tem fallback que não quebra | Contrato do motor é do Arquiteto |
| 5 | **Invariante 9 do índice** — ver acima. Esquema é do PO | Não edito esquema sem PDR |

Três decisões conscientes de **não fazer**, registradas para que sejam deliberadas e não esquecimento: sem ação de descartar rascunho (ele expira); sem paginação ou filtro por ano na série (24 competências cabem numa lista); sem gráfico no MVP (justificado no spec, com gatilho de reabertura).

### DDRs criados

Todos em `status: proposed` — **nenhum vai para `accepted` sem aprovação humana explícita**.

| DDR | Título | Escopo |
|---|---|---|
| **DDR-001** | Formulário longo é wizard por blocos com rascunho por bloco — dialeto único do produto | formulários |
| **DDR-002** | Entrada em dois níveis — seletor de modo por grupo, com o custo em campos declarado na escolha | formulários |
| **DDR-003** | Valores herdados do período anterior — a marca some ao confirmar, e o silêncio é o progresso | formulários |
| **DDR-004** | Demonstração financeira — nominal + percentual, três níveis, cards por trecho da conta | dados financeiros |
| **DDR-005** | Vocabulário visual de indisponibilidade — "não dá para calcular", "não existe" e "é zero" | **transversal** |

### Design System

Inicializado em `design/system/` (não existia — `index.json` marcava `status: canonical_only`). **Herda** do DS canônico em `especificacao/V2/design-system.md` v1; `tokens.md` e `voice-and-tone.md` são **ponteiros** que carregam só o que não está nas fontes canônicas — duplicar a tabela de tokens criaria uma terceira verdade.

- **12 componentes** em produção registrados · **5 especificados** (`progresso-blocos`, `aviso-inline`, `seletor-modo`, `demonstracao-linha`, `serie-linha-competencia`) + o estado `herdado` do `campo-monetario` e a variante `link` do `button`
- **4 padrões compostos:** `formulario-longo`, `pre-preenchimento-periodo-anterior`, `demonstracao-financeira`, `indisponibilidade`
- **0 tokens novos** · **3 dívidas de acessibilidade do produto** documentadas com evidência numérica em `tokens.md`


## Decisão do PO — 2026-08-06

Registrada em `decisions/pdr/PDR-007-aprovacao-specs-drm-e-motor-exportacao.md`. A estória fecha como `done`.

**Aprovado (§1):** as três specs sobem de `draft` para `ready` e os `DDR-001` a `DDR-005` de `proposed` para `accepted`. Com isso a `STORY-047`, a `STORY-049` e a `STORY-051` estão destravadas — a invariante 12 está satisfeita e o Checkpoint 1 pode abrir.

**Item 1 da escalação — default do modo no 1º lançamento (§2):** `detalhado`, confirmando a recomendação. O argumento da irreversibilidade venceu: agregar é irreversível para aquele mês, reduzir depois não é. O contra-argumento (38 campos no primeiro contato) ficou registrado como gatilho de reabertura, atrelado à métrica primária do épico.

**Itens 2 a 5 da escalação:** seguem abertos, deliberadamente. Nenhum bloqueia o Checkpoint 1 — a proposta de `tipo`/`destaque` ao Arquiteto, as duas dívidas de acessibilidade sugeridas para a `STORY-059` e a leitura literal da invariante 9 do índice.
