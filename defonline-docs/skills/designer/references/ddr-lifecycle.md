# DDR — ciclo de vida e método de decisão

DDR (Design Decision Record) é a forma como você **registra decisão de design durável** — análoga a ADR (Arquiteto), PDR (PO) e IDR (Programador), mas focada em UX/UI.

Este documento cobre: o que vira DDR, o método de deliberação, os estados, e como DDRs interagem entre si.

## O que vira DDR (e o que não vira)

### Vira DDR

- Decisão que **afeta múltiplas telas** (ex.: navegação principal lateral persistente).
- Decisão que **define padrão durável** (ex.: wizard de 3 passos para qualquer fluxo com >5 campos).
- Decisão que **restringe uso de token ou componente** (ex.: cor primária só em CTA primário e navegação ativa).
- Decisão que **introduz componente novo no DS**.
- Decisão que **é cara de reverter** (ex.: padrão de URL/rota que outras telas vão herdar).
- Decisão sobre **regra de paridade mobile/desktop** quando não é óbvia (ex.: tabela > 7 colunas vira lista de cards no mobile).

### NÃO vira DDR (é local do spec)

- Posição de um botão **nesta tela específica**.
- Microcopy **deste form específico** (vai no spec).
- Escolha de espaçamento que **usa token existente sem novidade**.
- Variação visual de uma vez só, justificada como exceção no spec.

### Não é seu — devolva

- "Qual lib de form usar?" → Arquiteto (ADR).
- "Vamos priorizar este CA?" → PO (PDR).
- "Como estruturar o componente em código?" → Programador (IDR ou decisão local).

## Estados do DDR

```
proposed ──(aprovado)──→ accepted ──(supersedes)──→ superseded
   │
   ├──(rejeitado)──→ rejected
   │
   └──(adiado)────→ deferred ──(retomado)──→ proposed
```

| Estado | Significado | Pode mudar para |
|---|---|---|
| `proposed` | Decisão escrita, aguardando aprovação humana | `accepted`, `rejected`, `deferred` |
| `accepted` | Aprovado por humano explicitamente, em vigor | `superseded` |
| `rejected` | Aprovador rejeitou; histórico preservado | (terminal) |
| `deferred` | Decisão adiada conscientemente; gatilho de retomada explícito | `proposed` |
| `superseded` | Substituído por DDR mais novo; ponteiro para o novo | (terminal) |

### Regras de transição

- `proposed → accepted` exige **aprovação humana registrada** no DDR (campo `approved_by` + data). Sem isso, não pode ir para `accepted` — independente de quanto tempo passou.
- `accepted → superseded` exige que o novo DDR **declare `supersedes: DDR-XXX`** e que o antigo seja atualizado com `superseded_by: DDR-YYY`. Nada apagado, histórico preservado.
- `deferred` exige **gatilho de retomada explícito** no campo "Critérios para revisitar". Sem gatilho, é DDR fantasma.
- `rejected` é terminal; se a decisão precisar voltar à mesa, abre **novo DDR** com contexto atualizado, citando o `rejected` antigo em "Contexto".

## Método de deliberação

Antes de escrever o DDR, **delibere com critério**. DDR mal pensado vira DDR superseded em 2 semanas.

### 1. Leia o contexto inteiro

- Estória/conversa que motivou (não confie no resumo).
- DDRs vigentes relacionados — você pode estar prestes a contradizer um deles.
- DS atual — talvez a decisão já esteja implícita lá.
- Telas existentes afetadas — olha como ficaria nelas.
- PDRs vigentes — restrições de produto que limitam.
- ADRs vigentes — restrições técnicas que limitam.

Tempo de leitura típico: 15–45 min. Pular este passo é a fonte número um de DDR raso.

### 2. Identifique as forças com peso

Toda decisão é equilíbrio entre forças. Liste-as **com peso explícito** (alto/médio/baixo). Forças típicas:

- **Persona** (alto sempre): contador/gestor de MPE, atenção fragmentada, mobile relevante.
- **Princípios** (alto): qual dos 7 princípios essa decisão tensiona?
- **DS atual** (médio-alto): você está estendendo ou contradizendo o que existe?
- **Restrição técnica** (médio-alto): ADR vigente ou lib de componentes oficial restringe?
- **Tempo de implementação** (variável): trade-off entre opções mais caras.
- **Custo de reversão** (variável): se errarmos, é fácil voltar atrás?

Forças sem peso são critério decorativo. Peso obriga a decidir o que importa.

### 3. Enumere opções reais

No mínimo **duas opções + status quo**. Tomar cuidado com:

- **Falsos dilemas:** "ou A ou B" quando na verdade tem C óbvio.
- **Tudo-ou-nada:** geralmente existe opção incremental no meio.
- **Status quo invisível:** "não mudar nada" é sempre uma opção válida — declare.

### 4. Sketch curto de cada opção viável

Decisão de design sem visual é opinião abstrata. Sketch ASCII/SVG inline no DDR. Não precisa ser polido — precisa permitir comparar.

Mobile **e** desktop para cada opção (lembrando paridade).

### 5. Avalie contra forças e princípios

Tabela de avaliação (no template). Para cada opção, ✅/⚠️/❌ em cada princípio. Justifique ⚠️ e ❌.

**Red flag:** opção escolhida tem ❌ em princípio central sem justificativa robusta.

### 6. Considere `deferred`

Algumas decisões devem ser adiadas conscientemente. Critérios para `deferred`:

- O custo de decidir agora supera o custo de adiar.
- Falta informação real que só virá com uso (ex.: "antes de decidir padrão de filtro, queremos ver como usuários usam filtros simples").
- Decisão one-way muito cara — esperar mais sinal vale.

`deferred` exige **gatilho explícito de retomada**. Sem gatilho, vira buraco no projeto.

### 7. Escreva o DDR em `status: proposed`

Use `templates/ddr.md`. Não pule seções — cada uma existe por motivo.

### 8. Atualize o `index.json`

Adicione entrada em `decisions.ddr[]` **seguindo o schema vigente**. Se a seção ainda não existe no índice, **pare**: escale ao PO para abrir o PDR de evolução do schema (regra explícita em `po/references/indexing.md` — sem PDR, esquema não muda). Só popule a entrada **depois** que o schema for ratificado.

### 9. Apresente ao humano

Você é **conselheiro**, não árbitro. Mesmo modelo do Arquiteto: você propõe, humano aprova. Etiqueta:

- Apresentação no chat com link `computer://` para o DDR.
- Resumo em 3–5 linhas: decisão proposta, força decisiva, principal trade-off.
- Sinalize prioridade se estiver bloqueando estória ativa.
- Agrupe múltiplos DDRs propostos para a mesma estória/épico em uma apresentação só.

### 10. Aguarde aprovação humana

Não mude para `accepted` sozinho — mesmo com "ok" implícito. Aprovação registra `approved_by` + data.

## Como DDRs interagem entre si

### `supersedes`

DDR novo substitui DDR antigo. Quando:

- Contexto mudou (nova persona, nova restrição, nova ADR).
- Aprendemos com uso real que a decisão anterior não serve.
- Surgiu opção melhor que não existia antes.

Mecânica:

- Novo DDR declara `supersedes: DDR-XXX` no frontmatter.
- DDR antigo é atualizado: `status: superseded`, `superseded_by: DDR-YYY`.
- Nada é apagado. Histórico preservado.

### `related_ddrs`

DDRs que se restringem mutuamente, sem um substituir o outro. Ex.: "navegação lateral persistente" + "indicador de navegação ativa em cor primária" — ambos vivem juntos.

### `related_adrs` / `related_pdrs`

Decisões de outras skills que **restringem** ou **motivam** este DDR. Ex.:

- ADR-005 escolheu lib de componentes X → restringe componentes que você pode propor.
- PDR-012 priorizou persona contador → motiva DDR sobre densidade de informação.

### Conflito com `accepted` vigente

Se você está prestes a propor DDR que **contradiz** um `accepted` vigente, **pare**:

- Você não emite DDR conflitante sem reconhecer o conflito.
- Proponha **primeiro** um DDR que `supersedes` o antigo, com contexto da mudança.
- Aí sim, o novo DDR pode ser proposto sem contradição residual.

## Etiqueta de aprovação humana

DDRs aguardando aprovação não devem se acumular. Hábitos:

- **Agrupe propostas relacionadas** em uma sessão de aprovação ("tenho 3 DDRs relacionados ao EPIC-002 prontos; vou apresentá-los em sequência").
- **Sinalize bloqueio** quando o DDR estiver travando estória ativa.
- **Não acumule muitos `proposed` sem fechar.** Se 4+ aguardando, vire ao humano agrupado: "tenho 4 propostas — quer revisar um lote?".

## O que você NUNCA faz no DDR

- Marca como `accepted` sem aprovação humana registrada.
- Reabre `accepted` sem propor `supersedes` formal.
- Esconde trade-off da opção escolhida — declare honestamente.
- Propõe DDR sobre decisão que é de outra skill (devolva).
- Decide "pequeno mas afeta tudo" sem DDR — se afeta múltiplas telas, é DDR.
- Sketch faltando — decisão de design sem visual é opinião abstrata.
