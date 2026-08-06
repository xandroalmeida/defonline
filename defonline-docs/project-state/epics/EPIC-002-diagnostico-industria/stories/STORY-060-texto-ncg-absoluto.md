---
story_id: STORY-060
slug: texto-ncg-absoluto
title: "Texto do NCG absoluto — levar a intenção editorial da EBC para as três faixas e tratar a entrada inerte da matriz"
epic_id: EPIC-002
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: S
origem: "Escalação do CA-7 da STORY-042, decidida no PDR-006 §2"
---

# STORY-060 — Texto do NCG absoluto

## Contexto

A `STORY-042` levou o texto revisado da EBC para a entrada `ncg_absoluto` da matriz dez-2025. O texto está lá, mas **nenhum diagnóstico o exibe**: o indicador `NcgAbsoluto` não consome a matriz.

Isso não é bug. A `STORY-032` migrou 13 indicadores para a matriz e deixou o NCG absoluto de fora com justificativa registrada: o Anexo F tem 2 cenários, o motor entrega 3 faixas semânticas (folga / moderado / alto), e a granularidade maior é mais útil ao usuário.

O `PDR-006` §2 marcou o CA-7 como `n/a` — ele foi escrito sobre a premissa errada de que o indicador lê a matriz. Fica o resíduo legítimo que esta estória resolve: **a intenção editorial da EBC de fato não chegou ao usuário**, e a entrada da matriz virou configuração inerte que vai confundir quem passar por ela.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Ler antes: `decisions/pdr/PDR-006-escalacoes-sprint-w32.md` §2; o docblock do indicador `NcgAbsoluto`, que documenta a decisão da STORY-032; `especificacao/V2/anexos/anexo-F-matriz-recomendacoes-dez2025.md` §F.13; `decisions/idr/IDR-010-*`

## O quê

Incorporar a intenção editorial do texto revisado da EBC às três mensagens de faixa que o produto realmente exibe, e resolver a entrada inerte da matriz.

## Por quê

O usuário com NCG positivo recebe hoje uma mensagem que a EBC quis melhorar. A melhoria existe, escrita e revisada — só está no lugar errado.

## Critérios de aceite

- [ ] **CA-1 (Intenção editorial nas faixas):** as mensagens das faixas **moderado** e **alto** incorporam a orientação do texto revisado da EBC — que a elevação do NCG seja acompanhada, na mesma proporção, pelo aumento das fontes de financiamento de longo prazo, preferencialmente fornecedores. A mensagem de **folga** é revisitada contra o cenário "negativa" do Anexo F.
- [ ] **CA-2 (Granularidade preservada):** continuam sendo **três** faixas. A decisão da `STORY-032` não é revertida — não se troca três mensagens por duas.
- [ ] **CA-3 (Bump de versão do motor):** as mensagens fazem parte da saída do motor. A versão sobe conforme o padrão, os golden hashes são reemitidos deliberadamente, e o diff dos hashes é conferido e registrado — a mudança tem que atingir exatamente os cenários de NCG e nenhum outro.
- [ ] **CA-4 (Diagnósticos legados intocados):** diagnósticos gerados antes desta mudança preservam o texto com que foram gerados. `IDR-010`, sem exceção. Teste dedicado.
- [ ] **CA-5 (Entrada inerte da matriz):** a entrada `ncg_absoluto` em `config/motor/matriz-dez-2025-industria.php` deixa de ser uma armadilha. Duas saídas aceitáveis, à sua escolha, justificada nas notas: remover a entrada, ou mantê-la com marcação explícita de que é referência editorial não consumida em runtime. Se mantida, o teste arquitetural da matriz precisa saber que essa chave é a exceção conhecida — e não passar a acusá-la.
- [ ] **CA-6 (Rastreabilidade):** o docblock do indicador continua explicando por que ele não consome a matriz, agora apontando também para esta estória e para o `PDR-006`.
- [ ] **CA-7 (Testes):** unitários cobrindo as três faixas com os textos novos; teste de legado; golden hashes reemitidos e verdes.
- [ ] **CA-8 (Cobertura):** ≥ 98% — é núcleo do motor.

## Fora de escopo

- Reverter a decisão da `STORY-032`.
- Fazer o `NcgAbsoluto` consumir a matriz.
- Mexer em qualquer outro indicador ou em qualquer outra entrada da matriz.
- Recalcular diagnósticos existentes.

## Dependências

- **Bloqueada por:** `STORY-042` (o texto revisado precisa estar na matriz para servir de fonte).
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- `STORY-032` — o NCG absoluto não consome a matriz, e são três faixas.
- `PDR-006` §2 — o CA-7 da 042 é `n/a`; o produto não muda para satisfazer critério mal formulado.
- `IDR-010` — sem recálculo retroativo.

## Definição de Pronto

- [ ] CA-1 a CA-8 passam.
- [ ] Diff dos golden hashes conferido e registrado nas notas.
- [ ] Pipeline verde; deploy em homologação verificado.
- [ ] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. A redação final das três mensagens é conteúdo editorial: proponha, registre nas notas, e sinalize ao PO para revisão antes do merge.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### Cobertura final
