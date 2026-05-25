---
story_id: STORY-031
slug: resumo-executivo-deterministico
title: Resumo Executivo — algoritmo determinístico §4.7.1
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
related_briefing: briefings/STORY-031-abertura.md
estimated_session_size: M
---

# STORY-031 — Resumo Executivo determinístico

> Implementa o algoritmo §4.7.1 da spec — sem IA, sem GPT, sem aleatoriedade. Determinístico: mesmo input → mesma saída.

## Contexto

A spec §4.7.1 define o Resumo Executivo como bloco no topo do relatório, com **veredito sintético** ("saudável" / "atenção" / "alerta") e até **2 destaques negativos + 1 positivo**, mensagens truncadas em ~80 caracteres, fallback fixo quando ≥ 70% dos indicadores estão indisponíveis. NCG absoluto (sem farol) é **ignorado** na contagem.

## O quê

1. **Classe `app/Domain/Motor/ResumoExecutivo.php`** com método `gerar(array $indicadoresCalculados): ResumoExecutivoResultado`.
2. **Algoritmo (atualizado 2026-05-25 conforme spec §4.7.1):**
   - Contagem proporcional sobre os 13 indicadores com farol válido (NCG abs e Ciclo Operacional excluídos por `farol='nenhum'`).
   - Veredito por 4 condições literais da spec (códigos JSON estáveis):
     - `I / 14 ≥ 0,70` → `"fallback"` (mensagem fixa).
     - `N > 0 e V / N ≥ 0,30` → `"em_alerta"` (texto humano: *"Sua empresa apresenta indicadores em estado de alerta que demandam ação."*).
     - `N > 0 e (V ≥ 1 ou A / N ≥ 0,50)` → `"precisa_atencao"` (*"Sua empresa apresenta pontos de atenção que merecem acompanhamento."*).
     - Senão → `"saudavel"` (*"Sua empresa apresenta indicadores saudáveis no período avaliado."*).
   - **Sem regra patrimonial** — corrigido em 2026-05-25; redação anterior tinha invenção do briefing (PO retroativamente).
   - Destaques: até 2 negativos com severidade `|valor − fronteira_amarela| / amplitude_faixa_vermelha` (fórmula literal da spec); até 1 positivo com prefixo `"Por outro lado, "`. Desempate pelo Anexo D ASC.
   - Mensagens truncadas em ~80 chars com `"…"` preservando palavra; bloco global ≤ ~400 chars.
   - Fallback (≥70% indisponíveis sobre 14): *"Não foi possível calcular indicadores suficientes para um resumo executivo. Revise os dados informados ou consulte a tabela abaixo."*
3. **Persistência:** `resumo_executivo` (texto + estrutura JSON com veredito + 3 destaques) gravado em `diagnosticos.resumo_executivo`. Snapshot — não recalcula on-the-fly.
4. **Render:** Resumo aparece no topo do relatório (acima da tabela de indicadores) na view de STORY-029.
5. **Determinismo:** golden test — fixture canônica produz texto idêntico em 100 execuções.

## Critérios de aceite

- [x] **CA-1:** Classe `ResumoExecutivo` segue exatamente o algoritmo §4.7.1 (corrigido sem regra patrimonial).
- [x] **CA-2 (atualizado 2026-05-25):** Veredito correto em pelo menos 6 cenários canônicos: tudo verde / 1 vermelho / 5 amarelos / 30% vermelhos / 50% amarelos / quase tudo indisponível (fallback). **Cenário "patrimônio inviável" removido** — não consta na spec.
- [x] **CA-3:** Destaques ordenados deterministicamente — severidade DESC + Anexo D ASC como desempate.
- [x] **CA-4:** Truncamento em ~80 chars com `"…"` preservando palavra; bloco global ≤ ~400 chars (limite literal da spec).
- [x] **CA-5:** NCG abs e Ciclo Operacional **não entram** na contagem do veredito nem na seleção de destaques (`farol='nenhum'` excluído via whitelist `CODIGOS_ANEXO_D`).
- [x] **CA-6:** Snapshot persistido em `resumo_executivo`. Mesmo input + mesma `motor_version` = mesmo texto (validado por golden hashes + teste de determinismo 100×).
- [x] **CA-7:** Renderizado no topo do relatório (`<x-relatorio.resumo-executivo>`) com cor por veredito.
- [x] **CA-8 (testes):** 17 cenários canônicos em `ResumoExecutivoTest.php` + 3 testes feature em `DiagnosticoShowTest.php` (render no topo, modo fallback, snapshot legado 1.1.0 não quebra).
- [x] **CA-9 (cobertura):** **100%** no pacote `app/Domain/Motor/` (gate ≥98%).

## Fora de escopo

- Resumo gerado por IA — roadmap §2.3 (v2.0).
- Resumo multi-idioma.
- Resumo personalizado por persona (Marcos, Joana).

## Dependências

- **Bloqueada por:** STORY-028 (motor existente). STORY-030 fechou antes, então integração foi direta sobre os 14 indicadores.
- **Bloqueia:** STORY-032 (matriz DEZ/2025 troca o conteúdo das mensagens curtas).

## Decisões já tomadas

- Determinístico, sem IA, no MVP.
- NCG abs e Ciclo Operacional excluídos da contagem (spec + decisão da 030).
- Veredito em **4 códigos JSON** (`saudavel` / `precisa_atencao` / `em_alerta` / `fallback`) mapeados para os 3 textos humanos literais da spec.
- **Regra patrimonial inviável retirada** em 2026-05-25 — não consta na spec.

## DoD

CA-1 a CA-9 + tag `rc.W25S2.2`. `index.json` atualizado.

## Protocolo do agente

Padrão.

## Notas do agente

### 2026-05-25 — Implementação e fechamento

**Entregue:**
- `app/Domain/Motor/ResumoExecutivo.php` — classe final, determinística, pura (sem `now()`/Auth/DB).
- 14 indicadores do Anexo D na whitelist `CODIGOS_ANEXO_D`. NCG abs entra; Ciclo Operacional fora.
- NCG abs e Ciclo Op nunca aparecem como destaque (filtro por farol).
- Fallback `I/14 ≥ 0.70`, Passo 6 (todo-verde + todo-vermelho), severidade com fórmula literal da spec, desempate por Anexo D ASC, truncamento ~80 chars com `…` preservando palavra, `"Por outro lado, "` no positivo.
- Decisão local: a spec dá fórmula de severidade só para `vermelho`; quando precisa complementar destaque negativo com `amarelo` (V<2), generalizei para "mesma regra" (severidade normalizada pela amplitude da faixa amarela). Documentado no docblock da classe.

**Testes — 695 verdes:**
- `tests/Domain/Motor/ResumoExecutivoTest.php` — 17 cenários canônicos (CA-2..CA-5 + determinismo 100×).
- `tests/Feature/Http/DiagnosticoShowTest.php` — 3 testes novos (render no topo, modo fallback, snapshot legado 1.1.0 não quebra).
- 5 golden hashes re-emitidos (drift esperado pela substituição do placeholder).

**Integração:**
- `Motor::calcular()` agora chama `ResumoExecutivo::gerar(...)` e devolve o JSON real.
- `config/motor.php`: `1.1.0 → 1.2.0` com nota no histórico.
- `MotorTest`: removido o assert do placeholder, substituído por contrato do veredito real.

**View — `<x-relatorio.resumo-executivo>` no topo do relatório:**
- Bloco com `border-l-4`, cor por veredito (verde/amarelo/vermelho/cinza), reusa `<x-relatorio.farol>`, `aria-label="Resumo executivo"`.
- Render condicional: snapshots antigos em `1.1.0` com placeholder não renderizam (compatibilidade retroativa garantida — IDR-010 §sub-decisão 4).

**Qualidade:**
- Cobertura pacote Motor: **100%** (`pest -c phpunit-domain.xml --coverage`).
- Larastan: 0 erros. Pint: 213 arquivos OK.

**Pendência aprovada pelo PO em-sessão:**
- F-NB-1 (aprovação visual em browser): registrada como cheque do screencast do Checkpoint 2; PO aprovou formalmente com base no smoke + conformidade documental, sem ver o browser ainda nessa sessão.

— Programador (claude-opus-4-7)

### 2026-05-25 — Aprovação do PO

PO (Alexandro) aprovou em 2026-05-25 após smoke check:
- Conformidade com spec §4.7.1 confirmada (sem regra patrimonial, vereditos literais, linha 5 fixa, "Por outro lado", severidade pela fórmula, whitelist correta).
- Decisão de generalização da severidade para amarelos aceita como decisão local válida ("mesma regra" da spec).
- CAs retroativos atualizados nesta seção para refletir o briefing revisado.
