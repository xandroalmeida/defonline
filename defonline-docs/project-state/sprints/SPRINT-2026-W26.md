---
sprint_id: SPRINT-2026-W26
wave: WAVE-2026-01
status: planned
start_date: 2026-06-03
end_date: 2026-06-09
goal: "Absorver a devolutiva da EBC (Edvan) sobre o MVP do diagnóstico Indústria — bugfixes que travam uso real + ajustes de UX/conteúdo — sem mexer no algoritmo §4.7.1 do Resumo Executivo (aguarda lista dos 3 indicadores determinantes do Edvan + validação externa STORY-036)."
duration_weeks: 1
cadence_experiment: hotfix-pos-devolutiva-ebc
created_at: 2026-06-02
created_by: po (Alexandro)
related_devolutiva: "DEF on line - MVP 270526 - observações EBC.docx + áudio Edvan 2026-05-27 + 2 mensagens WhatsApp"
---

# SPRINT-2026-W26 — Ajustes pós-devolutiva EBC

## Objetivo do sprint

Entregar em homologação as correções da primeira rodada de devolutiva do analista financeiro externo (Edvan / EB Parcerias) sobre o MVP do diagnóstico Indústria entregue na SPRINT-2026-W25. Foco em **destravar uso real**: dois bugs funcionais (sócios fixos em 3 / Inadimplência rejeita 2 decimais), um bug visual no Resumo Executivo (card truncado e não clicável), ajuste de plural, pacote de mudanças de conteúdo do quiz e do glossário, e uma validação cruzada nova entre Q17 e Q09. **Não toca no §4.7.1 (Resumo Executivo) algoritmicamente** — essa discussão aguarda o Edvan voltar com a lista dos 3 indicadores "determinantes" e será arbitrada pela validação externa formal (STORY-036).

## Estórias incluídas

| ID | Título | Épico | Tipo | Tamanho | Status |
|---|---|---|---|---|---|
| STORY-038 | Sócios como lista dinâmica (1+) — add/edit/remove | EPIC-002 | implementation | M | ready |
| STORY-039 | Inadimplência aceita 2 casas decimais (0,00 ≤ x ≤ 100,00) | EPIC-002 | bugfix | XS | ready |
| STORY-040 | Card de destaque do Resumo Executivo: scroll-to + highlight | EPIC-002 | bugfix | S | ready |
| STORY-041 | Plural "ações" no veredito `em_alerta` | EPIC-002 | bugfix | XS | ready |
| STORY-042 | Pacote de conteúdo EBC (títulos, rótulos, glossário, NCG positivo) | EPIC-002 | implementation | M | ready |
| STORY-043 | Validação cruzada R4 — Q17.recebíveis ≥ Q09.venda_mensal | EPIC-002 | implementation | S | blocked_by:STORY-042 |

**Carga estimada:** 1×M + 2×S + 2×XS + 1×M ≈ 3-4 dias úteis. Buffer de 1-2 dias para validação local + smoke em homol + tag rc.

## Compromisso visível ao fim do sprint

Em homologação (`defonline.xandrix.com.br`):

- Quiz Indústria permite submissão com **1 sócio só**; usuário adiciona/remove/edita sócios via lista dinâmica.
- Campo `Inadimplência` aceita valores como `3,46%`.
- Resumo Executivo: card de destaque clica e leva para a linha correspondente da tabela detalhada (sem repetição visual).
- Texto fixo do veredito `em_alerta` aparece em plural ("ações").
- Quiz exibe os títulos/rótulos/textos auxiliares revistos pela EBC; o glossário tem EBITDA como 2º item; recomendação de NCG positivo usa o novo texto.
- Submeter quiz com `Q17.recebiveis < Q09.venda_mensal` é impedido com erro acionável.

Nenhum diagnóstico legado quebra ao abrir.

## Fora de escopo (explicitamente)

- **Reescrita do algoritmo do Resumo Executivo / farol geral por 3 indicadores eleitos** — aguarda lista do Edvan + validação externa formal (STORY-036).
- **STORY-036** (validador externo do motor) — corre em paralelo, dependência externa (contratação humana).
- **STORY-037** (validação final + handoff técnico) — espera STORY-036 e definição do farol geral.
- **STORY-023** (fix bump-rc.yml dispara release-homolog) — débito antigo deferred; pode ser pulled como bônus se houver folga, **não compõe a meta**.
- **Tags rc.W25S3.2 e rc.W25S4.1** pendentes da W25 — devem ser sweep'd na abertura desta sprint (releases já aprovados, faltando só a tag).
- Comércio e Serviços (Onda 2).

## Riscos identificados na abertura

| Risco | Probabilidade | Impacto | Mitigação | Owner |
|---|---|---|---|---|
| STORY-038 mexe no schema do `quiz_payload` e quebra rascunhos persistidos em homol | alta | baixo | descartar rascunhos `quiz_rascunhos` existentes em homol (TTL 90d já curto; IDR-010 §6.4); registrar como entry de release notes | Programador |
| STORY-038 muda canonicalização do `payload_hash` (IDR-010) | média | médio | golden hashes do motor não usam sócios — confirmado em STORY-026/028; rodar suíte completa para garantir | Programador |
| STORY-041 (plural) força bump de motor_version 1.2.0→1.2.1 + re-emit dos 5 goldens | média | baixo | decidir com Arquiteto se bump é necessário ou se texto é puramente apresentacional (renderizado, não lockado no snapshot) | Arquiteto + PO |
| Edvan demora a responder as 4 perguntas abertas e bloqueia STORY-042 (item glossário) e STORY-043 (bloqueante vs alerta) | média | médio | textos default já documentados; PO decide unilateralmente se Edvan não responder até 2026-06-04 | PO |
| STORY-040 colide com tokens v1 do design system (highlight temporário precisa de cor própria) | baixa | baixo | Designer ad-hoc decide token; se virar IDR, registrar | Designer/Programador |
| Sprint paralela com contratação do validador externo distrai o PO | média | baixo | sprint pequena (1 semana), foco em hotfix; contratação é trabalho externo do PO | PO |

## Decisões de produto já tomadas para esta sprint

- **Sócios** → lista dinâmica com **1+ obrigatório**, add/edit/remove (substitui CPF1/CPF2/CPF3 fixos).
- **Inadimplência** → aceita 2 casas decimais entre 0,00 e 100,00.
- **Card de destaque do Resumo Executivo** → clique faz **scroll-to** + highlight de ~1s na linha correspondente da tabela detalhada (não expand inline — economiza scroll em mobile).
- **STORY-043 / R4 (recebíveis ≥ venda mensal)** → regra **bloqueante** (diferente de R1/R2/R3 que são avisos não-bloqueantes — esta é consistência de input, não hipótese de erro).
- **Algoritmo §4.7.1 do Resumo Executivo** → **não mexer** nesta sprint.

## Perguntas abertas — devolução ao Edvan (pauta `pauta-retorno-ebc-2026-06.md`)

1. **Critério atual dos destaques no Resumo Executivo** — explicar a fórmula `|valor − fronteira_amarela| / amplitude_faixa_vermelha` da STORY-031 e perguntar se ele se dá por satisfeito vendo o método (antes da rediscussão dos 3 indicadores eleitos).
2. **Lista do que é anualizado (×12)** — confirmar os 5 itens propostos (compras, vendas, custos fixos, custos variáveis, despesas financeiras).
3. **Glossário** — qual item exatamente deve sair "antes do Farol/Semáforo" no `.docx`.
4. **Lista dos 3 indicadores "determinantes"** para o farol geral — Edvan disse que vai voltar; lembrar (não bloqueia esta sprint, mas bloqueia STORY-036/037).

## Mudanças no escopo do sprint (preencher se houver mid-sprint changes)

| Data | O que mudou | Motivo | Custo |
|---|---|---|---|

## Fechamento do sprint (preencher no encerramento)

### O que foi entregue

- ...

### O que ficou para trás (e por quê)

- ...

### Aprendizados

- ...

### Ajustes para o próximo sprint

- ...
