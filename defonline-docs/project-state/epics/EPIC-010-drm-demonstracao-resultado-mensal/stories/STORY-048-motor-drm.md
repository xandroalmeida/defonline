---
story_id: STORY-048
slug: motor-drm
title: "Motor do DRM — estrutura canônica em três setores, valores nominais e percentuais"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: L
decisoes: [PDR-003, PDR-004]
---

# STORY-048 — Motor do DRM

## Contexto

O coração do módulo, e a parte mais simples dele: somas e subtrações encadeadas sobre até 36 entradas, produzindo 11 linhas com valor nominal e percentual sobre a receita. A dificuldade não é o cálculo — é o rigor. Este motor produz o número que o usuário vai usar para decidir preço e contratação, e que vai alimentar a série histórica pelos próximos anos. Erro aqui é erro que se propaga e que ninguém percebe.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `epics/EPIC-010-*/design/estrutura-drm.md` (**referência aritmética canônica — é o contrato desta estória**); `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md`; a decisão da STORY-045
- Código existente a estudar: o motor de Diagnóstico e seus indicadores. Ele já resolveu pureza, aritmética exata, versionamento, resultado indisponível com motivo semântico e teste arquitetural de pureza. **Siga o mesmo padrão.**

## O quê

Implementar o motor que transforma um lançamento mensal na Demonstração de Resultado Mensal, com valor nominal e percentual em cada linha, funcionando nos três setores.

## Por quê

É o que responde a pergunta que dá nome ao épico. E é o pedido literal do Key User: *"registrar no relatório também o valor nominal junto a cada indicador apurado"*.

## Critérios de aceite

- [ ] **CA-1 (Estrutura exata):** o motor produz as linhas de `design/estrutura-drm.md` §1, com as fórmulas exatamente como especificadas, na ordem especificada. A ordem faz parte do contrato.
- [ ] **CA-1b (Dois modos):** o motor entrega os dois modos de apresentação do pró-labore — §1.1 (`fora`, default) e §1.2 (`dentro`) —, com os rótulos, o número de linhas e a ordem de cada um. **O modo é aplicado na leitura, não no cálculo persistido:** o snapshot guarda os valores que permitem derivar ambos, e alternar o modo não recalcula nem migra nada.
- [ ] **CA-1c (Invariante entre modos — obrigatório):** teste dedicado prova que o **Lucro Líquido do Mês é idêntico nos dois modos**, sobre um conjunto amplo de entradas. Divergência aqui é bug grave: a resposta a "meu negócio deu lucro?" não pode depender de uma preferência de exibição.
- [ ] **CA-1d (Linhas não operacionais):** L7b (`D41 − D42`) implementada nos dois modos, posicionada após o Resultado Operacional. **Omitida quando ambos os campos são zero** — sem linha zerada na tela.
- [ ] **CA-2 (Nominal e percentual):** cada linha carrega valor nominal **e** percentual sobre a Receita Bruta. Percentual com 1 casa decimal na apresentação.
- [ ] **CA-3 (Três setores):** o mesmo motor atende Indústria, Comércio e Serviços. Campos suprimidos por setor entram como ausentes, não como zero, e não alteram a estrutura de linhas.
- [ ] **CA-4 (Modo agregado):** grupo em modo agregado usa o total informado; em modo detalhado, a soma dos itens. Ambos produzem a mesma estrutura de saída.
- [ ] **CA-5 (Sem faturamento):** com Receita Bruta zero, todos os percentuais retornam indisponíveis com motivo `indisponivel:sem_faturamento` e mensagem semântica; **os valores nominais continuam presentes**. Nenhuma exceção, nenhuma divisão por zero, nenhum zero disfarçado de percentual.
- [ ] **CA-6 (EBITDA padrão):** o motor calcula `L5 − (L6 + pró-labore)`, que é o Resultado Operacional do modo `dentro`. Com o `PDR-004`, este valor **deixou de ser um cálculo paralelo oculto** e passou a ser exibido quando o modo `dentro` está ativo — é o mesmo número, agora com uso.
- [ ] **CA-7 (Pureza):** o motor não lê banco, não usa relógio, não usa sessão nem usuário autenticado. Competência e carimbo de tempo entram como parâmetro. Garantido por **teste arquitetural**, no mesmo padrão já usado pelo motor de Diagnóstico.
- [ ] **CA-8 (Aritmética exata):** sem ponto flutuante. Arredondamento apenas na formatação. Resultado bit-exato e reprodutível.
- [ ] **CA-9 (Versionamento):** o motor tem versão semântica própria, independente do motor de Diagnóstico, gravada em cada snapshot. Diagnósticos e lançamentos antigos **nunca** são recalculados.
- [ ] **CA-10 (Golden tests):** conjunto de casos congelados cobrindo, no mínimo: um mês saudável por setor, um mês com prejuízo, um mês sem faturamento, um mês com todos os grupos agregados, um mês com todos detalhados, um mês misto, e cada um dos dois modos de pró-labore. Alteração de saída deve quebrar o teste — é esse o objetivo.
- [ ] **CA-11 (Conferência com a planilha original):** teste que reproduz, para um conjunto de entradas, exatamente os valores que a planilha `Demonstração de Resultado Mensal - DRM.xlsx` produziria, célula a célula, conforme o mapeamento do Anexo K §K.11. Evidência anexada nas notas.
- [ ] **CA-12 (Cobertura):** ≥ 98% — é núcleo de regra de negócio.

## Fora de escopo

- Exibir qualquer coisa (STORY-049) — inclusive a UI de troca de modo, que é da tela. O motor só precisa **entregar** os dois modos.
- Faróis e leitura em prosa (STORY-054). Comparativos históricos (STORY-051).
- Recomendações textuais por faixa — o DRM **não** usa a matriz do Diagnóstico.
- Provisão de rubricas sazonais — sob spike na STORY-058. O motor trata férias e 13º como despesa do mês em que foram pagas.

## Desbloqueio

Esta estória estava `blocked` por `[DECIDIR-B]` e `[DECIDIR-E]`. **Ambas resolvidas em 04/08/2026 pelo `PDR-004`:** o pró-labore é parametrizável com default `fora`, e as linhas não operacionais foram aprovadas. A estória subiu de `M` para `L` por causa dos dois modos.

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`. Cobertura de núcleo ≥ 98%, sem exceção.

## Dependências

- **Bloqueada por:** STORY-045; STORY-047 (formato do lançamento); `[DECIDIR-B]`; `[DECIDIR-E]`.
- **Bloqueia:** STORY-049, STORY-051, STORY-054, STORY-055.

## Decisões já tomadas (não as reabra)

- `design/estrutura-drm.md` v1.1 é o contrato aritmético. Divergiu, é bug.
- PDR-004 — dois modos de pró-labore (default `fora`), percentual em todas as linhas, rótulo "antes do pró-labore", linhas não operacionais aprovadas.
- PDR-003; Anexo K v1.1; decisão da STORY-045; IDR-010 (padrão snapshot, não recálculo).

## Liberdade técnica do agente

Você decide como estruturar as linhas em código, como representar a saída, e o desenho dos testes. Você **não** decide fórmulas, ordem de linhas, rótulos, nem se uma linha existe.

## Definição de Pronto

- [ ] CA-1 a CA-12 passam.
- [ ] Golden tests congelados e commitados.
- [ ] Evidência de CA-11 nas notas.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
