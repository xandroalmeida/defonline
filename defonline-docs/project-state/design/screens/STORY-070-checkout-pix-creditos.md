---
id: SCREEN-STORY-070-checkout-pix-creditos
story: STORY-070-design-telas-cobranca
implemented_by: STORY-076
epic: EPIC-005-cobranca-e-planos
status: draft
created_at: 2026-11-05
updated_at: 2026-11-05
owner_designer: designer (claude-sonnet-5-5)
related_ddrs: [DDR-008, DDR-009, DDR-001, DDR-005]
ds_components_used: [page-header, card, button, label, pill, help, breadcrumb, aviso-inline, resumo-pedido]
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Checkout de pacote de créditos com Pix (base de todos os checkouts)

> Referência: estória `STORY-070-design-telas-cobranca`; implementada por `STORY-076`. **Esta spec define a estrutura de checkout que as outras reaproveitam** (`checkout-assinatura`, `checkout-creditos-cartao`, `checkout-pix-anual`, `cupom-no-checkout`, `troca-de-plano`): o que está aqui como "estrutura comum" não se repete lá.
> Decisões que valem além desta tela: **DDR-008** (resumo do pedido) e **DDR-009** (situação e avisos). Este spec **aplica**.
> **Microcopy provisória** até revisão do PO.
> Decisões de produto vêm do PDR-020 e da spec funcional §2.2, §3.4 e §4.3. O Pix de pacote é a primeira cobrança real; o cartão chega na STORY-078 (sem mudar esta estrutura).

## 1. Objetivo da tela

Fazer Roberto confirmar **o que compra, quanto paga e o que recebe**, aceitar o Termo e pagar com Pix, sem dúvida sobre o que acontece depois.

São três telas em sequência: **Revisão** (checkout), **Pagar com Pix** (QR Code) e **Confirmação**.

## 2. Fluxo

### Entrada
- De `Planos e preços` (botão `Comprar` num pacote) ou do bloqueio da análise.
- Pré-condições: sessão autenticada; interruptor de cobrança ligado; pacote ativo no catálogo; e-mail confirmado; não está em "Logar como".

### Ações possíveis
- **Revisão:** primária `Gerar QR Code Pix`; secundária `Voltar aos planos`; link `Termo de Adesão` e `Política de Privacidade` (nova aba).
- **Pagar com Pix:** primária `Copiar código Pix` (a ação do dia a dia no celular); não há botão "Já paguei": a tela se atualiza sozinha; `Cancelar compra` (ghost) volta para Planos.
- **Confirmação:** primária `Fazer uma análise`; secundária `Ver meu plano`.

### Saída
- Sucesso: Confirmação. Créditos na carteira com validade do pacote na data da compra.
- Cancelamento: Planos e preços; nenhuma cobrança fica pendente além do QR já gerado (que expira).
- Erro recuperável: permanece na tela, com a ação para tentar de novo (§4.4).

## 3. Layout

### 3.1 Revisão — mobile (≥360px)

```
+--------------------------------------------------+
| ‹ Planos e preços / Comprar créditos             |
+--------------------------------------------------+
| Comprar créditos                                 |
| Pacote Plus · 5 análises                         |
|                                                  |
| ┌ Resumo do pedido ────────────────────────────┐ |
| │ Preço                          R$ 349,90      │ |
| │ Desconto                       Sem desconto   │ |
| │ ──────────────────────────────────────────── │ |
| │ A pagar hoje                   R$ 349,90      │ |
| │ ──────────────────────────────────────────── │ |
| │ Ciclo               Compra única, não renova  │ |
| │ Próxima cobrança    Não há                    │ |
| │ Depois              Não há                    │ |
| │ Validade dos créditos   até 05/11/2027        │ |
| └───────────────────────────────────────────────┘ |
|                                                  |
| ⓘ Os créditos não incluem o Raio X do Negócio.   |
|   Ele faz parte dos planos e do teste.           |
|                                                  |
| Tem cupom?  [ Adicionar cupom ]   (STORY-083)    |
|                                                  |
| Pagamento                                        |
| Pix · o QR Code vale por 30 minutos.             |
|                                                  |
| ☐ Li e aceito o Termo de Adesão (v2) e a         |
|   Política de Privacidade.                       |
|                                                  |
| [        Gerar QR Code Pix · R$ 349,90        ]  |
| Reembolso em até 7 dias se você ainda não tiver  |
| feito nenhuma análise.                           |
+--------------------------------------------------+
```

- Ordem: o que compra → quanto → cupom → como paga → aceite → botão. **O botão é o último elemento e traz o valor.**
- `Termo de Adesão` e `Política de Privacidade` são links reais (nova aba, `rel="noopener"`), alvo ≥44px.
- O aviso sobre o Raio X é `aviso-inline` `info` e só aparece em pacote (CA-2 da STORY-075 vale também aqui: a informação reaparece antes de pagar).
- A linha do reembolso é texto de apoio (`--text-body-sm`, `--color-secondary`), visível sem rolagem adicional após o botão.

### 3.2 Revisão — desktop (≥1024px)

```
+---------+--------------------------------------------------------------+
| nav     | ‹ Planos e preços / Comprar créditos                         |
| lateral | Comprar créditos                                             |
|         +------------------------------+-------------------------------+
|         | 1. Pagamento                 | Resumo do pedido              |
|         | Pix · QR Code vale 30 min.   | Pacote Plus · 5 análises      |
|         |                              | Preço            R$ 349,90    |
|         | ☐ Li e aceito o Termo…       | Desconto    Sem desconto      |
|         |                              | A pagar hoje     R$ 349,90    |
|         | [ Gerar QR Code Pix · R$ … ] | Ciclo     Compra única        |
|         |                              | Próxima cobrança  Não há      |
|         | Reembolso em até 7 dias…     | Depois            Não há      |
|         |                              | Validade   até 05/11/2027     |
+---------+------------------------------+-------------------------------+
```

- Duas colunas (~60/40): ação à esquerda, **resumo fixo à direita** (`position: sticky`), para o valor ficar à vista enquanto o usuário lê o aceite. Container `container--mid` (640px) **não** serve aqui: o checkout usa o container padrão de 960px.
- Em mobile o resumo vem **antes** do formulário (a pergunta "quanto?" precede "como?"); em desktop fica ao lado.

### 3.3 Pagar com Pix — mobile

```
+--------------------------------------------------+
| Pague com Pix                                    |
| Pacote Plus · R$ 349,90                          |
|                                                  |
|            ┌────────────────┐                    |
|            │  ▓▓ QR CODE ▓▓ │   (220×220 mín.)   |
|            └────────────────┘                    |
|                                                  |
| Ou copie o código e cole no app do seu banco     |
| [ 00020126580014br.gov.bcb.pix0136…      ]       |
| [          Copiar código Pix             ]       |
|                                                  |
| Este código vale por 30 minutos.                 |
| Expira às 15:42  ·  restam 29 min                |
|                                                  |
| ⓘ Aguardando o pagamento…                        |
|   Esta tela se atualiza sozinha quando o banco   |
|   confirmar. Também enviamos o QR Code para      |
|   maria@empresa.com.br.                          |
|                                                  |
| [ Cancelar compra ]                              |
+--------------------------------------------------+
```

- Em mobile o botão `Copiar código Pix` é o primário (quem está no celular não escaneia o QR da própria tela). Em desktop o QR vem primeiro e o código copiável abaixo, em segundo plano.
- Mostra **a hora de expiração e os minutos restantes**, não um relógio de segundos. O texto de minutos atualiza a cada minuto; um relógio em segundos faria o leitor de tela anunciar sem parar e criaria urgência fabricada.
- `Aguardando o pagamento…` é `aviso-inline` `info` com `role="status"`.
- Ao copiar: o botão muda para `Código copiado` por 3 s (texto, não só ícone) e `aria-live="polite"` anuncia.

### 3.4 Pagar com Pix — desktop

Duas colunas: QR Code à esquerda; à direita título, valor, código copiável, hora de expiração e o aviso de e-mail. O resumo do pedido fica recolhido numa linha acima (`Pacote Plus · R$ 349,90`).

### 3.5 Confirmação

```
+--------------------------------------------------+
| Pagamento confirmado                             |
| Recebemos o Pix de R$ 349,90.                    |
|                                                  |
| Você tem 5 créditos, válidos até 05/11/2027.     |
| Nota fiscal: enviaremos por e-mail.              |
|                                                  |
| [        Fazer uma análise        ]              |
| [ Ver meu plano ]                                |
+--------------------------------------------------+
```

- A confirmação é uma **tela**, não toast: o usuário precisa do recibo à vista. Foco vai para o título (`tabindex="-1"`).
- Se o usuário veio do bloqueio da análise (rascunho de quiz salvo), o primário passa a `Continuar o diagnóstico` e leva ao rascunho.

## 4. Estados

### 4.1 Caminho feliz
Revisão → aceite marcado → `Gerar QR Code Pix` → tela Pix → webhook → Confirmação. A tela Pix atualiza para a Confirmação sem recarregar (Livewire polling, decisão do Programador; o requisito é "atualiza sozinha").

### 4.2 Loading
- Revisão: esqueleto do resumo (4 linhas) enquanto o catálogo carrega.
- `Gerar QR Code Pix` → botão `loading` (`Gerando QR Code…`), desabilitado. Se passar de 8 s, aparece abaixo `Está demorando mais que o normal. Não precisa clicar de novo.`
- Tela Pix: o QR é um bloco com esqueleto 220×220 até a imagem chegar.

### 4.3 Vazio
- Pacote inexistente ou desativado entre a página de planos e o checkout: `aviso-inline` `bloqueio` `Este pacote não está mais disponível.` + `Ver os pacotes disponíveis`. Não há formulário.
- Interruptor desligado: a rota se comporta como inexistente (404 padrão); nada de "cobrança desligada" ao usuário comum.

### 4.4 Erro
- **Aceite não marcado ao clicar:** o botão não fica desabilitado de início (botão cinza sem explicação é pior); ao clicar, erro sob a caixa: `Marque o aceite do Termo para continuar.` com foco na caixa.
- **E-mail não confirmado (CA-1 da STORY-076):** `aviso-inline` `bloqueio` no topo, sem formulário: `Confirme seu e-mail para comprar.` / `Enviamos um link para maria@…. Depois de confirmar, volte aqui.` Ações: `Reenviar e-mail de confirmação` (mesma página), `Voltar aos planos`.
- **Falha do provedor ao gerar o Pix:** `aviso-inline` `alerta` no lugar do QR: `Não conseguimos gerar o QR Code agora. Nada foi cobrado.` + `Tentar de novo`. Se persistir: `Se continuar assim, escreva para suporte@defonline.com.br.` [contato = pergunta 3]
- **Pix expirado:** a tela troca o QR por `aviso-inline` `info`: `O QR Code expirou e nada foi cobrado.` / `Gere outro para pagar.` Ação `Gerar novo QR Code` (leva a Revisão com aceite já marcado). O código antigo deixa de ser mostrado.
- **Pagamento confirmado depois da expiração (borda):** se o banco confirmar um Pix que já mostramos como expirado, vale a confirmação do banco; a tela vai para Confirmação. Não mostrar "expirado" e "pago" juntos.
- **Erro de rede ao atualizar:** o polling continua; após 3 falhas seguidas, `aviso-inline` `info` `Não estamos conseguindo checar o pagamento. Se você já pagou, os créditos entram assim que a conexão voltar.` O usuário nunca vê "falhou" para um pagamento que pode ter acontecido.

### 4.5 Sem permissão
- **"Logar como":** o formulário aparece, mas o botão primário fica desabilitado e `aviso-inline` `info` explica: `Você está acessando como administrador. Contratar e pagar não está disponível nesse modo.` (CA-7 da 076). A faixa persistente `personificacao-aviso` continua no rodapé.

### 4.6 Parcial / degradado
- Provedor indisponível: ver erro de geração. Nenhum estado parcial de cobrança aparece ao usuário.

### 4.7 Primeira vez
Primeira compra: nenhuma diferença; a Confirmação cita a validade e a nota fiscal, que são as dúvidas da primeira compra.

## 5. Microcopy completo (provisório)

| Lugar | Texto |
|---|---|
| Título (revisão) | Comprar créditos |
| Subtítulo | Pacote {nome} · {n} análises |
| Resumo | (DDR-008) Preço · Desconto · A pagar hoje · Ciclo · Próxima cobrança · Depois · Validade dos créditos |
| Ciclo | Compra única, não renova |
| Próxima cobrança / Depois | Não há |
| Aviso Raio X | Os créditos não incluem o Raio X do Negócio. Ele faz parte dos planos e do teste. |
| Pagamento | Pix · o QR Code vale por 30 minutos. |
| Aceite | Li e aceito o <a>Termo de Adesão</a> (v2) e a <a>Política de Privacidade</a>. |
| Apoio ao botão | Reembolso em até 7 dias, se você ainda não tiver feito nenhuma análise. |
| CTA | Gerar QR Code Pix · R$ {valor} |
| CTA loading | Gerando QR Code… |
| Título Pix | Pague com Pix |
| Instrução | Escaneie o QR Code ou copie o código e cole no app do seu banco. |
| Copiar | Copiar código Pix / Código copiado |
| Expiração | Este código vale por 30 minutos. Expira às {hh:mm} · restam {n} min |
| Aguardando | Aguardando o pagamento… Esta tela se atualiza sozinha quando o banco confirmar. Também enviamos o QR Code para {email}. |
| Cancelar | Cancelar compra |
| Sucesso (título) | Pagamento confirmado |
| Sucesso (corpo) | Recebemos o Pix de R$ {valor}. Você tem {n} créditos, válidos até {data}. Enviaremos a nota fiscal por e-mail. |
| CTA sucesso | Fazer uma análise / Continuar o diagnóstico |
| Secundário sucesso | Ver meu plano |
| Aceite faltando | Marque o aceite do Termo para continuar. |
| E-mail não confirmado | Confirme seu e-mail para comprar. |
| Falha do provedor | Não conseguimos gerar o QR Code agora. Nada foi cobrado. |
| Expirado | O QR Code expirou e nada foi cobrado. Gere outro para pagar. |
| Admin | Você está acessando como administrador. Contratar e pagar não está disponível nesse modo. |

## 6. Acessibilidade

- Ordem de tab: resumo (não focável) → aceite → links dos documentos → botão. Foco inicial no `<h1>` ao abrir cada uma das três telas.
- O QR Code tem `alt` = `QR Code Pix de R$ 349,90`; o **código copiável é o equivalente textual**, e é por isso que ele existe também no desktop.
- Expiração: `role="timer"` **sem** `aria-live` por segundo; texto de minutos em `aria-live="polite"` e `aria-atomic`, atualizado por minuto.
- Confirmação do pagamento anunciada por `role="status"`; a mudança de tela leva o foco ao título.
- Erros do aceite via `aria-describedby`; resumo de erro no topo se houver mais de um.
- Contraste: tokens aprovados em `tokens.md` (`primary`/`surface`, `secondary`/`surface`, `tertiary` só sobre `surface`, `destructive` só sobre `surface`). O botão primário `on-primary` sobre `tertiary` (4,70:1). ✅
- Alvos ≥44×44px: botões, links dos documentos, checkbox com área de toque no rótulo inteiro.

## 7. Identificadores estáveis sugeridos para E2E

| Elemento | Identificador lógico |
|---|---|
| Resumo | `screen-checkout-resumo` (e uma linha por item: `-preco`, `-desconto`, `-a-pagar`, `-ciclo`, `-proxima`, `-depois`) |
| Aceite | `screen-checkout-aceite` / erro `screen-checkout-error-aceite` |
| CTA | `screen-checkout-primary-cta` |
| QR Code | `screen-checkout-pix-qr` |
| Código copiável | `screen-checkout-pix-codigo` / botão `screen-checkout-pix-copiar` |
| Expiração | `screen-checkout-pix-expira` |
| Novo QR | `screen-checkout-pix-novo` |
| Confirmação | `screen-checkout-confirmacao` |
| Bloqueio de e-mail | `screen-checkout-email-nao-confirmado` |
| Aviso de admin | `screen-checkout-admin` |

## 8. Exceções ao Design System

| O que diverge | Por quê | Vai virar DDR? |
|---|---|---|
| Componente novo `resumo-pedido` | Nenhum componente existente apresenta pares rótulo/valor com linha dominante e seis linhas fixas | Sim — DDR-008 |
| Container de 960px no checkout em vez de `container--mid` | Duas colunas só cabem em 960px | Não |

## 9. Dependências e premissas

- Valor, datas, validade e desconto vêm calculados do back-end (Domain); a view só apresenta.
- `Setting.pix_expiracao_minutos` (padrão 30) alimenta o texto; o "30 minutos" da microcopy é variável, não literal.
- A faixa de "Logar como" já existe (`personificacao-aviso`).
- **Perguntas ao PO (nenhuma bloqueia o desenho, todas têm padrão adotado):**
  1. **Nota fiscal/CPF-CNPJ no checkout.** Padrão adotado: não coletar nada novo; usar os dados do cadastro e dizer "Enviaremos a nota fiscal por e-mail". Se o Asaas exigir CPF/CNPJ do pagador, a tela mostra os dados do cadastro, só leitura, com `Alterar` em Meu plano. Confirma?
  2. **Valor mínimo por cobrança (ADR-010 CA-4):** a regra ainda não existe; o estado de "valor abaixo do mínimo" (cupom parcial deixando o total pequeno) está em `cupom-no-checkout` §4.4 com texto provisório.
  3. **E-mail de suporte:** `suporte@defonline.com.br` é suposição; trocar pelo canal oficial.

## 10. Histórico de mudanças

| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-11-05 | criação | designer (claude-sonnet-5-5) | STORY-070 |
