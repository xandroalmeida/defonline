---
story_id: STORY-031
slug: resumo-executivo-deterministico
title: Resumo Executivo — algoritmo determinístico §4.7.1
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
type: implementation
target_role: programador
status: draft
owner_agent: claude-programador
created_at: 2026-05-25
updated_at: 2026-05-25
estimated_session_size: M
---

# STORY-031 — Resumo Executivo determinístico

> Implementa o algoritmo §4.7.1 da spec — sem IA, sem GPT, sem aleatoriedade. Determinístico: mesmo input → mesma saída.

## Contexto

A spec §4.7.1 define o Resumo Executivo como bloco no topo do relatório, com **veredito sintético** ("saudável" / "atenção" / "alerta") e até **2 destaques negativos + 1 positivo**, mensagens truncadas em ~80 caracteres, fallback fixo quando ≥ 70% dos indicadores estão indisponíveis. NCG absoluto (sem farol) é **ignorado** na contagem.

## O quê

1. **Classe `app/Domain/Motor/ResumoExecutivo.php`** com método `gerar(array $indicadoresCalculados): ResumoExecutivoResultado`.
2. **Algoritmo:**
   - Contagem proporcional sobre os 13 indicadores com farol (NCG abs excluído).
   - Veredito por limiares:
     - ≥ 30% vermelhos OU situação patrimonial inviável → `"alerta"`.
     - ≥ 1 vermelho OU ≥ 50% amarelos → `"atenção"`.
     - Senão → `"saudável"`.
   - Destaques: até 2 negativos (maior severidade, ordenação determinística por gravidade), até 1 positivo (maior distância favorável para verde).
   - Mensagens truncadas em ~80 caracteres, mantendo final em ponto/vírgula.
   - Fallback: ≥ 70% indisponíveis → mensagem fixa "Quiz incompleto. Preencha os campos faltantes para gerar um resumo."
3. **Persistência:** `resumo_executivo` (texto + estrutura JSON com veredito + 3 destaques) gravado em `diagnosticos.resumo_executivo`. Snapshot — não recalcula on-the-fly.
4. **Render:** Resumo aparece no topo do relatório (acima da tabela de indicadores) na view de STORY-029.
5. **Determinismo:** golden test — fixture canônica produz texto idêntico em 100 execuções.

## Critérios de aceite

- [ ] **CA-1:** Classe `ResumoExecutivo` segue exatamente o algoritmo §4.7.1.
- [ ] **CA-2:** Veredito correto em pelo menos 6 cenários canônicos: tudo verde / 1 vermelho / 5 amarelos / 30% vermelhos / patrimônio inviável / quase tudo indisponível.
- [ ] **CA-3:** Destaques ordenados deterministicamente (não depender de ordem de iteração de hash).
- [ ] **CA-4:** Truncamento em ~80 chars com final limpo (não corta no meio de palavra).
- [ ] **CA-5:** NCG abs **não entra** na contagem (regra explícita da spec).
- [ ] **CA-6:** Snapshot persistido em `resumo_executivo`. Mesmo input + mesma `motor_version` = mesmo texto.
- [ ] **CA-7:** Renderizado no topo do relatório com estilo destacado.
- [ ] **CA-8 (testes):** Pest unit ≥ 10 casos. Golden test específico para determinismo.
- [ ] **CA-9 (cobertura):** ≥ 98% no pacote motor mantida.

## Fora de escopo

- Resumo gerado por IA — roadmap §2.3 (v2.0).
- Resumo multi-idioma.
- Resumo personalizado por persona (Marcos, Joana).

## Dependências

- **Bloqueada por:** STORY-030 (precisa dos 14 indicadores prontos).
- **Bloqueia:** STORY-032 (matriz DEZ/2025 lê resumo + indicadores).

## Decisões já tomadas

- Determinístico, sem IA, no MVP.
- NCG abs excluído da contagem (spec).
- Veredito em 3 níveis fixos: "saudável" / "atenção" / "alerta".

## DoD

CA-1 a CA-9 + tag `rc.W25S2.2`. `index.json` atualizado.

## Protocolo do agente

Padrão.

## Notas do agente

*(A preencher.)*
