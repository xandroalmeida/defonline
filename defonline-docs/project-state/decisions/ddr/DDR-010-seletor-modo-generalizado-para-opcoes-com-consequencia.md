---
id: DDR-010
title: seletor-modo serve a qualquer escolha de duas opções que tenham consequência factual — inclusive o meio de pagamento
status: proposed
created_at: 2026-11-05
decided_at: ~
approved_by: ~
supersedes: ~
superseded_by: ~
related_ddrs: [DDR-002, DDR-008]
related_adrs: []
related_pdrs: [PDR-020]
scope: componentes
affects_screens: [SCREEN-STORY-070-checkout-creditos-cartao, SCREEN-STORY-070-checkout-pix-anual]
---

# DDR-010 — `seletor-modo` generalizado

## Contexto

O DDR-002 criou o `seletor-modo` para duas opções em que **cada opção declara o seu custo em campos**. O checkout de pacote (Pix ou cartão) e o de plano anual (cartão ou Pix) têm a mesma forma: duas opções lado a lado, e cada uma tem uma consequência factual que o usuário precisa ver antes de escolher (cartão anual renova sozinho; Pix anual, não; Pix libera após a confirmação do banco). Criar um segundo controle de duas opções seria o "segundo dialeto" que a STORY-046 proibiu para formulários.

## Opções consideradas

- **A — Rádios comuns empilhados.** Prós: nada novo. Contras: a consequência vira texto pequeno sob o rádio, e o controle parece outro dentro do mesmo produto.
- **B — Reusar a anatomia do `seletor-modo`, trocando a linha "custo" por "consequência" (escolhida).**
- **Status quo.** Não existe checkout.

## Decisão

> **Opção B.**

1. O componente passa a chamar-se `seletor-modo` em código e na documentação (nome mantido) com duas linhas por opção: **rótulo** e **linha factual**. Para o DRM, a linha factual é o custo em campos (inalterado). Para o checkout, é a consequência: `Libera na hora · renova sozinho` / `Libera quando o banco confirmar · você paga a renovação`.
2. As regras do DDR-002 valem: semântica de `radiogroup`, `--color-tertiary` só em borda e marcador, **só duas opções**, rótulos factuais, sem adjetivo de valor ("melhor", "mais rápido").
3. **Nenhuma das opções é pré-selecionada como "recomendada".** O padrão é a primeira da lista (cartão), sem destaque.
4. Quando o catálogo só admite uma opção (plano mensal só cartão; 076 só Pix), o controle **não aparece**: a única forma de pagamento é dita numa linha do resumo.

## Consequências

- Zero componente e zero token novo; o DDR-002 ganha um segundo uso.
- `components.md` §2.3: a regra "o custo é obrigatório e factual" passa a ler "a linha factual (custo ou consequência) é obrigatória".
