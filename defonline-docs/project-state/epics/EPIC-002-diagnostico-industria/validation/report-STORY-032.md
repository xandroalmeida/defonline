---
artifact: story-validation-report
story_id: STORY-032
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
validated_by: claude-validador
validated_at: 2026-05-25
verdict: approved
verdict_initial: approved_with_pending
verdict_promoted_after_visual: 2026-05-25
approved_by: Alexandro
pending_count: 2
blockers: 0
related_idr: IDR-010
related_briefing: briefings/STORY-032-abertura.md
---

# Relatório de validação independente — STORY-032

> Validação pré-commit, conforme pedido do Programador (status `in_progress` aguardando aprovação do PO). Implementação ainda **uncommitted** localmente no momento desta validação. **Aprovação formal do PO em 2026-05-25** após validação visual em homol local (motor 1.3.0 vivo, diagnóstico `019e6187-...`).

## Veredito

**`approved`** — implementação atende os 9 CAs da estória. Veredito inicial era `approved_with_pending` antes da validação visual; após confirmação visual em `localhost:8090` (Resumo Executivo + 13 indicadores com texto literal do Anexo F + NCG abs preservando MSG_FOLGA + rodapé `motor v1.3.0`), promovido a `approved`. As 2 pendências (P-001 e P-002) continuam registradas como follow-up não-bloqueante.

## Escopo da validação

- Briefing PO `briefings/STORY-032-abertura.md` (versão enxuta de 25/mai).
- Estória `stories/STORY-032-matriz-dez2025-industria.md` (CA-1 a CA-9, Notas do agente).
- Anexo F `defonline-docs/especificacao/V2/anexos/anexo-F-matriz-recomendacoes-dez2025.md` (autoria EBC — fonte autoritativa).
- Código novo:
    - `app/config/motor/matriz-dez-2025-industria.php` (123 linhas).
    - `app/app/Domain/Motor/MatrizRecomendacoes.php` (110 linhas).
- Código modificado:
    - 13 indicadores em `app/app/Domain/Motor/Indicadores/*.php` (migração `MensagensFarol::paraFarol` → `MatrizRecomendacoes::texto`).
    - `app/app/Domain/Motor/Indicadores/NcgAbsoluto.php` (apenas docblock atualizado; mantém 3 mensagens hardcoded).
    - `app/config/motor.php` (bump `1.2.0 → 1.3.0` + `matriz_lacunas_path` configurável).
    - `app/tests/Domain/Motor/GoldenHashesTest.php` (5 hashes re-emitidos).
    - `app/tests/Feature/Http/DiagnosticoShowTest.php` (1 novo teste de render).
    - `app/tests/Domain/Motor/Indicadores/MargemBrutaTest.php` (assertion atualizada).
- Testes novos:
    - `app/tests/Domain/Motor/MatrizRecomendacoesTest.php` (158 linhas, 7 testes, ≥ 44 cenários via dataset).
    - `app/tests/Domain/Motor/MatrizArchTest.php` (58 linhas, 2 testes arquiteturais).
- Simulação independente em Python do auto-append idempotente contra 4 cenários (anexo §6).

## Limitação metodológica

Sandbox sem PHP/Docker — não rodei `pest` localmente. A confirmação de "743 testes verdes / cobertura 99.6% em `App\Domain\` / 100% em `MatrizRecomendacoes`" vem do reporte do Programador. Está consistente com a estrutura dos testes inspecionada.

## CA por CA — evidências

### CA-1 — Anexo F transcrito em `config/motor/matriz-dez-2025-industria.php`

**Status:** ✓ atendido.

Conferi 41 textos linha-por-linha contra Anexo F coluna Indústria:

- 13 indicadores com farol × 3 cores = 39 textos.
- 1 indicador informativo (NCG absoluto) × 2 cenários = 2 textos.
- **Total: 41/41 batem** com o Anexo F (após remoção do prefixo de faixa, conforme decisão registrada).

**Decisão de design no config:** o Programador adicionou comentários de bloco mapeando cada chave ao Anexo F (`// F.1 Margem Bruta`, etc.), facilitando auditoria futura. Boa prática.

### CA-2 — Apenas coluna Indústria + cobertura completa

**Status:** ✓ atendido.

Teste arquitetural `MatrizArchTest.php` (linha 17-44) varre `ResumoExecutivo::CODIGOS_ANEXO_D` (whitelist de 14 códigos), confirma:

- 13 indicadores com farol têm entrada no config com chaves `verde/amarelo/vermelho`.
- Cada par tem texto não-vazio (não placeholder).
- NCG absoluto tem chaves `positiva/negativa`.

Comércio/Serviços fora do escopo do MVP — confirmado por inspeção do config (só coluna Indústria do Anexo F entrou).

### CA-3 — `MatrizRecomendacoes` substitui `MensagensFarol` nos 13 indicadores

**Status:** ✓ atendido.

Inspeção direta:

- 13 indicadores com farol importam e usam `MatrizRecomendacoes::texto($this->chave(), $farol)` — 2 hits por arquivo (import + chamada).
- Teste arquitetural `MatrizArchTest.php` (linha 46-57) grep todos os arquivos da pasta `Indicadores/` e asserta zero ocorrências de `MensagensFarol::paraFarol`.
- `MensagensFarol` permanece como classe no código (não é deletada) — defendido pelo Programador como salvaguarda para snapshots legados 1.0.0–1.2.0. **Decisão razoável** — esses snapshots carregam strings literais ("Faixa verde.") no banco; a classe não é mais consumida em runtime mas remover quebraria o histórico simbólico.

### CA-4 — NCG abs: decisão registrada

**Status:** ✓ atendido.

Decisão tomada: **manter 3 mensagens hardcoded** (`MSG_FOLGA` / `MSG_MODERADO` / `MSG_ALTO`) em `NcgAbsoluto.php`. Justificativa nas Notas do agente da estória: granularidade entregue pela STORY-028 não é regredida para acomodar Anexo F (que só tem 2 cenários).

A entrada `ncg_absoluto.{positiva, negativa}` permanece no config **só para auditoria editorial / roadmap futuro** — não é consumida pelo motor 1.3.0. Documentado no comentário do config (linhas 109-112) e no docblock do `NcgAbsoluto.php`. Coerente com o briefing-PO.

### CA-5 — Fallback gracioso

**Status:** ✓ atendido **com ressalva** — ver **pendência P-001**.

Implementação em `MatrizRecomendacoes`:

- **Lookup falhou** → retorna `"Recomendação em revisão."` (constante `FALLBACK`).
- **`Log::warning('matriz.recomendacao.lacuna', {codigo, farol, setor, matrix_version})`** disparado.
- **Auto-append idempotente** em `design/matriz-lacunas.md` — testado com 4 cenários em Python (anexo §6): mesma linha 100× = 1 ocorrência; 3 lacunas distintas = 3 linhas + cabeçalho; path vazio silencia; dir inexistente silencia.

**Cobertura por testes** — 3 cenários em `MatrizRecomendacoesTest.php`:
- `'fallback gracioso — par (codigo × farol) sem texto retorna placeholder e loga warning'`.
- `'fallback gracioso — farol válido mas codigo conhecido sem essa cor retorna placeholder'`.
- `'auto-append idempotente em matriz-lacunas.md — escreve uma linha por par lacunoso'`.

**Ressalva (P-001):** o path default do `matriz_lacunas_path` (`base_path('../defonline-docs/...')`) **não aterrissa em diretório existente** dentro do container Docker — o volume mount em `docker-compose.yml` é apenas `./app:/var/www/html`, então `/var/www/html/../defonline-docs/` resolve para `/var/defonline-docs/`, que não existe no container. Em runtime real (homol ou prod), `is_dir($diretorio)` retorna `false` e o `appendLacunaIdempotente` silencia. **O `Log::warning` continua disparando — quem checar logs vê a lacuna**, mas o arquivo `.md` nunca materializa em runtime. Defeito "morto" enquanto a matriz não tiver lacunas reais (o config foi transcrito completo do Anexo F, então não há lacunas hoje). Recomendação no §"Pendências".

### CA-6 — `matrix_version` inalterado + bump `motor_version`

**Status:** ✓ atendido.

- `motor.php`: `'matrix_version' => 'dez-2025'` (inalterado).
- `motor.php`: `'version' => '1.3.0'` (era 1.2.0).
- Linha de histórico adicionada explicando o bump como STORY-032.
- Bump é **MINOR** (capacidade nova — motor passa a "falar" matriz oficial em vez de placeholder), coerente com IDR-010 §Sub-decisão 1.

### CA-7 — Testes

**Status:** ✓ atendido **com ressalva cosmética** — ver **pendência P-002**.

**Cobertura por arquivo:**

- `MatrizRecomendacoesTest.php` — 7 testes Pest com dataset cobrindo **39 pares `(codigo, farol)`** + fallback (2 cenários) + determinismo 100× + auto-append idempotente + 2 defensivos de I/O.
- `MatrizArchTest.php` — 2 testes arquiteturais (cobertura do config + ausência de `MensagensFarol::paraFarol`).
- `GoldenHashesTest.php` — 5 fixtures re-emitidos. Hashes novos, diferentes dos da STORY-031.
- `DiagnosticoShowTest.php` — 1 teste novo (`'exibe texto da matriz dez-2025 (Anexo F Indústria) no lugar do placeholder'`) — assertSee `"Buscar manter a margem atual."`.
- `Indicadores/MargemBrutaTest.php` — assertion atualizada para texto da matriz.

**Pendência cosmética (P-002):** comentários `// F.X` no dataset de `MatrizRecomendacoesTest.php` (linhas 58, 63, 68, 73, 78, 83) **têm numeração trocada** com a do Anexo F real:

| Linha do teste | Comentário do teste | Indicador | Seção real do Anexo F |
|---|---|---|---|
| 58 | `// F.8 Ciclo Financeiro` | ciclo_financeiro | F.12 |
| 63 | `// F.10 NCG / Vendas` | ncg_vendas | F.14 |
| 68 | `// F.11 PMC` | pmc | F.8 |
| 73 | `// F.12 PME` | pme | F.9 |
| 78 | `// F.13 PMR` | pmr | F.10 |
| 83 | `// F.14 Inadimplência` | inadimplencia | F.11 |

**Não afeta funcionalmente nada** — os textos esperados estão corretos; só os comentários humanos estão errados. Correção de 30 segundos em PR de polimento.

### CA-8 — Cobertura

**Status:** ✓ atendido por declaração.

Notas do agente reportam:

- `app/Domain/`: **99.6%** (gate ≥ 98%).
- `MatrizRecomendacoes`: **100%**.

Não re-executei `pest --coverage` (ver "Limitação metodológica"). Estrutura dos testes está consistente com a meta — em particular, o teste `'auto-append silencia quando matriz_lacunas_path é string vazia'` cobre o early-return da linha 88 do `MatrizRecomendacoes`, que sem `matriz_lacunas_path` configurável não seria exercitável.

### CA-9 — Compatibilidade retroativa

**Status:** ✓ atendido.

- Snapshots persistidos em `motor_version <= 1.2.0` carregam `indicadores_calculados[*].mensagem = "Faixa verde."` (texto antigo) no banco. A view `linha-indicador.blade.php` / `card-indicador.blade.php` renderiza o que está no JSON — sem lógica condicional dependente do conteúdo da `mensagem`. Diagnóstico antigo **continua exibindo o texto original**, conforme IDR-010 §Sub-decisão 2 (snapshot imutável).
- Teste feature herdado da STORY-031 (`'snapshot legado em motor_version < 1.2.0 (placeholder) não quebra a view'`) continua verde — cobre o caso do `resumo_executivo` placeholder, que é o ponto mais sensível da regressão.

## Pendências (não-bloqueantes)

### P-001 — Auto-append do `matriz-lacunas.md` não funciona em runtime real

**Tipo:** gap funcional latente do CA-5.

O path default configurado em `config/motor.php` (`base_path('../defonline-docs/...')`) **não aterrissa em diretório existente** no container Docker — o `docker-compose.yml` monta apenas `./app:/var/www/html`. Em runtime, `is_dir($diretorio)` retorna `false` e o auto-append silencia. O `Log::warning` continua funcionando — quem checar os logs identifica a lacuna — mas o `.md` visível ao PO não materializa.

**Por que não bloqueia o approve:**

- A matriz vigente foi transcrita completa (41/41 textos do Anexo F) — não há lacunas reais em runtime.
- O Log::warning cobre o caso defensivamente.
- O comportamento foi documentado pelo Programador como "silencia para não quebrar" (linha 76 da classe).

**Recomendação:** quando STORY-036 (validação externa) ou uma evolução futura de matriz introduzir lacuna, ativar uma das duas saídas:

1. **Montar volume adicional no docker-compose.yml** — `./defonline-docs:/var/defonline-docs:rw` — e ajustar o path default. Custo: 5 minutos de infra.
2. **Mover o arquivo para dentro de `/app`** (ex.: `app/storage/matriz-lacunas.md`) e ajustar `base_path` no config. Custo: 5 minutos. Trade-off: tira a visibilidade direta para o PO no repo de docs.

Janela: até STORY-036 (Checkpoint 4 / 2026-06-19). Owner: Arquiteto ou Programador.

### P-002 — Comentários `// F.X` trocados no dataset do teste

**Tipo:** cosmético — não afeta nenhuma asserção.

6 comentários de bloco no dataset de `MatrizRecomendacoesTest.php` (linhas 58, 63, 68, 73, 78, 83) referem a seções erradas do Anexo F. Correção de PR de polimento.

**Recomendação:** o Programador corrige no commit de fechamento da estória (junto com o `done` no front-matter) ou em PR separado. Não impacta a aprovação.

## Recomendação ao PO

**Aprovar formalmente** a STORY-032 e marcá-la como `done`. As 2 pendências entram em backlog:

- **P-001** → fila de débito técnico do EPIC-002. Resolver até a STORY-036.
- **P-002** → cosmético, corrigir no commit de fechamento ou ignorar até polimento de final de épico.

**F-NB-1** (aprovação visual em homologação) segue como pendência genuína do PO — depende do deploy do rc desta sprint.

## Anexo §6 — Verificação independente do auto-append idempotente (Python)

Reescrevi `appendLacunaIdempotente()` em Python (~25 linhas) e rodei 4 cenários:

| Cenário | Esperado | Calculado | OK? |
|---|---|---|---|
| Mesma linha gravada 100× | 1 ocorrência no arquivo | 1 | ✓ |
| 3 lacunas distintas (codigo_a/b/c) | 3 linhas + cabeçalho | 3 linhas + cabeçalho | ✓ |
| `path = ''` | silencia (sem I/O) | silenciou | ✓ |
| Diretório inexistente | silencia (sem I/O) | silenciou | ✓ |

A lógica do `MatrizRecomendacoes` é consistente.

— Validador (claude-opus-4-7), 2026-05-25
