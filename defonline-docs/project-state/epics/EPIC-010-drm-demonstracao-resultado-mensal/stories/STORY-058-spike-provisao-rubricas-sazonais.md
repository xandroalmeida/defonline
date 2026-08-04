---
story_id: STORY-058
slug: spike-provisao-rubricas-sazonais
title: "Spike — provisão de férias e 13º: onde mora o saldo num módulo que só tem resultado"
epic_id: EPIC-010
type: spike
target_role: arquiteto
requires_design: false
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: M
decisoes: [PDR-004]
checkpoint: CP3
---

# STORY-058 — Spike: provisão de rubricas sazonais

> **Para o agente que vai executar:** você é o **Arquiteto**. Esta estória tem uma saída legítima que **não é implementar** — leia a seção "Saídas aceitáveis" antes de começar.

## Contexto

A EBC decidiu (`PDR-004`, decisão D) que o tratamento de férias e 13º deve ser **provisão contábil completa**: o sistema acumula a provisão mês a mês e a baixa contra o pagamento quando ele ocorre.

A decisão responde a uma dor real. Em regime de caixa puro, dezembro parece um desastre porque o 13º foi pago inteiro naquele mês, e o usuário lê queda de resultado onde houve apenas concentração de despesa.

Mas provisão não é uma escolha de exibição, como foi a posição do pró-labore. É uma **conta que atravessa meses**: acumula-se 1/12 por mês, o acúmulo fica guardado em algum lugar, e é abatido quando o pagamento acontece. O que sobra ou falta é saldo. E saldo de provisão é um **passivo** — dinheiro que a empresa deve e ainda não pagou.

Aqui está a colisão: **o DRM não tem onde guardar um passivo.** O `PDR-003` desenhou o módulo como demonstração de resultado em regime de caixa puro, sem nenhuma conta patrimonial — e é justamente essa ausência que o torna acessível ao MEI. Quem tem contas patrimoniais tem escrita contábil, e quem tem escrita contábil não é o público deste módulo.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `decisions/pdr/PDR-004-decisoes-estruturais-drm.md` (decisão D — **o enunciado do problema**); `decisions/pdr/PDR-003-*.md` (a premissa de caixa puro); `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` §K.5-A e §K.8; `epics/EPIC-010-*/design/estrutura-drm.md` §6; a decisão da STORY-045 (versionamento e retificação)

## O quê

Produzir um **ADR** que responda se — e como — a provisão de rubricas sazonais cabe no DRM, e a que custo.

## Perguntas que a decisão precisa responder

1. **Onde mora o saldo?** Provisão acumulada é conta patrimonial. Introduzi-la no DRM contradiz a premissa de caixa puro do `PDR-003`. Há caminho que entregue o efeito sem quebrar a premissa?
2. **Base de cálculo.** 1/12 de quê? O 13º é aproximadamente um salário anual; férias são um salário mais um terço. O sistema deriva isso de `D16` (salários), ou o usuário informa a base? Encargos (FGTS, INSS) sobre essas rubricas também se provisionam?
3. **Baixa contra o pagamento.** Quando `D20` ou `D21` são lançados, como o valor é abatido da provisão? O que acontece quando o pago difere do provisionado — vira ajuste no resultado do mês, ou saldo residual?
4. **Virada de exercício.** O que acontece com saldo remanescente em 31/12? E com provisão de um funcionário que saiu no meio do ano?
5. **Início de uso.** Empresa que começa a usar o DRM em setembro não tem provisão acumulada de janeiro a agosto. O sistema provisiona retroativamente, começa do zero, ou pede saldo inicial?
6. **Retificação.** Retificar um mês antigo altera a provisão acumulada de todos os meses seguintes. Como isso convive com a decisão da `STORY-045` e com a regra de que competências não retificadas nunca são recalculadas?
7. **Comparabilidade da série.** Ligar a provisão no meio do uso muda o resultado de meses passados? Se sim, a série deixa de ser comparável — e isso é aceitável?
8. **Alcance.** A decisão vale só para férias e 13º, ou abre precedente para outras despesas sazonais (IPTU anual, seguro anual, licença de software)?

## Saídas aceitáveis

Três, todas legítimas. **Nenhuma é falha do spike.**

- **A — Cabe no EPIC-010.** Existe caminho que entrega provisão sem introduzir conta patrimonial, ou introduzindo-a a custo aceitável. O ADR descreve o modelo e o épico ganha uma estória de implementação.
- **B — Cabe, mas não aqui.** A provisão exige modelagem patrimonial que muda a premissa do módulo. Vira **EPIC-011** na onda seguinte, e o `PDR-003` é reaberto para decidir se o DRM deixa de ser caixa puro. **Esta é a saída que eu considero mais provável.**
- **C — Não vale a pena.** O tratamento vigente da `STORY-051` — alerta de rubrica atípica, marcação na série, comparativo explicado — resolve a dor a uma fração do custo, e a provisão completa serviria a um público que este módulo não atende. O ADR recomenda medir antes de construir.

Escolher B ou C **não é recusar a decisão da EBC** — é informá-la do custo real antes que ele seja pago. Recomende com franqueza.

## Critérios de aceite

- [ ] **CA-1:** ADR publicado em `decisions/adr/`, `status: accepted`, respondendo às 8 perguntas com opções, prós, contras e justificativa.
- [ ] **CA-2:** A relação com a premissa de caixa puro do `PDR-003` é declarada explicitamente — a decisão a preserva, a estende ou a contradiz.
- [ ] **CA-3:** Recomendação clara entre as saídas A, B e C, com o custo estimado de cada uma em termos de modelagem, não de horas.
- [ ] **CA-4:** Se a recomendação for A, esboço do modelo de dados aderente ao `ADR-003` e à decisão da `STORY-045`, mais a lista de estórias que o épico precisaria ganhar.
- [ ] **CA-5:** Se for B ou C, a recomendação declara o que o usuário **perde** em relação à decisão da EBC, e por que o tratamento vigente é ou não suficiente. Sem eufemismo.
- [ ] **CA-6:** Impacto sobre a comparabilidade da série histórica avaliado em qualquer cenário — é o ativo do módulo e não pode ser degradado sem decisão consciente.
- [ ] **CA-7:** Se houver protótipo, vive em branch `spike/STORY-058-*` e não é mergeado.
- [ ] **CA-8:** `index.json` atualizado; se a saída for B, o EPIC-011 é esboçado em `roadmap/next-wave.md`.

## Fora de escopo

- Implementar a provisão, em qualquer cenário.
- Alterar o tratamento vigente da `STORY-051`, que entrega independentemente do resultado deste spike.
- Reabrir a decisão da EBC. Ela está tomada; você está definindo o caminho e informando o custo.

## Dependências

- **Bloqueada por:** STORY-045 (a mecânica de retificação condiciona a pergunta 6).
- **Bloqueia:** STORY-057 (a validação precisa saber se há implementação a validar).

## Decisões já tomadas (não as reabra)

- `PDR-004`, decisão D — a provisão contábil é o alvo.
- `PDR-003` — o DRM é resultado em caixa puro. **Contradizê-lo é possível, mas exige dizer isso em voz alta no ADR**, não de lado.
- Decisão da `STORY-045` — versionamento e retificação.
- `IDR-010` — competência não retificada não é recalculada.

## Definição de Pronto

- [ ] CA-1 a CA-8 atendidos.
- [ ] ADR revisado com o PO antes de `accepted` — o PO valida se a recomendação foi feita com franqueza suficiente para embasar a conversa de volta com a EBC.
- [ ] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### ADRs criados
