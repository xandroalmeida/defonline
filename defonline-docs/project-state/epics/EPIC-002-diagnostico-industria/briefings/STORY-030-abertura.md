---
artifact: story-briefing
target_role: programador
story_id: STORY-030
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
written_by: po (alexandro)
written_at: 2026-05-25
status: ready-for-pickup
parallel_with: STORY-031
---

# Briefing de abertura — STORY-030 (Programador)

> Esta estória **roda em paralelo com a STORY-031** (Resumo Executivo). As duas vão bumpar `motor_version`. **Leia a seção §4 "Coordenação do bump em paralelo" antes de tudo.**

## Estado em que a estória chega

V1 (STORY-028) está `done`: 7 indicadores essenciais com farol + NCG absoluto informativo, cobertura 100% em `app/Domain`, 5 fixtures canônicos com golden hashes.

Você consome:

- **`App\Domain\Motor\Motor`** — orquestrador pronto, recebe payload + setor, devolve array com `indicadores_calculados` + metadados. Não muda arquitetura: você **adiciona** classes em `Indicadores/` seguindo o contrato.
- **`Indicadores\Indicador` (interface)** — `calcular(QuizPayload $p): IndicadorResultado`. Reuse exatamente.
- **`IndicadorResultado`** value object — `valor`, `farol`, `motivo`, `mensagem`.
- **`config/motor.php`** — `'version' => '1.0.0'`, `'matrix_version' => 'dez-2025'`.
- **`config/motor/faroes-industria.php`** — você adiciona as faixas dos 7 novos indicadores aqui.
- **`Calculos\DreAdaptada`** — Vendas, EBITDA, LOL, PL já calculados. Você reusa para os indicadores que precisam de EBITDA (Margem EBITDA, Desp.Fin./EBITDA).
- **5 fixtures canônicos** em `tests/Domain/Motor/Fixtures/*.json` + `GoldenHashesTest.php` — você vai **re-emitir os hashes** ao bumpar a versão.

## Escopo — os 7 indicadores que faltam

Da spec §4.5 Anexo D + faixas do Anexo E filtradas para Indústria:

| # | Indicador | Fórmula | Faixas Indústria (verde/amarelo/vermelho) |
|---|---|---|---|
| 2 | **Margem EBITDA** | EBITDA / Vendas × 100 | > 20% / 15,01–20% / ≤ 15% |
| 5 | **Desp. Financeiras / EBITDA** | Despesas financeiras / EBITDA × 100 | ≤ 35% / 35,01–50% / > 50% |
| 6 | **Fontes de Recursos (PC / PL)** | PC / PL | ≤ 0,5 / 0,501–1 / > 1 |
| 7 | **Giro do Ativo** | Vendas / Ativo Total | > 2 / 1,01–2 / ≤ 1 |
| 12 | **PME (prazo de estoque)** | Q11 (declarado) | ≤ 30 dias / 30,01–60 / > 60 |
| 14 | **Inadimplência** | Q13 (declarada) | ≤ 3% / 3,01–5% / > 5% |
| — | **Ciclo Operacional** | PME + PMR | (sem faixa direta no Anexo E — Indústria; ver nota abaixo) |

**Nota sobre Ciclo Operacional:** o `epic.md` lista 7 indicadores a completar incluindo "Ciclo Operacional", mas o Anexo D só tem 14 e Ciclo Operacional **não está entre eles** explicitamente — só Ciclo Financeiro (PME+PMR−PMC) está no item #8. Vou **interpretar como complementar informativo**: Ciclo Operacional = PME + PMR (sem PMC), exibido como linha contextual sem farol formal, valendo a regra do `casos-extremos.md` (faltante de PMR ou PME = `indisponivel`). **Se você (Programador) achar que o §4.5 manda algo diferente, PARE e pergunte** antes de implementar.

## Coordenação do bump em paralelo (LEIA ANTES DE TUDO)

A STORY-031 (Resumo Executivo) também vai bumpar `motor_version`. Sua estória e a 031 vão concorrer pelo mesmo `config/motor.php`. A regra é a seguinte:

**Protocolo do bump:**

1. **No início da sessão:** leia `config('motor.version')`. Hoje é `"1.0.0"`. Anote.
2. **Implemente sua estória**, sem mexer em `motor.version` ainda.
3. **No momento de abrir o PR:** verifique de novo `config('motor.version')` no branch `main` atual (`git fetch && git show origin/main:config/motor.php`).
   - Se ainda é `"1.0.0"` → você vai bumpar para `"1.1.0"`.
   - Se a STORY-031 já mergeou e está em `"1.1.0"` → você vai bumpar para `"1.2.0"`.
4. **Re-emita TODOS os golden hashes** em `GoldenHashesTest.php` (5 fixtures × hash novo) usando o tinker do `idempotencia.md` §3.
5. **PR description obrigatória:**
   ```
   Bump motor_version: X.Y.0 → X.(Y+1).0
   Motivo: STORY-030 adiciona 7 indicadores (Margem EBITDA, Desp.Fin/EBITDA,
   Fontes de Recursos, Giro do Ativo, PME, Inadimplência, Ciclo Operacional).
   Golden hashes re-emitidos para incluir os 7 novos no snapshot.
   ```
6. **Se ao rebase do PR a 031 já mergeou primeiro** (conflito em `motor.php` e `GoldenHashesTest.php`):
   - `git rebase main` → resolve conflito em `motor.php` (a versão mergeada provavelmente está em `"1.1.0"` da 031; você bumpa pra `"1.2.0"`).
   - Resolve conflito em `GoldenHashesTest.php`: você **re-emite todos os hashes do zero** (porque o motor agora produz tanto o resumo_executivo da 031 quanto seus 7 novos indicadores).
   - Atualiza PR description: "Bump 1.1.0 → 1.2.0 (race com STORY-031)".

**Pontos críticos:**

- Hashes V1 (STORY-028) **vão mudar** quando você adicionar os 7 indicadores, porque a saída do motor cresce (de 8 entradas no array para 15: 14 com farol + NCG abs). Isso é esperado e é o motivo do MINOR bump. Não é regressão.
- Diagnósticos antigos persistidos em `motor_version="1.0.0"` continuam intactos no banco (snapshot — IDR-010 §sub-decisão 4). Não recalcule.

## Ordem sugerida de execução

Estimado L. Esta ordem minimiza retrabalho:

1. **Confirme `motor_version` no início** (~5 min)
   - `php artisan tinker --execute='echo config("motor.version").PHP_EOL;'` → deve imprimir `1.0.0`.
   - Se já estiver em outro valor (031 mergeou primeiro), você vai bumpar a partir desse valor (vide §Coordenação acima).

2. **Faixas no config** (~15 min)
   - Edite `config/motor/faroes-industria.php` adicionando as faixas dos 7 indicadores listados na tabela acima. Use o mesmo formato dos 7 da V1.

3. **TDD por indicador** (~3-4h — núcleo)
   - Ordem sugerida (do mais simples ao mais complexo):
     1. **Inadimplência** (Q13 direto, mais simples)
     2. **PME** (Q11 direto)
     3. **Margem EBITDA** (reusa EBITDA já calculado em DreAdaptada)
     4. **Desp.Fin./EBITDA** (reusa EBITDA)
     5. **Giro do Ativo** (reusa AT já calculado)
     6. **Fontes de Recursos** (precisa de PC — Q06+Q07 — e PL — já em DreAdaptada)
     7. **Ciclo Operacional** (PMR + PME; sem PMC; informativo, sem farol)
   - Para cada um: 10+ casos por indicador (verde típico, amarelo nas 3 fronteiras, vermelho típico, ≥ 4 casos extremos do `casos-extremos.md`).
   - **Aritmética em bcmath escala 4.** Float arithmetic em monetário = bug.
   - **Ciclo Operacional não tem farol** — `farol = 'nenhum'` na saída, similar ao NCG absoluto. **Sem mensagem semântica por faixa** (não tem; é só informativo). Se a `casos-extremos.md` não cobre, retorna mensagem padrão `"Indicador informativo: ciclo operacional = PMR + PME"`. **Se você achar que isso é interpretação minha indevida, PARE e pergunte**.

4. **Atualize o orquestrador `Motor::indicadores()`** (~15 min)
   - Adicione os 7 novos no array de indicadores que o motor itera. Cuidado com **ordem** (ela vira parte da chave do snapshot — ordem original do motor, antes do Postgres reordenar via jsonb). Sugiro a ordem do Anexo D: 1..14, com NCG abs no slot #9.

5. **Atualize `IndicadorFormatter::ORDEM_ESSENCIAIS`** em `app/Support/Relatorio/IndicadorFormatter.php` (~10 min)
   - Adicione os 7 novos códigos. Sem essa atualização, o relatório (STORY-029) **não os exibe** mesmo o motor calculando. Adicionar `nome humanizado` para cada código novo no catálogo da classe.
   - O formato dos novos: Margem EBITDA (%), Desp.Fin./EBITDA (%), Fontes de Recursos (× decimal), Giro do Ativo (×), PME (dias), Inadimplência (%), Ciclo Operacional (dias).

6. **Atualize fixtures e re-emita golden hashes** (~45 min)
   - Edite os 5 fixtures em `tests/Domain/Motor/Fixtures/` para garantir que os campos novos necessários (Q11 PME, Q13 inadimplência) estão presentes — alguns já têm, outros podem estar em `null`. Confira que cada fixture exercita pelo menos 1 caso novo (verde/amarelo/vermelho).
   - Considere adicionar **2 fixtures novos** se faltar cobertura: `quiz_industria_margem_ebitda_amarelo.json` e `quiz_industria_inadimplencia_alto.json` (decisão do programador).
   - Re-emita os hashes esperados no `GoldenHashesTest.php` rodando o tinker do `idempotencia.md` §3 e copiando o output.

7. **Bump `motor_version`** (~5 min)
   - Edite `config/motor.php`: `'version' => '1.0.0'` → `'1.1.0'` (assumindo que 031 ainda não mergeou; se mergeou, → `'1.2.0'`).
   - Atualize o doc-block do `Motor::class` com a nova versão.

8. **Verificar STORY-029 e seeder de demo** (~30 min)
   - Rode `php artisan db:seed --class=DiagnosticosDemoSeeder --force` em dev. Os 5 fixtures geram 5 diagnósticos novos com `motor_version="1.1.0"`.
   - Navegue para `/diagnosticos/{id}` de cada um e confira visualmente: o relatório agora deve ter 13 linhas com farol + 1 card NCG + 1 linha de Ciclo Operacional informativa.
   - Se o componente `<x-relatorio.linha-indicador>` quebrar com algum indicador novo (formatação, faixa de cor desconhecida), corrija. O Formatter já documentado cobre os tipos.

9. **Multi-tenant + cross-tenant** (~10 min)
   - Não muda. Replique pattern dos testes da 028 se adicionou rota nova (improvável — não adicionou).

10. **Cobertura ≥ 98% no pacote Motor** mantida.

## Pegadinhas

- **`config('motor.version')` em runtime vs hardcoded no PR:** os testes lêem `config('motor.version')` para asserções. Quando você bumpa o config, todos os testes que assertam `expect($d->motor_version)->toBe('1.0.0')` quebram. Atualize-os para o novo valor (ou use `config('motor.version')` no expect — preferência).
- **Hashes V1 da 028 vão mudar** porque a saída agora tem 15 entradas em vez de 8. Isso é correto (mudou comportamento). PR description **precisa** justificar.
- **Ciclo Operacional sem farol:** se você decidir colocar `farol = 'nenhum'`, o relatório (STORY-029) precisa renderizá-lo. Confira que `<x-relatorio.linha-indicador>` aceita esse caso ou crie variante (ou adicione na lista de "renderizado em card separado" se preferir consistência com NCG abs).
- **`epic.md` lista "Ciclo Operacional" mas Anexo D não.** Se o §4.5/Anexo D ainda contradisse, **PARE e pergunte** ao PO. Não invente fórmula.
- **`config/motor/faroes-industria.php`:** copy literal das faixas do Anexo E. Mudança requer PO.
- **Inadimplência (Q13) > 100%** já é tratado pelo validador no quiz (STORY-027). Se chegar no motor com > 100, é invariante violada — exception (não null + motivo).

## Quando escalar para o PO

- Se a fórmula de "Ciclo Operacional" não estiver clara na spec — **PARE**.
- Se uma das 7 fórmulas novas precisar de campo que o quiz da STORY-027 não está coletando — **PARE**. STORY-027 só pega o que está no Anexo A.
- Se a mudança do header da tabela do relatório (de 7 para 13 indicadores com farol) ficar visualmente confusa em mobile — **PARE**, posso decidir entre cards-por-categoria ou outra hierarquia.

## Quando avisar o PO em meio à execução

- Ao terminar o passo 3 (7 indicadores calculando + 70+ testes verdes) — *"V2 nasceu"*.
- Ao terminar o passo 8 (relatório visual com 14 indicadores OK em mobile + desktop) — *"pronto para PR"*. Verifico visualmente antes de aprovar.

## Referências obrigatórias

- `defonline-docs/especificacao/V2/especificacao-funcional.md` §4.5 + Anexos D, E
- `defonline-docs/project-state/decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md` (regra do bump)
- `defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/casos-extremos.md` (cobrir os novos)
- `defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/idempotencia.md` (golden hashes)
- `app/app/Domain/Motor/` (estrutura existente da V1)
- `app/app/Support/Relatorio/IndicadorFormatter.php` (catálogo de formatação)
- `defonline-docs/skills/po/references/agent-task-format.md`

## Checklist de "puxei a estória, posso começar?"

- [ ] Li a STORY-030 inteira.
- [ ] Li este briefing.
- [ ] Li a §4 "Coordenação do bump em paralelo".
- [ ] Confirmei `config('motor.version')` atual.
- [ ] Anotei se STORY-031 já está em `in_progress` (impacta o número do bump).
- [ ] Atualizei front-matter da STORY-030 (`status: in_progress`, `owner_agent`, `updated_at`).
- [ ] Atualizei `index.json` correspondente.
- [ ] Comecei pelo passo 2 (faixas no config) — porque é o input para o TDD.

— PO (Alexandro)
