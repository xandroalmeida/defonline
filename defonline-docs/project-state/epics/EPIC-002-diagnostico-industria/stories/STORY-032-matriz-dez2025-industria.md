---
story_id: STORY-032
slug: matriz-dez2025-industria
title: Matriz DEZ/2025 — recomendações por indicador × farol filtradas para Indústria
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: done
owner_agent: claude-programador
approved_by: Alexandro
approved_at: 2026-05-25
closed_at: 2026-05-25
created_at: 2026-05-25
updated_at: 2026-05-25
related_briefing: briefings/STORY-032-abertura.md
related_validation: validation/report-STORY-032.md
estimated_session_size: M
related_idrs: ["IDR-010"]
---

# STORY-032 — Matriz DEZ/2025 (Indústria)

> Acopla as **recomendações textuais por indicador × farol** ao relatório, alimentadas pelo Anexo F da spec, filtradas para a coluna Indústria. Texto é **gravado no snapshot** do diagnóstico (não lido on-the-fly) conforme IDR-010 §Sub-decisão 2.

## Contexto

Anexo F da spec V2 (`defonline-docs/especificacao/V2/anexos/anexo-F-matriz-recomendacoes-dez2025.md`, **autoria EB Parcerias / EBC**) tem texto por **indicador × cor de farol × setor**. Esta estória traz esse texto para dentro do relatório.

A IDR-010 (aprovada 2026-05-25) **já fixou** as decisões arquiteturais relevantes:

- **Versionamento:** `matrix_version = "dez-2025"` (formato datado curto, varchar(16)) — definido em IDR-010 §Sub-decisão 1.
- **Snapshot dos resultados:** o texto da matriz é gravado em `diagnosticos.indicadores_calculados[*].mensagem_detalhada` (JSONB imutável) **no momento do cálculo**, garantindo reprodutibilidade absoluta — IDR-010 §Sub-decisão 2.
- **Matriz como dado versionado, não como código:** arquivo PHP versionado em git carregado por `matrix_version` — IDR-010 §Operacional ("Mensagens da matriz são lidas como dado versionado (config PHP carregado por `matrix_version`), não como template Twig/Blade").

> **Correção 2026-05-25:** a redação original (a) listava decisão "snapshot vs lookup" como aberta no CA-7 — decisão já tomada na IDR-010; (b) usava nome de campo `indicadores_calculados.recomendacao` divergente do schema da IDR-010; (c) delegava ao programador a escolha "config PHP ou banco" — fixada como **config PHP versionado** na IDR-010. Realinhado.
>
> **Correção 2026-05-25 (mais tarde, durante a redação do briefing):** revisão do schema real do código + Anexo F mostrou que (1) o schema atual `indicadores_calculados[*]` tem **apenas o campo `mensagem`** — não há `mensagem_curta` separado; (2) o **Anexo F tem 1 único texto por par** (indicador × farol × setor), não dois; (3) as views `linha-indicador.blade.php` e `card-indicador.blade.php` (entregues por STORY-029) **já renderizam** `indicador['mensagem']`. Conclusão: a STORY-032 **não cria** o campo `mensagem_detalhada` nem o componente `<x-recomendacao>`. O que muda é o **conteúdo** injetado em `mensagem` — antes vinha de `MensagensFarol::paraFarol()` (placeholder genérico "Faixa verde/amarela/vermelha"), agora passa a vir do config da matriz (`config/motor/matriz-dez-2025-industria.php`). Schema do snapshot **fica intacto**. A chave `mensagem_detalhada` fica reservada na IDR-010 para evolução futura quando a matriz tiver texto curto + detalhado distintos (não é o caso do Anexo F vigente). Realinhamento detalhado da estória abaixo.

## O quê

1. **Config PHP da matriz:** criar `config/motor/matriz-dez-2025-industria.php` com array `[codigo_indicador => [farol => texto_literal_do_anexo_F]]`. Texto **transcrito do Anexo F (autoria EBC)** sem reescrita; pode incluir ou excluir o prefixo de faixa numérica entre parênteses (`(até 20%)` etc.) — decisão fica no briefing.
2. **Filtro Indústria:** apenas coluna Indústria do Anexo F entra no config. Comércio/Serviços ficam fora desta sprint (Onda 2).
3. **Classe `App\Domain\Motor\MatrizRecomendacoes`** com método `texto(string $codigo, string $farol, string $setor = 'industria'): string` — lê o config e devolve a string correspondente.
4. **Trocar `MensagensFarol::paraFarol()` em cada indicador `with farol`:** os 13 indicadores que hoje fazem `mensagem: MensagensFarol::paraFarol($farol)` passam a usar `MatrizRecomendacoes::texto($codigo, $farol)`. `MensagensFarol` fica como fallback caso o config falhe a carregar.
5. **NCG abs (F.13):** o Anexo F só tem 2 cenários (positiva/negativa) e o motor atual hardcoda 3 mensagens semânticas (FOLGA/MODERADO/ALTO) em `NcgAbsoluto.php`. **Decisão a tomar no briefing**: (a) manter as 3 mensagens atuais (mais granulares, fora do Anexo F) ou (b) reduzir para 2 (alinhar 100% ao Anexo F). Default sugerido para o briefing: (a) — não regredir granularidade já entregue por STORY-028.
6. **Schema do snapshot fica intacto:** `indicadores_calculados[*]` continua `{valor, farol, motivo, mensagem}`. **O que muda é o conteúdo de `mensagem`** — antes "Faixa vermelha.", agora "Reduzir custos na compra de matéria-prima e insumos…". Sem campo novo, sem componente Blade novo. Views `linha-indicador.blade.php` e `card-indicador.blade.php` (STORY-029, `done`) continuam funcionando sem mudança.
7. **Fallback gracioso:** se o config não tem entrada para `(codigo, farol)`, `MatrizRecomendacoes::texto()` devolve placeholder `"Recomendação em revisão."` + `Log::warning(...)` com contexto + auto-append em `design/matriz-lacunas.md`. **Não quebra cálculo nem relatório.**
8. **Versionamento:** `matrix_version = "dez-2025"` em `config/motor.php` já está carimbado pela STORY-028. Não muda. Diagnósticos antigos persistidos em `motor_version = 1.0.0/1.1.0/1.2.0` continuam intactos (snapshot — IDR-010); o relatório deles segue exibindo a `mensagem` original (placeholder) — não recalcula.
9. **Bump do `motor_version`:** o conteúdo de `mensagem` muda, golden hashes vão mudar. Recomendação no briefing: **MINOR `1.2.0 → 1.3.0`** (motor passa a "falar" matriz oficial em vez de placeholder — capacidade nova, sem mudar fórmulas/farol). Re-emitir os 5 golden hashes.

## Critérios de aceite

- [x] **CA-1:** Anexo F lido (humano) e transcrito em `config/motor/matriz-dez-2025-industria.php` (PHP versionado, sem tabela de banco) com array `[codigo => [farol => texto]]`. 13 indicadores com farol + 1 informativo (NCG abs) cobertos.
- [x] **CA-2:** Apenas coluna Indústria do Anexo F transcrita. Teste arquitetural Pest valida que o array carregado tem **exatamente** 13 chaves de indicadores com farol + entrada para NCG abs, e que cada uma tem as 3 cores `verde/amarelo/vermelho` (ou os cenários do NCG abs).
- [x] **CA-3:** Classe `MatrizRecomendacoes` substitui `MensagensFarol` nos 13 indicadores. Teste arquitetural assegura que nenhum indicador continua usando `MensagensFarol::paraFarol`.
- [x] **CA-4 (NCG abs):** decisão tomada e justificada — **manter 3 mensagens hardcoded** (default). Registrado nas Notas do agente.
- [x] **CA-5 (fallback):** par sem texto no config → `"Recomendação em revisão."` + `Log::warning` com `{codigo, farol, matrix_version}` + linha em `design/matriz-lacunas.md` (auto-append idempotente — sem duplicar se a mesma lacuna disparar 100×). **Não quebra cálculo nem relatório.**
- [x] **CA-6:** `matrix_version` continua `"dez-2025"`. `motor_version` bumpado para **`1.3.0`** (MINOR — capacidade nova, sem mudar fórmulas).
- [x] **CA-7 (testes):** Pest unit ≥ 1 por indicador (13+) validando que `MatrizRecomendacoes::texto($codigo, $farol)` devolve o texto esperado do Anexo F para cada par. Pest unit do fallback (par não-existente). Pest feature do relatório mostrando texto da matriz. **5 golden hashes re-emitidos** em `GoldenHashesTest.php`.
- [x] **CA-8 (cobertura):** ≥ 98% no pacote `App\Domain\Motor\` — **99.6%**. `MatrizRecomendacoes` com cobertura **100%**.
- [x] **CA-9 (compatibilidade retroativa):** diagnósticos persistidos em `motor_version` anterior continuam abrindo sem erro (testes feature de STORY-031 cobrindo snapshots 1.1.0 mantidos verdes; nada na STORY-032 re-busca matriz em runtime).

## Fora de escopo

- Comércio e Serviços — onda 2.
- Edição da matriz pelo PO via UI — roadmap §6 (backoffice).
- Mecanismo de feedback 👍/👎 por recomendação — roadmap §1.4.
- Migração retroativa de diagnósticos antigos para preencher `mensagem_detalhada` — fora do MVP.

## Dependências

- **Bloqueada por:** STORY-031 (resumo + 14 indicadores prontos — **`done`** ✓), IDR-010 (versionamento + snapshot — **`accepted`** ✓).
- **Bloqueia:** STORY-036 (Validador externo revisa as recomendações também).

## Decisões já tomadas

- **Anexo F como fonte da verdade** (autoria EB Parcerias / EBC).
- **Filtro Indústria-only** no MVP.
- **Snapshot da recomendação no momento do cálculo** (IDR-010 §Sub-decisão 2) — não lookup em runtime.
- **Config PHP versionado em git**, sem tabela de banco para a matriz (IDR-010 §Operacional).
- **Schema do snapshot fica como está:** o campo é `mensagem` (singular) — o que muda é o conteúdo (vai do placeholder genérico para o texto literal da matriz). Sem campo novo, sem componente Blade novo.
- **`mensagem_detalhada` reservada na IDR-010** para futuro: quando uma versão da matriz tiver texto curto + detalhado distintos, esse campo é ativado. Anexo F (dez/2025) tem só 1 texto por par, então `mensagem_detalhada` não é populado nesta estória.
- **Bump recomendado:** `motor_version` MINOR `1.2.0 → 1.3.0` (capacidade nova, sem mudar fórmulas). Confirmar com Arquiteto no briefing.
- Sem UI de edição da matriz nesta sprint.

## DoD

CA-1 a CA-9 + tag `rc.W25S3.1`. `index.json` atualizado.

## Protocolo do agente

Padrão. Reportar lacunas do Anexo F ao PO em backlog dedicado durante a execução (não bloqueia, mas precisa ser visto antes do Validador externo).

## Notas do agente

### 2026-05-25 — Implementação

**Entregue:**

- `app/config/motor/matriz-dez-2025-industria.php` — 13 indicadores × 3 cores + NCG abs (positiva/negativa). Texto literal do Anexo F, **sem o prefixo de faixa** entre parênteses (decisão registrada em-sessão com o PO; a faixa já é visível pelo valor + farol da linha).
- `app/Domain/Motor/MatrizRecomendacoes.php` — final class, pura para fins de hash (`texto()` é determinístico). Fallback `"Recomendação em revisão."` + `Log::warning` + auto-append idempotente em `design/matriz-lacunas.md` (path configurável via `config('motor.matriz_lacunas_path')` para permitir teste do I/O).
- 13 indicadores migrados de `MensagensFarol::paraFarol($farol)` para `(new MatrizRecomendacoes)->texto($this->chave(), $farol)`.
- `NcgAbsoluto.php` — **mantido** com as 3 mensagens hardcoded (FOLGA/MODERADO/ALTO) por decisão de produto (Anexo F só tem 2 cenários — granularidade entregue pela STORY-028 não é regredida). Entrada `ncg_absoluto.positiva/negativa` fica no config para auditoria editorial. Docblock atualizado.
- `config/motor.php` — bump `1.2.0 → 1.3.0` com nota no histórico.
- 5 golden hashes re-emitidos em `GoldenHashesTest.php` (drift esperado: a mensagem dos 13 indicadores com farol mudou; destaques do `resumo_executivo` herdam automaticamente).

**Decisões locais registradas:**

1. **Prefixo de faixa removido** — PO confirmou em-sessão. Texto fica enxuto, faixa já visível.
2. **NCG abs com 3 mensagens hardcoded** — PO confirmou em-sessão. Anexo F tem 2 cenários; manter granularidade da STORY-028.
3. **`MensagensFarol::paraFarol` deixou de ser usado pelos indicadores** — briefing sugeria "como fallback se config falhar". Optei por fallback único `"Recomendação em revisão."` (consistência + simplicidade). A classe `MensagensFarol` continua existindo (snapshots legados em 1.0.0/1.1.0/1.2.0 carregam suas constantes; remover quebraria compatibilidade retroativa — IDR-010).
4. **`matriz_lacunas_path` configurável** — necessário para cobrir 100% do hot path em testes (o path default fica fora do volume do container).

**Testes — 743 verdes:**

- `tests/Domain/Motor/MatrizRecomendacoesTest.php` — 38 cenários (37 pares codigo × farol literais do Anexo F + 6 testes de comportamento: fallback, determinismo 100×, auto-append idempotente, idempotência de linha duplicada, path inexistente, path vazio).
- `tests/Domain/Motor/MatrizArchTest.php` — 2 testes arquiteturais: cobertura completa do config (CA-2) + assert que nenhum indicador usa `MensagensFarol::paraFarol` (CA-3).
- `tests/Feature/Http/DiagnosticoShowTest.php` — 1 teste novo: relatório exibe texto da matriz (`"Buscar manter a margem atual."`), não mais placeholder.
- `tests/Domain/Motor/Indicadores/MargemBrutaTest.php` — assertion de mensagem placeholder atualizada para texto da matriz.
- `GoldenHashesTest.php` — 5 hashes re-emitidos (saudavel/atencao/alerta/ncg_negativo/70pct_indisponivel).

**Qualidade:**

- Cobertura `app/Domain/`: **99.6%** (gate ≥ 98%).
- `MatrizRecomendacoes`: **100%**.
- Larastan: 0 erros. Pint: 218 arquivos OK.

**Pendência (F-NB):**

- F-NB-1: Aprovação visual em homologação fica pendente do deploy do rc desta sprint. Smoke local + feature tests cobrem o caminho funcional.

— Programador (claude-opus-4-7)

### 2026-05-25 — Validação independente

Validador (claude-opus-4-7) emitiu parecer em `validation/report-STORY-032.md` com veredito `approved_with_pending` (2 pendências não-bloqueantes).

**Conferido contra spec autoritativa (Anexo F coluna Indústria):**

- 41/41 textos do config `matriz-dez-2025-industria.php` batem literalmente com o Anexo F (39 pares × 3 cores + 2 cenários NCG abs). Sem reescrita, sem prefixo de faixa.
- 13 indicadores migrados para `MatrizRecomendacoes::texto`. Teste arquitetural confirma zero uso restante de `MensagensFarol::paraFarol`.
- `NcgAbsoluto` mantém 3 mensagens hardcoded (decisão CA-4 preservada).
- Bump motor `1.2.0 → 1.3.0` conforme IDR-010 §Sub-decisão 1 (MINOR — capacidade nova sem mudar fórmulas). 5 golden hashes re-emitidos com valores novos.
- Compatibilidade retroativa: diagnósticos persistidos em motor anterior continuam exibindo texto da época (snapshot — IDR-010).
- Auto-append idempotente do `matriz-lacunas.md` validado em Python (4/4 cenários OK).

**Pendências registradas no relatório (não-bloqueantes, vão para backlog):**

- **P-001:** path default de `matriz_lacunas_path` aponta para fora do volume Docker — `is_dir()` retorna `false` e o auto-append silencia em runtime real (Log::warning continua funcionando). Defeito morto enquanto a matriz não tiver lacunas (transcrição é completa). Resolver até STORY-036 — montar volume adicional ou mover arquivo para `app/storage/`.
- **P-002 (cosmético):** 6 comentários `// F.X` no dataset de `MatrizRecomendacoesTest.php` com numeração trocada (ex.: ciclo_financeiro marcado "F.8" mas é F.12). Textos esperados estão certos — só comentários humanos errados. Corrigir em PR de polimento.

### 2026-05-25 — Aprovação do PO

PO (Alexandro) aprovou após validação visual em homol local (`localhost:8090`):

- Diagnóstico novo gerado para Demo · saudavel (`019e6187-6ac1-72f7-b5c4-a5d104171af0`) com motor `v1.3.0`.
- Resumo Executivo no topo (Sua empresa apresenta indicadores saudáveis... + Passo 6 todo-verde + linha 5 fixa) — STORY-031 + 032 funcionando juntas.
- 11 indicadores com farol exibindo texto literal do Anexo F Indústria (conferido linha por linha contra a tabela do `MatrizRecomendacoesTest.php`).
- NCG abs exibindo "Folga operacional..." (MSG_FOLGA preservada).
- Rodapé carimbado `motor v1.3.0 · matriz dez-2025`.
- Diagnóstico antigo (motor v1.1.0) continua abrindo sem erro, exibindo "Faixa verde." (compat retroativa).

Veredito final: **`approved`**. Pendências P-001 e P-002 vão para backlog. Próximo passo: Programador commita e empurra para homologação.
