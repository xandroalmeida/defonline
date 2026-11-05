---
story_id: STORY-076
slug: compra-de-creditos-com-pix
title: "Compra de créditos com Pix — primeira cobrança real"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: claude-sonnet-5-5
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: L
---

# STORY-076 — Compra de créditos com Pix — primeira cobrança real

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

É a menor fatia que atravessa todo o caminho do dinheiro: checkout, provedor, webhook e carteira. Por não ter recorrência, valida a integração com menor risco.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.2 e §3.4
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §3.2
  - ADR-010
  - Spec de tela da STORY-070

## O quê

Permitir que o usuário compre um pacote com Pix e receba os créditos após a confirmação do provedor.

## Por quê

Primeiro momento em que o produto recebe dinheiro, e a comprovação da abstração do ADR-010.

## Critérios de aceite

- [ ] **CA-1:** O checkout exige e-mail confirmado e o aceite da versão vigente do Termo de Adesão, registrado com data, IP e hash em `term_acceptances`.
- [ ] **CA-2:** Dado o aceite, o sistema gera um QR Code Pix com a expiração configurada (padrão de 30 minutos) e envia por e-mail, caso o usuário feche a tela.
- [ ] **CA-3:** Quando o provedor confirma o pagamento, a tela é atualizada e os créditos entram na carteira com a validade do pacote no momento da compra.
- [ ] **CA-4:** O mesmo webhook recebido duas vezes credita uma única vez. Webhook sem autenticação válida é recusado.
- [ ] **CA-5:** Pix expirado não credita, e a tela permite gerar outro.
- [ ] **CA-6:** Falha do provedor exibe mensagem clara, não gera crédito e é registrada para alerta. Toda cobrança fica registrada com status e identificador externo.
- [ ] **CA-7:** Durante o "Logar como", o admin não consegue contratar nem pagar.
- [ ] **CA-8:** A suíte roda com o adaptador de teste, e existe um teste contra o sandbox do Asaas que pode ser executado sob demanda.
- [ ] **CA-9:** Teste arquitetural impede referência ao Asaas fora do adaptador.
- [ ] **CA-10:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Ajustes do ADR-010 (prevalecem sobre os CAs acima onde houver conflito)

O ADR-010 está `proposed`. O orquestrador adotou as recomendações dele em 2026-11-05 para não parar o trabalho, e o PO ainda vai revisá-las.

- O pagamento ocorre no Checkout hospedado do Asaas (`DETACHED`, Pix, `minutesToExpire` igual à configuração). O usuário é redirecionado após o aceite do Termo. O e-mail do CA-2 leva ao link do checkout, não a um QR Code gerado por nós.
- A tela de retorno aguarda o evento `cobranca.paga`: o retorno por si só nunca libera crédito.
- A interface `PaymentProvider`, o evento canônico, a tabela de eventos e o adaptador Fake nascem nesta estória, no namespace `App\Domain\Cobranca`, não `Billing`.
- Cada CA sobre webhook é testado com o Fake e com requisição HTTP assinada no formato do Asaas. O teste contra o sandbox é opcional e não entra na suíte.

## Fora de escopo

- Cartão (STORY-077 e STORY-078).
- Cupom (STORY-083).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-069, STORY-070, STORY-073, STORY-075
- **Bloqueia:** STORY-078, STORY-080, STORY-083, STORY-085, STORY-086, STORY-087

## Decisões já tomadas (não as reabra)

- PDR-020: catálogo em tela, trial, Asaas substituível, Termos `v2`, DRM em planos pagos e no trial, e cupons.

## Liberdade técnica

Você decide estrutura de código, tabelas, bibliotecas dentro dos ADRs e desenho dos testes. Não decide critérios de aceite nem padrões de qualidade. Se faltar uma decisão arquitetural, pare e registre em "Notas do agente".

## Definição de Pronto

- [ ] Todos os critérios de aceite têm teste e passam.
- [ ] `scripts/verificar.sh` verde.
- [ ] Commits em `main` local; push, PR e tag só com pedido do PO.
- [ ] IDR registrado se houve decisão técnica com impacto futuro.
- [ ] `index.json` e "Notas do agente" atualizados; status `in_review`.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Ao iniciar, atualize status e `owner_agent` aqui e no `index.json`. Se travar, use `status: blocked` e descreva o bloqueio.

## Notas do agente

### Decisões tomadas
- Leitura: estória, ADR-010, spec STORY-070-checkout-pix-creditos, skill do programador. A spec descreve QR na nossa tela; pelo ADR-010 (que prevalece) o pagamento é no checkout hospedado do Asaas: a tela "Pagar com Pix" virou a tela de retorno/aguardando (link para abrir o QR, expiração, aviso, cancelar), e o e-mail leva ao link do checkout.
- Entrada: `GET /creditos/comprar/{pacote?}` (`creditos.comprar`, rota já citada por `TextoBloqueio`). Sem pacote lista os ativos (sem depender da STORY-075). Retorno: `/creditos/pagamentos/{invoice}` (`creditos.retorno`, polling de 3 s). Webhook: `POST /webhooks/pagamentos`.
- `ensureCustomer` não foi implementado (o checkout hospedado coleta os dados do pagador; `customerData` exige endereço completo, verificado no sandbox). `Asaas` não recebe CPF/e-mail nossos.
- Cupom (STORY-083) e cartão (077/078) ficam fora; "Adicionar cupom" não aparece.
- Conciliação com o provedor (jobs horário/diário do ADR-010) NÃO foi feita: só o reprocessamento de eventos gravados (`cobranca:reprocessar-eventos`). Fica para quem precisar (087).
- Estorno/contestação: eventos são gravados e ignorados; não há reversão de crédito nesta estória.
- Alertas: falha do provedor, valor divergente e pagamento sem compra viram `Log::error` com `module=cobranca` (sem Telegram dedicado).
- Registrada IDR-018.

### Descobertas
- Sandbox real: `POST /checkouts` devolve `link` e aceita/devolve `externalReference`; `customerData` exige endereço completo (rejeitado sem ele); `POST /checkouts/{id}/cancel` funciona. O teste `tests/Sandbox` abriu e cancelou um checkout de verdade.
- O banco local roda em America/Sao_Paulo e a app em UTC: datas sem offset deslocam 3 h. Corrigido em `Invoice`, `ProviderEvent` e `LoteCredito` (IDR-018). `Assinatura`, `Cupom*` etc. continuam com o risco (só no banco local).
- `docker compose` imprime, como aviso, um trecho da chave do sandbox: `app/.env` (ou o `.env` lido pelo compose) tem `ASAAS_SANDBOX_API_KEY=$aact_hmlg_...` e o `$` é interpolado. Convém escapar como `$$` ou aspar com `'`. Não imprimi a chave de propósito.

### Bloqueios encontrados
- Nenhum. Dúvida para o PO: o aceite pré-marcado ao "Gerar novo QR Code" (spec §4.4) foi implementado como a spec manda; confirme se o jurídico aceita aceite pré-marcado (cada clique grava novo aceite com data/IP/hash de qualquer forma).

### IDRs criados
- IDR-018.

### Cobertura final
- Suíte All: 98,4% total; `app/Domain` 99,8% (gate 98). Pint e PHPStan limpos.

### Evidências
- `tests/Feature/Cobranca/*`, `tests/Feature/Livewire/{ComprarCreditos,AcompanharPagamento}Test.php`, `tests/Unit/Arch/PagamentoArchTest.php`, `tests/Browser/CompraCreditosPixBrowserTest.php`, `tests/Sandbox/AsaasSandboxTest.php`.
