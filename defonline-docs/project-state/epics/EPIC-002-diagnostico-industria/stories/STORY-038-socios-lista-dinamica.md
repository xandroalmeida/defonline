---
story_id: STORY-038
slug: socios-lista-dinamica
title: Sócios como lista dinâmica (1+) — add/edit/remove
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: programador (claude-opus-4-8)
created_at: 2026-06-02
updated_at: 2026-06-02
estimated_session_size: M
related_devolutiva: "WhatsApp Edvan #1 — 'poderíamos tornar obrigatório só o primeiro campo... no teste que fiz, repeti meu cpf em todos os campos'"
---

# STORY-038 — Sócios como lista dinâmica (1+)

> Substitui a Q23 fixa de 3 CPFs (Q23.1/Q23.2/Q23.3 obrigatórios) por uma lista dinâmica com **1 sócio obrigatório** e quantos adicionais o usuário quiser.

## Contexto

Na devolutiva da EBC (Edvan, 2026-05-27), o analista financeiro testou o quiz e relatou que muitas empresas LTDA do nosso público-alvo (MPEs/EPPs Indústria) são unipessoais ou têm um único sócio relevante. O quiz atual (STORY-027) exige 3 CPFs válidos e o Edvan foi forçado a **repetir o próprio CPF nos três campos** para conseguir submeter — comportamento que invalida o dado capturado. O PO refinou a proposta: **lista dinâmica** com 1 sócio obrigatório, demais opcionais e adicionáveis livremente.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Devolutiva integral: `epics/EPIC-002-diagnostico-industria/devolutivas/2026-05-27-EBC/`
- Documentos canônicos a ler ANTES de codificar:
  - `defonline-docs/especificacao/V2/anexos/anexo-A-quiz-industria.md` (Q23)
  - `decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md` (canonicalização `payload_hash`)
  - `defonline-docs/especificacao/V2/design-system.md` (componentes de input, padrão mobile)

## O quê

Implementar uma lista dinâmica de sócios no Bloco 4 do quiz Indústria, com mínimo de 1 sócio obrigatório e cap interno de 10, substituindo os campos fixos `cpf_socio_1/2/3` por um array `socios[]` no `quiz_payload`.

## Por quê

Destrava uso real do MVP por empresas unipessoais ou de poucos sócios (perfil dominante do Roberto). Sem isso, o quiz força dado inválido (CPF duplicado) ou impede submissão — bloqueando a entrega de valor (diagnóstico).

## Critérios de aceite

- [x] **CA-1 (submeter com 1):** Dado quiz preenchido com exatamente 1 CPF válido no Bloco 4, quando o usuário clica `Calcular diagnóstico`, então o submit é aceito e o diagnóstico é gerado. _(feature + Dusk)_
- [x] **CA-2 (adicionar):** Dada lista com 1 sócio, quando o usuário clica `+ Adicionar sócio`, então uma nova linha vazia aparece e recebe foco automático para o input do CPF. _(feature `assertCount`; foco via `dispatch('focar-socio')` + handler Alpine no Dusk)_
- [x] **CA-3 (remover):** Dada lista com ≥2 sócios, quando o usuário clica no ícone de remover de qualquer linha, então a linha some e o restante é renumerado. **Quando há 1 sócio na lista, o botão de remover está desabilitado** (não é possível ficar com 0 sócios). _(feature + `assertDisabled` no Dusk)_
- [x] **CA-4 (validação por linha):** Dado um CPF inválido digitado em qualquer linha, então a mensagem `CPF inválido — confira os dígitos.` aparece apenas naquela linha; outras linhas ficam intocadas. _(unit + feature `assertHasErrors('socios.0')`/`assertHasNoErrors('socios.1')`)_
- [x] **CA-5 (duplicidade):** Dada uma lista onde 2 ou mais linhas têm o **mesmo CPF válido**, quando o usuário tenta submeter, então o submit é impedido com mensagem `CPF duplicado na lista — cada sócio só pode aparecer uma vez.` apontando para a 2ª ocorrência em diante. _(unit + feature)_
- [x] **CA-6 (cap interno):** Dada lista com 10 sócios, então o botão `+ Adicionar sócio` desabilita e mostra mensagem `Limite de 10 sócios por empresa.` (cap de sanidade). _(feature `adicionarSocio` para em 10; unit cobre o guard defensivo de `erros()`)_
- [x] **CA-7 (linhas vazias descartadas):** Dadas linhas vazias entre preenchidas, quando o usuário submete, então as linhas vazias são silenciosamente descartadas antes da persistência (não geram erro); o `quiz_payload.socios[]` contém apenas os CPFs preenchidos. _(unit + feature)_
- [x] **CA-8 (rascunho persiste):** Dado um rascunho com lista de N sócios, quando o usuário fecha e reabre o quiz, então a lista volta com os mesmos N sócios na mesma ordem. _(feature: persiste no `proximoBloco`, re-hidrata na mesma ordem)_
- [x] **CA-9 (canonicalize):** O `payload_hash` da IDR-010 considera `socios[]` **ordenado alfabeticamente pelo CPF** (independente da ordem de digitação) — testado com 3 permutações da mesma lista produzindo o mesmo hash. _(unit `canonicalizar` 3 permutações + feature: 2 submits permutados ⇒ mesmo `payload_hash`)_
- [x] **CA-10 (cobertura):** ≥ 80% no pacote do quiz; ≥ 95% na classe `ValidaSocios` (hot path); 2 testes Dusk. _(domínio total 99,6%; `ValidaSocios` **100%**; 2 Dusk verdes)_
- [x] **CA-11 (mobile):** Layout em stack vertical em ≤ 768px (linhas empilhadas, lixeira à direita, botão "Adicionar sócio" largura total abaixo). _(auditado em Dusk a 375×667 — screenshot `socios-mobile-375` confere empilhamento + lixeira à direita + Adicionar full-width; layout único para desktop/mobile por decisão do briefing)_

## Fora de escopo

- Cargo/percentual de participação dos sócios (Anexo A só pede CPF).
- Enriquecimento via Receita Federal por CPF (não está na spec; STORY-018 é CNPJ).
- Migration de conversão de rascunhos antigos `cpf_socio_1/2/3` → `socios[]`: **descartar rascunhos existentes em homol** (TTL 90d da IDR-010 §6.4 já é curto; em homol o impacto é nulo). Documentar na release note.
- Diagnósticos legados em `1.0.0`/`1.1.0`/`1.2.0`: snapshot imutável (IDR-010) — não alterar.

## Padrões de qualidade exigidos

Esta estória segue os padrões em `defonline-docs/skills/po/references/quality-standards.md`. Resumo aplicável:

- **Cobertura de testes unitários:** ≥ 80% no código novo; ≥ 95% na classe `ValidaSocios` (regra de negócio nova).
- **Testes E2E (Dusk):** dois cenários (CA-10).
- **Acessibilidade:** botões de remover com `aria-label="Remover sócio N"`; botão `+ Adicionar` com `aria-label`; erros com `aria-describedby`.
- **Sem código não testado.**

## Dependências

- **Bloqueada por:** nada.
- **Bloqueia:** nada da sprint atual; pode rodar 1º.
- **Pré-requisitos de ambiente:** homol operante; rascunhos em `quiz_rascunhos` em homol serão limpos (PO autoriza explicitamente).

## Decisões já tomadas (não as reabra)

- **Ordem de canonicalização do `socios[]`:** alfabética pelo CPF (PO + Arquiteto, 2026-06-02 — registrar IDR se houver consequência futura).
- **Mínimo:** 1 sócio. **Máximo:** 10 (cap interno de sanidade).
- **Schema:** ~~`quiz_payload.socios` é `array<string CPF mascarado>`.~~ **ALTERADO em 2026-06-02 (PO, durante a execução):** o sócio passa a ser um objeto `{cpf, nome}` — `quiz_payload.socios` é `array<{cpf: string, nome: string}>`. **Nome obrigatório** junto com o CPF em cada sócio preenchido. Continua sem cargo/percentual. Consequência: canonicalize ordena a lista por CPF e o canonicalizer ordena as chaves do objeto (`cpf`, `nome`) + normaliza o nome (NFC); `payload_hash` muda, mas **sem bump de `motor_version`** (nome/CPF não entram no cálculo).
- **Rascunhos antigos:** descartar em homol; release note. Sem migration de conversão.
- **IDR-010** continua valendo — `payload_hash` muda quando o esquema do payload muda; bump de `motor_version` **não é necessário** porque CPF/nome não entram em cálculo.

## Liberdade técnica do agente

Você (Programador) decide:
- Componente Livewire (filho `<livewire:quiz.socios-list>` ou estado no componente pai com array reativo).
- Como tratar foco automático na nova linha (`wire:after` / JS mínimo).
- Estrutura do canonicalizer — pode ser método estático em `QuizPayload::canonicalize()` ou helper isolado.
- Layout exato da lista (alinhamento, espaçamento, ícone da lixeira — respeitando tokens v1 do design system).
- Estratégia de teste E2E (Dusk) — pode usar fixtures ou seeder dedicado.

Você (Programador) NÃO decide:
- Mínimo/máximo da lista (PO já decidiu: 1+ / 10).
- Ordem de canonicalização (PO já decidiu: alfabética pelo CPF).
- Bloqueante vs alerta para CPF duplicado (PO já decidiu: bloqueante).

## Definição de Pronto (DoD)

- [x] Todos os critérios de aceite passam (CA-1..CA-11 verificados local — ver acima).
- [x] Testes unitários ≥ 80% pacote / ≥ 95% `ValidaSocios` → domínio 99,6%; `ValidaSocios` **100%**.
- [x] 2 Dusk verdes (local; em homol após deploy).
- [ ] CI verde no PR. _(pendente: abrir PR)_
- [ ] Deploy em homol verificado (tag rc.W26.X). _(pendente: pós-merge)_
- [x] Release note redigida (em "Notas do agente" → Release note) mencionando o descarte de rascunhos pré-W26.
- [ ] `index.json` atualizado: `status: done`. _(em `in_review` enquanto aguarda PR/CI/deploy)_
- [x] Seção "Notas do agente" preenchida.

## Notas do agente (preenchido durante/após execução)

### Plano (registrado antes de codar — 2026-06-02)

**Documentos lidos:** estória inteira + briefing; `Quiz.php` (Livewire), `quiz.blade.php`, `campo-cpf.blade.php`, `Cpf.php`, `QuizPayloadCanonicalizer.php` (+ test), `CalcularDiagnostico.php`, `QuizRascunho.php`, `ValidacoesCruzadas.php`, `QuizTest.php`, `ValidacoesCruzadasBrowserTest.php`, IDR-010, `help-industria.php`, componentes `x-input`/`x-button`.

**Entendimento consolidado:** o quiz hoje tem 3 props fixas `Q21/Q22/Q23` (CPF) condicionais a `Q17="sim"`. Vou substituí-las por uma única prop array `$socios` (lista de CPFs mascarados), com mín 1 / máx 10. O canonicalizer **preserva ordem de listas** (não ordena) — portanto a ordenação alfabética do `socios[]` (CA-9) tem de acontecer no `payloadCanonico()` do quiz, via a nova classe pura `ValidaSocios::canonicalizar()`. O motor não usa CPF (fixtures/golden não têm `socios`) ⇒ sem bump de `motor_version`. Rascunho guarda a lista crua (ordem de digitação) para CA-8; o payload canônico guarda a lista ordenada só-dígitos.

**Plano (5 passos):**
1. `app/Domain/Quiz/ValidaSocios.php` (pura, ≥95%): `canonicalizar()` (descarta vazios, normaliza, ordena alfabético) + `erros()` (CPF inválido por linha, duplicado na 2ª+ ocorrência, mínimo 1, cap 10). TDD primeiro.
2. `Quiz.php`: trocar Q21/Q22/Q23 por `public array $socios = ['']`; métodos `adicionarSocio()`/`removerSocio($i)`; integrar `ValidaSocios` no `submeter()`; ajustar `payloadParcial`/`payloadCanonico`/`validationAttributes`/regras do bloco 4.
3. View: novo partial `socios-list.blade.php` (linhas empilhadas, lixeira à direita, +Adicionar full-width no mobile, aria-labels); foco automático via `dispatch('focar-socio')`.
4. Atualizar testes/feature/tooltips, `DiagnosticoFactory`, `help-industria.php` (chave `socios`).
5. 2 Dusk (CA-10) + aditivo IDR-010 + release note. Rodar suíte inteira + cobertura.

**Testes que vou escrever:** unit `ValidaSocios` (1 sócio ok; 3 válidos ok; vazio→mínimo; só linhas vazias; CPF inválido isolado por linha; duplicado aponta 2ª ocorrência; linhas vazias entre preenchidas descartadas; canonicalize ordena alfabético; 3 permutações → mesma lista; >10 cap). Feature: submit com 1; add/remove muda contagem; duplicado bloqueia submit; Q17=nao ignora socios; rascunho persiste ordem; canonicalize idempotente no hash (3 permutações). Dusk: (a) submeter com 1 sócio; (b) add 3, remove 1, submeter.

**Dúvidas:** nenhuma — defaults do briefing (sem bump de motor, aditivo IDR-010 curto, stack vertical) seguidos.

### Decisões tomadas
- **`ValidaSocios` (pura, `app/Domain/Quiz`)** concentra descarte de vazios, validação por linha, duplicidade e ordenação canônica — mantém o canonicalizer genérico (que preserva ordem de listas por design).
- **Sem bump de `motor_version`** — CPF/nome não entram em cálculo nem em texto de veredito; fixtures/golden do motor não contêm `socios`.
- **[Mudança de escopo do PO, 2026-06-02] Sócio agora tem `nome` além do CPF.** Schema passou de `array<string>` para `array<{cpf, nome}>`; nome obrigatório por sócio preenchido; duplicidade segue por CPF; canonicalize ordena por CPF e o canonicalizer ordena as chaves do objeto + normaliza o nome (NFC). UI virou um card por sócio (Nome + CPF + remover). Registrado também na seção "Decisões já tomadas" e no aditivo da IDR-010.

### Descobertas
- **Bug latente de Blade encontrado e corrigido:** uma diretiva `@error(...) ... @enderror` colocada **dentro da tag** de um componente Blade (`<x-input ... @error(...) ... @enderror ...>`) impede o compilador de component-tags de reconhecer a tag — ela não é um par `name="value"` — e o `<x-input>` sai **literal** no HTML (vira um custom element não-editável). No browser isso quebra como `InvalidElementStateException` ao digitar. Solução: atributos aria-* condicionais via **bind nulo-seguro** (`:aria-describedby="$erroId"`), nunca diretiva dentro da tag. O bloco de erro `<p>` continua FORA da tag (padrão dos partials `campo-*`). O antigo `campo-cpf.blade.php` nunca foi exercido por Dusk, então esse risco passou despercebido até agora; ele foi removido (órfão após a migração).
- O `QuizPayloadCanonicalizer` **preserva ordem de listas** (só ordena chaves associativas). Confirmado por teste existente — por isso a ordenação alfabética de `socios[]` (CA-9) é feita no produtor (`ValidaSocios::canonicalizar`), não no canonicalizer.

### Bloqueios encontrados
- Nenhum bloqueio de produto/arquitetura. Travas técnicas resolvidas no fluxo: (1) gate de cobertura do Pest estourava `memory_limit` de 128MB na fase de relatório → reexecutado com `php -d memory_limit=1G`; (2) bug de Blade acima.

### IDRs criados
- Nenhum IDR novo. Registrado **aditivo na IDR-010** (Histórico, 2026-06-02): canonicalização de listas no `payload_hash` — listas independentes de ordem devem ser ordenadas no produtor; `socios[]` ordenado alfabeticamente por CPF; sem bump de `motor_version`; rascunhos pré-W26 descartados.

### Cobertura final
- **Unitários/Domínio:** suíte `phpunit-domain.xml` (Unit+Domain+Feature) — **812 testes verdes**; `app/Domain` total **99,6%** (gate ≥98%). Classe `App\Domain\Quiz\ValidaSocios`: **100,0%** (CA-10 exige ≥95%).
- **E2E (Dusk):** `tests/Browser/SociosListaBrowserTest.php` — 2 cenários verdes: (a) submeter com 1 sócio; (b) adicionar 3, remover 1, submeter (3 sócios, payload ordenado alfabeticamente). Browser do quiz pré-existente (tooltips + validações cruzadas) revalidado sem regressão.

### Release note (para a tag rc.W26.x)
> **Sócios do diagnóstico Indústria agora são uma lista dinâmica (1+).** Rascunhos de quiz iniciados antes da W26 (`quiz_rascunhos`) **foram descartados** em homologação — não há migração dos antigos campos `cpf_socio_1/2/3` para a nova lista `socios[]`. Diagnósticos já emitidos permanecem inalterados (snapshot imutável, IDR-010).

### Links de evidência
- PR: _(a abrir)_
- Pipeline: _(CI do PR)_
- Deploy de homologação: _(tag rc.W26.x após merge)_
