---
story_id: STORY-052
slug: pacote-conteudo-ebc-drm
title: "Pacote de conteúdo do DRM — rótulos, tooltips, textos de leitura e estrutura paramétrica de faixas"
epic_id: EPIC-010
type: enablement
target_role: po
requires_design: false
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: M
---

# STORY-052 — Pacote de conteúdo EBC do DRM

> **Esta estória é do PO, não do programador.** O entregável é conteúdo editorial validado, versionado em arquivo de configuração — no mesmo padrão já usado pelo Diagnóstico, onde faixas, textos e tooltips vivem em `config/`, versionados em git, e não em banco.

## Contexto

O módulo tem três camadas de texto que não são decisão de engenharia: os **rótulos** das linhas da demonstração (com duas divergências deliberadas em relação à planilha original), os **tooltips** dos campos de entrada (onde há confusões previsíveis que vão gerar dado errado), e as **faixas de farol** por setor, que definem quando uma margem é verde, amarela ou vermelha.

As faixas são o item mais sensível. No Diagnóstico, elas passaram por validação externa da EBC (NRF §9.3), e o mesmo rigor se aplica aqui: **margem bruta saudável em comércio de alimentos não é a mesma de indústria moveleira.** Publicar faixa não validada é pior do que não publicar farol nenhum.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `revisao/analise-po-feedback-jose-marques-2026ago03.md` (F2, F3, F5, F7, F10); `epics/EPIC-010-*/design/estrutura-drm.md` §3; `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` §K.10

## O quê

Conduzir a consulta à EBC, consolidar as respostas e publicar o pacote de conteúdo do DRM em formato consumível pela implementação.

## Critérios de aceite

- [x] **CA-1 (Decisões fechadas):** `[DECIDIR-B]`, `[DECIDIR-C]`, `[DECIDIR-D]` e `[DECIDIR-E]` respondidas e registradas no `PDR-004` (04/08/2026). **Concluído.**
- [x] **CA-2 (Rótulos):** rótulo final de cada linha, nos dois modos, fixado em `design/estrutura-drm.md` v1.1 §1.1 e §1.2. Divergências §3.1 e §3.2 resolvidas pelo `PDR-004`. **Concluído.**
- [ ] **CA-3 (Rótulos por setor):** rótulo do grupo de custo nos três setores, conforme Anexo K §K.4.
- [ ] **CA-4 (Tooltips):** texto de ajuda para cada campo do Anexo K, priorizando os cinco pares de confusão previsível listados em §K.10 — frete na aquisição vs. na entrega; taxa de cartão vs. juros de antecipação; salários vs. pró-labore; guia única do Simples; sazonalidade de férias e 13º.
- [ ] **CA-5 (Estrutura paramétrica de faixas):** o pacote entrega a **estrutura** das faixas de verde, amarelo e vermelho por setor e por linha — não necessariamente os valores. Requisitos:
  - Cada faixa tem um estado explícito: `calibrada` (validada pela EBC, com origem declarada) ou `nao_calibrada`.
  - **Faixa `nao_calibrada` não produz farol.** A linha aparece sem semáforo, e isso é comportamento correto — não é lacuna nem erro.
  - Popular uma faixa depois **não exige mudança de código**: é edição do arquivo de configuração versionado, com bump da versão do conteúdo.
  - O módulo pode ir para homologação com todas as faixas `nao_calibrada`. A demonstração e os comparativos entregam valor sem farol.
- [ ] **CA-6 (Textos de leitura):** frases-modelo da leitura em linguagem simples da STORY-054, por combinação de linha e farol, escritas para quem não tem formação contábil. Evitar jargão; quando o termo for inevitável, explicá-lo no lugar.
- [ ] **CA-7 (Mensagens de alerta):** texto final de cada regra bloqueante e alerta do Anexo K §K.6.
- [ ] **CA-8 (Formato):** pacote publicado em formato consumível pela implementação, versionado em git, com campo de versão próprio, no mesmo padrão de configuração editorial já usado pelo Diagnóstico.
- [ ] **CA-9 (Lacunas explícitas):** todo item sem resposta da EBC até a data-limite fica registrado com o default aplicado, para que a implementação nunca fique parada esperando texto.

## Fora de escopo

- Implementar a exibição do farol e da leitura (STORY-054).
- Preço, nome comercial do módulo (`[DECIDIR-A]`) e material de marketing.

## Dependências

- **Bloqueada por:** consulta à EBC.
- **Bloqueia:** STORY-054. Bloqueia parcialmente STORY-048 (`[DECIDIR-B]`, `[DECIDIR-E]`) e STORY-051 (`[DECIDIR-D]`).

## Estado em 04/08/2026 — desbloqueada

A consulta foi respondida e o `PDR-004` fechou B, C, D e E. **CA-1 e CA-2 estão concluídos** e a `STORY-048` foi desbloqueada.

As **faixas de farol** deixaram de bloquear esta estória por decisão do PO em 04/08: em vez de esperar a validação da EBC, entregamos a **estrutura paramétrica** com as faixas em estado `nao_calibrada`, e a EBC popula os valores quando os tiver — sem tocar em código.

**A regra de segurança permanece e é o ponto central do CA-5:** faixa não calibrada **não produz farol**. Dizer a um microempresário que a margem dele está vermelha com base num palpite é pior do que não dizer nada — é exatamente o risco que o NRF §9.3 existe para evitar. O módulo pode ir ao ar sem semáforo nenhum; a demonstração, os comparativos e a leitura já entregam valor.

Decisão editorial posterior implica bump de versão do conteúdo — **nunca** recálculo de lançamentos já feitos.

## Definição de Pronto

- [ ] CA-1 a CA-9 atendidos.
- [ ] Decisões registradas em PDR; `index.json` atualizado.
- [ ] STORY-048 e STORY-051 desbloqueadas ou com defaults confirmados por escrito.

## Notas

### Consulta enviada em
### Respostas recebidas
### Defaults aplicados
