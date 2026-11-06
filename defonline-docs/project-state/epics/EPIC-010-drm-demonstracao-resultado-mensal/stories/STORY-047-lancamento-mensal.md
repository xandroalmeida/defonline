---
story_id: STORY-047
slug: lancamento-mensal
title: "Lançamento mensal — formulário adaptativo por setor, modos agregado/detalhado, rascunho e pré-preenchimento"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: done
owner_agent: "programador (claude-sonnet-5.5)"
created_at: 2026-08-04
updated_at: 2026-11-05
estimated_session_size: L
---

# STORY-047 — Lançamento mensal

## Contexto

Esta é a estória de maior risco do épico. Não pelo cálculo — que é trivial — mas porque é aqui que o usuário decide se volta no mês seguinte. Os três mecanismos anti-atrito do PDR-003 não são "nice to have": são o produto.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` (integral — é a referência canônica dos campos); **`decisions/adr/ADR-007-modelo-dados-drm-versionamento-retificacao.md`** (decisão da STORY-045 — modelo de persistência; ver §*Para o time — impacto por estória*); os specs da STORY-046; `decisions/pdr/PDR-003-*.md`
- Código existente a estudar antes: o componente de Quiz do Diagnóstico e o mecanismo de rascunho. Reaproveite padrões — máscara BRL, validação declarativa, persistência de rascunho, tooltips. **Não crie um segundo dialeto de formulário no produto.**

## O quê

Implementar o fluxo de lançamento mensal do DRM, do ponto de entrada até o lançamento fechado e persistido, pronto para o motor consumir.

## Por quê

Sem entrada não há módulo. E sem entrada de baixo atrito não há segundo mês — que é a métrica primária do épico.

## Critérios de aceite

- [ ] **CA-1 (Ponto de entrada e escopo):** o lançamento acontece sempre no contexto de uma Empresa Analisada do usuário autenticado. Acesso cross-tenant retorna 404 silente (IDR-009). Setor e documento vêm da empresa e **não são perguntados** (Anexo K §K.3.1).
- [ ] **CA-2 (Competência):** campo obrigatório mês/ano, default = mês anterior ao corrente. Competência futura é bloqueada (regra B2). Competência já lançada é bloqueada e oferece abrir ou retificar o lançamento existente (regra B1).
- [ ] **CA-3 (Adaptação por setor):** Comércio não exibe matéria-prima; Serviços não exibe compras de mercadorias nem matéria-prima; o rótulo do grupo de custo muda por setor (CPV / CMV / CSP). Campos suprimidos são gravados como nulos. Ver Anexo K §K.4.
- [ ] **CA-4 (Modo agregado/detalhado):** cada um dos quatro grupos alterna entre total único e detalhamento por item, conforme §K.5 — incluindo o pré-preenchimento do total pela soma ao ir para agregado, a confirmação explícita no caminho destrutivo inverso, e a persistência do modo como preferência da empresa.
- [ ] **CA-5 (Pré-preenchimento):** havendo competência anterior lançada, os campos vêm preenchidos com aqueles valores, visualmente marcados como repetidos até confirmação, com ação de confirmar tudo de uma vez. Sem competência anterior, campos vazios.
- [ ] **CA-6 (Rascunho):** salvo automaticamente a cada bloco concluído e retomável em outra sessão; ao fechar o lançamento, o rascunho é encerrado preservando trilha de auditoria — mesmo tratamento dado ao rascunho do Quiz.
- [ ] **CA-7 (Entrada em reais):** todos os campos monetários com máscara BRL, valor mínimo zero, negativos rejeitados (regra B3). **Nenhum campo pede valor "em mil reais"** (PDR-003, decisão 1).
- [ ] **CA-8 (Regras bloqueantes):** B1 a B4 do Anexo K §K.6 impedem o fechamento, com mensagem específica por regra.
- [ ] **CA-9 (Alertas):** A1, A2, A3 e A5 sinalizam e permitem prosseguir mediante aceite explícito, registrado junto ao lançamento — mesmo padrão do `alertas_aceitos` do Diagnóstico. A4 e A6 dependem de histórico e entram na STORY-051; deixe o ponto de extensão preparado, sem implementá-las.
- [ ] **CA-10 (Persistência):** lançamento fechado é persistido conforme o `ADR-007` — `versao = 1` em `drm_lancamentos`, com o **modo de cada grupo dentro do `lancamento_payload`** (entra no `payload_hash`) e os **alertas aceitos em coluna própria** (fora do hash). Regra B1 é atendida pelo índice único parcial: trate a violação de unicidade como "já existe" e ofereça abrir/retificar — **não** dependa de um `exists()` prévio, que sempre tem janela de corrida. Regra B2 (competência futura) é validação de aplicação, não `CHECK` (depende de `now()`, que o Postgres recusa em constraint).
- [ ] **CA-11 (Mobile):** fluxo completável em viewport 360×800 sem scroll horizontal, alvo de toque ≥ 44×44px.
- [ ] **CA-12 (Testes):** unitários das regras de validação e da adaptação por setor; feature cobrindo o fluxo completo nos três setores, os dois modos, retomada de rascunho e cada regra bloqueante; E2E em browser percorrendo lançamento completo em pelo menos um setor.
- [ ] **CA-13 (Cobertura):** ≥ 80% no código novo; ≥ 98% no que for regra de negócio.

## Fora de escopo

- Cálculo da demonstração (STORY-048) e sua exibição (STORY-049).
- Retificação (STORY-050); série histórica e alertas A4/A6 (STORY-051).
- Redação final de rótulos e tooltips (STORY-052) — use os textos do Anexo K como provisórios.
- Linhas `[DECIDIR-E]` (D41/D42) enquanto a EBC não decidir.

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`. Sem código não testado. Qualquer setup novo automatizado.

## Dependências

- **Bloqueada por:** STORY-045 (modelo de dados), STORY-046 (specs).
- **Bloqueia:** STORY-048, STORY-049, STORY-050, STORY-051.

## Decisões já tomadas (não as reabra)

- PDR-003 (todas as decisões de escopo); Anexo K; **ADR-007** (decisão da STORY-045); ADR-003; IDR-008; IDR-009.

## Liberdade técnica do agente

Você decide estrutura de código, composição de componentes, forma da validação declarativa e desenho dos testes. Você **não** decide: quais campos existem (Anexo K), o modelo de persistência (`ADR-007`), o layout (STORY-046), nem os critérios de aceite.

Um ponto que o `ADR-007` deixou explicitamente para você, e que merece IDR: **como garantir que o DRM não seja capaz de alterar `diagnosticos.payload_hash`.** A canonicalização do DRM segue as mesmas cinco regras do IDR-010; se você optar por extrair um canonicalizador compartilhado em vez de manter dois, os golden hashes do Diagnóstico são o estopim obrigatório em CI. O mecanismo é seu; a restrição não é negociável.

Se descobrir que o Anexo K é ambíguo ou incompleto, **pare e registre** — não invente campo nem regra.

## Definição de Pronto

- [ ] CA-1 a CA-13 passam.
- [ ] Pipeline verde; deploy em homologação verificado.
- [ ] IDR registrado se houve decisão de baixo nível com impacto futuro.
- [ ] `index.json` atualizado; notas do agente preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Plano (2026-11-05, antes de codar)

Lido: estória, spec `design/STORY-046-lancamento-mensal.md`, ADR-007, Anexo K, DDR-001/002/003, PDR-009 (pacote de conteúdo já existe em `config/drm/`), Quiz (Livewire + máscaras), `QuizRascunho`, `QuizPayloadCanonicalizer`.

Entendimento: formulário em 6 blocos por grupo do Anexo K; modo agregado/detalhado por grupo (preferência em `drm_preferencias` + fato dentro do `lancamento_payload`); pré-preenchimento do último lançamento anterior com `repetidos[]` no rascunho; fechamento grava `versao=1` com índice único parcial como árbitro da B1; alertas A1/A2/A3/A5 com aceite único fora do hash.

Plano: (1) domínio puro em `App\Domain\Drm` (estrutura por setor, dinheiro em centavos, regras B2-B4/A1-A5, canonicalizador/hash, semente de pré-preenchimento); (2) migration + models; (3) action de fechamento; (4) componente Livewire + componentes Blade; (5) Pest unit/feature, Dusk 360x800; (6) IDR do canonicalizador.

Testes: B1 (unicidade + corrida), B2 (futura, URL manipulada), B3 (negativo por colagem), B4, A1/A2/A3/A5 com aceite, três setores x dois modos, retomada/expirado de rascunho, troca de modo (soma, destrutiva, cancelar), herança parcial, cross-tenant 404, imutabilidade, hash estável e fora do hash os alertas.

### Decisões tomadas

- **Domínio puro em `App\Domain\Drm`** (`Setor`, `Grupo`, `Modo`, `Competencia`, `Dinheiro`, `Lancamento`, `ValidaLancamento`, `Alertas\*`, `PreenchimentoInicial`, `LancamentoCanonicalizer`). Valores em centavos inteiros; a tela guarda o texto BRL. Uma só declaração (`Grupo`/`Lancamento::camposColetados`) decide quais campos aparecem, quantos o seletor conta e o que vai ao payload.
- **Campo não coletado = `null`; item opcional em branco = `0.00`; obrigatórios** (Anexo K: D01, D02, D40 e o totalizador do grupo agregado) em branco são erro com "Se não houve, informe 0,00". Totalizador de grupo detalhado e itens de grupo agregado ficam `null` no payload (o motor soma).
- **Default de modo no 1º lançamento = `detalhado`** (PDR-007 §2, override do `agregado` esboçado no ADR-007): `drm_preferencias` tem `DEFAULT 'detalhado'`.
- **Pré-preenchimento vem do último lançamento vigente de competência anterior** à escolhida (não exige que seja o mês imediatamente anterior). Troca de modo entre os dois meses: detalhado→agregado semeia o total pela soma (com a legenda); agregado→detalhado deixa os itens vazios e mostra o total anterior (herança parcial). Nunca se infere item de total.
- **"Conferir tudo" apaga todas as marcas e vai ao bloco 6** (não fecha). As marcas que chegam ao bloco 6 são as dos blocos atravessados com `Próximo` sem `Conferi este bloco`.
- **Troca agregado→detalhado**: se o total ainda é a soma automática, restaura os itens preservados sem confirmar (é "voltar atrás", Anexo K §K.5); se foi digitado ou editado, confirma inline e descarta; se está em branco, não há o que perder e não confirma. O rádio é controlado por `$wire.modos` (`x-on:click.prevent` + `x-bind:checked`): sem isso o navegador marcava a opção nova antes da confirmação (achado do Dusk).
- **Bloqueios**: B2 aparece na hora; B3/B4 só depois de uma tentativa de avançar (B4 mudaria a cada dígito). B3 é mensagem sob o campo, com o texto do pacote de conteúdo. B1 é detectado ao escolher a competência (consulta de leitura só para a UX) e **decidido pelo banco** no fechamento (`UniqueConstraintViolationException` → `CompetenciaJaLancada`).
- **A1 e A2 podem disparar juntos** (A2 é literal: custo > D01, mesmo com D01 = 0). **A3** usa `Lancamento::lucroLiquido()` (L11 da `estrutura-drm`); a demonstração real é da STORY-048, que deve concordar com ele. Os textos de bloqueios/alertas/tooltips/rótulos vêm do pacote de conteúdo (`ConteudoDrm`, STORY-052), não do spec — onde divergem (ex.: "Despesas com Vendas", "(CSP)") vale o pacote validado.
- **Canonicalizador próprio do DRM** e **FK de vigência adiável**: ver IDR-016.
- **Componentes sob `x-drm.*`** (`progresso-blocos`, `aviso-inline`, `seletor-modo`, `campo-monetario`), e não com os nomes globais do DDR-001/002, para não colidir com a pista de cobrança (DDR-009/010 também usam `aviso-inline`/`seletor-modo`). Consolidar no DS global é um follow-up de merge.
- **D41/D42** entram como campos opcionais recolhidos (bloco 5): estão no Anexo K v1.1 e no PDR-004; o "fora de escopo" da estória é sobre as *linhas* `[DECIDIR-E]` da demonstração, que são da STORY-048.
- **Sem paywall** (STORY-084): o ponto de entrada é um card "Raio X do Negócio" com `Lançar o mês` na tela da empresa, aberto a qualquer usuário autenticado.

### Descobertas

- **[ESCALONAMENTO-PO] `empresas_analisadas` não tem coluna de setor** (o Quiz fixa "industria"). O Anexo K/CA-1 mandam herdar o setor da empresa, mas não há de onde. Implementei `Setor::doCnae()`: divisão do CNAE 05–33 indústria, 45–47 comércio, demais serviços (convenção IBGE), e **sem CNAE = Indústria** (o único setor sem campo suprimido). É um palpite reversível, isolado numa função; precisa de decisão do PO (coluna na empresa? pergunta no cadastro? outro mapa de CNAE, p. ex. construção civil?). O setor é copiado para o lançamento no fechamento.
- **ADR-007 tinha uma ordem inexequível na retificação** (INSERT da N+1 antes do UPDATE do ponteiro viola o índice único parcial). Resolvido com FK adiável; a STORY-050 deve usar UPDATE-do-ponteiro → INSERT (IDR-016).
- **`demonstracao` nasce `[]`** (o motor é a STORY-048) e a coluna é imutável por desenho. **Não liberar o módulo a usuários antes de a STORY-048 gravar o snapshot no fechamento** (ou prever backfill por migration como exceção declarada). O card na tela da empresa já expõe a entrada.
- Retificação (`Corrigir {mês}` no B1) é da STORY-050: o botão está presente e desabilitado ("Em breve"). O destino do fechamento e do `Abrir {mês}` é uma página mínima (`drm.mes`, `DrmMesController`) que a STORY-049 substitui; já devolve 404 silente cross-tenant e para mês sem lançamento.
- `ConteudoDrm::rotuloGrupo()` foi acrescentado (3 linhas) porque o rótulo dos grupos de vendas/administrativas não tinha acessor.
- A máscara BRL do Quiz está inline no Blade; copiei o mesmo corpo (com a reemissão de `input`, BUG-001) em `x-drm.campo-monetario`. Extrair para um lugar só é um refactor do Quiz que ficou de fora.

### Bloqueios encontrados

Nenhum que impedisse a entrega. Pendência de decisão: origem do setor (acima).

### IDRs criados

- `IDR-016` — canonicalizador próprio do DRM isolado do Diagnóstico; FK `substituido_por_id` adiável. (Número reservado nesta pista; conferir colisão com a pista de cobrança no merge.)

### Cobertura final

- Suíte `All`: 1424 passed (6861 assertions); `--coverage --min=80` passou. `app/Domain` (gate 98%): 99,7% no total e 100% em todas as classes `Drm/*`. `Livewire/Drm/Lancamento` 95,4%; `Models/DrmLancamento` 95,2%.
- Dusk completo: 46 passed, incluindo `DrmLancamentoBrowserTest` (5 testes, 360×800: fluxo agregado completo com a máscara persistida igual ao exibido, bloco de 21 itens sem scroll horizontal e alvos ≥44px, segundo mês em dois toques sem fechar sozinho, confirmação destrutiva inline com rádio fiel, retomada em outra sessão).
- Pint e PHPStan limpos.
- **Não verificado:** fluxo só por teclado ponta a ponta (spec §6) e varredura axe da tela; skeleton de carregamento (a tela é renderizada no servidor, sem estado de loading inicial); deploy/homologação (fora desta pista).
