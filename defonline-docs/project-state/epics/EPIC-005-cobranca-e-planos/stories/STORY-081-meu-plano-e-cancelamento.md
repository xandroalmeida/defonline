---
story_id: STORY-081
slug: meu-plano-e-cancelamento
title: "Meu plano, troca de cartão e cancelamento"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: claude-sonnet-5-5
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-081 — Meu plano, troca de cartão e cancelamento

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

Os Termos prometem cancelamento a qualquer momento no painel e prestação de contas ao assinante.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.4 e §4.3
  - Spec de tela da STORY-070

## O quê

Dar ao usuário uma tela com plano, cota, créditos, cobranças e as ações de trocar cartão e cancelar.

## Por quê

Transparência reduz chargeback e reclamação, e permite recuperar uma assinatura em atraso.

## Critérios de aceite

- [x] **CA-1:** A tela mostra plano, ciclo, próxima renovação e valor, cota restante e, por lote, créditos e vencimento, com alerta para lote perto de vencer.
- [x] **CA-2:** A tela lista as cobranças com data, valor, desconto, status e meio de pagamento.
- [x] **CA-3:** O usuário troca o cartão, e uma assinatura em atraso é cobrada novamente com o cartão novo.
- [x] **CA-4:** O usuário cancela a renovação, a tela informa até quando o acesso vai, e não há nova cobrança. O acesso continua até o fim do ciclo pago.
- [x] **CA-5:** O usuário reativa a renovação antes do fim do ciclo.
- [x] **CA-6:** A tela informa como pedir arrependimento nos 7 dias corridos, quando aplicável.
- [x] **CA-7:** Usuários sem plano e sem créditos veem a oferta de planos.
- [x] **CA-8:** Durante o "Logar como", o admin vê a tela, mas não executa ações.
- [x] **CA-9:** Teste E2E cobre cancelamento e troca de cartão.
- [x] **CA-10:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Ajustes do ADR-010 (prevalecem sobre os CAs acima onde houver conflito)

O ADR-010 está `proposed`. O orquestrador adotou as recomendações dele em 2026-11-05 para não parar o trabalho, e o PO ainda vai revisá-las.

- Trocar o cartão significa concluir um novo Checkout recorrente e cancelar a assinatura antiga só depois da confirmação, sem cobrança em dobro no ciclo.
- Assinatura anual com Pix não tem botão de cancelar, e a tela diz até quando vai o acesso.

## Fora de escopo

- Troca de plano (STORY-082).
- Reembolso (STORY-085).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-077
- **Bloqueia:** STORY-082, STORY-083, STORY-087

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
- Leitura: estória (com "Ajustes do ADR-010"), spec `STORY-070-meu-plano`, DDR-009, IDR-019, notas da 077, ADR-010 e skill do programador. Rota nomeada `meu-plano` (`/meu-plano`, já esperada por `TextoBloqueio::ROTAS`); com a cobrança desligada é 404 e o item de menu some (CA-10).
- **Cancelar renovação** = suspender a recorrência no provedor + `Cancelada` na carteira; **reativar** = religar + `Ativa`. Acesso e cota intactos até `proximo_vencimento`. Provedor primeiro: se falhar, nada muda e a tela diz "sua renovação continua ativa". Só `Ativa` cancela (em atraso é caminho da 079/troca de cartão). Ver IDR-021.
- **Trocar cartão** (ajuste do ADR-010): novo Checkout recorrente (`trocas_de_cartao`, não `Invoice`); em dia a 1ª cobrança é no vencimento já pago (`PedidoCheckout::primeiraCobrancaEm`), em atraso é hoje. O ponteiro `assinaturas.id_externo` só muda na confirmação (`checkout.concluido` ou 1ª `cobranca.paga`); só então a antiga é cancelada, com repetição horária (`assinaturas:cancelar-antigas`) e `Log::error` se o provedor falhar.
- **Em atraso (CA-3)**: o pagamento da 1ª cobrança do cartão novo volta a assinatura a `Ativa` e renova o ciclo (`ConfirmarAssinatura::renovar(recuperaAtraso: true)`). Nenhuma máquina de estados nova; o resto do atraso (retentativas, régua, encerramento) continua da 079 e o teste antigo "renovação em atraso fica para a 079" segue valendo.
- **Pix anual (ajuste do ADR-010)**: plano pago sem `id_externo` (sem recorrência no provedor) mostra "Este plano não renova sozinho. Termina em {data}." e não tem cancelar nem trocar cartão. "Pagar renovação" (spec §4.9) é da 080.
- **Arrependimento (CA-6)**: canal `eb@ebparcerias.com` (e-mail, sem botão). Texto fixo no rodapé e, no diálogo de cancelamento, a frase personalizada quando o plano tem até 7 dias corridos e nenhuma análise da cota dele.
- `IniciarAssinatura::assinaturaVigente` passou a contar a assinatura `Cancelada` com ciclo por correr (assinar de novo seria pagar duas vezes o mesmo período). A tela de checkout diz "com a renovação cancelada e acesso até".
- `Carteira::mudarStatus(Ativa)` apaga `data_cancelamento`. `x-confirmacao` ganhou o rótulo opcional `voltar` (spec: "Voltar"); o padrão continua "Cancelar".
- Cobranças: lista tudo que virou cobrança (pago, pendente não expirado, recusado, reembolsado, contestado) e esconde checkout abandonado/expirado e falha nossa com o provedor. Desconto é "—" até a STORY-083 (não há coluna).
- Limiar de lote perto de vencer: `services.payments.lote_perto_de_vencer_dias` (env `LOTE_PERTO_DE_VENCER_DIAS`, padrão 30).
- "Trocar plano" só aparece quando a rota `plano.trocar` (082) existir; "Atualizar cartão" do bloqueio (`cartao.atualizar`) continua para a 079: a ação some até lá. A troca de cartão vive em Meu plano.
- Teste alterado: `QuizCobrancaTest` "esconde as ações enquanto as telas não existem" usava a ausência da rota `meu-plano` como exemplo. Com a rota real, o teste agora espera a ação "Ver meu plano" desde o início e usa `cartao.atualizar` (079) como a rota que ainda nasce. Mesma garantia (ação some sem rota, aparece com ela).

### Descobertas
- O Livewire de teste não leva a sessão para `request()`, então `Personificacao::ativa()` não dispara dentro de `Livewire::test()`. O somente-leitura é coberto por GET (HTML com botões `disabled`), pelos testes de serviço (negam durante "Logar como") e pelo Dusk com "Logar como" real.
- O fake agora conhece a página da troca de cartão (`/pagamentos/fake/troca/{troca}`), decidida pela existência da troca na referência do checkout.

### Bloqueios encontrados
- Nenhum. Sandbox do Asaas verificado depois (IDR-021): `PUT status` INACTIVE/ACTIVE funciona e o checkout aceita data futura sem gerar cobrança antes do pagamento. Seguem não verificáveis sem pagar: (2) `nextDueDate` futuro no Checkout recorrente não cobrar no ato (senão a troca em dia cobra em dobro: validar no sandbox ANTES de ligar a cobrança); (3) `CHECKOUT_PAID` trazer `checkout.subscription`.
- Dúvidas ao PO/Arquiteto: (a) RESPONDIDA pelo orquestrador: cancelar em atraso precisa existir (Termo v2: "a qualquer momento"). NÃO implementado nesta estória: a régua de falha é da STORY-079 em outra pista, e o orquestrador trata depois da junção. Hoje, em atraso, a tela só oferece "Trocar cartão" e `GerirRenovacao::cancelar` só aceita `Ativa`; (b) em atraso o cartão novo cobra o valor da assinatura na hora; se o provedor recusar, a troca falha e a assinatura segue em atraso com o cartão antigo (conforme a spec §4.3).

### IDRs criados
- IDR-021 (cancelar = suspender a recorrência; troca de cartão por novo checkout, antiga cancelada após confirmação).

### Cobertura final
- Suíte All: 2457 testes verdes; cobertura total 98,6%; `app/Domain` com todas as classes novas em 100%. Dusk inteiro: 80 verdes (6 novos). Pint e PHPStan limpos.

### Evidências
- `tests/Domain/Cobranca/MeuPlanoDominioTest.php` (situações, arrependimento, lote perto), `tests/Feature/Cobranca/GerirRenovacaoETrocaDeCartaoTest.php` (CA-3/4/5/8 nos serviços e webhooks), `tests/Feature/Cobranca/AsaasGestaoDeAssinaturaTest.php`, `tests/Feature/Cobranca/PagamentoFakeTrocaDeCartaoTest.php`, `tests/Feature/Livewire/MeuPlanoTest.php` (CA-1/2/4/5/6/7/8/10 na tela), `tests/Browser/MeuPlanoBrowserTest.php` (CA-9: cancelar/reativar em 360px, trocar cartão em dia e em atraso, recusa, oferta, "Logar como").
