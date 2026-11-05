---
story_id: STORY-080
slug: pix-no-plano-anual
title: "Pix no plano anual"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: in_review
owner_agent: claude-sonnet-5-5 (STORY-080)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-080 — Pix no plano anual

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A spec permite Pix no ciclo anual. Pix não debita sozinho: a renovação depende de ação do usuário.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.1 e §3.4
  - ADR-010

## O quê

Permitir assinar o plano anual com Pix e renovar por um novo QR Code.

## Por quê

Atende quem não quer ou não pode usar cartão, com a vantagem de receber o ano adiantado.

## Critérios de aceite

- [ ] **CA-1:** No plano anual, o checkout oferece cartão ou Pix; no mensal, só cartão.
- [ ] **CA-2:** A assinatura fica ativa após a confirmação do Pix, nunca antes.
- [ ] **CA-3:** Pix expirado não ativa a assinatura e pode ser gerado de novo.
- [ ] **CA-4:** Antes do fim do ciclo, o usuário recebe por e-mail o aviso e o caminho para pagar a renovação.
- [ ] **CA-5:** Sem pagamento até o fim do ciclo, as funções pagas são bloqueadas com o histórico preservado, sem retentativa de cobrança.
- [ ] **CA-6:** O mesmo webhook recebido duas vezes não ativa nem renova duas vezes.
- [ ] **CA-7:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Ajustes do ADR-010 (prevalecem sobre os CAs acima onde houver conflito)

O ADR-010 está `proposed`. O orquestrador adotou as recomendações dele em 2026-11-05 para não parar o trabalho, e o PO ainda vai revisá-las.

- Assinatura anual com Pix é um Checkout Pix avulso por ano, sem assinatura no Asaas. A renovação envia por e-mail um novo checkout.

## Fora de escopo

- Pix em plano mensal.

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-076, STORY-077
- **Bloqueia:** STORY-087

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
- Leitura: estória (com "Ajustes do ADR-010", que prevalecem), ADR-010, IDR-018, IDR-019, notas das STORY-076/077/078, spec `STORY-070-checkout-pix-anual.md` e a skill do programador.
- Contratação: `IniciarAssinatura` ganhou `MeioPagamento` (cartão padrão). Pix só no anual (`CompraNaoPermitida::pixSoNoAnual`). É um Checkout Pix avulso (`DETACHED`, sem `subscription` no Asaas); a assinatura nasce em `ConfirmarAssinatura::ativar` só com `cobranca.paga`, com `meio_pagamento = pix` e `id_externo` nulo. Pix vencido ainda ativa se o banco confirmar depois (mesma regra dos créditos).
- Renovação: `IniciarRenovacaoPix` cria uma Invoice `renovacao = true` (Pix, ligada à assinatura) só nos últimos 7 dias (`RenovacaoPix`, domínio puro). O pagamento (`ConfirmarAssinatura::renovarPorPix`) estende o ciclo a partir do fim atual (`data_inicio + 12*n meses`, sem perder dias), grava `periodos_pagos`, preço e cota do catálogo de hoje, e NÃO zera a cota do mês em curso: `proxima_cota_em` passa a ser o fim do ciclo antigo, quando a cota volta cheia. Se o plano já tinha acabado quando o Pix foi pago, vira assinatura nova.
- Preço da renovação: o da última contratação (`preco_snapshot_centavos`), não o do catálogo. A regra de mudança de preço com aviso de 30 dias é da STORY-079; até lá o preço do Pix não muda sem o usuário ver o valor na tela.
- Aviso (CA-4): `assinaturas:pix-anual` (horária, `routes/console.php`) envia `RenovacaoPixMessage` (data do fim, novo fim, valor, link `assinatura.renovar`) uma vez por ciclo (`assinaturas.aviso_renovacao_pix_para` guarda o vencimento avisado, gravado depois do envio). Falha de envio não marca e a rotina sai com erro.
- Fim sem pagamento (CA-5): a carteira já não dá acesso a ciclo vencido (`PlanoEncerrado`); a mesma rotina passa a assinatura a `expirada` (libera a vaga para contratar de novo, com auditoria). Sem retentativa e sem chamada ao provedor. Histórico e diagnósticos intocados.
- Tela `/assinar/renovar` (`assinatura.renovar`, `RotasCobranca::RENOVAR`, componente `RenovarAssinaturaPix`): fora da janela diz quando abre; na janela mostra o aviso, o resumo e "Pagar renovação"; depois do fim mostra "Plano encerrado" com "Renovar com Pix" e "Assinar com cartão". O retorno (`assinatura.retorno`) cobre aguardando, expirado, ativo e renovado do Pix. Retorno de renovação por cartão (já nasce paga) continua 404.
- Refatoração (terceira ocorrência): `AbrirCheckoutDaInvoice` concentra abrir o checkout, gravar link/prazo e tratar falha do provedor; usado por créditos, assinatura e renovação. Efeito colateral: créditos também gravam `motivo_encerramento = provedor`.
- Teste antigo alterado: em `AssinarPlanoTest`, "no anual ... assertDontSee('Pix')" virou `assertSee('Assinar e pagar')` + `assertDontSee('Gerar QR Code Pix')`, porque o seletor do anual agora cita Pix por exigência do CA-1.

### Descobertas
- O aviso e o estado "Encerrado" da spec moram em Meu plano, que é a STORY-081 e ainda não existe. Implementei o e-mail e a tela `/assinar/renovar` (com os ids `screen-meuplano-*`); a 081 deve exibir o aviso `info` dos últimos 7 dias e o "Encerrado" apontando para `RotasCobranca::RENOVAR`, e esconder "Cancelar" para `Assinatura::pagoComPix()`.
- `FakePaymentProvider` usa o relógio real no prazo do checkout; em testes com `travelTo` é preciso ajustar `checkout_expira_em`.
- Flake preexistente: `TrialTest` "CA-8 assinar durante o trial" (empate de `data_inicio` na ordenação de `Carteira::snapshot`) e a ordenação por `occurred_at` em `CobrancaInterruptorTest` falham de vez em quando em suíte cheia e passam isoladas. Não mexi.

### Integração com as STORY-079 e 081 (2026-11-05, pós-junção)
- Pix anual nunca entra na régua de cartão nem no aviso D-7 de cartão: `AvisarRenovacao`, `ExecutarReguaDeCobranca`, `FalhaDeRenovacao` e `ConciliarCobrancas` (assinaturas) excluem `meio_pagamento = pix`. Ele segue só `assinaturas:pix-anual` (aviso e encerramento). Teste: `PixAnualComReguaEConciliacaoTest`.
- Meu plano (081) mostra para Pix: aviso `info` nos últimos 7 dias ("Seu plano anual termina em {data}. Pague a renovação com Pix para não perder o acesso."), "Pagar renovação" e, depois do fim, o estado Encerrado da spec com "Renovar com Pix" (ambos para `RotasCobranca::RENOVAR`). Sem cancelar nem trocar cartão. Testes: `MeuPlanoTest` (grupo "plano anual com Pix") e `MeuPlanoBrowserTest::test_plano_anual_com_pix_...`.
- Conciliação: as cobranças de Pix (QR de contratação e de renovação) são Invoices com checkout e entram em `invoicesEmAberto`; pago com webhook perdido ativa/renova uma vez só (a Invoice paga sai da busca e o webhook tardio é ignorado); QR sem pagamento só expira.
- Flakes corrigidos (a nota "não mexi" acima ficou superada): `Carteira::snapshot` desempata por paga sobre trial, não encerrada sobre encerrada e maior id (`CarteiraDesempateDeAssinaturaTest`); `CobrancaInterruptorTest` não ordena mais por `occurred_at`.

### Bloqueios encontrados
- Nenhum. Perguntas ao PO: (1) a spec §9 propõe não ter "Cancelar renovação" no Pix; implementei assim (a 081 decide a tela). (2) Confirmar o preço da renovação Pix = último preço contratado. (3) Reembolso/estorno de Pix de renovação é da 087.

### IDRs criados
- Nenhum novo: decisões locais, ligadas ao IDR-019 (`periodos_pagos`, vencimento ancorado no início).

### Cobertura final
- Ver relatório da execução.

### Evidências
- `tests/Feature/Cobranca/PixNoPlanoAnualTest.php`, `tests/Feature/Livewire/RenovarAssinaturaPixTest.php`, `tests/Domain/Cobranca/RenovacaoPixTest.php`, `tests/Browser/PixAnualBrowserTest.php`.
