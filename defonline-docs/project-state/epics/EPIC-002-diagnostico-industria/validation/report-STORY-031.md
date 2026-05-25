---
artifact: story-validation-report
story_id: STORY-031
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
validated_by: claude-validador
validated_at: 2026-05-25
verdict: approved_with_pending
pending_count: 2
blockers: 0
related_idr: IDR-010
related_briefing: briefings/STORY-031-abertura.md
---

# Relatório de validação independente — STORY-031

> Validação pontual, sob demanda do PO em 2026-05-25, após a estória ser fechada no mesmo dia pelo agente Programador (commit `7feb406`). **Não substitui** a validação final do EPIC-002 (STORY-037).

## Veredito

**`approved_with_pending`** — implementação está em alta aderência à spec §4.7.1 e à IDR-010. Os 9 CAs da estória têm evidência concreta no código + testes + integração + view. Existem **2 pendências não-bloqueantes** (uma de divergência semântica menor com a spec, outra de cobertura visual ainda não conferida). Ambas podem entrar no backlog do EPIC-002 ou serem fechadas durante a STORY-037 — não impedem o avanço para STORY-032.

## Escopo da validação

- Spec §4.7.1 do `especificacao-funcional.md` (autoritativa) lida e comparada linha-a-linha.
- Briefing de abertura `briefings/STORY-031-abertura.md` (revisão crítica de 25/mai).
- Estória `stories/STORY-031-resumo-executivo-deterministico.md` (CA-1 a CA-9).
- Código de implementação:
    - `app/Domain/Motor/ResumoExecutivo.php` (489 linhas, nova).
    - `app/Domain/Motor/Motor.php` (integração).
    - `app/Actions/CalcularDiagnostico.php` (persistência do snapshot).
    - `app/Models/Diagnostico.php` (cast AsArrayObject).
    - `resources/views/components/relatorio/resumo-executivo.blade.php` (89 linhas, novo).
    - `resources/views/diagnosticos/show.blade.php` (render + fallback p/ snapshot legado).
    - `config/motor.php` (bump 1.1.0 → 1.2.0).
- Testes:
    - `tests/Domain/Motor/ResumoExecutivoTest.php` — 17 cenários.
    - `tests/Domain/Motor/GoldenHashesTest.php` — 5 fixtures canônicos re-emitidos.
    - `tests/Feature/Http/DiagnosticoShowTest.php` — 3 testes novos (render, fallback, snapshot legado).
- Commit `7feb406` — autoria do PO + co-author Claude Sonnet 4.6.
- Simulação independente em Python do algoritmo do Passo 1 + Passo 5 + Passo 6 contra 10 cenários canônicos (anexo §5).

## Limitação metodológica

**A suíte Pest não foi executada localmente nesta sessão de validação** — o sandbox onde rodo não tem PHP nem Docker. A confirmação de "695 testes verdes / cobertura 100% no pacote Motor" vem do **commit message** do Programador (commit `7feb406`) e da estrutura dos testes, não de re-execução independente. **Sub-pendência registrada** (P-002 abaixo).

## CA por CA — evidências

### CA-1 — Classe `ResumoExecutivo` segue algoritmo §4.7.1

**Status:** ✓ atendido.

Conferi linha por linha contra a spec:

| Passo da spec §4.7.1 | Implementação |
|---|---|
| **Passo 1 — Veredito** (4 condições em ordem) | `classificarVeredito()` (linhas 199-213) — ordem top-down preservada literalmente. |
| **Passo 2 — Destaques negativos** (≤ 2, vermelhos por severidade DESC, depois amarelos) | `selecionarDestaquesNegativos()` (221-236) + `ordenarPorSeveridade()` (277-302). |
| **Passo 2 — Destaque positivo** (verde com maior distância da fronteira amarela) | `selecionarDestaquePositivo()` (244-256) + `ordenarPorFavorabilidade()` (313-336). |
| **Passo 2 — Empate por Anexo D ASC** | `usort` com critério secundário `$a['anexoD'] <=> $b['anexoD']` (298, 332). |
| **Passo 3 — Truncamento ~80 chars** | `truncarMensagem()` (442-462) — preserva palavra, usa primeira frase quando há ponto antes do limite, sufixo `…` (caractere único). `LIMITE_MENSAGEM_DESTAQUE = 80` (linha 77). |
| **Passo 3 — Prefixo "Por outro lado, "** no positivo | `textoDestaque(..., prefixarPorOutroLado: true)` (423-431) — literal `'Por outro lado, '`. |
| **Passo 4 — Linha 5 fixa** | `LINHA_FIXA` (71) — texto literal da spec. |
| **Passo 5 — Fallback I/14 ≥ 0.70** | linha 101: `if ($I / 14 >= 0.70)`. Texto literal em `MSG_FALLBACK` (73). |
| **Passo 6 — Todos verdes** | linhas 118-124 — checa `V==0 && A==0 && I==0 && Vd==N`; substitui destaques por `MSG_TUDO_VERDE` (75). |
| **Passo 6 — Todos vermelhos** | linhas 125-128 — checa `V==N` (sem destaque positivo). |

### CA-2 — Veredito correto em pelo menos 6 cenários

**Status:** ✓ atendido **e excedido**.

`ResumoExecutivoTest.php` tem **9 cenários explícitos de veredito** mapeados aos CAs (linhas 78, 91, 104, 124, 145, 162, 189, 327, 355):

1. Todos verde → `saudavel` + mensagem extra.
2. 1 vermelho, 0 amarelos → `precisa_atencao` (regra V≥1).
3. 5 amarelos sem vermelho → `saudavel` (A/N=5/13<0.50).
4. 7 amarelos sem vermelho → `precisa_atencao` (A/N=7/13≥0.50).
5. 5 vermelhos → `em_alerta` (V/N=5/13≈0.38≥0.30).
6. Todos vermelhos → `em_alerta` sem destaque positivo.
7. ≥70% indisponíveis → fallback.
8. V/N borderline (V=1, N=4 → V/N=0.25<0.30) → `precisa_atencao` (regra V≥1).
9. N=0 patológico → fallback defensivo.

Reverifiquei a lógica em **simulação Python independente** contra 10 cenários (anexo §5) — todos os outputs batem.

### CA-3 — Destaques ordenados deterministicamente

**Status:** ✓ atendido.

`ResumoExecutivoTest.php` linhas 214 e 229 cobrem empate de severidade em vermelhos e empate de favorabilidade em verdes — ambos com desempate por Anexo D ASC. A implementação usa `usort` com comparador completo (sev/fav DESC + Anexo D ASC); não há `usort` instável escondido.

### CA-4 — Truncamento ~80 chars preservando palavra

**Status:** ✓ atendido (com **ressalva semântica — ver pendência P-001**).

`ResumoExecutivoTest.php` linha 248 cobre truncamento e linha 367 cobre o caso "primeira frase com `. ` antes do limite". A implementação usa `mb_*` (multibyte-safe) e o ellipsis horizontal `…` (caractere único Unicode), conforme briefing.

### CA-5 — NCG abs e Ciclo Operacional excluídos da contagem e dos destaques

**Status:** ✓ atendido.

Whitelist `CODIGOS_ANEXO_D` (linhas 48-63) inclui `ncg_absoluto` mas exclui `ciclo_operacional`. Em `contar()` (175-197) o `match` por farol manda `default => null` para `farol='nenhum'`, garantindo que NCG abs não entra em V/A/Vd. Em `ordenarPorSeveridade()` o filtro `$ind['farol'] !== $farolAlvo` exclui ambos (porque nenhum tem farol verde/amarelo/vermelho). Testes linhas 270 e 287 cobrem.

### CA-6 — Snapshot persistido + determinismo 100×

**Status:** ✓ atendido.

Persistência confirmada:
- `Motor::calcular()` retorna `resumo_executivo` no array final (linha 85 do Motor.php).
- `CalcularDiagnostico::execute()` grava em `diagnosticos.resumo_executivo` (linha 55 da action).
- Coluna é JSONB com cast `AsArrayObject` no model (Diagnostico.php linha 70).
- `Diagnostico::$guarded = ['indicadores_calculados', 'resumo_executivo', ...]` (linha 56) — sem update após INSERT, conforme IDR-010 §Sub-decisão 2 (imutabilidade).

Determinismo: teste linha 308 do `ResumoExecutivoTest` executa 100 vezes e exige saída bit-exata. Golden hashes em `GoldenHashesTest.php` re-emitidos para os 5 fixtures (saudavel, atencao, alerta, ncg_negativo, 70pct_indisponivel) — drift esperado pela substituição do placeholder pelo algoritmo real.

### CA-7 — Renderização no topo do relatório

**Status:** ✓ atendido (com **ressalva visual — ver pendência P-002**).

`show.blade.php` (linhas 75-77) renderiza `<x-relatorio.resumo-executivo>` antes da tabela de indicadores. Componente Blade (89 linhas) usa tokens do design system v1 (`var(--color-farol-*)`, `var(--color-primary)`, `var(--shadow-sm)`), tem `role="region"` + `aria-label="Resumo executivo"`, e `data-testid` para automação. Renderização condicional (`@if ($resumoEhValido)`) protege snapshots legados em motor 1.1.0 (placeholder não tem `veredito` → bloco não renderiza, conforme IDR-010 §Sub-decisão 4).

### CA-8 — Testes (17 cenários + 3 feature)

**Status:** ✓ atendido (não-executado localmente — ver §"Limitação metodológica").

- `ResumoExecutivoTest.php` — **17 testes** (contagem via `grep -cE "^it\(" → 17`).
- `DiagnosticoShowTest.php` linhas 251, 270, 282 — render no topo, modo fallback, snapshot legado 1.1.0 não quebra.
- `GoldenHashesTest.php` — 5 fixtures + sanidade JSON + verificação de 5 hashes distintos (caminhos cobertos).

### CA-9 — Cobertura ≥ 98% no motor

**Status:** ✓ atendido por declaração do Programador (commit message: "Cobertura pacote Motor: 100%"). Não re-executei `pest --coverage` — ver pendência P-002.

## Pendências (não-bloqueantes)

### P-001 — Severidade do amarelo: extensão local não-literal da spec

**Tipo:** decisão local documentada vs spec.

A spec §4.7.1 dá a fórmula literal de severidade **apenas para vermelhos**: `|valor − fronteira_amarela| / amplitude_faixa_vermelha`. Para amarelos a spec diz "mesma regra de severidade", sem fórmula. O Programador implementou em `severidade()` (linhas 353-379) uma generalização razoável (distância da fronteira do vermelho dentro da faixa amarela, normalizada pela amplitude amarela) e a documentou no docblock + nas notas da estória.

Há também uma **convenção sobre amplitude**: a spec usa "amplitude_faixa_vermelha", mas a faixa vermelha é tecnicamente ilimitada para indicadores `maior_melhor` (`(-∞, Y]`) ou `menor_melhor` (`(X, +∞)`). A implementação aproxima por `$fronteira` (linha 364) — escolha razoável que preserva ordenação relativa, mas que **não é o que a spec literalmente diz**.

**Recomendação:** abrir IDR-011 (ou anotar dentro da IDR-010 como adendum) formalizando essas duas decisões locais como interpretação oficial da §4.7.1. Sem isso, qualquer futura auditoria externa (STORY-036) pode questionar. **Custo:** ~30min do Arquiteto.

**Não-bloqueia:** o comportamento é determinístico, testado, e gera ordenação semanticamente correta (pior primeiro). A spec é ambígua nesse ponto, não a implementação está errada.

### P-002 — Verificações visuais e de cobertura não feitas nesta validação

**Tipo:** limitação de método.

Esta validação rodou no sandbox sem PHP/Docker. Faltou:

- Execução de `pest -c phpunit-domain.xml --coverage` para confirmar 100% no pacote Motor.
- Captura visual do componente `<x-relatorio.resumo-executivo>` em homol (browser screencast) — conforme F-NB-1 mencionado nas notas da estória, registrado como aprovado em-sessão pelo PO mas sem evidência anexada.

**Recomendação:** durante a STORY-037 (validação final do EPIC-002), incluir no checklist:

- `pest --coverage` rodado em CI, output anexado em `validation/evidence/coverage-motor-story-037.txt`.
- Screencast de 30 segundos demonstrando os 4 vereditos no relatório em homol (`saudavel`, `precisa_atencao`, `em_alerta`, `fallback`), anexado em `validation/evidence/resumo-executivo-render.mov`.

**Não-bloqueia:** o commit message declara cobertura 100% e o PO confirmou smoke em-sessão. Pendência é sobre **evidência arquivada**, não sobre o estado real do produto.

## Anexo §5 — Simulação Python (verificação independente do algoritmo)

Para reduzir dependência da implementação PHP do Programador, reescrevi os Passos 1, 5 e 6 do algoritmo em Python (~30 linhas) e exercitei contra 10 cenários canônicos:

| Cenário | V | A | Vd | I | Esperado | Calculado | OK? |
|---|---|---|---|---|---|---|---|
| Todos verdes (NCG abs fora) — Passo 6 | 0 | 0 | 13 | 0 | saudavel + extra | saudavel + extra | ✓ |
| 5 vermelhos, 8 verdes — V/N=0.385 ≥ 0.30 | 5 | 0 | 8 | 0 | em_alerta | em_alerta | ✓ |
| 1 vermelho, 12 verdes — V/N=0.077, V≥1 | 1 | 0 | 12 | 0 | precisa_atencao | precisa_atencao | ✓ |
| 7 amarelos, 6 verdes — A/N=0.538 ≥ 0.50 | 0 | 7 | 6 | 0 | precisa_atencao | precisa_atencao | ✓ |
| 5 amarelos, 8 verdes — A/N=0.385 < 0.50 | 0 | 5 | 8 | 0 | saudavel | saudavel | ✓ |
| 10 indisponíveis — 10/14=0.714 ≥ 0.70 | 0 | 0 | 3 | 10 | fallback | fallback | ✓ |
| 9 indisponíveis borderline — 9/14=0.643 < 0.70 | 1 | 1 | 2 | 9 | precisa_atencao | precisa_atencao | ✓ |
| 13 vermelhos — Passo 6 oposto | 13 | 0 | 0 | 0 | em_alerta | em_alerta | ✓ |
| Edge V=4, A=4, Vd=5 — V/N=4/13=0.308 ≥ 0.30 | 4 | 4 | 5 | 0 | em_alerta | em_alerta | ✓ |
| Edge V=3, A=5, Vd=5 — V/N=0.231 < 0.30, V≥1 | 3 | 5 | 5 | 0 | precisa_atencao | precisa_atencao | ✓ |

**10 de 10 cenários OK** — a lógica de veredito do `ResumoExecutivo.php` está consistente com a leitura literal da spec §4.7.1.

## Recomendação ao PO

**Aprovar formalmente** a STORY-031 mantendo o status atual (`done`). As 2 pendências entram em backlog dedicado:

1. **P-001:** abrir IDR-011 (ou adendum à IDR-010) durante a STORY-032 ou imediatamente após, formalizando as decisões locais da severidade. Owner sugerido: Arquiteto. Janela: até Checkpoint 2 (2026-06-05).
2. **P-002:** incluir os 2 itens de evidência (coverage + screencast) no `validation/checklist.md` da STORY-037. Owner: PO. Janela: até Semana 4 (Checkpoint 4).

Nenhuma das pendências impacta o caminho crítico da V3 (matriz DEZ/2025 + tooltips), portanto a STORY-032 pode ser entregue ao Programador sem espera.

— Validador (claude-opus-4-7), 2026-05-25
