---
id: SCREEN-STORY-070-planos-e-precos
story: STORY-070-design-telas-cobranca
implemented_by: STORY-075
epic: EPIC-005-cobranca-e-planos
status: draft
created_at: 2026-11-05
updated_at: 2026-11-05
owner_designer: designer (claude-sonnet-5-5)
related_ddrs: [DDR-009, DDR-010, DDR-008]
ds_components_used: [page-header, card, button, pill, help, aviso-inline, seletor-modo]
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Planos e preços

> Referência: `STORY-070`; implementada por `STORY-075`. Lê o catálogo (planos, pacotes, trial) do banco: nada aqui é literal em código. Os números abaixo são os de seed (PDR-020), apenas ilustrativos. Microcopy provisória.

## 1. Objetivo
Roberto compara **planos, créditos avulsos e teste** em um só lugar e segue para o checkout do item escolhido.

## 2. Fluxo
- Entrada: item `Planos` do menu; botão `Ver planos` de Meu plano, do bloqueio e dos avisos; visitante via link público (leva ao cadastro).
- Ações: alternar ciclo (Mensal/Anual) · `Assinar` (plano) · `Comprar` (pacote) · `Começar teste grátis` · `Entrar para assinar` (visitante).
- Saída: logado → checkout do item (ciclo escolhido já aplicado); visitante → cadastro, retornando ao item escolhido depois.

## 3. Layout

### Mobile (≥360px)
```
+--------------------------------------------------+
| Planos e preços                                  |
| Escolha como quer usar o DEFOnline.              |
|                                                  |
| ┌ Teste grátis (só se ativo e elegível) ──────┐  |
| │ 7 dias, 1 análise e o Raio X do Negócio.    │  |
| │ Sem cartão.   [ Começar teste grátis ]      │  |
| └─────────────────────────────────────────────┘  |
|                                                  |
| Planos                                           |
| ( Mensal ) ( Anual — economize 33% )             |
|                                                  |
| ┌ Básico ─────────────────────────────────────┐  |
| │ R$ 49,90 por mês                            │  |
| │ 1 análise por mês                           │  |
| │ Empresas sem limite                         │  |
| │ Raio X do Negócio incluído                  │  |
| │ [ Assinar ]                                 │  |
| └─────────────────────────────────────────────┘  |
| ┌ Pro ────────────────────────────────────────┐  |
| │ R$ 199,00 por mês                           │  |
| │ 10 análises por mês · Empresas sem limite   │  |
| │ Raio X do Negócio incluído                  │  |
| │ [ Assinar ]                                 │  |
| └─────────────────────────────────────────────┘  |
| Renova sozinho até você cancelar, em Meu plano.  |
|                                                  |
| Créditos avulsos                                 |
| Pague uma vez, use quando quiser. Valem 12       |
| meses. Não incluem o Raio X do Negócio.          |
| ┌ Plus ───────────────────────────────────────┐  |
| │ 5 análises · R$ 349,90                      │  |
| │ R$ 69,98 por análise · válidas por 12 meses │  |
| │ [ Comprar ]                                 │  |
| └─────────────────────────────────────────────┘  |
| (Mini, Max…)                                     |
+--------------------------------------------------+
```

- Ordem: teste → planos → créditos. Plano vem antes do crédito porque é a opção mais barata por análise (PDR-020 item 6). Mas **sem selo de "recomendado"**: o produto não tem adjetivo de valor.
- Ciclo: `seletor-modo` (DDR-010). **Anual mostra o preço por ano e, abaixo, o equivalente mensal e a economia** como texto: `R$ 399,00 por ano · R$ 33,25 por mês · economia de 33%`. Valores derivados no back-end.
- Cada card traz, **no mesmo lugar sempre**: preço, cota, empresas, Raio X do Negócio. O Raio X aparece em plano como linha de benefício e em pacote como **negativa explícita** (`Não inclui o Raio X do Negócio`), e uma frase fixa na seção de créditos (CA-2).
- Pacote mostra quantidade, preço, unitário e validade (CA-2). O unitário é texto, não destaque de desconto.
- O usuário com plano atual vê `Seu plano` no card dele em vez de `Assinar`, e `Trocar para este` nos outros (leva a `troca-de-plano`).

### Desktop (≥1024px)
Planos lado a lado (2 colunas, até 3 se houver mais planos ativos), pacotes em linha de 3 cartões; teste como faixa horizontal no topo. Container 960px. O seletor de ciclo fica acima dos planos, centralizado.

## 4. Estados
- **Feliz:** acima.
- **Loading:** esqueleto de cartões (3 em mobile). Preço nunca aparece como `R$ 0,00` provisório.
- **Vazio (CA-4):** sem planos ativos: `aviso-inline` `info` na seção: `Não há planos à venda neste momento.`; sem pacotes: seção some inteira (não há título solto). Nada ativo: `Voltamos em breve com novas ofertas.` + `Voltar`.
- **Teste:**
  - trial inativo (CA-3): faixa de teste não existe; nenhum espaço reservado.
  - logado e **inelegível**: no lugar da faixa, nada (a mensagem de motivo aparece quando ele tenta ativar, em `trial`), para não cobrar o que ele já usou.
  - visitante: faixa com `Criar conta e começar o teste`.
- **Erro:** falha ao carregar o catálogo: `Não conseguimos carregar os planos agora.` + `Tentar de novo`. Preço nunca é mostrado do cache sem avisar.
- **Alteração no catálogo (CA-4):** sem estado visual próprio; a página sempre lê o catálogo atual. Quem já está no checkout vê o preço do momento em que **abriu** o checkout; se mudar antes de pagar, ver `checkout` §4 (pergunta 1).
- **Visitante (CA-5):** botões dizem `Criar conta para assinar`; levam ao cadastro com o item no retorno.
- **Sem permissão:** "Logar como": cartões visíveis, botões `disabled` com `aviso-inline` `info` (como no checkout).
- **Interruptor desligado (CA-7):** a rota não existe para o usuário comum (404); o item de menu não aparece.

## 5. Microcopy (provisório)
| Lugar | Texto |
|---|---|
| Título | Planos e preços |
| Subtítulo | Escolha como quer usar o DEFOnline. |
| Teste | {dias} dias, {n} análise(s) e o Raio X do Negócio. Sem cartão. |
| CTA teste | Começar teste grátis |
| Ciclo | Mensal / Anual — economize {n}% |
| Plano (linhas) | {n} análise(s) por mês · Empresas sem limite · Raio X do Negócio incluído |
| Preço anual | R$ {a} por ano · R$ {m} por mês · economia de {n}% |
| Rodapé planos | Renova sozinho até você cancelar, em Meu plano. |
| Seção créditos | Créditos avulsos — Pague uma vez, use quando quiser. Valem {n} meses. Não incluem o Raio X do Negócio. |
| Pacote | {n} análise(s) · R$ {preço} · R$ {unit} por análise · válidas por {n} meses |
| CTAs | Assinar · Comprar · Seu plano · Trocar para este · Criar conta para assinar |
| Erro | Não conseguimos carregar os planos agora. |
| Vazio | Não há planos à venda neste momento. |

## 6. Acessibilidade
Um `<h1>`; `Planos` e `Créditos avulsos` em `<h2>`; cada oferta é `<article>` com `aria-labelledby`; botões têm nome completo (`Assinar plano Básico mensal`). `seletor-modo` com `<legend>` `Ciclo de cobrança`; mudar o ciclo atualiza preços com `aria-live="polite"`. Preço lido por inteiro (`cinquenta e nove reais e noventa...` fica a cargo do leitor; o texto é `R$ 49,90`).

## 7. Identificadores E2E
`screen-planos-teste` · `screen-planos-ciclo` · `screen-planos-plano-{id}` · `-assinar-{id}` · `screen-planos-pacote-{id}` · `-comprar-{id}` · `screen-planos-vazio` · `screen-planos-erro`.

## 8. Exceções ao DS
Nenhuma.

## 9. Dependências e perguntas
- Benefícios derivam de atributos do catálogo (cota, empresas, inclui Raio X). **Pergunta ao PO/STORY-071:** é preciso um campo de **texto livre de benefícios** por plano? Padrão: não — só os três atributos, para a tela nunca prometer o que o código não entrega.
- **Pergunta 1:** se o preço muda enquanto o usuário está no checkout — padrão: o resumo atualiza, `aviso-inline` `alerta` `O preço mudou desde que você abriu esta tela. Confira o novo valor.` e o botão pede novo clique.
- A tela pública para visitante pode coexistir com o hotsite (EPIC-009, fora de escopo); esta é a versão do app.

## 10. Histórico
| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-11-05 | criação | designer (claude-sonnet-5-5) | STORY-070 |
