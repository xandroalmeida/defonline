---
pdr_id: PDR-007
slug: aprovacao-specs-drm-e-motor-exportacao
title: Aprovação das specs de tela do DRM, do default do modo no 1º lançamento e do motor de exportação — com o PDF confirmado como documento da plataforma
status: accepted
decided_at: 2026-08-06
decided_by: PO (Alexandro / Claude)
related_epics: [EPIC-010, EPIC-007]
related_stories: [STORY-046, STORY-047, STORY-049, STORY-051, STORY-053, STORY-055]
related_adrs: [ADR-007, ADR-008]
related_ddrs: [DDR-001, DDR-002, DDR-003, DDR-004, DDR-005]
related_pdrs: [PDR-003, PDR-004, PDR-006]
---

# PDR-007 — Aprovação das specs do DRM e do motor de exportação

## Contexto

A `STORY-046` (Designer) e a `STORY-053` (Arquiteto) fecharam a fundação do EPIC-010 e pararam em `in_review`, como previsto: specs de tela nascem `draft`, DDRs e ADRs nascem `proposed`, e nenhum dos três sobe de estado sem aprovação humana explícita. A invariante 12 do índice faz dessa aprovação o que destrava o Checkpoint 1 — sem spec `ready`, a `STORY-047` não pode codificar UI.

Este PDR registra as quatro decisões que estavam na minha mesa.

---

## Decisão 1 — As três specs de tela e os cinco DDRs estão aprovados

As specs `SCREEN-STORY-046-lancamento-mensal`, `SCREEN-STORY-046-demonstracao-mes` e `SCREEN-STORY-046-serie-historica` sobem para `ready`. Os `DDR-001` a `DDR-005` sobem para `accepted`.

**A `STORY-047`, a `STORY-049` e a `STORY-051` estão destravadas.** Junto com o `ADR-007`, aceito em 04/08, o Checkpoint 1 pode abrir sem nenhuma decisão pendente à frente.

O que pesou na aprovação, em três pontos:

- **Os três mecanismos anti-atrito do `PDR-003` viraram desenho, não intenção.** O custo em campos declarado dentro da própria opção do seletor (`DDR-002`) transforma a escolha de modo numa decisão informada — o usuário decide o tamanho do bloco antes de ver 21 campos. A marca de herança que some ao conferir (`DDR-003`) faz a tela convergir para o silêncio conforme o trabalho avança, o que é progresso visível sem indicador adicional.
- **Nenhum dialeto novo de formulário.** O padrão do Quiz em produção foi promovido a `DDR-001` e virou o padrão único do produto. Era exatamente o risco que eu queria evitar ao pedir reaproveitamento.
- **Zero tokens novos.** Cinco situações pareciam pedir cor e nenhuma precisou. O `DDR-005` em particular — mês não lançado difere de mês zerado por quatro canais simultâneos, nenhum deles cor — é a garantia "ausência ≠ zero" do `ADR-007` chegando ao pixel. Uma garantia que morre antes da tela não é garantia.

## Decisão 2 — O default do modo dos grupos no 1º lançamento é `detalhado`

Confirmo a recomendação do Designer, pelo argumento da irreversibilidade: quem chega sem histórico não sabe o que perde ao agregar, e agregar é irreversível para aquele mês. O caminho inverso não é — o seletor está ali para reduzir quando ele quiser, com o custo em campos na frente.

O contra-argumento é real e fica registrado: 38 campos no primeiro contato é o pior momento possível para o atrito, e o 1º lançamento é a porta de entrada do módulo.

**Gatilho de reabertura:** a métrica primária do épico é o 2º lançamento em 45 dias. Se o abandono se concentrar no 1º lançamento, este default é o primeiro suspeito, e inverter custa o mesmo que hoje — a implementação é idêntica nos dois casos.

## Decisão 3 — O `ADR-008` está aceito: planilha agora, PDF no EPIC-007

Aprovado como escrito. A separação que a ADR faz é a parte que importa: planilha e PDF parecem a mesma coisa ("exportar") e não são parentes. A planilha é uma dependência e um loop sobre 17 linhas; o PDF é uma frente de infraestrutura inteira, e ela pertence ao EPIC-007. Pagar o custo de um épico dentro do Checkpoint 3 de outro, por um documento de uma página que o usuário não pediu, seria dívida disfarçada de entrega.

Vale para o DRM: XLSX síncrono, nunca armazenado, lendo o snapshot, com fórmulas nas linhas derivadas.

## Decisão 4 — O PDF é documento da plataforma, não impressão do navegador

O Arquiteto deixou uma pergunta aberta, deliberadamente: aceitar a impressão do navegador **em definitivo** eliminaria o EPIC-007 técnico inteiro, trocando um épico por uma estória.

**Não aceito.** O PDF tem que ser gerado pela plataforma, para garantirmos consistência sem depender do navegador do usuário.

O documento em questão é o que o Roberto leva à reunião com o gerente do banco. Um artefato que muda de paginação, de quebra de página e possivelmente de cor conforme o navegador de quem imprime não é um documento nosso — é um resultado imprevisível com a nossa marca em cima. O farol sair de uma cor na tela e de outra no papel, no relatório que sustenta um pedido de crédito, é um risco que não vale a economia de um épico.

**Consequências:**

- A **Decisão 4 do `ADR-008` vale como está**: Chromium isolado em container (direção Gotenberg, a validar em spike próprio do EPIC-007); `dompdf`, `mPDF` e SaaS de HTML→PDF seguem desqualificados por antecipação, com motivo registrado. O EPIC-007 não começa do zero.
- A camada `@media print` permanece **saída interina, não solução final**. Ela existe para que adiar o PDF não deixe o usuário sem papel — não para substituí-lo.
- O EPIC-007 abre com **spike de motor de PDF** como primeira estória, quando entrar em onda.

---

## Efeitos

- `STORY-046` e `STORY-053` fecham como `done`.
- `DDR-001` a `DDR-005` → `accepted`; as três specs → `ready`; `ADR-008` → `accepted`.
- `STORY-047`, `STORY-049` e `STORY-051` destravadas — o Checkpoint 1 abre.
- O EPIC-007 ganha escopo definido antes de existir: spike de motor + geração no servidor.

## Aberto, e deliberadamente não decidido aqui

Quatro itens escalados pelos agentes seguem sem decisão, por escolha — nenhum bloqueia o Checkpoint 1:

1. `tipo` e `destaque` nas linhas do snapshot do DRM (proposta ao Arquiteto, `STORY-048`).
2. Ajuste dos CAs da `STORY-055` por consequência do `ADR-008`, e onde mora a camada de impressão.
3. As duas dívidas de acessibilidade de produção sugeridas para a `STORY-059`.
4. A leitura literal da invariante 9 do índice.
