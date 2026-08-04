---
story_id: STORY-044
slug: valor-nominal-indicadores
title: "Valor nominal junto a cada indicador do relatório de Diagnóstico"
epic_id: EPIC-002
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: S
origem: "Feedback do Key User José Marques, 03/08/2026 — item 1"
---

# STORY-044 — Valor nominal junto a cada indicador

## Contexto

O Key User José Marques pediu, no feedback de 03/08/2026: *"registrar no relatório também o valor nominal junto a cada indicador apurado"*.

O relatório atual mostra o indicador calculado — margem em percentual, prazo em dias, ciclo em dias — mas não mostra os números que o produziram. Quem lê "margem bruta de 22%" não sabe se isso são R$ 22 mil sobre R$ 100 mil ou R$ 220 mil sobre R$ 1 milhão. A ordem de grandeza muda completamente a leitura, e é o primeiro reflexo de quem confere um número: "22% de quanto?".

Há um efeito de confiança além da leitura. Indicador sem valor nominal parece caixa-preta; com o valor ao lado, o usuário confere e passa a confiar no resto do relatório.

**Este item não faz parte do módulo DRM.** É melhoria do relatório de Diagnóstico já entregue — pós-devolutiva, no mesmo padrão das STORY-038 a STORY-043.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Ler antes: `revisao/analise-po-feedback-jose-marques-2026ago03.md` §1, item A; o relatório de Diagnóstico e o formatador de indicadores já em produção.

## O quê

Exibir, junto a cada indicador do relatório de Diagnóstico, os valores nominais que o originaram, em reais e no padrão de formatação BR.

## Por quê

Reduz a percepção de caixa-preta no relatório e responde ao pedido direto do Key User. Custo baixo, ganho de confiança desproporcional.

## Critérios de aceite

- [ ] **CA-1 (Cobertura dos indicadores):** cada indicador do relatório exibe o valor ou os valores nominais que o compõem. Onde o indicador é uma razão, os dois termos aparecem — por exemplo, uma margem mostra o resultado e a base de receita sobre a qual foi calculada.
- [ ] **CA-2 (Fonte é o snapshot):** os valores nominais derivam do snapshot já persistido do diagnóstico. **Nenhum recálculo, nenhuma nova invocação do motor, nenhuma alteração no `payload_hash` nem na versão do motor** (IDR-010).
- [ ] **CA-3 (Indicadores em dias e percentual):** indicadores expressos em dias ou percentual exibem os valores monetários de origem quando existirem. Quando não houver valor nominal aplicável, nada é exibido — sem placeholder vazio, sem "N/A".
- [ ] **CA-4 (Indicador indisponível):** indicador marcado como indisponível continua exibindo sua mensagem semântica atual, sem valor nominal.
- [ ] **CA-5 (Hierarquia visual):** o valor nominal é informação de apoio, não concorre com o indicador. O indicador permanece o elemento dominante da linha ou do card.
- [ ] **CA-6 (Formatação BR):** padrão monetário brasileiro, consistente com o resto do produto.
- [ ] **CA-7 (Mobile e desktop):** funciona na tabela densa de desktop e nos cards de mobile, sem scroll horizontal em 360×800.
- [ ] **CA-8 (Compatibilidade retroativa):** diagnósticos gerados por versões anteriores do motor continuam renderizando. Se o snapshot antigo não tiver algum dado necessário, o valor nominal daquele indicador simplesmente não aparece — a tela **não quebra** e não exibe erro.
- [ ] **CA-9 (Acessibilidade):** o valor nominal é associado ao indicador de forma legível por leitor de tela, sem duplicar leitura confusa.
- [ ] **CA-10 (Testes):** feature cobrindo render com valores nominais, indicador indisponível, e snapshot de versão antiga sem os dados; verificação de que o `payload_hash` permanece inalterado.
- [ ] **CA-11 (Cobertura):** ≥ 80% no código novo.

## Fora de escopo

- Qualquer coisa do módulo DRM — é o EPIC-010.
- Alterar fórmulas, faixas de farol, ordem dos indicadores ou textos de recomendação.
- Alterar a versão do motor. Esta estória é de apresentação, não de cálculo.
- Exportação em PDF (EPIC-007).

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`.

## Dependências

- **Bloqueada por:** nada. Independe do EPIC-010.
- **Bloqueia:** nada.

## Decisões já tomadas (não as reabra)

- IDR-010 — snapshot imutável, sem recálculo retroativo. Esta estória lê o snapshot; não o altera.
- IDR-008 — tokens de design, sem hex literal.
- Design system e padrão mobile-first do relatório atual.

## Liberdade técnica do agente

Você decide como extrair e formatar os valores a partir do snapshot e como compor a apresentação. Você **não** decide quais indicadores existem, sua ordem, nem pode tocar no motor.

Se descobrir que algum indicador não tem valor nominal derivável do snapshot atual, **registre nas notas e siga** — não altere o motor para produzi-lo. Se vários indicadores estiverem nessa situação, pare e avise o PO: pode ser sinal de que o pedido exige mudança de motor, e aí é outra estória.

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
