---
story_id: STORY-054
slug: farois-leitura-simples
title: "Faróis e leitura em linguagem simples — o que este mês está dizendo"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: M
checkpoint: CP3
---

# STORY-054 — Faróis e leitura em linguagem simples

## Contexto

O microempresário não sabe se 12% de margem bruta é bom. Ele precisa que alguém diga. Esta estória entrega a camada de interpretação: um farol por linha e um parágrafo curto que traduz a demonstração para português comum, usando o que o mês mostra e o que os meses anteriores mostraram.

O padrão já existe no produto — o relatório de Diagnóstico tem farol classificado por faixas declarativas e um resumo executivo determinístico. **Reaproveite a mecânica, não a matriz:** o DRM tem faixas e textos próprios, vindos da STORY-052.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: pacote de conteúdo da STORY-052 (**contrato desta estória**); `epics/EPIC-010-*/design/estrutura-drm.md`; o farol e o resumo executivo do Diagnóstico já em produção.

## O quê

Classificar as linhas da demonstração em faróis por setor e gerar uma leitura determinística em linguagem simples, exibidas na Demonstração do Mês.

## Por quê

Número sem interpretação não muda comportamento. É a interpretação que faz o usuário ajustar preço, cortar despesa ou renegociar — e voltar no mês seguinte para ver se funcionou.

## Critérios de aceite

- [ ] **CA-1 (Farol só onde há faixa calibrada):** as linhas cuja faixa está marcada como `calibrada` no pacote da STORY-052 recebem farol verde, amarelo ou vermelho, respeitando o setor da empresa. **Linha com faixa `nao_calibrada` não recebe farol** — comportamento correto, não lacuna.
- [ ] **CA-1b (Grau zero funcional):** com **todas** as faixas `nao_calibrada`, a tela renderiza corretamente, sem espaços vazios nem placeholders de erro, e a leitura em prosa se apoia apenas nos comparativos. Teste dedicado para este cenário — é o estado em que o módulo vai ao ar.
- [ ] **CA-1c (Calibrar não exige deploy de código):** popular uma faixa é edição do arquivo de configuração versionado + bump da versão do conteúdo. Teste prova que uma faixa recém-calibrada passa a produzir farol sem nenhuma mudança de lógica.
- [ ] **CA-2 (Faixas declarativas):** as faixas vivem em configuração versionada, não em código. Ajustá-las não exige alterar lógica.
- [ ] **CA-3 (Leitura determinística):** o parágrafo de leitura é gerado por algoritmo determinístico a partir dos faróis e dos comparativos — mesma entrada, mesma saída, sempre. Nenhuma geração livre de texto.
- [ ] **CA-4 (Leitura usa o histórico):** havendo meses anteriores, a leitura menciona o que mudou e por quê, com base nos comparativos da STORY-051. Sem histórico, a leitura é honesta sobre isso e não inventa tendência.
- [ ] **CA-5 (Prejuízo):** a leitura de um mês com prejuízo é direta, útil e não moralizante. Aponta a linha que mais pesou, sem julgar o usuário.
- [ ] **CA-6 (Sem faturamento):** com receita zero, não há farol de margem. A leitura explica o que se pode dizer — o custo de manter o negócio parado — e não força interpretação.
- [ ] **CA-7 (Lacuna editorial):** combinação de linha e farol sem texto no pacote produz mensagem neutra de reserva e registra o fato para o PO, no mesmo padrão adotado pelo Diagnóstico. **Nunca** deixa espaço vazio nem inventa texto.
- [ ] **CA-8 (Versionamento):** a versão do pacote de conteúdo é gravada no snapshot do lançamento. Meses antigos preservam a leitura com que foram gerados.
- [ ] **CA-9 (Acessibilidade):** farol nunca comunicado só por cor — cor, ícone e texto.
- [ ] **CA-10 (Testes):** unitários cobrindo cada faixa em cada setor, cada combinação de farol na leitura, mês sem faturamento, mês com prejuízo, mês sem histórico e lacuna editorial; feature validando o render.
- [ ] **CA-11 (Cobertura):** ≥ 98% — é regra de negócio.

## Fora de escopo

- Recomendações de ação por indicador nos moldes da matriz do Diagnóstico. O DRM diz o que aconteceu; prescrever o que fazer é outra decisão de produto.
- Comparação com benchmark de mercado ou com outras empresas.
- Qualquer geração de texto por modelo de linguagem. Determinístico, ponto.

## Dependências

- **Bloqueada por:** STORY-051, STORY-052.
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- Pacote de conteúdo da STORY-052 — estrutura das faixas e textos.
- Leitura determinística, nunca generativa.
- **Faixa não calibrada não produz farol.** Decisão do PO em 04/08: entregamos a estrutura paramétrica e a EBC popula depois, sem código.

## Definição de Pronto

- [ ] CA-1 a CA-11 passam.
- [ ] Pipeline verde; deploy em homologação verificado.
- [ ] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
