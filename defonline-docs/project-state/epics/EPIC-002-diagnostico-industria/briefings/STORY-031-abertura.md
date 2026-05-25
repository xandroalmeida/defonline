---
artifact: story-briefing
target_role: programador
story_id: STORY-031
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
written_by: po (alexandro)
written_at: 2026-05-25
status: ready-for-pickup
parallel_with: STORY-030
---

# Briefing de abertura — STORY-031 (Programador)

> Esta estória **roda em paralelo com a STORY-030** (Motor V2 — completar 14 indicadores). Ambas vão bumpar `motor_version`. **Leia a §4 "Coordenação do bump em paralelo" antes de tudo.**

## Estado em que a estória chega

V1 (STORY-028) está `done` com 7 indicadores + NCG abs + `resumo_executivo` **placeholder** `{"pendente_story": "STORY-031", "fallback_acionado": false}`. Esta estória **substitui o placeholder** pelo conteúdo real do algoritmo §4.7.1.

Você consome:

- **`App\Domain\Motor\Motor`** (orquestrador) — você adiciona uma chamada a `ResumoExecutivo::gerar()` no final, antes de empacotar o snapshot.
- **`indicadores_calculados`** já existe no snapshot — seu input. Cada entrada tem `{valor, farol, motivo, mensagem}`.
- **5 fixtures canônicos** em `tests/Domain/Motor/Fixtures/` — você re-emite golden hashes (incluem o resumo_executivo agora).
- **STORY-029 (relatório)** já existe — você adiciona um bloco no topo da view `diagnosticos/show.blade.php` para renderizar o resumo.
- **Spec §4.7.1** — fonte autoritativa do algoritmo. **Cole o algoritmo da spec na seção §6 abaixo.** Não invente.

## Importante: você trabalha com 7 indicadores hoje (V1), 14 amanhã

A STORY-030 (paralela) vai adicionar 7 indicadores. Sua estória precisa funcionar **em ambos os mundos**:

- **Cenário A (você mergeia primeiro):** motor tem 7 indicadores + NCG abs. Seu algoritmo conta sobre os 7 com farol (NCG abs excluído, regra da spec). Veredito calculado sobre 7. Quando STORY-030 mergear depois, a contagem vira sobre 13 com farol — **o veredito pode mudar para o mesmo input**, o que é esperado (output cresce com o motor). Golden hashes serão re-emitidos pela 030 nessa hora.

- **Cenário B (você mergeia depois da 030):** motor já tem 13 indicadores com farol + NCG abs. Sua contagem é direta sobre 13. Sem retrabalho.

Em ambos os casos, **o algoritmo §4.7.1 não muda** — ele opera sobre "indicadores com farol disponíveis". Quantos são é definido pelo motor no momento da execução.

## Coordenação do bump em paralelo (LEIA ANTES DE TUDO)

A STORY-030 também vai bumpar `motor_version`. Regra:

1. **No início da sessão:** `php artisan tinker --execute='echo config("motor.version").PHP_EOL;'`. Hoje é `"1.0.0"`. Anote.
2. **Implemente sua estória** sem mexer em `motor.version`.
3. **No momento de abrir o PR:** verifique `motor.version` no `main` atual (`git fetch && git show origin/main:config/motor.php`):
   - Se ainda `"1.0.0"` → bumpe para `"1.1.0"`.
   - Se já estiver `"1.1.0"` (030 mergeou primeiro) → bumpe para `"1.2.0"`.
4. **Re-emita todos os golden hashes** em `GoldenHashesTest.php` (5 fixtures × hash novo, agora com resumo_executivo real em vez de placeholder).
5. **PR description obrigatória:**
   ```
   Bump motor_version: X.Y.0 → X.(Y+1).0
   Motivo: STORY-031 substitui resumo_executivo placeholder pelo algoritmo §4.7.1.
   Golden hashes re-emitidos: resumo_executivo agora contém veredito + destaques reais.
   ```
6. **Se conflito de merge** (030 mergeou primeiro): rebase, atualize `motor.php` para `1.2.0`, re-emita TODOS os hashes do zero (porque agora o motor produz seu resumo + os 7 indicadores novos da 030).

**Pontos críticos:**

- Hashes V1 (STORY-028) **vão mudar** quando você substituir o placeholder por veredito real. Isso é o motivo do bump. Não é regressão.
- Diagnósticos antigos persistidos em `motor_version="1.0.0"` continuam com placeholder no banco — não recalcule.
- **`resumo_executivo` é coluna `NOT NULL`** — seu output **precisa** ser um JSON válido (não `null`). Mesmo o fallback fixo precisa virar um objeto JSON.

## Algoritmo §4.7.1 — colado da spec (autoritativo)

Recorte dos §§ relevantes da `especificacao-funcional.md`:

**Entrada:** lista dos 14 indicadores calculados pelo motor (Anexo D), cada um com `valor`, `farol` (`verde`, `amarelo`, `vermelho`, `nenhum` quando indisponível **ou** informativo) e `mensagem`.

**Observação chave:** o **indicador #9 (NCG absoluto)** é classificado como informativo (sem farol). Para este algoritmo, ele é tratado como `farol = "nenhum"` e fica **sempre fora** da contagem do veredito e da seleção de destaques. A interpretação semântica do capital de giro no Resumo Executivo se dá via o indicador irmão **#10 NCG/Vendas**, que tem farol completo.

**Passo 1 — Classificação por contagem proporcional (apenas entre indicadores válidos).**

Sejam:
- `V` = quantidade de vermelhos
- `A` = quantidade de amarelos
- `Vd` = quantidade de verdes
- `I` = quantidade de indisponíveis (`valor === null`)
- `N = V + A + Vd` = total de indicadores **válidos** (não-indisponíveis e não-informativos)

(NCG absoluto **não entra** em nenhuma das somas. PME pode entrar dependendo de setor — para Indústria, entra.)

**Veredito:**

- Se `V ≥ 0,30 × N` (≥ 30% vermelhos do total válido) **OU** condição patrimonial inviável (Margem Líquida < 0 **e** Fontes de Recursos > 1) → `"alerta"`.
- Se `V ≥ 1` **OU** `A ≥ 0,50 × N` (≥ 50% amarelos) → `"atenção"`.
- Senão → `"saudável"`.

**Passo 2 — Destaques.**

- **Negativos (até 2):** os indicadores em `vermelho` mais severos. Severidade = distância da faixa verde (na escala do indicador). Em empate, ordenação ascendente pelo número do Anexo D (#1, #2, ...). Se sobrar slot (menos de 2 vermelhos), complementa com amarelos mais severos (mesma regra).
- **Positivo (até 1):** o indicador em `verde` mais favorável. "Mais favorável" = maior distância da faixa amarela na direção desejada do indicador (maior=melhor ou menor=melhor, conforme Anexo D). Em empate, ordenação ascendente pelo número do Anexo D.
- Cada destaque carrega a `mensagem` curta do indicador (já existe no snapshot), **truncada em ~80 chars** com sufixo `"..."` se cortar, preservando o final em palavra completa.

**Linha 5 fixa do bloco (texto literal da spec, sempre presente):**

*"Veja a tabela abaixo para o detalhamento dos demais indicadores."*

**Fallback fixo:**

Se `I / 14 ≥ 0,70` (70% ou mais indisponíveis sobre o total de 14 indicadores — inclui NCG abs no denominador para essa contagem específica) → **não** gera resumo padrão. Exibe **mensagem fixa**:

*"Não foi possível calcular indicadores suficientes para um resumo executivo. Revise os dados informados ou consulte a tabela abaixo."*

E `fallback_acionado: true` no JSON.

**Casos limite documentados na spec:**

- Todos os 14 em verde: veredito `"saudável"`, linha única "Todos os indicadores avaliados estão em patamar saudável. Continue acompanhando." + linha 5 fixa.
- Todos em vermelho: veredito `"alerta"`, 2 destaques mais severos (sem destaque positivo) + linha 5 fixa.

## Estrutura do JSON `resumo_executivo` (contrato)

```json
{
  "motor_version_origem": "1.1.0",
  "veredito": "saudavel | atencao | alerta",
  "destaques_negativos": [
    {"codigo": "margem_liquida", "mensagem": "Margem líquida abaixo do recomendado..."}
  ],
  "destaque_positivo": {"codigo": "margem_bruta", "mensagem": "Margem bruta excelente..."} | null,
  "linha_fixa": "Veja a tabela abaixo para o detalhamento dos demais indicadores.",
  "fallback_acionado": false,
  "mensagem_fallback": null
}
```

Quando `fallback_acionado: true`:

```json
{
  "motor_version_origem": "1.1.0",
  "veredito": null,
  "destaques_negativos": [],
  "destaque_positivo": null,
  "linha_fixa": null,
  "fallback_acionado": true,
  "mensagem_fallback": "Não foi possível calcular indicadores suficientes para um resumo executivo. Revise os dados informados ou consulte a tabela abaixo."
}
```

**Sem `null` nas chaves que podem virar string** — sempre presentes (use string vazia se a spec não definir; mas o contrato acima já cobre).

## Ordem sugerida de execução

Estimado M. Esta ordem minimiza retrabalho:

1. **Confirme `motor_version` no início** (~5 min)
   - `php artisan tinker --execute='echo config("motor.version").PHP_EOL;'`.

2. **Skeleton da classe** (~20 min)
   - `app/Domain/Motor/ResumoExecutivo.php` (`final class`, método estático ou injetável — preferência: classe instanciável via container; método `gerar(array $indicadoresCalculados, int $totalIndicadoresMotor): array`).
   - Por que `int $totalIndicadoresMotor` no parâmetro: o fallback usa `I / total`, então o total precisa ser dinâmico (8 em V1, 15 em V2 — depende do que está no snapshot do momento).
   - Considere também `ResumoExecutivoResultado` value object se preferir tipagem (opcional; array associativo na estrutura acima já serve).

3. **TDD do algoritmo** (~2h — núcleo)
   - Pelo menos 10 cenários canônicos (CA-2 e CA-8):
     1. Tudo verde (N=13, V=0, A=0, Vd=13, I=0) → veredito `"saudavel"` + 1 destaque positivo + linha fixa.
     2. 1 vermelho (V=1) → `"atencao"` + 1 destaque negativo + complemento amarelo se houver + 1 positivo.
     3. 5 amarelos sem vermelho (V=0, A=5, N=13) → 5/13 = 38% amarelos < 50% → `"saudavel"`.
     4. 7 amarelos sem vermelho (A=7, N=13) → 7/13 = 53% ≥ 50% → `"atencao"`.
     5. 5 vermelhos (V=5, N=13) → 5/13 = 38% ≥ 30% → `"alerta"` + 2 destaques negativos.
     6. Margem Líquida vermelho + Fontes de Recursos > 1 (independente do resto) → `"alerta"` (regra patrimonial).
     7. 11 indisponíveis (I=11, 11/14 ≥ 70%) → fallback acionado.
     8. Todos os 13 em vermelho → `"alerta"` + 2 destaques + 0 positivos.
     9. Empate de severidade no destaque negativo → ordenação pelo número do Anexo D (Margem Bruta #1 antes de Margem EBITDA #2).
     10. Truncamento de mensagem > 80 chars em ponto/vírgula sem cortar palavra.

4. **Determinismo (golden test específico)** (~30 min)
   - Pest: para 1 fixture canônico, gere `ResumoExecutivo` 100 vezes em um loop e asserte que todas as saídas são bit-exato iguais. Sem `now()`, sem `array_rand`, sem `usort` instável.
   - Ordenação determinística: sempre primário (severidade DESC ou favorabilidade) + secundário (número do Anexo D ASC). Use `usort` com comparador que devolve 0 só em empate verdadeiro de identidade.

5. **Integração no orquestrador `Motor::calcular()`** (~30 min)
   - Ao final do loop de indicadores, antes de empacotar o snapshot:
     ```php
     $resumo = app(ResumoExecutivo::class)->gerar(
         indicadoresCalculados: $indicadoresArray,
         totalIndicadoresMotor: count($indicadoresArray),
     );
     ```
   - Substitui o placeholder atual `['pendente_story' => 'STORY-031', 'fallback_acionado' => false]` pelo retorno real.

6. **Render do Resumo Executivo na view** (~30 min)
   - Adicione bloco no topo de `resources/views/diagnosticos/show.blade.php` (entre o cabeçalho e a tabela de indicadores).
   - Componente novo: `<x-relatorio.resumo-executivo :resumo="$diagnostico->resumo_executivo" />`.
   - Visual: cabeçalho colorido por veredito (verde=saudavel, amarelo=atencao, vermelho=alerta), 1 linha do veredito + lista de destaques + linha fixa. Quando `fallback_acionado: true`, só a mensagem fixa em caixa cinza.
   - Acessibilidade: `<aside role="region" aria-label="Resumo executivo">`.

7. **Re-emita golden hashes** (~30 min)
   - Os 5 fixtures em `GoldenHashesTest.php` vão produzir hashes novos (porque `resumo_executivo` mudou de placeholder para real).
   - Use o tinker do `idempotencia.md` §3 para gerar; copie para o teste; rode `pest --filter Golden` para confirmar verde.

8. **Bump `motor_version`** (~5 min)
   - `config/motor.php`: `'version' => '1.0.0'` → `'1.1.0'` (ou `'1.2.0'` se 030 mergeou primeiro).
   - Atualizar testes que assertam versão.

9. **Cobertura ≥ 98% no pacote Motor** mantida.

## Pegadinhas

- **`I / 14` vs `I / N`:** o denominador do fallback é 14 (constante do produto — total de indicadores da spec), não N (que é o total atual do motor). Cuidado: V1 hoje tem 8 indicadores no array (7 com farol + NCG abs). Se 6 forem indisponíveis (`valor === null`), `6/14 = 43% < 70%`, não aciona fallback. Mas `6/8 = 75%` aciona. **Use 14 sempre**, conforme spec.
- **Hoje (V1) só tem 7 indicadores com farol** — sua contagem proporcional opera sobre 7. Quando 030 mergear, opera sobre 13. Algoritmo é o mesmo; só o número muda.
- **NCG absoluto excluído da contagem** (spec é explícita). Filtre por `farol !== 'nenhum'` antes de classificar. **Mas o NCG abs entra no denominador `14` do fallback** (porque é um dos 14).
- **"Condição patrimonial inviável"** = Margem Líquida vermelho **E** Fontes de Recursos > 1. Não é só uma OU outra — é AND. Veredito direto `"alerta"`.
- **Truncamento em ~80 chars** preservando palavra: use `str_word_count`/`mb_substr` ou função custom que corta em espaço/pontuação. Adicione `"…"` (caractere reticências, não 3 pontos) se cortou.
- **Cuidado com `array_filter`/`array_map`** que reordenam chaves — use `array_values()` após para reindex se for iterar com ordem garantida.
- **`usort` instável:** sempre adicione critério secundário (número do Anexo D ASC) como fallback do comparador.

## Quando escalar para o PO

- Se a interpretação de "condição patrimonial inviável" não fechar para algum caso real — **PARE**.
- Se descobrir que a STORY-030 (paralela) está produzindo um indicador com `farol = 'nenhum'` que **não é** NCG abs (ex.: Ciclo Operacional informativo) — **PARE**. Sua filtragem precisa saber que esses não entram na contagem.
- Se o truncamento ficar feio em mobile com fonte grande — **PARE**, eu defino limite.

## Quando avisar o PO em meio à execução

- Ao terminar o passo 3 (10 cenários canônicos verdes) — *"algoritmo nasceu"*.
- Ao terminar o passo 6 (resumo renderizando no topo do relatório) — *"pronto para PR"*. Quero ver visual antes de aprovar.

## Referências obrigatórias

- `defonline-docs/especificacao/V2/especificacao-funcional.md` §4.7.1 (autoritativo)
- `defonline-docs/project-state/decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md` (aditivo de 2026-05-25 sobre placeholder)
- `defonline-docs/project-state/epics/EPIC-002-diagnostico-industria/design/idempotencia.md`
- `app/app/Domain/Motor/` (estrutura V1)
- `app/app/Models/Diagnostico.php` (coluna resumo_executivo NOT NULL)
- `app/app/Domain/Motor/Motor.php` (orquestrador a integrar)
- `defonline-docs/skills/po/references/agent-task-format.md`

## Checklist de "puxei a estória, posso começar?"

- [ ] Li a STORY-031 inteira.
- [ ] Li este briefing.
- [ ] Li a §4.7.1 da spec (autoritativa) — não me baseio só no resumo aqui.
- [ ] Li a §4 "Coordenação do bump em paralelo".
- [ ] Confirmei `config('motor.version')` atual.
- [ ] Anotei se STORY-030 já está em `in_progress` (impacta o número do bump).
- [ ] Atualizei front-matter da STORY-031 (`status: in_progress`, `owner_agent`, `updated_at`).
- [ ] Atualizei `index.json` correspondente.
- [ ] Comecei pelo passo 2 (skeleton da classe + value object).

— PO (Alexandro)
