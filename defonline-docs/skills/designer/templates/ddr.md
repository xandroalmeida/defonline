---
id: DDR-XXX
title: <título curto e específico>
status: proposed   # proposed | accepted | superseded | rejected | deferred
created_at: YYYY-MM-DD
decided_at: ~        # preenchido quando vai para accepted
approved_by: ~       # nome do humano que aprovou (obrigatório para accepted)
supersedes: ~        # DDR-XXX se houver
superseded_by: ~     # preenchido quando outro DDR substitui este
related_ddrs: []     # DDRs que se restringem mutuamente
related_adrs: []     # ADRs cuja decisão técnica restringe este DDR
related_pdrs: []     # PDRs cuja decisão de produto motiva este DDR
scope: <transversal | área específica (ex.: navegação, formulários, listagens)>
affects_screens: []  # ids de specs de tela impactados, se aplicável
---

# DDR-XXX — <título>

## Contexto

Por que esta decisão precisa ser tomada agora? Que problema concreto motivou? Quem está pedindo (estória, conversa, observação de uso, conflito entre telas)?

Cite os documentos lidos: estória(s), PDRs/ADRs/DDRs relacionados, trechos da especificação, telas existentes afetadas. Sem contexto, decisão não se sustenta.

## Forças (drivers)

Liste o que pesa nesta decisão, com peso explícito. Ex.:

- **Persona** (alto): contador/gestor de MPE, atenção fragmentada, mobile relevante.
- **Princípio #1 (simplicidade radical)** (alto): tela atual está com 3 CTAs primários competindo.
- **Restrição técnica** (médio): lib de componentes escolhida pelo Arquiteto não tem componente X nativo.
- **Tempo de implementação** (baixo): variação A custa 1h, variação B custa 1 dia.

Forças sem peso viram critério decorativo. Peso obriga você a decidir o que importa.

## Opções consideradas

No mínimo 2 opções + status quo. Para cada uma, descreva curto e — se ajudar — inclua **sketch ASCII/SVG inline**.

### Opção A — <nome curto>

Descrição em prosa curta.

```
+----------------------------+
| Sketch mobile (≥360px)    |
|                            |
+----------------------------+
```

```
+--------------------------------------------------+
| Sketch desktop (≥1024px)                         |
|                                                  |
+--------------------------------------------------+
```

- **Prós:** ...
- **Contras:** ...

### Opção B — <nome curto>

(mesmo formato)

### Status quo — manter como está

(mesmo formato — sempre considere)

## Avaliação contra os princípios

Marque ✅ / ⚠️ / ❌ com 1 frase. Onde houver ❌ ou ⚠️ na opção escolhida, justifique.

| Princípio | A | B | Status quo |
|---|---|---|---|
| 1. Simplicidade radical | | | |
| 2. Mobile-first com paridade | | | |
| 3. Tom profissional para MPE | | | |
| 4. Padronização > criatividade | | | |
| 5. Acessibilidade como hábito | | | |
| 6. Performance percebida | | | |
| 7. Estados além do caminho feliz | | | |

## Decisão

> **Adotada:** Opção <X>.

Justificativa em 2–5 linhas. Cite a(s) força(s) decisiva(s) e por que as outras não venceram.

## Consequências

### Positivas

- ...

### Negativas / trade-offs assumidos

- ... (esconder trade-off é viés — declare)

### Impacto no Design System

- Cria componente novo? Estende variante de componente existente? Introduz token novo? Atualiza padrão? Registre aqui — e atualize `project-state/design/system/` na mesma operação.

### Impacto em telas existentes

- Liste specs de tela em `affects_screens` que precisam ser revistos. Abra estórias com o PO se a revisão for grande.

## Implementação sugerida (notas para o Programador)

Notas opcionais sobre o que facilita a implementação — sem ditar código. Ex.: identificadores `data-testid` sugeridos, componentes do DS a reutilizar, ordem natural de implementação.

## Critérios para revisitar

Quando esta decisão deveria ser reaberta? Ex.: "se a lib de componentes mudar", "se o uso mobile passar de 60%", "depois de 5 telas usando o padrão — reavaliar dor real".

## Aprovação humana

| Campo | Valor |
|---|---|
| Apresentado em | YYYY-MM-DD |
| Aprovado por | (nome) |
| Data da aprovação | YYYY-MM-DD |
| Observações do aprovador | (livre) |

> Sem este bloco preenchido, o DDR **não** pode ir para `accepted`.
