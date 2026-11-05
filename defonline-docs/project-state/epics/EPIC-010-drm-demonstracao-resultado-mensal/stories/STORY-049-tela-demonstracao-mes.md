---
story_id: STORY-049
slug: tela-demonstracao-mes
title: "Tela da Demonstração do Mês — valor nominal e percentual em cada linha"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: programador (claude, pista DRM)
created_at: 2026-08-04
updated_at: 2026-11-05
estimated_session_size: M
checkpoint: CP1
---

# STORY-049 — Tela da Demonstração do Mês

## Contexto

Fecha o **Checkpoint 1**: com esta estória, o caminho feliz do módulo está vivo em homologação — o usuário lança um mês e vê se o negócio deu lucro. Tudo o que vem depois melhora um produto que já existe.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: spec da Tela 2 (STORY-046); `epics/EPIC-010-*/design/estrutura-drm.md`; a tela de relatório de Diagnóstico já em produção — mesma linguagem visual, mesmo padrão mobile-first, mesmos componentes.

## O quê

Renderizar a Demonstração de Resultado Mensal produzida pelo motor, com valor nominal e percentual em cada linha, hierarquia visual entre níveis, e todos os estados especificados.

## Por quê

Motor sem tela é invisível. E é aqui que o pedido do Key User — valor nominal junto ao indicador — se materializa.

## Critérios de aceite

- [ ] **CA-1 (Rota e escopo):** rota autenticada para uma competência de uma Empresa Analisada do usuário. Cross-tenant e competência inexistente retornam 404 silente (IDR-009).
- [ ] **CA-2 (Linhas e hierarquia):** todas as linhas de `design/estrutura-drm.md` §1 renderizadas na ordem canônica, com distinção visual entre totalizadores de nível 1 e subtotais de nível 2. Subtotais de grupo em modo agregado não são exibidos como itens.
- [ ] **CA-3 (Nominal e percentual):** cada linha exibe valor em reais formatado no padrão BR **e** percentual sobre a receita. Nenhuma linha exibe só um dos dois.
- [ ] **CA-3b (Troca de modo):** o usuário alterna entre os modos de pró-labore (`PDR-004`, decisão B) e a demonstração é reexibida imediatamente, incluindo rótulos, número de linhas e ordem. A troca **não recalcula nem migra nada** e vale para toda a série. O modo vigente fica visível junto à demonstração.
- [ ] **CA-3c (Linhas não operacionais):** L7b exibida quando há valor, **omitida quando `D41` e `D42` são zero**.
- [ ] **CA-4 (Destaque dos três números):** quanto vendeu, quanto sobrou antes do pró-labore e quanto sobrou depois são localizáveis sem ler a tabela inteira, conforme o spec.
- [ ] **CA-5 (Sem faturamento):** percentuais exibidos como indisponíveis com a mensagem semântica; valores nominais presentes; a tela não parece quebrada nem culpa o usuário.
- [ ] **CA-6 (Prejuízo):** resultado negativo é exibido com clareza e neutralidade — sinalizado, não dramatizado.
- [ ] **CA-7 (Alertas aceitos):** alertas que o usuário aceitou no lançamento aparecem como nota de contexto na demonstração, para que o número seja lido junto com a ressalva.
- [ ] **CA-8 (Snapshot, não recálculo):** a tela lê o resultado persistido da versão vigente. Não invoca o motor. Rodapé exibe `motor_drm_version` e `gerado_em` **do snapshot**, não da configuração atual.
- [ ] **CA-8b (Render genérico — `ADR-007`):** a tela renderiza a lista de linhas **como ela vem do snapshot**, sem hardcode de `L1..L11`. O snapshot é auto-descritivo (código, nível, rótulo, nominal, percentual, motivo) justamente para que um bump MAJOR do motor não quebre a exibição de meses antigos. Teste: um snapshot com estrutura de linhas diferente da atual renderiza sem erro.
- [ ] **CA-9 (Mobile-first):** tabela densa em desktop, cards empilhados em mobile, sem scroll horizontal em 360×800.
- [ ] **CA-10 (Acessibilidade AA):** semântica de tabela com cabeçalhos associados, contraste ≥ 4.5:1, navegação por teclado, nenhum estado comunicado só por cor.
- [ ] **CA-11 (Navegação):** breadcrumb coerente com o app shell e caminho de volta para a empresa e para o lançamento.
- [ ] **CA-12 (Testes):** feature cobrindo render dos três setores, **os dois modos de pró-labore**, mês sem faturamento, mês com prejuízo, modos agregado e detalhado, linhas não operacionais presentes e omitidas, cross-tenant; E2E percorrendo lançamento → demonstração.
- [ ] **CA-13 (Cobertura):** ≥ 80%.

## Fora de escopo

- Faróis e leitura em prosa (STORY-054) — reserve o espaço conforme o spec, sem renderizar.
- Comparativos (STORY-051), exportação (STORY-055), retificação (STORY-050).

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`.

## Dependências

- **Bloqueada por:** STORY-046, STORY-047, STORY-048.
- **Bloqueia:** STORY-051, STORY-054, STORY-055.

## Decisões já tomadas (não as reabra)

- `design/estrutura-drm.md` (ordem e conteúdo das linhas); spec da STORY-046 (layout); IDR-008 (tokens, sem hex literal); IDR-009; **ADR-007** (padrão snapshot, render genérico, troca de modo).

**Do `ADR-007`:** a troca de modo do pró-labore (CA-3b) é **escolher o outro array do snapshot** e gravar `posicao_prolabore` em `drm_preferencias` — não recalcula e não migra nada, exatamente como o `PDR-004` §B promete. `alertas_aceitos` (CA-7) vêm de coluna própria do lançamento. O selo de retificação está disponível via `versao > 1` da linha vigente, embora a UI plena seja da STORY-050.

## Definição de Pronto

- [ ] CA-1 a CA-13 passam.
- [ ] **Checkpoint 1 demonstrado em homologação:** lançar um mês e ver a demonstração, nos três setores.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Avise o PO em `in_review` — o PO grava a demo do Checkpoint 1.

## Notas do agente

### Plano (2026-11-05, antes de codar)

Lido: estória, spec `design/STORY-046-demonstracao-mes.md`, `estrutura-drm` v1.1, DDR-004, ADR-007, IDR-016, notas das STORY-047 e 048, motor (`tipo`/`destaque`/`motivo_nominal`), golden `saudavel_comercio`.

Entendimento: a tela e um Livewire de leitura sobre `drm_lancamentos.demonstracao` (nunca o motor); render generico por `tipo`/`nivel`/`destaque` do proprio snapshot; troca de modo = outro array + `drm_preferencias.posicao_prolabore`.

Plano: (1) `App\Support\Drm\DemonstracaoVisao` (view-model puro: destaques, trechos, formatacao, indisponibilidade) com configuracao em `config/drm/tela.php`; (2) Livewire `Drm\Demonstracao` substitui `DrmMesController` na rota `drm.mes`; (3) Blade com tabela (lg) e cards (mobile); (4) `.btn--link`; (5) testes Pest (view-model, feature, snapshot divergente, motor nao invocado) e Dusk 360x800 + fluxo lancamento -> demonstracao.

### Decisões tomadas

- **Livewire de leitura (`App\Livewire\Drm\Demonstracao`) na rota `drm.mes`**, no lugar do `DrmMesController` e da view mínima da STORY-047 (removidos). Não injeta o motor: lê `drm_lancamentos.demonstracao` da versão vigente. `competencia` é `#[Locked]`; empresa alheia e competência sem lançamento são o mesmo 404 (IDR-009), pelo Global Scope.
- **View-model puro `App\Support\Drm\DemonstracaoVisao`** (+ `LinhaVisao`) transforma o snapshot em texto de tela (reais BR, `−` U+2212 para negativo, percentual a 1 casa via `Percentual::formatar`). Nada é somado nem recalculado. O Blade só conhece `tipo`, `nivel` e o par nominal/percentual; não há `L1..L11` no template.
- **Destaques vêm do `destaque` do snapshot** (DDR-004 item 3, que pedia exatamente isso ao motor). Sem nenhuma marca (snapshot de outra geração), cai na regra declarativa do DDR (âncora de 100%, `L9` senão `L7`, última linha de nível 1). Texto de cada destaque e o verbo lucro/prejuízo do resultado final ficam em `config/drm/tela.php` (versionado), nunca no template.
- **Cards por trecho (mobile):** um card fecha em cada `tipo = total` (DDR-004 item 4); o nome do card é configuração indexada pelo código da linha que o fecha, com variante por modo (`dentro.L11` = "Financeiras e resultado"). Linhas depois do último total viram o card "Demais linhas"; código desconhecido nunca derruba.
- **Tabela no desktop (`hidden lg:block`) e cards no mobile/tablet (`lg:hidden`)**, duas marcações do mesmo dado como no Diagnóstico: `<table>` com `<caption>`, `th scope=col` e `th scope=row`; `<dl>` por card. Ordem de tabulação: breadcrumb, troca de modo, ressalvas, botões.
- **L8a/L8b com financeiras agregadas** (nominal `null`, `motivo_nominal = grupo_agregado`) **não são exibidas** (CA-2: subtotal de grupo agregado não vira item). Regra genérica: linha de nível >= 2 sem nominal e com esse motivo é omitida; qualquer outra linha sem valor aparece com traço e texto acessível. `L6a`/`L6b` (nível 2, com nominal) continuam. **[Para o PO confirmar]**: leio "subtotais de grupo em modo agregado não são exibidos como itens" assim; a alternativa seria mostrar L8a/L8b com "—".
- **Confirmação do DDR-004 sobre as pendências da STORY-048:** `tipo` de **L1 = `item`** e de **L7b = `deducao`** estão de acordo com o DDR-004: L1 abre o card "Receita" e fecha em L3, porque o card fecha em "linha totalizadora de nível 1" (item 4) e L1 é a âncora de 100%, não um total (o mock do spec mostra L1 dentro do card Receita sem prefixo "="); L7b é nível 1 "(±)", dedutor, sem "=". O prefixo "(–)" vem do `tipo`, mas L7b ganha "(±)" por sobreposição por código em `tela.php` (o motor não distingue "–" de "±"). Sugestão ao Arquiteto: se quiser, o motor passa a emitir o prefixo/sinal no snapshot.
- **L6c** só existe no modo `dentro` com administrativas detalhado (decisão do motor); a tela desenha o que vier (teste: `dentro` mostra L6c; `fora` e agregado, não).
- **Troca de modo = `trocarPosicao()`**: alterna `posicao` e grava `drm_preferencias.posicao_prolabore` (`DrmPreferencia::gravarPosicao`, cria a linha com defaults na primeira vez). A tela abre na preferência da empresa, então vale para toda a série. Teste prova que a linha de `drm_lancamentos` fica idêntica e que o motor, se resolvido do contêiner, quebra o teste (a classe é `final`, não dá para espionar). A região `aria-live="polite"` anuncia a frase do modo e "N linhas na demonstração".
- **Prejuízo**: sinal `−`, verbo trocado no destaque, `pill` neutro "Prejuízo no mês" (destaque, linha da tabela e do card): três canais, nenhum cor; teste Pest garante que não há `destructive`/`farol` e o Dusk compara a cor computada do valor negativo com a do positivo.
- **Ressalvas (CA-7)**: `alertas_aceitos` guarda só `regra`, data e `valor_envolvido`; o texto de cada regra é o da frase do spec, em `tela.php` (`:valor` vem do `valor_envolvido`). Regra desconhecida ou sem valor cai em frase genérica. `<details>` recolhido e `wire:ignore` (para o `open` não ser fechado pelo re-render da troca de modo); não existe sem alertas.
- **Rodapé**: `motor vX · gerado em dd/mm/aaaa hh:mm` lidos do JSON do snapshot (teste com `0.9.0` no snapshot e `9.9.9` na config). Horário no fuso da aplicação (UTC), como o Diagnóstico; o ajuste de fuso do ambiente está apontado na STORY-048.
- **`.btn--link`** acrescentado em `app.css` (DDR-004 pede a variante `link` de `<x-button>`); só sobre `surface` (tertiary 4,70:1; sobre `neutral` 4,45:1 reprovaria).
- **Fora desta estória, deixado como está:** `Corrigir este mês` desligado ("Em breve", STORY-050, como no lançamento); sem "Ver todos os meses" (série histórica é a STORY-051; no lugar, `Voltar para a empresa` e `Lançar outro mês`, que cobrem o CA-11); selo `Retificado` e rodapé de correção (STORY-050); faróis e leitura em prosa (STORY-054; a tela simplesmente não tem coluna nem card para eles).
- **Teste antigo alterado:** `PersistenciaDrmTest` "mostra o mês fechado" esperava `Setembro/2026` (rótulo da página mínima da STORY-047); a spec pede o título `Setembro de 2026`.

### Descobertas

- Dusk e `assertSee`: `text-transform: uppercase` do rótulo dos destaques faz o Selenium ver "QUANTO VENDEU"; os testes de browser usam a forma em caixa alta.
- Livewire injeta `<!--[if BLOCK]>` no HTML dos testes; extração por regex precisa tirar comentários.
- Ajuda (`<x-help>`) da troca de modo: texto provisório em `tela.php` (`modo_ajuda`); o pacote de conteúdo da STORY-052 não tem esse item. Fica como lacuna editorial para a EBC/PO.
- A varredura axe não existe no projeto; o contraste segue os pares já verificados no spec e os tokens (`ContrasteTokensTest`).

### Bloqueios encontrados

Nenhum.

### IDRs criados

Nenhum. As decisões são locais à tela (config `drm/tela.php`, view-model).

### Cobertura final

- Suíte `All`: **1968 passed** (44.946 assertions); `--coverage --min=80`: total **98,4%**. `Livewire/Drm/Demonstracao`, `Support/Drm/DemonstracaoVisao`, `Support/Drm/LinhaVisao` e `Models/DrmPreferencia`: 100%. `app/Domain` segue com 100% nas classes `Drm/*`.
- Dusk completo: **57 passed** (472 assertions), incluindo `DrmDemonstracaoBrowserTest` (6: 360x800 sem scroll horizontal e os três números na primeira dobra, tabela x cards, troca de modo por teclado com preferência persistida, prejuízo sem cor de erro + ressalva, sem faturamento, 404 cross-tenant) e o fluxo lançamento -> demonstração em `DrmLancamentoBrowserTest`.
- Pint e PHPStan limpos.
- **Não verificado:** varredura axe; navegação por teclado ponta a ponta (só a troca de modo foi exercitada); skeleton de carregamento do spec §4.2 (a tela é renderizada no servidor, sem estado inicial de loading) e `aviso-inline bloqueio` + "Tentar novamente" (sem falha de carga possível: leitura de uma linha); zoom 200%; deploy/homologação e a demo do Checkpoint 1 (fora da pista).
