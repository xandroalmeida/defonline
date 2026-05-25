---
artifact: story-briefing
target_role: programador
story_id: STORY-028
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
written_by: po (alexandro)
written_at: 2026-05-25
status: ready-for-pickup
---

# Briefing de abertura — STORY-028 (Programador)

> Este briefing **não substitui** a estória. Leia `STORY-028-motor-7-indicadores-essenciais.md` por inteiro, depois volte aqui para o roteiro operacional. O briefing é um mapa por cima: o que está pronto, em que ordem encarar, onde estão as pegadinhas.

## Estado em que a estória chega

A STORY-026 (SPIKE do Arquiteto) já fechou em 2026-05-25 e deixou pronto **para você consumir direto**:

- **IDR-010** (`decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md`, `accepted` pelo PO) — define versionamento, persistência por snapshot, idempotência por SHA-256, política de evolução, política de casos extremos. **Esta IDR manda nas decisões técnicas desta estória.**
- **Migration esqueleto** em `app/database/migrations/2026_05_25_000010_create_diagnosticos_table.php` — você roda como **primeiro ato** da estória.
- **Model Eloquent** em `app/app/Models/Diagnostico.php` — pronto, com casts `AsArrayObject`, `belongsTo(Usuario)` + `belongsTo(EmpresaAnalisada)`, Global Scope multi-tenant via `BelongsToUsuarioScope`, helper `hasSameInputsAs()` para teste de idempotência. Falta só a Factory.
- **Catálogo de casos extremos** em `epics/EPIC-002-diagnostico-industria/design/casos-extremos.md` — 34 entradas, 17 códigos de motivo padronizados. Cada código vira teste em `tests/Domain/Motor`.
- **Protocolo de idempotência** em `epics/EPIC-002-diagnostico-industria/design/idempotencia.md` — canonicalização do `quiz_payload`, layout dos golden hashes, política de bump, fontes de não-determinismo proibidas, 5 fixtures recomendados.

Você **não precisa** decidir esquema de tabela, formato dos JSON, política de `null`, política de bump de versão, ou layout de fixtures — tudo isso está decidido.

## Divergências entre a redação da STORY-028 e a IDR-010 — IDR vence

A STORY-028 foi redigida antes do SPIKE. Em três pontos a IDR-010 (que o PO aceitou em 2026-05-25) decidiu diferente:

| Ponto | STORY-028 (rascunho) | IDR-010 (vigente) — siga este |
|---|---|---|
| Forma do retorno de indicador | `{disponivel, valor, farol, mensagem_curta}` | `{valor: null OU número, farol, motivo, mensagem}` — chave `valor=null` substitui `disponivel=false`; `motivo` é código estável |
| Fixtures | `tests/fixtures/quiz/industria/*.yaml` | `app/tests/Domain/Motor/Fixtures/*.json` canonicalizado |
| Faixas de farol | `config/motor/faroes-industria.php` (PHP array) | **OK manter PHP array** — IDR-010 não muda isso; só registre o path final no `config/motor/faroes-industria.php` |

Se algo mais conflitar, **a IDR-010 vence**. Registre nas Notas do agente e siga adiante; o PO ajusta a redação da STORY-028 retroativamente se necessário.

## Escopo desta estória — só V1 (7 indicadores + NCG abs)

Repetindo o que está na estória, porque é fácil de errar: nesta sessão entregam-se **7 indicadores com farol** + **NCG absoluto sem farol** (informativo). Os outros 7 indicadores (Margem EBITDA, Desp. Fin./EBITDA, Fontes PC/PL, Giro do Ativo, PME, Inadimplência, Ciclo Operacional) ficam para a **STORY-030** com bump para `motor_version = "1.1.0"`.

Os 7 essenciais desta estória:

1. Margem Bruta
2. Margem Líquida
3. Dívida Líquida / EBITDA
4. NCG / Vendas
5. PMR (declarado em Q12)
6. PMC (declarado em Q10)
7. Ciclo Financeiro (PMR + PME − PMC) — note que **PME** entra no cálculo do Ciclo, mas a Margem EBITDA não. **PME é coletada e usada como input** (não é exibida como linha do relatório nesta V1; entra como linha própria na STORY-030).

Mais o **8º item informativo:** NCG absoluto (R$), sem farol, com mensagem semântica em 3 faixas (espec §4.5).

## Ordem sugerida de execução

Estimado L (sessão longa). Esta ordem minimiza retrabalho:

1. **Migration + Factory** (~30 min)
   - `php artisan migrate` em homol — roda a migration esqueleto da STORY-026.
   - Cria `Database\Factories\DiagnosticoFactory` mínima (gera UUIDs, payload genérico que pode ser sobrescrito por `state()`).
   - Smoke: `Diagnostico::factory()->create()` deve inserir sem erro e respeitar Global Scope (criar dentro de `actingAs($usuario)`).

2. **Skeleton do pacote `App\Domain\Motor`** (~30 min)
   - Cria `app/Domain/Motor/` (PSR-4 já mapeado em `composer.json`; se não estiver, adiciona).
   - Stub das classes principais: `Motor`, `QuizPayloadCanonicalizer`, `IndicadorResultado` (value object), `Indicadores\Indicador` (interface), `Farois\FarolIndustria`.
   - **Sem lógica ainda** — só estrutura + assinaturas.
   - Cria `config/motor.php` com `['version' => '1.0.0', 'matrix_version' => 'dez-2025']`.

3. **TDD por indicador** (~3-4h — núcleo da sessão)
   - Para cada um dos 7 indicadores essenciais (ordem sugerida: Margem Bruta → Margem Líquida → PMR → PMC → Ciclo Financeiro → Dívida Líq./EBITDA → NCG/Vendas):
     - **Teste primeiro** — pelo menos 10 casos por indicador (`Pest dataset`), cobrindo: verde típico, amarelo limítrofe (3 fronteiras), vermelho típico, **casos extremos** do `casos-extremos.md` correspondentes ao indicador.
     - **Implementação depois** — classe `Indicadores\<NomeDoIndicador>` que recebe `quiz_payload` canonicalizado e devolve `IndicadorResultado`.
     - **Aritmética em `bcmath`** (`bcdiv`, `bcmul`, `bcsub`, `bcadd`) com escala 4. Float arithmetic em valor monetário = bug em produção (já dito na idempotencia.md).
   - Depois dos 7: implementa **NCG absoluto** (sem farol, 3 mensagens por faixa — texto exato do §4.5 da spec).

4. **Anualização e cálculo de DRE adaptada** (~30 min)
   - Antes dos indicadores rodarem, valores mensais (Q08, Q09, Q14, Q15, Q16) são multiplicados por 12 (espec §4.5 — *"As respostas Q08 (compras médias mensais), Q09 (vendas médias mensais)… são multiplicadas por 12"*).
   - PL = Ativo Total − Passivo Circulante (simplificação validada pela EBC, espec §4.5).
   - EBITDA = Lucro Bruto − Despesas fixas − Despesas variáveis = (Vendas − Compras) − Q14×12 − Q15×12.
   - Centralizar em `Motor\Calculos\DreAdaptada` para reutilizar entre os 7 indicadores.

5. **Persistência idempotente** (~45 min)
   - `App\Actions\CalcularDiagnostico::execute(EmpresaAnalisada $empresa, array $quizPayload): Diagnostico`.
   - Dentro: canonicaliza payload → calcula → monta `indicadores_calculados` JSON → grava `Diagnostico` com `motor_version`, `matrix_version`, `payload_hash`, `gerado_em = now()`, `setor = 'industria'`.
   - **Transaction:** falha do motor (exception inesperada) faz `rollback` — não persiste registro parcial. Exception conhecida (caso extremo) **não é exception** — vira `valor=null` no JSON.

6. **Golden hashes** (~45 min)
   - Cria 5 fixtures em `app/tests/Domain/Motor/Fixtures/`: `quiz_industria_saudavel.json`, `quiz_industria_atencao.json`, `quiz_industria_alerta.json`, `quiz_industria_ncg_negativo.json`, `quiz_industria_70pct_indisponivel.json` (lista no `idempotencia.md`).
   - Cria `GoldenHashesTest.php` rodando os 5 fixtures e comparando com hashes esperados (hardcoded).
   - Para gerar o hash esperado da primeira vez, use o `php artisan tinker` mostrado no `idempotencia.md` §3.

7. **Teste multi-tenant** (~15 min)
   - Outro usuário tenta `GET /diagnosticos/{id}` do diagnóstico do Roberto → 404 (IDR-009 — 404 silente via Global Scope). Reusa o padrão da STORY-014.

8. **Latência baseline** (~15 min)
   - Script Pest que mede 50 execuções com fixtures variadas. `expect(p95)->toBeLessThan(500)` (ms) — esta estória, sem render do relatório, deve ficar muito abaixo.

9. **Cobertura ≥ 98% no `app/Domain/Motor`** — gate de regra de núcleo. Verifique com `php artisan test --coverage --min=98 --filter Domain/Motor`. Conforme já implementado na STORY-010 (PCOV).

## Pegadinhas (das que custam tempo se você não souber)

- **`AsArrayObject` cast volta `ArrayObject`, não `array`.** Para hashear `quiz_payload` lido do banco em teste, use `iterator_to_array($diagnostico->quiz_payload)` antes de `json_encode`. A IDR-010 §6 da `idempotencia.md` cita isso.
- **`json_encode` PHP preserva ordem de inserção do array.** Canonicalize **antes** de `json_encode`; não confie em ordenação automática.
- **Postgres `jsonb` reordena chaves ao armazenar.** Por isso o `payload_hash` é calculado **antes** do `INSERT` e persistido — não recalcule a partir do `SELECT`.
- **`now()` proibido no motor.** `gerado_em` é setado no controller/action, não dentro do motor. Idempotência depende disso.
- **`usort` sem critério secundário é instável.** Para ordenar destaques do Resumo Executivo (não nesta estória, mas o padrão começa aqui): severidade DESC + ordem do Anexo D ASC.
- **PME é coletada (Q11) mesmo nesta V1.** Ela alimenta o Ciclo Financeiro. Só não é exibida como linha própria — isso é responsabilidade do relatório (STORY-029), não sua.
- **NCG absoluto tem código de farol `'nenhum'`** (não `null` na chave farol). Valor é número (`R$`), farol é a string literal `'nenhum'`. Mensagem semântica em 1 de 3 faixas (texto exato do §4.5).
- **Validação cruzada DRE×Balanço (§6.6)** **não** é desta estória — fica para STORY-034. Aqui você confia no payload já validado pela STORY-027.
- **Matriz DEZ/2025 (texto de recomendação)** **não** é desta estória — `mensagem_curta` aqui pode ser placeholder genérico (`"Faixa verde"`, `"Faixa amarela"`, `"Faixa vermelha"`); STORY-032 troca pelos textos reais. O snapshot já fica com a string final que entrou — a coluna `matrix_version` rastreia isso.

## Quando escalar para o PO

- Se algum dos 7 indicadores essenciais tiver fórmula que **não bate** com a §4.5 quando você for codar, **PARE** e me avise. Não invente.
- Se você descobrir que a STORY-027 (quiz) não está enviando algum campo necessário (ex.: estoques Q05 separado de Q03), **PARE** — STORY-027 e STORY-028 podem estar rodando em paralelo; o contrato entre elas é a chave.
- Se a cobertura ≥ 98% bater no teto de algum caminho impossível de cobrir (ex.: defensive default num switch), **abra IDR** justificando suppressão local; não baixe o gate.

## Quando avisar o PO em meio à execução

- Ao terminar o passo 3 (TDD dos 7 indicadores) — *"motor V1 calculando, faltam fixtures e golden hashes"*. Sinal para eu começar a coordenar o handoff com STORY-029 (relatório).
- Ao terminar o passo 6 (golden hashes verdes) — *"motor pronto para revisão"*. Sinal para abrir PR.
- Ao surgir qualquer dúvida de escopo — não invente, PARE e pergunte.

## Referências obrigatórias

- `defonline-docs/project-state/decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md`
- `defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/casos-extremos.md`
- `defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/idempotencia.md`
- `defonline-docs/especificacao/V2/especificacao-funcional.md` §4.5 (motor) e Anexos D/E
- `defonline-docs/skills/po/references/agent-task-format.md` (protocolo geral)
- `defonline-docs/project-state/quality-standards.md` (gates de cobertura e regra de núcleo)
- `defonline-docs/project-state/decisions/idr/IDR-009-cross-tenant-403-vs-404.md` (404 silente — padrão transversal)
- `app/app/Models/Diagnostico.php` (model já pronto — leia para entender o contrato)

## Checklist de "puxei a estória, posso começar?"

- [ ] Li a STORY-028 inteira.
- [ ] Li este briefing.
- [ ] Li a IDR-010 inteira.
- [ ] Li `casos-extremos.md` e `idempotencia.md` (skim ok, mas marque os 5 fixtures recomendados — você vai criar todos).
- [ ] Atualizei o front-matter da STORY-028 para `status: in_progress`, `owner_agent: programador (claude-<id>)`, `updated_at: <hoje>`.
- [ ] Atualizei `index.json` correspondente.
- [ ] Comecei pela migration (passo 1).

Boa sessão. O motor é o coração do produto — vale a meticulosidade. Se algo cheirar a improviso, é hora de parar e perguntar.

— PO (Alexandro)
