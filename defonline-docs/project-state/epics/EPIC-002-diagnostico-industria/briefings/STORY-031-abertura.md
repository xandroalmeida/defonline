---
artifact: story-briefing
target_role: programador
story_id: STORY-031
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
written_by: po (alexandro)
written_at: 2026-05-25
status: ready-for-pickup
revisions:
  - at: 2026-05-25
    by: po (alexandro)
    note: |
      Revisão crítica após o Programador anterior identificar divergências do briefing vs spec §4.7.1.
      Mudanças:
      1. REMOVIDA "regra patrimonial inviável" (Margem Líquida < 0 E Fontes de Recursos > 1) — não consta na spec. Era invenção minha.
      2. Vereditos passam a usar texto literal da spec ("Saudável" / "Precisa de atenção" / "Em alerta") nos campos visíveis ao usuário; códigos JSON estáveis ficam separados.
      3. Linha 5 fixa corrigida para texto literal: "Veja a tabela abaixo para análise detalhada e recomendações específicas." (antes estava errada).
      4. Fórmula de severidade explicitada conforme spec: |valor − fronteira_amarela| / amplitude_faixa_vermelha.
      5. Prefixo "Por outro lado, " adicionado ao destaque positivo (literal da spec).
      6. Limite do bloco: 4 a 5 linhas, ~400 chars no total (não só ~80 chars/destaque).
      7. STORY-030 fechou nesse meio-tempo — seção de bump simplificada: 1.1.0 → 1.2.0 direto.
      Princípio reiterado: spec §4.7.1 é a única fonte autoritativa. Se este briefing divergir, spec vence.
---

# Briefing de abertura — STORY-031 (Programador) — revisado 2026-05-25

> **STORY-030 fechou.** Motor está em `1.1.0` com 14 indicadores. Seu bump é `1.1.0 → 1.2.0` direto.
> **A spec §4.7.1 é a única fonte autoritativa do algoritmo.** Se este briefing divergir da spec, **siga a spec**.

## Estado em que a estória chega

V1 (STORY-028) está `done` com 7 indicadores + NCG abs + `resumo_executivo` **placeholder** `{"pendente_story": "STORY-031", "fallback_acionado": false}`. Esta estória **substitui o placeholder** pelo conteúdo real do algoritmo §4.7.1.

Você consome:

- **`App\Domain\Motor\Motor`** (orquestrador) — você adiciona uma chamada a `ResumoExecutivo::gerar()` no final, antes de empacotar o snapshot.
- **`indicadores_calculados`** já existe no snapshot — seu input. Cada entrada tem `{valor, farol, motivo, mensagem}`.
- **5 fixtures canônicos** em `tests/Domain/Motor/Fixtures/` — você re-emite golden hashes (incluem o resumo_executivo agora).
- **STORY-029 (relatório)** já existe — você adiciona um bloco no topo da view `diagnosticos/show.blade.php` para renderizar o resumo.
- **Spec §4.7.1** — fonte autoritativa do algoritmo. **Cole o algoritmo da spec na seção §6 abaixo.** Não invente.

## Estado do motor agora (STORY-030 fechou)

Motor está em `motor_version = "1.1.0"`, com **13 indicadores com farol** (Anexo D) + **2 informativos** (`farol = 'nenhum'`): **NCG absoluto** e **Ciclo Operacional**. Seu algoritmo conta sobre os 13 com farol; os 2 informativos ficam **sempre fora** da contagem do veredito e da seleção de destaques.

Total de indicadores no array `indicadores_calculados` do snapshot: **15** (13 + 2 informativos).

## Bump do `motor_version`

1. **No início da sessão:** `php artisan tinker --execute='echo config("motor.version").PHP_EOL;'`. Deve imprimir `"1.1.0"`. Anote.
2. **Implemente sua estória** sem mexer em `motor.version`.
3. **Ao abrir o PR:** bumpe `config/motor.php` para `"1.2.0"`.
4. **Re-emita todos os 5 golden hashes** em `GoldenHashesTest.php` — eles **vão mudar** porque `resumo_executivo` agora terá conteúdo real (veredito + destaques) em vez do placeholder atual. Não é regressão; é o motivo do bump.
5. **PR description obrigatória:**
   ```
   Bump motor_version: 1.1.0 → 1.2.0
   Motivo: STORY-031 substitui resumo_executivo placeholder pelo algoritmo §4.7.1 da spec.
   Golden hashes re-emitidos: resumo_executivo agora contém veredito + destaques reais.
   ```

**Pontos críticos:**

- Diagnósticos antigos persistidos em `motor_version="1.0.0"` ou `"1.1.0"` **continuam intactos** no banco (snapshot — IDR-010). Não recalcule.
- **`resumo_executivo` é coluna `NOT NULL`** — seu output **precisa** ser um JSON válido (não `null`). Mesmo o fallback fixo precisa virar um objeto JSON.

## Algoritmo §4.7.1 — fiel à spec (autoritativo)

> **Leia a spec direto** em `defonline-docs/especificacao/V2/especificacao-funcional.md` §4.7.1. O resumo abaixo é guia. **Se algo divergir, spec vence.**

**Entrada:** lista dos 14 indicadores do Anexo D (no produto atual: 13 com farol + NCG absoluto informativo + Ciclo Operacional informativo = **15 entradas no snapshot, mas 14 para o algoritmo** — Ciclo Operacional não conta nem como "14" porque é complementar fora do Anexo D).

**Observação chave da spec:** o **indicador #9 (NCG absoluto)** é informativo (sem farol). Para este algoritmo é tratado como `farol = "nenhum"` e fica **sempre fora** da contagem do veredito e da seleção de destaques. A interpretação semântica do capital de giro no Resumo Executivo se dá via o irmão **#10 NCG/Vendas**, que tem farol completo. **Ciclo Operacional** (adicionado pela STORY-030, fora do Anexo D, também `farol = "nenhum"`) também não entra na contagem — mesma regra.

**Passo 1 — Classificação por contagem proporcional.**

Sejam, **considerando apenas indicadores com farol `verde`/`amarelo`/`vermelho`** (NCG abs e Ciclo Operacional excluídos por terem `farol = "nenhum"`):

- `V` = quantidade de vermelhos
- `A` = quantidade de amarelos
- `Vd` = quantidade de verdes
- `I` = quantidade de indisponíveis (indicadores do Anexo D com `valor === null`)
- `N = V + A + Vd` = total de **válidos** (não-indisponíveis e com farol)

**Veredito (tabela literal da spec):**

| Condição | Veredito (código JSON) | Veredito (texto humano — linha 1) |
|---|---|---|
| `I / 14 ≥ 0,70` | `"fallback"` | (mensagem fixa do Passo 5) |
| `N > 0` e `V / N ≥ 0,30` | `"em_alerta"` | *"Sua empresa apresenta indicadores em estado de alerta que demandam ação."* |
| `N > 0` e (`V ≥ 1` OU `A / N ≥ 0,50`) | `"precisa_atencao"` | *"Sua empresa apresenta pontos de atenção que merecem acompanhamento."* |
| `N > 0` e `V = 0` e `A / N < 0,50` | `"saudavel"` | *"Sua empresa apresenta indicadores saudáveis no período avaliado."* |

> **NÃO existe regra patrimonial específica.** A redação anterior deste briefing tinha "Margem Líquida < 0 E Fontes de Recursos > 1 → alerta" — **isso não está na spec e foi removido em 2026-05-25**. Use só as 4 condições acima.

**Passo 2 — Seleção de destaques (até 3 itens no total).**

- **Até 2 destaques negativos**, na ordem:
  1. Indicadores em `vermelho`, ordenados por **severidade decrescente**. Fórmula explícita da spec:
     ```
     severidade = |valor − fronteira_amarela| / amplitude_faixa_vermelha
     ```
     Em empate de severidade → ordem do Anexo D ASC (#1, #2, ...).
  2. Se sobrar slot (menos de 2 vermelhos), complementar com `amarelo`, **mesma regra de severidade**.
- **Até 1 destaque positivo:** o indicador em `verde` com **maior distância (no sentido bom)** à fronteira amarela. Omitir se não houver verde. Em empate → ordem do Anexo D ASC.
- Indicadores com `farol = "nenhum"` (Indisponível, NCG abs, Ciclo Operacional) **são ignorados** e nunca aparecem como destaque.

**Passo 3 — Texto de cada destaque.**

- Mensagem curta do indicador (já existe no snapshot — `indicadores_calculados[codigo].mensagem`), **truncada em ~80 chars** mantendo a primeira frase semântica + reticências `"…"` (caractere único, não 3 pontos) se cortar.
- Prefixar com **nome do indicador + dois-pontos** (ex.: `"Margem Líquida: ..."`). Use `IndicadorFormatter::NOMES`.
- Para o **destaque positivo**, prefixar com **`"Por outro lado, "`** (literal da spec — sinaliza contraste de tom).

**Passo 4 — Composição do bloco.**

```
Linha 1: [veredito_texto_humano]
Linha 2: [Destaque negativo 1, se existir]
Linha 3: [Destaque negativo 2, se existir]
Linha 4: [Destaque positivo, se existir — com prefixo "Por outro lado, "]
Linha 5: Veja a tabela abaixo para análise detalhada e recomendações específicas.
```

**Limites da spec:**
- Bloco tem **4 a 5 linhas**, **até ~400 caracteres no total** (não só ~80/destaque).
- Linha 5 é **literal e fixa**: *"Veja a tabela abaixo para análise detalhada e recomendações específicas."*

**Passo 5 — Fallback (`I / 14 ≥ 0,70`).**

Se 70% ou mais dos 14 indicadores do Anexo D estão indisponíveis, **não** gera resumo padrão. Exibe mensagem fixa única:

*"Não foi possível calcular indicadores suficientes para um resumo executivo. Revise os dados informados ou consulte a tabela abaixo."*

E `fallback_acionado: true` no JSON.

**Passo 6 — Casos limite da spec:**

- **Todos os 14 em verde** (`V=0, A=0, Vd=14, I=0`): veredito `"saudavel"` + linha 2 única *"Todos os indicadores avaliados estão em patamar saudável. Continue acompanhando."* + linha 5 fixa. (Sem destaque negativo, sem positivo separado.)
- **Todos os 14 em vermelho** (`V=14`): veredito `"em_alerta"` + 2 destaques mais severos (sem destaque positivo) + linha 5 fixa.

## Estrutura do JSON `resumo_executivo` (contrato)

Caso normal (não-fallback):

```json
{
  "motor_version_origem": "1.2.0",
  "veredito": "saudavel | precisa_atencao | em_alerta",
  "veredito_texto": "Sua empresa apresenta pontos de atenção que merecem acompanhamento.",
  "destaques_negativos": [
    {"codigo": "margem_liquida", "texto": "Margem Líquida: margem líquida abaixo do recomendado…"},
    {"codigo": "divida_liquida_ebitda", "texto": "Dívida Líquida / EBITDA: alavancagem elevada para o setor…"}
  ],
  "destaque_positivo": {"codigo": "margem_bruta", "texto": "Por outro lado, Margem Bruta: margem bruta acima do referencial setorial…"},
  "linha_fixa": "Veja a tabela abaixo para análise detalhada e recomendações específicas.",
  "fallback_acionado": false,
  "mensagem_fallback": null
}
```

Caso fallback (`I / 14 ≥ 0,70`):

```json
{
  "motor_version_origem": "1.2.0",
  "veredito": "fallback",
  "veredito_texto": null,
  "destaques_negativos": [],
  "destaque_positivo": null,
  "linha_fixa": null,
  "fallback_acionado": true,
  "mensagem_fallback": "Não foi possível calcular indicadores suficientes para um resumo executivo. Revise os dados informados ou consulte a tabela abaixo."
}
```

**Notas sobre o contrato:**

- `veredito` (código JSON estável): `"saudavel"` | `"precisa_atencao"` | `"em_alerta"` | `"fallback"`. Use estes códigos para programar — texto humano sai em `veredito_texto`.
- `veredito_texto` é o texto **literal da spec** (linha 1 do bloco) — view renderiza esse campo direto.
- `texto` em destaques **já inclui** o prefixo do nome do indicador + dois-pontos (e o "Por outro lado, " no positivo). View renderiza cru, sem interpolar de novo.
- `linha_fixa`: sempre `"Veja a tabela abaixo para análise detalhada e recomendações específicas."` no caso normal; `null` no fallback.
- Caso especial **"todos os 14 em verde"** (Passo 6): `destaques_negativos: []`, `destaque_positivo: null`, e um campo opcional `mensagem_extra` carrega *"Todos os indicadores avaliados estão em patamar saudável. Continue acompanhando."* — view exibe entre veredito_texto e linha_fixa. Se preferir, modele como um único `destaque_positivo` sintético; decisão do programador.

## Ordem sugerida de execução

Estimado M. Esta ordem minimiza retrabalho:

1. **Confirme `motor_version` no início** (~5 min)
   - `php artisan tinker --execute='echo config("motor.version").PHP_EOL;'` → esperado `1.1.0`.

2. **Skeleton da classe** (~20 min)
   - `app/Domain/Motor/ResumoExecutivo.php` (`final class`, instanciável via container; método `gerar(array $indicadoresCalculados): array`).
   - **Não precisa de parâmetro `totalIndicadoresMotor`** — o denominador do fallback é a constante `14` (indicadores do Anexo D), conforme spec. NCG abs e Ciclo Operacional **não entram no denominador 14** (são informativos fora do Anexo D ou marcados como `farol='nenhum'`). Para reconhecer "é um dos 14": filtrar por `farol IN (verde, amarelo, vermelho, nenhum-por-indisponibilidade)`. Programador escolhe a estratégia (lista whitelist dos 14 códigos vs. exclusão por código informativo).
   - Considere `ResumoExecutivoResultado` value object se preferir tipagem (opcional; array associativo serve).

3. **TDD do algoritmo** (~2h — núcleo)
   - Pelo menos 10 cenários canônicos (CA-2 e CA-8). Lista atualizada (cenário "regra patrimonial" **removido** — não existe):
     1. **Tudo verde** (N=13, V=0, A=0, Vd=13, I=0) → veredito `"saudavel"` + caso especial Passo 6 ("Todos os indicadores... continue acompanhando.") + linha 5 fixa.
     2. **1 vermelho, 0 amarelos** (V=1, A=0, Vd=12, N=13) → `"precisa_atencao"` (regra `V ≥ 1`) + 1 destaque negativo + 1 positivo.
     3. **5 amarelos sem vermelho** (V=0, A=5, Vd=8, N=13) → 5/13 ≈ 38% < 50% → `"saudavel"` + 1 destaque positivo.
     4. **7 amarelos sem vermelho** (V=0, A=7, N=13) → 7/13 ≈ 54% ≥ 50% → `"precisa_atencao"` + 2 destaques negativos (amarelos por severidade) + 1 positivo.
     5. **5 vermelhos** (V=5, A=0, Vd=8, N=13) → 5/13 ≈ 38% ≥ 30% → `"em_alerta"` + 2 destaques negativos vermelhos + 1 positivo verde.
     6. **Tudo vermelho** (V=13, N=13) → `"em_alerta"` + 2 destaques negativos (sem positivo — Passo 6).
     7. **Fallback** (I=11, 11/14 ≈ 79% ≥ 70%) → `fallback_acionado=true` + mensagem fixa.
     8. **Empate de severidade negativa** → desempate pelo Anexo D ASC (Margem Bruta #1 antes de Margem EBITDA #2).
     9. **Empate de favorabilidade positiva** → desempate pelo Anexo D ASC.
     10. **Truncamento da mensagem > 80 chars** com `"…"` preservando palavra completa; bloco total ≤ ~400 chars.
     11. (bônus) **NCG abs e Ciclo Operacional ignorados**: cenário com `farol='nenhum'` em ambos confirma que não entram em V/A/Vd/N nem em destaques.

4. **Determinismo (golden test específico)** (~30 min)
   - Pest: para 1 fixture canônico, gere `ResumoExecutivo` 100 vezes em um loop e asserte que todas as saídas são bit-exato iguais. Sem `now()`, sem `array_rand`, sem `usort` instável.
   - Ordenação determinística: sempre primário (severidade DESC ou favorabilidade) + secundário (número do Anexo D ASC). Use `usort` com comparador que devolve 0 só em empate verdadeiro de identidade.

5. **Integração no orquestrador `Motor::calcular()`** (~30 min)
   - Ao final do loop de indicadores, antes de empacotar o snapshot:
     ```php
     $resumo = app(ResumoExecutivo::class)->gerar($indicadoresArray);
     ```
   - Substitui o placeholder atual `['pendente_story' => 'STORY-031', 'fallback_acionado' => false]` pelo retorno real.

6. **Render do Resumo Executivo na view** (~30 min)
   - Adicione bloco no topo de `resources/views/diagnosticos/show.blade.php` (entre o cabeçalho/breadcrumb e a tabela de indicadores).
   - Componente novo: `<x-relatorio.resumo-executivo :resumo="$diagnostico->resumo_executivo" />`.
   - Visual: cabeçalho colorido por veredito (verde=`saudavel`, amarelo=`precisa_atencao`, vermelho=`em_alerta`, cinza=`fallback`). Render: `veredito_texto` (linha 1) + lista de `destaques_negativos[*].texto` + `destaque_positivo.texto` (já vem com prefixo "Por outro lado, ") + `linha_fixa`. No caso de fallback, apenas `mensagem_fallback` em caixa cinza.
   - Acessibilidade: `<aside role="region" aria-label="Resumo executivo">`.

7. **Re-emita os 5 golden hashes** (~30 min)
   - Os 5 fixtures em `GoldenHashesTest.php` vão produzir hashes novos.
   - Use o tinker do `idempotencia.md` §3 para gerar; copie para o teste; rode `pest --filter Golden` para confirmar verde.

8. **Bump `motor_version`** (~5 min)
   - `config/motor.php`: `'version' => '1.1.0'` → `'1.2.0'`.
   - Atualize a linha de histórico no comentário do config (1.0.0 / 1.1.0 / agora 1.2.0).
   - Atualizar testes que assertam versão (use `config('motor.version')` em vez de hardcoded).

9. **Cobertura ≥ 98% no pacote Motor** mantida.

## Pegadinhas

- **`I / 14` vs `I / N`:** denominador do fallback é **constante 14** (total do Anexo D), **não** N (válidos). Snapshot atual tem 15 entradas (13 com farol + NCG abs + Ciclo Operacional). NCG abs **conta como um dos 14** (faz parte do Anexo D, slot #9, embora informativo). **Ciclo Operacional NÃO conta como um dos 14** (não está no Anexo D, foi adicionado pela 030 como complementar). Para um indicador específico marcar "indisponível" → `valor === null` E ele estar entre os 14 do Anexo D.
- **NCG absoluto excluído da contagem do veredito** (`V/A/Vd/N`) porque tem `farol = 'nenhum'`. **Mas conta no denominador 14 do fallback.** É a única exceção contraintuitiva.
- **Ciclo Operacional excluído de tudo** (contagem do veredito **e** denominador 14) — está fora do Anexo D.
- **Truncamento em ~80 chars** preservando palavra: use `mb_substr` + busca regressiva por espaço/pontuação. Adicione `"…"` (caractere único reticências) se cortou.
- **Limite global do bloco ≤ ~400 chars** — se a soma de `veredito_texto` + destaques + `linha_fixa` passar disso, encurte mensagens dos destaques agressivamente (a spec é explícita sobre esse teto).
- **Cuidado com `array_filter`/`array_map`** que reordenam chaves — use `array_values()` após para reindex se for iterar com ordem garantida.
- **`usort` instável:** sempre adicione critério secundário (número do Anexo D ASC) como fallback do comparador. Garante determinismo do golden hash.
- **Severidade tem fórmula explícita:** `|valor − fronteira_amarela| / amplitude_faixa_vermelha`. A "fronteira amarela" e a "amplitude da faixa vermelha" são lidas de `config/motor/faroes-industria.php`. Para indicadores `maior_melhor` e `menor_melhor` os pontos de fronteira são distintos — implemente helper que pega a fronteira correta conforme `tipo` da config.

## Quando escalar para o PO

- **Se este briefing divergir da spec §4.7.1, PARE e siga a spec.** É a regra única. Se algo soar "inventado" e não estiver na §4.7.1, é invenção minha — me avise.
- Se a fórmula de severidade não fechar para algum indicador `menor_melhor` (por exemplo Dívida Líq/EBITDA tem amplitude vermelha "ilimitada" — `> 3`) — **PARE**, eu defino o teto convencional.
- Se o truncamento ficar feio em mobile com fonte grande — **PARE**, eu defino limite.
- Se descobrir que algum indicador novo da 030 tem comportamento que escapa do que está catalogado — **PARE**.

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
- [ ] Li este briefing (revisão 2026-05-25 — atenção ao front-matter `revisions`).
- [ ] **Li a §4.7.1 da spec direto** — fonte autoritativa única. Se algo divergir do briefing, sigo a spec.
- [ ] Confirmei `config('motor.version') === "1.1.0"` antes de começar.
- [ ] Atualizei front-matter da STORY-031 (`status: in_progress`, `owner_agent`, `updated_at`).
- [ ] Atualizei `index.json` correspondente.
- [ ] Comecei pelo passo 2 (skeleton da classe).

— PO (Alexandro)
