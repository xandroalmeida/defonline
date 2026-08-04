---
epic_id: EPIC-010
type: handoff
created_at: 2026-08-04
owner_role: po
---

# Handoff do EPIC-010 — o que cada papel faz e em que ordem

Ponto de partida para quem vai executar. Cada estória é autocontida; este documento existe para dar a visão do todo e a ordem correta.

## Leitura obrigatória, na ordem

1. `revisao/analise-po-feedback-jose-marques-2026ago03.md` — o parecer do PO. Explica **por que** o módulo existe e traz os 12 achados que moldaram o escopo. Sem isso, várias decisões das estórias parecem arbitrárias.
2. `decisions/pdr/PDR-003-modulo-drm-raio-x-do-negocio.md` — a decisão de produto e as 8 decisões de escopo que a acompanham.
3. `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md` — outcome, métricas, entregável, fora de escopo, riscos.
4. `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` — **referência canônica dos campos**. Se o Anexo K e uma estória divergirem, o Anexo K vence e a divergência é reportada ao PO.
5. `epics/EPIC-010-*/design/estrutura-drm.md` — **contrato aritmético**. Se o código e este documento divergirem, é bug.
6. A sua estória.

## Ordem de execução

```
                        ┌─ STORY-045 (arquiteto) ─┐
                        │  modelo, versionamento  │
   consulta EBC ────────┤  e retificação          ├──┐
   (B, C, D, E)         │                         │  │
        │               └─ STORY-046 (designer) ──┘  │
        │                  specs das 3 telas         │
        │                                            ▼
        │                                    STORY-047  lançamento
        ▼                                            │
   STORY-052 ────────────────────────────►  STORY-048  motor
   conteúdo EBC                                      │
        │                                    STORY-049  demonstração ── CP1
        │                                            │
        │                                    STORY-050  retificação
        │                                            │
        └──────────────────────────────────► STORY-051  série histórica ── CP2
                                                     │
   STORY-053 (arquiteto) ────────────────►  STORY-055  exportação
   spike de exportação                              │
   STORY-058 (arquiteto)                            │
   spike de provisão                                │
                                             STORY-054  faróis e leitura
                                                     │
                                             STORY-056  eventos ── CP3
                                                     │
                                             STORY-057  validação final
```

**STORY-045 e STORY-046 rodam em paralelo e abrem o épico.** Nenhuma implementação começa antes das duas. **STORY-052 e STORY-053 também podem rodar cedo e em paralelo** — a primeira é do PO com a EBC, a segunda é do Arquiteto e não depende de nada.

## Checkpoints

| CP | Fecha em | O que fica demonstrável em homologação |
|---|---|---|
| **CP1** | STORY-049 | O usuário lança um mês e vê se o negócio deu lucro, nos três setores. **Caminho feliz vivo.** |
| **CP2** | STORY-051 | Ele corrige um mês, e vê como este mês se compara aos anteriores. **Vira produto de recorrência.** |
| **CP3** | STORY-056 | Farol, leitura em português simples, exportação e instrumentação. **Módulo completo.** |

O PO grava demo em cada checkpoint.

## Por papel

### Arquiteto — STORY-045, STORY-053 e STORY-058

Três decisões, todas com alcance além deste épico.

A **STORY-045** é a mais importante do épico inteiro: o DRM é o primeiro objeto retificável do produto, e isso colide com o `IDR-010` (snapshot imutável do Diagnóstico). O IDR-010 **não é revogado** — a questão é como acomodar um objeto retificável ao lado de um imutável. Decidida tarde, esta questão vira migração dolorosa sobre dados reais.

A **STORY-053** decide como o produto gera documentos, e serve também ao EPIC-007. Se a conclusão for que o PDF é caro demais para agora, **dizer isso é uma saída legítima e prevista** — a exportação em planilha entrega sozinha.

A **STORY-058** trata a provisão de férias e 13º, que a EBC pediu (`PDR-004`, decisão D). Provisão exige um saldo acumulado entre meses — conta patrimonial que o DRM não tem por desenho. **Concluir que não cabe aqui é uma das três saídas legítimas**, e é a que eu considero mais provável. Recomende com franqueza: o objetivo do spike é informar o custo real à EBC antes que ele seja pago.

Você **não** decide: posição do pró-labore, rótulos, quais campos existem, faixas de farol. São decisões de negócio ou de produto — e as quatro primeiras já estão fechadas no `PDR-004`.

### Designer — STORY-046

Três specs. O problema real está na tela de lançamento: **até 36 campos monetários, doze vezes por ano, no celular, à noite, depois de fechar a loja.** Se essa tela for uma lista de 36 campos, o módulo morre no segundo mês — e a métrica primária do épico é justamente o detector desse fracasso.

O PDR-003 já fixou os três mecanismos anti-atrito (modo agregado por grupo, pré-preenchimento do mês anterior, rascunho retomável). Fazê-los funcionar visualmente é o trabalho.

Reaproveite os padrões do Quiz e do relatório de Diagnóstico já em produção. Não crie um segundo dialeto de formulário dentro do mesmo produto.

### Programador — STORY-047, 048, 049, 050, 051, 054, 055, 056

O código do DRM tem pouca dificuldade algorítmica e muita exigência de rigor. Quatro coisas para carregar:

1. **`design/estrutura-drm.md` é contrato.** Divergiu, é bug. Não "melhore" a estrutura.
2. **O padrão do motor de Diagnóstico é o padrão do motor do DRM.** Pureza garantida por teste arquitetural, aritmética exata, resultado indisponível com motivo semântico, golden tests, versionamento em snapshot. Não reinvente — estude o que existe e siga.
3. **Ausência nunca é zero.** Mês não lançado ≠ mês sem movimento. Percentual indisponível ≠ 0%. Item agregado ≠ item zerado. Confundi-los produz série histórica mentirosa, e a série histórica é o ativo do módulo.
4. **Nada do DRM toca o Diagnóstico.**
4b. **O modo de pró-labore é exibição, nunca cálculo.** Alternar não recalcula, não migra e não muda o Lucro Líquido do Mês. Nenhuma alteração no motor, no `payload_hash` ou nas versões do EPIC-002.

Cobertura: ≥ 80% geral, ≥ 98% em motor, comparativos e classificação de farol. Não é negociável.

### Validador — STORY-057

O checklist tem 14 seções. A **seção 2 (correção aritmética) é bloqueante por si só**: um erro de cálculo aqui contamina a série histórica do usuário para sempre, e ninguém percebe. Confira manualmente pelo menos um lançamento por setor contra `design/estrutura-drm.md` §1.

Preste atenção especial à **seção 6 (atrito)**, incluindo o percurso cronometrado. É o item que mede se o épico entregou seu propósito real, e é o único do checklist que não é verificável por teste automatizado.

Evidência primária sempre. Limitação de acesso é declarada como limitação, nunca convertida em falha nem em aprovação silenciosa.

### PO — STORY-052 e o que resta pendente

As quatro decisões de negócio foram respondidas em 04/08/2026 e estão registradas no `PDR-004`. O motor está desbloqueado.

Restam dois itens, nenhum deles travando a construção:

- **Faixas de farol por setor** — exigem validação externa da EBC, no mesmo rigor aplicado ao Diagnóstico. Mantêm a `STORY-052` bloqueada. Faixa não validada não entra: a linha fica sem farol, e isso é correto.
- **`[DECIDIR-A]`** (nome "Raio X do Negócio") — pode entrar até o fim do épico.

E uma conversa de volta com a EBC, depois que a `STORY-058` concluir: se a provisão contábil não couber nesta onda, é o PO quem leva o custo e a alternativa.

## As cinco regras que valem para todo mundo

1. **Ausência nunca é zero.**
2. **Snapshot, não recálculo.** O passado não se reescreve sozinho — só por retificação explícita do usuário.
3. **O Anexo K e a `estrutura-drm.md` vencem.** Divergiu, reporta; não improvisa.
4. **Nada do DRM toca o Diagnóstico.**
4b. **O modo de pró-labore é exibição, nunca cálculo.** Alternar não recalcula, não migra e não muda o Lucro Líquido do Mês.
5. **Encontrou ambiguidade, para e registra.** Não invente campo, regra, faixa nem texto.

## Estória fora do épico

`STORY-044` (valor nominal junto a cada indicador do relatório de Diagnóstico) responde ao item 1 do mesmo feedback, mas é melhoria do **EPIC-002** e não depende deste épico. Vive em `epics/EPIC-002-diagnostico-industria/stories/` e pode entrar em qualquer sprint.
