---
idr_id: IDR-010
slug: versionamento-motor-persistencia-diagnostico
title: Versionamento do motor de cálculo + persistência idempotente do diagnóstico (snapshot dos resultados)
status: accepted
decided_at: 2026-05-25
decided_by: arquiteto (claude-opus-4-7)
approved_by: Alexandro
approved_at: 2026-05-25
owner_agent: arquiteto (claude-opus-4-7)
related_story: STORY-026
related_stories: ["STORY-027", "STORY-028", "STORY-029", "STORY-030", "STORY-032", "STORY-036"]
related_adrs: ["ADR-001", "ADR-003"]
related_idrs: []
supersedes: null
superseded_by: null
created_at: 2026-05-25
updated_at: 2026-05-25
---

# IDR-010 — Versionamento do motor + persistência idempotente do diagnóstico

## Contexto

A `epic.md` do EPIC-002 lista como **decisão arquitetural pendente** (linha 66):

> *"estratégia de versionamento do motor de cálculo (a matriz e as fórmulas vão evoluir — espec I.1.2); padrão de geração/renderização do relatório; idempotência do cálculo (mesmo input → mesmo output, para reprocessamento); persistência de diagnóstico para o EPIC-003."*

A spec V2.5 §4.5 + Anexos D/E/F é explícita sobre o fato de que **a matriz DEZ/2025 e as fórmulas vão evoluir**. O Anexo F traz a observação: *"A tabela completa é versionada no banco de dados — atualizações geram uma nova versão; diagnósticos já emitidos mantêm o texto da versão vigente na data de geração."* A spec já antecipou a evolução; falta amarrar o contrato técnico.

A STORY-026 pede uma **IDR** + esqueletos (migration + model + catálogo de casos extremos + protocolo de idempotência) — **sem rodar código de produção**. STORY-027 (quiz) e STORY-028 (motor) consomem o esqueleto.

Sem essa decisão tomada agora, três problemas concretos:

1. **EPIC-003 (histórico)** lista diagnósticos antigos. Se a matriz mudar entre a emissão e a consulta, o que Roberto vê hoje quando reabre o relatório de 3 meses atrás? Recalculado on-the-fly (e potencialmente diferente)? Ou idêntico ao que ele viu no dia?
2. **NRF §9.3 (validação externa)** — o parecer do validador refere-se a *uma versão exata* do motor. Sem `motor_version` carimbado, a auditoria fica ambígua.
3. **Reprocessamento e debug** — quando um indicador muda de cor entre dois diagnósticos da mesma empresa, é mudança de input do Roberto ou mudança de motor? Sem versão amarrada, ninguém sabe.

A spec resolve (1) pela frente: *"diagnósticos já emitidos mantêm o texto da versão vigente na data de geração"*. Esta IDR formaliza o **como** técnico: versão carimbada no registro + snapshot dos resultados + matriz lida como dado versionado, não como código.

## Decisão

> **1. Versionamento: `motor_version` em semver inteiro + `matrix_version` em formato datado. 2. Persistência: snapshot dos resultados em colunas JSONB imutáveis (não recalcula on-the-fly). 3. Idempotência: hash determinístico do `quiz_payload` canônico; golden files em `app/tests/Domain/Motor` com hash esperado por fixture + `motor_version`. 4. Evolução: motor único + snapshot — sem `MotorV1`/`MotorV2` no MVP. 5. Casos extremos: retorno `null` semântico (não exception), catalogados em `design/casos-extremos.md`.**

Concretamente, cada uma das 5 sub-decisões está abaixo.

### Sub-decisão 1 — Esquema de versionamento

- **`motor_version`** em **semver inteiro** (`MAJOR.MINOR.PATCH`, armazenado como `varchar(16)`):
  - **MAJOR** sobe quando uma fórmula muda comportamento (novo cálculo da NCG, mudança de denominador, anualização nova) — diagnósticos antigos **não** são recalculáveis pelo motor novo.
  - **MINOR** sobe quando entra um novo indicador (15º indicador no roadmap §2) ou novo setor sem mudar fórmula existente.
  - **PATCH** sobe quando corrige bug puro de implementação **que altera output** (caso raro: corrigir divisão por zero que retornava 0 em vez de `null`). Bug puro que **não altera output** não bumpa nada — só cobertura de teste.
- **Valor inicial:** `motor_version = "1.0.0"` para STORY-028 (Motor V1, 7 indicadores). **`motor_version = "1.1.0"`** para STORY-030 (completar 14 indicadores) — é MINOR porque adiciona indicadores sem mudar os 7 já entregues. **Não é MAJOR** porque os 7 V1 continuam reproduzíveis bit-exato com o motor V2.
- **`matrix_version`** em **formato datado curto** (`varchar(16)`): `"dez-2025"`, `"mar-2026"`, etc. Alinhado ao naming dos anexos da spec (Anexo F = `dez/2025`, Anexo G = `jul/2025`). Quando a EBC validar uma nova matriz, vira `"jun-2026"` (ou o mês de fechamento).
- **Por quê dois campos separados:** matriz e motor evoluem em ritmos diferentes. A EBC pode atualizar textos de recomendação sem mexer em fórmula (só `matrix_version` sobe). O dev pode trocar fórmula sem mexer em matriz (só `motor_version` sobe). Um campo único acoplaria os dois ciclos artificialmente.

### Sub-decisão 2 — Modelo de dados de persistência

Tabela `diagnosticos` (Postgres, schema na migration anexa):

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | `uuid` (PK, UUID v7 via `HasUuids`) | Padrão ADR-003 §Decisão 3 |
| `empresa_analisada_id` | `uuid` FK → `empresas_analisadas.id` ON DELETE RESTRICT | Tenant-scoped (Global Scope herdado via `empresa`) |
| `usuario_id` | `uuid` FK → `usuarios.id` ON DELETE RESTRICT | Denormalizado para Global Scope direto (`BelongsToUsuarioScope`) sem JOIN |
| `motor_version` | `varchar(16)` NOT NULL | Ex. `"1.0.0"` |
| `matrix_version` | `varchar(16)` NOT NULL | Ex. `"dez-2025"` |
| `setor` | `varchar(16)` NOT NULL CHECK IN (`industria`,`comercio`,`servicos`) | Snapshot do setor no momento (matriz é setor-dependente) |
| `quiz_payload` | `jsonb` NOT NULL | Respostas Q01–Q23 do Anexo A, **canonicalizadas** (chaves ordenadas, decimais como string) |
| `payload_hash` | `varchar(64)` NOT NULL | SHA-256 hex do `quiz_payload` canonicalizado — usado para idempotência |
| `indicadores_calculados` | `jsonb` NOT NULL | Snapshot dos 14 indicadores: valor numérico ou `null`, farol, faixa, mensagem-curta-da-matriz, mensagem-detalhada (preenchida quando ativada em STORY-032) |
| `resumo_executivo` | `jsonb` NOT NULL | Snapshot estruturado §4.7.1: `{veredito, destaques_negativos[], destaque_positivo, fallback_acionado}` |
| `gerado_em` | `timestamptz` NOT NULL | Momento do cálculo (≠ `created_at` se houver reprocessamento manual) |
| `created_at` | `timestamptz` NOT NULL | Padrão Laravel |
| `updated_at` | `timestamptz` NOT NULL | Padrão Laravel |
| `deleted_at` | `timestamptz` NULL | Soft delete (ADR-003 §Decisão 5) |

**Índices:**

- `INDEX (usuario_id)` — Global Scope.
- `INDEX (empresa_analisada_id, gerado_em DESC)` — listagem do EPIC-003 (histórico 12 meses por empresa).
- `INDEX (motor_version)` — análise pós-bump ("quantos diagnósticos usaram a 1.0.0?").
- **Sem unique em `payload_hash`** — idempotência é contrato de **teste** (mesmo input → mesmo output), **não** de banco (Roberto pode reemitir conscientemente o mesmo diagnóstico em 2 datas — são 2 registros).

**JSON imutável:** `quiz_payload`, `indicadores_calculados`, `resumo_executivo` **nunca** são editados após `INSERT`. Edição = novo diagnóstico (novo `id`). Garantia: no model não há setter público para essas colunas após persistência inicial; teste arquitetural assegura que o controller de diagnóstico não chama `update()` nelas.

### Sub-decisão 3 — Garantia de idempotência

**Contrato:** dado um `quiz_payload` canonicalizado X + `motor_version` = `M` + `matrix_version` = `Mat`, o resultado é **bit-exato**:

- mesmo `indicadores_calculados[*].valor` numérico (sem flutuação por float drift — todos os cálculos em `bcmath` ou casts para string com `number_format`);
- mesmo `indicadores_calculados[*].farol`;
- mesmo `resumo_executivo` (algoritmo determinístico §4.7.1 — sem IA, sem random, sem `now()`).

**Canonicalização do `quiz_payload`:**

1. Chaves do JSON ordenadas lexicograficamente (recursivo).
2. Decimais serializados como **string** com casas fixas (ex.: `"123.45"` em vez de `123.45` para evitar `123.45 == 123.449999...` em deserialização).
3. Strings vazias normalizadas para `null`.
4. Booleanos como `true`/`false` literais JSON.
5. Encoding UTF-8 NFC.

**Como testar (golden hashes):**

- Em `app/tests/Domain/Motor/Fixtures/` ficam JSONs de quiz canônicos (ex.: `quiz_industria_saudavel.json`, `quiz_industria_alerta.json`, `quiz_industria_ncg_negativo.json` — mínimo de 5 fixtures pela DoD da STORY-028).
- Em `app/tests/Domain/Motor/GoldenHashesTest.php`, para cada fixture roda o motor e compara `sha256(json_canonical(saida_completa))` com o hash esperado **hardcoded no teste**.
- **Mudou o hash em CI = bump obrigatório de `motor_version`** (ou correção de bug — discutível no PR). Hash sem bump = falha de teste = pipeline vermelho.
- Quando bumpar legitimamente (refactor que muda output), o programador atualiza o hash **no PR que bumpa `motor_version`**, e o reviewer confirma a justificativa.

**Determinismo extra:**

- O `gerado_em` **não** entra no hash de idempotência (é metadado, não output de cálculo).
- A ordem dos `destaques_negativos[]` no `resumo_executivo` segue critério explícito da spec §4.7.1 (severidade descendente; em empate, ordem do Anexo D) — determinístico, sem `array_unique` que reordena.
- Mensagens da matriz são lidas como **dado versionado** (config PHP carregado por `matrix_version`), não como template Twig/Blade — render do relatório (Blade) é **fora** do cálculo; o snapshot `indicadores_calculados` contém o texto curto e o detalhado já interpolados, prontos para o Blade exibir cru.

### Sub-decisão 4 — Política de evolução

**Estratégia adotada: motor único + snapshot dos resultados.**

- O código do motor (`app/Domain/Motor/` — namespace a ser criado em STORY-028) tem **uma só implementação ativa** por momento. Não existem `MotorV1`, `MotorV2`, `MotorDispatcher` no MVP.
- Quando uma fórmula muda em produção: o programador bumpa `motor_version` no código (`config('motor.version')`), atualiza os golden hashes no PR, deploya. Os diagnósticos novos saem com a versão nova; os antigos **não são recalculados** — ficam congelados como snapshot.
- Reabrir um diagnóstico antigo = ler `indicadores_calculados` cru do banco e renderizar o Blade com o que está lá. O motor **não é invocado** na leitura.
- O `motor_version` do registro permite ao validador (NRF §9.3) saber "isto foi gerado pela versão aprovada"; e ao suporte saber "o ciclo financeiro mudou de cor entre o diagnóstico de março e o de maio — o motor bumpou de 1.0.0 para 1.1.0 entre as datas, então é provável que seja mudança de motor, não de input do Roberto".

**Por que não dispatcher (`MotorV1`, `MotorV2`):**

- O custo de manter código antigo branchando por versão é alto (cada mudança exige cuidar de 2+ branches, testes duplicados).
- O ganho — "poder recalcular diagnóstico antigo com motor antigo" — **não tem uso real no MVP**. O snapshot já é a fonte da verdade.
- Se algum dia o requisito mudar ("queremos oferecer ao Roberto a opção de ver o diagnóstico antigo recalculado pelo motor novo"), abre-se IDR específica e refactora-se. O custo de **chegar lá** a partir do snapshot é menor do que o custo de **manter o dispatcher** preventivamente desde o Dia 1.
- Defesa em profundidade: o snapshot **é** a versão antiga executada. Se a fórmula antiga for útil, ela está no `git log`, recuperável.

**Trade-off aceito:** se descobrirmos um bug no motor 1.0.0 que invalidou diagnósticos antigos, **não conseguimos** reemitir automaticamente para os Robertos afetados — precisamos refazer o quiz com cada um (ou re-rodar manualmente com input persistido + motor patched). Esse cenário é raro e o RNF §9.2 + golden hashes minimizam-no.

### Sub-decisão 5 — Tratamento de casos extremos

**Política única:** *valor numérico do indicador = `null` + mensagem semântica não-crítica, em vez de exception/erro 500.*

- O motor **nunca** lança exception para input inválido conhecido (vendas=0, EBITDA negativo, dado faltante). Lança apenas para invariantes de programação violadas (ex.: setor desconhecido, indicador inexistente).
- Quando um indicador não pode ser calculado, o snapshot grava:
  ```
  {"valor": null, "farol": "nenhum", "motivo": "indisponivel:vendas_zero", "mensagem": "Indicador indisponível: vendas anuais são zero."}
  ```
- O relatório (STORY-029) renderiza a linha com farol cinza + texto curto explicativo. Não mostra "Erro" ao Roberto.
- O algoritmo do Resumo Executivo §4.7.1 conta indicadores `null` como "Indisponível" (já previsto na spec) — fallback fixo se ≥ 70% indisponíveis.

**Catálogo completo:** em `defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/casos-extremos.md` — 14 indicadores × ≥ 2 casos cada (entregue como CA-5).

## Por quê

**Por que semver inteiro para o motor:**

- Convenção universal, suporta ferramentas de comparação (`composer/semver` já existe na stack).
- Distingue "mudou fórmula" (MAJOR) de "adicionou indicador" (MINOR) sem novo vocabulário.
- Bumps explícitos no PR forçam revisão consciente da diferença de output.

**Por que datado para a matriz:**

- A matriz **é alinhada à validação da EBC** (parecer técnico DEZ/2025). Datar reflete essa origem editorial: "matriz aprovada em dezembro de 2025". Semver implicaria nivelar a matriz a artefato de software — não é (é spec de domínio, validada por terceiros).
- Já é o naming usado nos anexos (`anexo-F-matriz-recomendacoes-dez2025.md`). Reusar reduz friction cognitiva.
- Curto e legível em logs ("`matrix_version=dez-2025`") — em vez de `"3.2.1-rc.4"`.

**Por que snapshot em vez de recálculo on-the-fly:**

- Reprodutibilidade trivial: ler do banco, renderizar — 0 chance de drift.
- Performance: leitura de histórico (EPIC-003) é O(1) por registro, sem chamar motor.
- Auditabilidade NRF §9.3: validador externo aprova "estes 14 valores na versão X". Snapshot **é** essa evidência.
- O custo (espaço em disco) é trivial: cada `indicadores_calculados` JSON tem ~3 KB. 10 mil diagnósticos = 30 MB. Postgres não sente.

**Por que JSON imutável em vez de tabelas relacionais (uma linha por indicador):**

- 14 indicadores × estrutura heterogênea (alguns têm faixa, alguns não — NCG absoluto não tem farol, PME varia por setor) → schema relacional vira tabelão com 14 ENUMs e meio dos campos `nullable`.
- O `indicadores_calculados` **só é lido inteiro** (renderiza relatório) — não há query `SELECT * FROM indicadores WHERE indicador='margem_bruta' AND farol='vermelho'`. A análise agregada (EPIC pós-MVP) usa o JSONB com índices GIN se precisar, ou ETL para DW.
- Idempotência mais fácil de validar (1 blob = 1 hash; 14 linhas = N hashes correlatos).

**Por que `null` + mensagem em vez de exception:**

- Robert ​não tem mental model para erro técnico. Mostrar "Indicador indisponível: vendas=0" é informativo; mostrar "Erro 500" é assustador.
- A spec V2.5 §4.5 já adota essa semântica (`"Indisponível"` como categoria de saída).
- Exception forçaria o relatório inteiro a falhar quando um único indicador tem input ruim. Granularidade do `null` permite o relatório sair parcial e útil.

## Alternativas consideradas

### Opção B — Dispatcher de motores (MotorV1, MotorV2, …)

Implementar `App\Domain\Motor\Contract` + `MotorV1` + `MotorV2` + um `MotorFactory` que escolhe a implementação por `motor_version` lido do registro. Diagnóstico antigo reabre e re-renderiza chamando `MotorV1->calcular($quiz_payload)`.

**Por que rejeitada:**

- Custo de manutenção alto: cada mudança de fórmula exige novo `MotorVN`, com **todos** os indicadores que existiam, mesmo os não afetados — duplicação.
- Os testes precisam rodar **N suites** (uma por versão ainda viva), cresce linearmente.
- O ganho prático (recalcular antigo) **não é requisito do MVP**. Não há estória pedindo "veja seu diagnóstico antigo recalculado com motor novo" — pelo contrário, a spec explicita que o diagnóstico antigo retrata o que Roberto viu no dia.
- Defesa em profundidade contra "ter que voltar atrás": Git history do motor preserva código antigo. Reemitir manualmente um diagnóstico antigo (raro, suporte) é viável com `git checkout` + script — não justifica framework permanente.

Se um dia o requisito mudar (improvável), abre-se IDR específica e refactora-se. **A migração de "motor único" para "dispatcher" é mais barata do que manter dispatcher desde o Dia 1.**

### Opção C — Versionamento por hash de fórmulas (sem semver)

Em vez de `motor_version = "1.0.0"`, usar `motor_version = sha256(arquivo_de_formulas.php)`. Auto-detectável; humano não esquece de bumpar.

**Por que rejeitada:**

- Refactor cosmético (renomear variável, reformatar) muda o hash sem mudar comportamento — bump espúrio.
- Hash não é ordenável (`"1.1.0" > "1.0.0"` é trivial; `sha256 > sha256` é sem sentido). Inviável para query "diagnósticos antes da 1.1.0".
- Validador externo (NRF §9.3) precisa referenciar versão **falável** ("aprovo motor 1.0.0"). Hash hexadecimal de 64 chars é impronunciável e ilegível em relatório de parecer.
- Golden hashes (sub-decisão 3) já capturam "mudou comportamento" automaticamente. Não há razão para versionar o motor pelo mesmo mecanismo.

### Opção D — Sem versionamento explícito; usar `created_at` como proxy

Já temos `created_at`. Para saber "qual motor rodou em maio", olha-se o git tag da release em produção naquela data.

**Por que rejeitada:**

- Acoplamento frágil entre app e processo de release: hotfix sem tag, rollback parcial, deploy entre tags — qualquer um quebra o proxy.
- NRF §9.3 (validação externa) exige versão **carimbada no dado**, não inferida do calendário.
- Roberto recebe email "matriz atualizada, seu diagnóstico de março continua válido" — para escrever esse texto precisamos saber qual matriz/motor rodou em março **do registro**, não do servidor de então.

## Consequências

### Para outros agentes (e estórias subsequentes)

- **STORY-027 (quiz)** — rascunho do quiz **NÃO** é persistido na tabela `diagnosticos`. Rascunho fica em tabela à parte (`quiz_rascunhos` — definição é responsabilidade da STORY-027 já que a estória prevê 90 dias). Diagnóstico **só** entra em `diagnosticos` no momento do `submit` que dispara o motor.
- **STORY-028 (motor V1)** — consome esta IDR. Cria namespace `App\Domain\Motor`. `config/motor.php` traz `'version' => '1.0.0'` e `'matrix_version' => 'dez-2025'`. Roda a migration aqui esqueleta. Implementa golden hashes para ≥ 5 fixtures (≥ 10 casos por fórmula da DoD do épico já estão cobertos pelos testes unitários por fórmula; os 5 fixtures são para idempotência ponta-a-ponta).
- **STORY-029 (relatório)** — lê `Diagnostico::indicadores_calculados` e renderiza Blade. **Não invoca motor.** Decisão de Blade server-side vs SPA continua aberta na STORY-029.
- **STORY-030 (motor V2)** — bumpa para `motor_version = "1.1.0"` (MINOR; adiciona indicadores, não muda os existentes). Atualiza fixtures e adiciona hashes para os novos indicadores. **Reusa** os hashes V1 (devem permanecer bit-exatos — se quebrarem, é regressão e o PR é rejeitado).
- **STORY-031 (Resumo Executivo)** — produz `resumo_executivo` JSON conforme estrutura desta IDR. Sem IA, sem `now()`, sem random.
- **STORY-032 (matriz DEZ/2025)** — carrega `config/matriz/dez-2025.php` (config PHP versionado em código). O motor injeta o texto curto da matriz **no snapshot** durante o cálculo. Reabrir relatório antigo lê o texto do snapshot, **não** do config atual. Quando matriz for atualizada (`mar-2026`, etc.), cria novo arquivo `config/matriz/mar-2026.php` lado a lado, sem tocar no antigo.
- **STORY-035 (eventos analíticos)** — eventos `quiz_iniciado` e `diagnostico_concluido` carregam `motor_version` e `matrix_version` no payload (já especificado no `epic.md`).
- **STORY-036 (validação externa)** — parecer cita `motor_version="1.1.0"` e `matrix_version="dez-2025"` como objeto da aprovação. Se algum dos dois mudar pós-parecer, requer reaprovação (gate explícito).
- **EPIC-003 (histórico)** — lê `diagnosticos` filtrado por `empresa_analisada_id`, ordenado por `gerado_em DESC`, snapshot direto na tela. Nada de chamar motor.

### Para o projeto

- **Tabela `diagnosticos`** vira a referência canônica para "o que Roberto viu". Toda análise pós-MVP (medianas setoriais, evolução do farol, métricas de produto) deriva dela.
- **Estória de débito sugerida ao PO (não cria agora):** adicionar índice GIN em `indicadores_calculados` (`USING gin (indicadores_calculados jsonb_path_ops)`) **quando** análise pós-MVP exigir queries do tipo "quantos diagnósticos têm Margem Bruta vermelha". Não no MVP — overhead de índice GIN sem uso.
- **Pequena divergência da estória aprovada pelo PO no Default:** o `epic.md` e a STORY-026 dizem `belongsTo Empresa` e `User`; o domínio efetivo do projeto usa **`EmpresaAnalisada`** e **`Usuario`** (vide `app/Models/EmpresaAnalisada.php` e `app/Models/Usuario.php`). A coluna FK é `empresa_analisada_id` (não `empresa_id`). Isso é alinhamento com o domínio existente — não muda a substância da decisão. Story-026 atualizada nas notas para refletir.

### Trade-offs aceitos

- **Não é possível recalcular diagnóstico antigo com motor novo.** Aceitável — o snapshot **é** o que Roberto viu; mudar isso retroativamente seria semanticamente errado.
- **Espaço em disco cresce monotônico com cada diagnóstico.** Aceitável — ~3 KB por registro. 1 milhão de diagnósticos = 3 GB. Soft delete + anonimização (ADR-003 §Decisão 5) cuida do LGPD.
- **`payload_hash` na coluna é redundante** (calculável de `quiz_payload`). Aceito para permitir índice e query rápida "diagnóstico duplicado nas últimas 24h?" no futuro, sem custo significativo (~64 chars/registro).
- **Falha do motor é exception inesperada** — se o motor levantar exception, o controller deve capturar, logar com `request_id`, e devolver mensagem genérica ao Roberto + opção "tentar de novo". **Não** persistir registro parcial em `diagnosticos`. Critério "tudo ou nada" garantido por DB transaction no controller.

## Como verificar

- **Pest unit por fórmula** (STORY-028 DoD): cada uma das 14 fórmulas tem ≥ 10 casos (cobre normal + extremo). Caminho do `null` validado por fixture específico ("vendas=0 → Margem Bruta `null`").
- **Pest feature golden hashes** (STORY-028 + STORY-030): 5 fixtures de quiz canônico → hash esperado hardcoded → bit-exato a cada CI run.
- **Pest feature persistência** (STORY-028): submit do quiz → `Diagnostico::count() == 1` → `motor_version === config('motor.version')` → `payload_hash === sha256(canonical(quiz_payload))`.
- **Pest feature imutabilidade** (STORY-028): tentativa de `$diagnostico->indicadores_calculados = ...` + `save()` falha por arquitetural (constraint em runtime: setter protegido depois de `wasRecentlyCreated == false`).
- **Pest feature multi-tenant** (STORY-028 + STORY-029): cross-tenant em `GET /diagnosticos/{id}` retorna **404** (IDR-009 — padrão transversal).
- **Smoke pós-deploy:** chamar `/diagnosticos/{id}` para 1 fixture conhecida em homologação; comparar `motor_version` e `matrix_version` do response com `config('motor.version')` e `config('motor.matrix_version')`.
- **Sinais de revisão (quando reabrir esta IDR):**
  1. Requisito real de "Roberto, vê esse antigo recalculado com motor novo" — abre-se IDR de dispatcher.
  2. Volume de diagnósticos > 1M com queries agregadas frequentes em `indicadores_calculados` — abre-se IDR de índice GIN ou ETL para DW.
  3. Auditoria externa exigir prova criptográfica do payload (assinatura digital, não só hash) — IDR de notarização.
  4. Múltiplos motores convivendo (cenário híbrido de A/B testing do algoritmo) — improvável, mas justificaria dispatcher.

## Tipo

- [x] **Padrão transversal**: define o contrato de persistência + versionamento para todos os entregáveis do EPIC-002 e do EPIC-003.
- [ ] **Workaround**: contornar bug/limitação documentado.
- [ ] **Convenção interna**: padrão de código local que precisa ser seguido.
- [ ] **Otimização**: mudança feita por motivo de performance.
- [ ] **Refatoração estrutural**: mudança que afeta vários módulos por motivo de qualidade.

---

## Histórico

- 2026-05-25 — criada como `proposed` pelo Arquiteto (claude-opus-4-7) durante a STORY-026 (SPIKE). Decisão tomada considerando 4 opções (A adotada; B/C/D rejeitadas). Default do PO no `epic.md` (snapshot + semver inteiro + matrix datada) foi confirmado pela análise — IDR formaliza com a granularidade técnica que estava faltando (canonicalização do JSON, golden hashes, política de `null`, divergência nominal `EmpresaAnalisada`/`Usuario` vs `Empresa`/`User` da estória).
- 2026-05-25 — **`accepted` pelo PO (Alexandro)** no chat (mensagem: *"sim, confirmo. Siga em frente"*). PO também confirmou explicitamente que **rascunho do quiz (STORY-027) fica em tabela separada `quiz_rascunhos`, não em `diagnosticos`** — premissa registrada nesta IDR § Consequências e agora ratificada. STORY-026 movida para `done`. STORY-027, STORY-028 e STORY-029 destravadas.
- 2026-05-25 — **Aditivo após entrega da STORY-028 (Motor V1):** durante a implementação, o Programador identificou que o `resumo_executivo` (coluna `NOT NULL` em `diagnosticos`) é **responsabilidade da STORY-031**, não da STORY-028. Decisão adicional do PO ratificada: **Motor V1 grava placeholder explícito** `{"pendente_story": "STORY-031", "fallback_acionado": false}` na coluna `resumo_executivo` até a STORY-031 entrar. Ao executar a STORY-031, o `motor_version` sobe (MINOR — 1.0.0 → 1.1.0 se STORY-031 antes de STORY-030, senão acompanha o bump da V2) e os golden hashes V1 são re-emitidos junto. Diagnósticos antigos com placeholder permanecem inalterados (snapshot). Validador externo (STORY-036) deve estar ciente de que diagnósticos com `resumo_executivo.pendente_story != null` são de fase pré-Resumo Executivo e não refletem o produto final — espera-se 0 desses em produção quando STORY-031 fechar.

## Aceite do PO

- [x] PO (Alexandro) confirma decisão no chat — 2026-05-25.
- [x] PO confirma premissa do rascunho do quiz em tabela separada (`quiz_rascunhos`).
- [x] `status` movido para `accepted`; `approved_by: Alexandro`, `approved_at: 2026-05-25` registrados no front-matter.
- [x] STORY-026 movida para `done`; STORY-027/028/029 destravadas.
