---
id: SCREEN-STORY-070-meu-plano
story: STORY-070-design-telas-cobranca
implemented_by: STORY-081
epic: EPIC-005-cobranca-e-planos
status: ready
created_at: 2026-11-05
updated_at: 2026-11-05
owner_designer: designer (claude-sonnet-5-5)
related_ddrs: [DDR-009, DDR-008, DDR-005]
ds_components_used: [page-header, card, button, pill, help, breadcrumb, aviso-inline, resumo-pedido]
exceptions_to_ds: [confirmacao (existente em código, ainda fora de components.md)]
viewports: [mobile, desktop]
---

# Spec de tela — Meu plano: situação, cota, créditos, cobranças, cartão e cancelamento

> Referência: `STORY-070`; implementada por `STORY-081`. Os estados de plano são os de **DDR-009**. Troca de plano: `SCREEN-STORY-070-troca-de-plano`. Microcopy provisória.

## 1. Objetivo
Responder, sem sair da tela: **o que tenho, quanto me resta, quando renova, quanto paguei — e como cancelo.**

## 2. Fluxo
- Entrada: item `Meu plano` do menu lateral; links de e-mails de cobrança; bloqueio da análise; avisos.
- Ações: `Trocar plano`, `Trocar cartão`, `Cancelar renovação` / `Reativar renovação`, `Comprar créditos`, `Ver cobranças`.
- Menu: dois itens novos, visíveis só com o interruptor ligado: `Meu plano` e `Planos`. Em mobile ficam no drawer.

## 3. Layout

### Mobile (≥360px), plano ativo
```
+--------------------------------------------------+
| Meu plano                                        |
|                                                  |
| ⓘ [aviso de cobrança, se houver — um só]         |
|                                                  |
| ┌ Plano ──────────────────────────────────────┐  |
| │ Básico · mensal                  [Ativo]    │  |
| │ R$ 49,90 por mês · renova em 05/12/2026     │  |
| │ Cartão final 4242                           │  |
| │ [ Trocar plano ] [ Trocar cartão ]          │  |
| │ [ Cancelar renovação ]                      │  |
| └─────────────────────────────────────────────┘  |
|                                                  |
| ┌ Análises ───────────────────────────────────┐  |
| │ Do plano      1 de 1 restante neste ciclo   │  |
| │ Renova em 05/12/2026 (não acumula)          │  |
| │ Créditos      3 no total                    │  |
| │  · 2 — vencem em 20/12/2026   ⓘ perto       │  |
| │  · 1 — vencem em 05/11/2027                 │  |
| │ [ Comprar créditos ]                        │  |
| │ Raio X do Negócio: incluído no plano.       │  |
| └─────────────────────────────────────────────┘  |
|                                                  |
| ┌ Cobranças ──────────────────────────────────┐  |
| │ 05/11/2026  Plano Básico mensal             │  |
| │ R$ 24,95 (desconto R$ 24,95) · Cartão · Pago│  |
| │ …                                           │  |
| └─────────────────────────────────────────────┘  |
|                                                  |
| Arrependimento: em até 7 dias da contratação, se |
| você ainda não fez nenhuma análise, peça o       |
| reembolso em {canal}.                            |
+--------------------------------------------------+
```

- **Cancelar fica no cartão do plano, na primeira dobra**, como `button ghost` com texto literal `Cancelar renovação` (DDR-009 item 6). Nunca atrás de menu.
- Três cartões, nesta ordem: Plano (a pergunta "o que tenho"), Análises (o que resta), Cobranças (o que paguei). Mobile empilha; cobranças viram **lista de cartões**, uma por cobrança (data, descrição, valor líquido, desconto, meio, status em `pill`).
- Valor de lote perto de vencer: o limiar de "perto" é configuração (sugestão: 30 dias); o texto diz a data, `ⓘ` é complemento.
- Pill de status da cobrança: `Pago`, `Pendente`, `Recusado`, `Reembolsado`. Neutros, sem token de farol.

### Desktop (≥1024px)
```
| Meu plano                                                          |
| ┌ Plano ──────────────────────┐ ┌ Análises ────────────────────┐  |
| │ …                           │ │ …                            │  |
| └─────────────────────────────┘ └──────────────────────────────┘  |
| ┌ Cobranças (tabela) ──────────────────────────────────────────┐  |
| │ Data │ Descrição │ Valor │ Desconto │ Meio │ Status          │  |
| └──────────────────────────────────────────────────────────────┘  |
```
Plano e Análises lado a lado; Cobranças em tabela de largura total (padrão tabela ≥md / cartão <md).

## 4. Estados (situações, DDR-009)

### 4.1 Ativo — acima.

### 4.2 Renovação cancelada (CA-4, CA-5)
```
| Básico · mensal                  [Cancelado]     |
| Você cancelou a renovação. Seu acesso continua   |
| até 05/12/2026. Não haverá nova cobrança.        |
| [ Reativar renovação ]                           |
```
- A ação principal do cartão vira `Reativar renovação` (CA-5). `Trocar plano` some (não há ciclo futuro a trocar; reativar primeiro).
- Fluxo do cancelamento: `Cancelar renovação` abre o diálogo `confirmacao` existente: título `Cancelar a renovação?`; mensagem `Você continua com acesso até 05/12/2026 e não será cobrado de novo. Dá para reativar até lá.`; botões `Voltar` (foco inicial) e `Cancelar renovação`. Confirmar → estado Cancelado + `aviso-inline` `info` `Renovação cancelada. Seu acesso continua até 05/12/2026.`
- Não há pesquisa de motivo obrigatória nem tela de "tem certeza?" em cascata: um passo, um diálogo.

### 4.3 Em atraso (CA-3)
`aviso-inline` `alerta` no topo: `Não conseguimos cobrar a renovação de 05/12/2026. Atualize o cartão para manter o acesso.` Ação `Trocar cartão` (primária). Pill `Em atraso`. Informa o ritmo das tentativas: `Tentamos de novo em 07/12/2026.`; quando a última acontecer: `Última tentativa em {data}. Depois dela, o plano é encerrado.` Ao salvar um cartão válido, a cobrança é feita na hora e, aprovada, volta a `Ativo` com `Pagamento recebido. Seu plano está ativo.`; recusada: erro do cartão (como `checkout-assinatura` §4.4).

### 4.4 Trocar cartão
Seção expandida inline (não nova página): campos de cartão iguais ao checkout, botão `Salvar cartão` (ou `Salvar e cobrar agora` em atraso). Dados de cartão não são exibidos depois; só `Cartão final 4242 (Visa)`.

### 4.5 Encerrado
Pill `Encerrado`; `Seu plano terminou em {data}. Seus diagnósticos continuam disponíveis.` + `Assinar um plano`. Histórico de cobranças preservado.

### 4.6 Vazio / sem plano e sem créditos (CA-7)
Não é uma tela vazia: é a **oferta**. Título `Você ainda não tem um plano`; abaixo, os cartões de plano e de pacote de `planos-e-precos` em versão compacta, e a oferta de teste se elegível. Cobranças: `Você ainda não fez nenhuma compra.`

### 4.7 Só créditos (sem plano)
Cartão Plano: `Sem plano. Você usa créditos avulsos.` + `Ver planos`; a nota `O Raio X do Negócio não está incluído nos créditos.` com `Assinar para ter o Raio X`.

### 4.8 Teste
Cartão Plano: `Teste gratuito [Teste]` / `Termina em 11/11/2026 · 1 análise` + `Assinar um plano`. Cancelar não existe (nada a cancelar).

### 4.9 Plano anual por Pix
`Este plano não renova sozinho. Termina em {data}.` + `Pagar renovação` a partir de 7 dias antes. Sem botão de cancelar (ver pergunta 1).

### 4.10 Downgrade agendado
`aviso-inline` `info` no cartão do plano: `Em 05/12/2026 seu plano passa para Básico (R$ 49,90 por mês).` + `Desfazer` (ver `troca-de-plano`).

### 4.11 Carregando
Esqueleto dos três cartões. Parcial: se **só as cobranças** falharem, o cartão Cobranças diz `Não conseguimos carregar as cobranças agora.` + `Tentar de novo`; plano e análises aparecem normalmente.

### 4.12 Erro
- Falha ao cancelar: `Não conseguimos cancelar agora. Sua renovação continua ativa. Tente de novo.` (diz o estado real).
- Falha ao trocar cartão: como na recusa.
- Sem rede: padrão do produto.

### 4.13 Sem permissão ("Logar como", CA-8)
A tela renderiza completa; **todas** as ações ficam `disabled`, com `aviso-inline` `info` no topo: `Você está acessando como administrador. Aqui é só leitura.`

### 4.14 Arrependimento (CA-6)
Bloco de texto fixo no final **e** dentro do diálogo de cancelamento quando o plano tem ≤7 dias e nenhuma análise: `Você contratou há {n} dias e ainda não fez nenhuma análise: dá para pedir o reembolso integral em {canal}.` Fora dessa condição o bloco geral aparece só como texto de apoio. [canal = pergunta 2]

## 5. Microcopy (provisório)
| Lugar | Texto |
|---|---|
| Título | Meu plano |
| Cartões | Plano · Análises · Cobranças |
| Cota | {n} de {total} restante(s) neste ciclo · Renova em {data} (não acumula) |
| Créditos | Créditos · {n} no total · {n} vencem em {data} |
| Lote esgotado | Lote esgotado em {data} |
| Lote vencido | Venceu em {data} |
| DRM incluído | O Raio X do Negócio está incluído no plano. |
| Ações | Trocar plano · Trocar cartão · Cancelar renovação · Reativar renovação · Comprar créditos |
| Diálogo | Cancelar a renovação? / Você continua com acesso até {data} e não será cobrado de novo. Dá para reativar até lá. / Voltar · Cancelar renovação |
| Sucesso | Renovação cancelada. Seu acesso continua até {data}. / Renovação reativada. Próxima cobrança em {data}. |
| Colunas | Data · Descrição · Valor · Desconto · Meio · Status |
| Status | Pago · Pendente · Recusado · Reembolsado |
| Arrependimento | Em até 7 dias da contratação, se você ainda não fez nenhuma análise, peça o reembolso em {canal}. |
| Admin | Você está acessando como administrador. Aqui é só leitura. |

## 6. Acessibilidade
- H1 `Meu plano`; cada cartão é `<section>` com `aria-labelledby`. Tabela de cobranças com `<caption>` e `<th scope>`; em mobile, `<dl>` por cobrança.
- Diálogo `confirmacao`: já com foco inicial em Cancelar e `Esc`; título anunciado.
- Situação nunca só por cor: pill com texto e frase completa.
- Resultado de cancelar/reativar em `aria-live="polite"`; foco volta ao botão que mudou de rótulo.
- Alvos ≥44px (inclusive links de e-mail/ajuda).

## 7. Identificadores E2E
`screen-meuplano-plano` · `-situacao` · `-cancelar` · `-reativar` · `-confirmar-cancelamento` · `-acesso-ate` · `-trocar-cartao` · `-cota` · `-lotes` · `-cobrancas` · `-aviso` · `-arrependimento`.

## 8. Exceções ao DS
| O que diverge | Por quê | Vai virar DDR? |
|---|---|---|
| Usa `confirmacao` (diálogo nativo já em produção, fora de `components.md`) | Já existe e atende | Não: registrar em `components.md` na aceitação do DDR-009 |

## 9. Dependências e perguntas
- Dados: carteira (cota, lotes), assinatura e cobranças da STORY-073/077/079. Limiar de "perto de vencer" é configuração.
- **Perguntas ao PO:** (1) plano anual por Pix sem botão de cancelar (ver `checkout-pix-anual`); (2) **canal do pedido de arrependimento** (padrão provisório: e-mail para o suporte; um botão `Pedir reembolso` seria nova funcionalidade, não prevista nos CAs); (3) o rótulo `Cancelar renovação` em vez de `Cancelar plano` segue a spec §2.4 (cancela a renovação, não o acesso).

## 10. Histórico
| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-11-05 | criação | designer (claude-sonnet-5-5) | STORY-070 |
