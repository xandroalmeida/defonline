---
epic_id: EPIC-005
type: validation-report
validated_at: 2026-11-05
verdict: rejected
commit: 7a3150b
---

# Relatório de validação do EPIC-005 (STORY-087)

## Veredito: REPROVADO. Não ligar a cobrança.

- **Checklist (28 itens):** 7 pass, 10 pass com ressalva, 6 fail, 4 pendentes, 1 n/a.
- **Defeitos:** 21 (3 críticos, 6 altos, 7 médios, 5 baixos).
- **Sem pagamento real:** nenhum passou pelo Asaas, e nada do épico foi publicado (`main` está 112 commits à frente de `origin/main`).
- **CAs da STORY-087:**
  - CA-2 não cumprido (restrição do sandbox).
  - CA-3 não cumprido (sem produção).
  - CA-1 e CA-4 atendidos neste texto.

A base resistiu bem:
- Webhook forjado, duplicado ou fora de ordem não muda saldo.
- "Logar como" não executa ação financeira.
- Não há IDOR nas rotas novas.
- Nenhum dado de cartão apareceu no banco nem nos logs.

Os defeitos graves apareceram em cenários que a suíte não exercita. D-02, D-03 e D-04 passam despercebidos porque o fake não herda a referência e devolve data com hora.

## Execução

| Verificação | Resultado |
|---|---|
| Pint / PHPStan | exit 0 / exit 0 |
| Pest `All` | **1 falha, 3254 passam** (`FinanceiroTest.php:101`, ver D-13) |
| Cobertura, sem esse teste | geral **98,2%**; `app/Domain` **99,7%** |
| Dusk, suíte inteira | 111 passam |
| Pennant / `composer audit` | sem pendência |
| Gitleaks / Trivy / pgAdmin | sem achados (`verificar.sh seguranca`, exit 0) |
| Exploratórios | 28 cenários, cada um afirmando o comportamento correto: 20 falham, 8 passam (E6b e E15 são só informativos) |
| Migrations | 18 com `rollback` e novo `migrate` numa cópia do banco local: sem erro, md5 de `usuarios` e `diagnosticos` igual antes e depois do ciclo |
| Produção / sandbox | **não acessados**; grupo `asaas-sandbox` não executado |

## Checklist

| Item | Status | Evidência |
|---|---|---|
| 1.1 Estórias `done` | **fail** | 069 e 070 estão `in_review` no índice; as demais estão `done` no índice, mas `in_review` no frontmatter |
| 1.2 CA com teste | **fail** | Script cruzou CA × testes. 069 é documento e seu CA-1 exige ADR "aceito" (está `proposed`). CA-7 e CA-8 da 073 não citam o CA nos testes (o registro do consumo é conferido em `CarteiraTest`). CA-2 da 087 não cumprido |
| 2.1 Cobertura ≥ 80% | pass | 98,2%. Menores em cobrança: `FalhaDeCobrancaMessage` 66,7%, `ComCupom` 82,1% |
| 2.2 Núcleo ≥ 98% | pass | 99,7% |
| 2.3 E2E por fluxo | pass com ressalva | `tests/Browser` cobre os fluxos, sempre com o provedor fake |
| 2.4 Browser real | pass | Dusk, 111 testes |
| 3.1 Setup em máquina limpa | pendente | `up.sh` não testado em máquina limpa |
| 3.2 CI verde na principal | **fail** | Sem CI em push na `main`, nada publicado; o portão local fica vermelho à noite (D-13) |
| 3.3 Deploy em homologação | n/a | A ADR-009 substituiu a homologação da ADR-005 pelo ZalkVPS, só com `defonline-prod` |
| 3.4 Deploy em produção | pass com ressalva | `zalk-deploy.yml` por tag, 5 execuções ok, todas anteriores ao épico |
| 3.5 Infra como código | pass com ressalva | `infra/zalk/docker-compose.yml`; plataforma no repo ZalkVPS, não verificada |
| 4.1 Entregável publicado | pendente | Nada publicado; local coberto por Dusk |
| 4.2 Fluxo manual publicado | pendente | Idem |
| 4.3 Logs e métricas | pass com ressalva | `EventosCobranca`, `cobranca:metricas`, alerta Telegram, com testes; não visto em produção |
| 5.1 Scanner | pass | Gitleaks: 292 commits sem leak; Trivy: 0; audit limpo |
| 5.2 Migrations | pass com ressalva | Reversíveis com tabelas de cobrança vazias; `down` com dados de cobrança não exercitado; sem homologação |
| 5.3 LGPD | pass com ressalva | D-11h, D-18; `provider_events` sem prazo de retenção |
| 6.1 Documentação | **fail** | `database-schema.md` parado em 04/08, sem as tabelas novas; README e `ZALKVPS.md` não falam de cobrança; `.env.example` incompleto (D-14) |
| 6.2 ADR/IDR no índice | pass com ressalva | ADR-010 `proposed`; IDR-018 a 021 e 025 a 029; numeração sem 022 a 024 |
| 6.3 Notas do agente | pass | Preenchidas em 069 a 086 |
| 7.1 Fluxos no sandbox | **fail** | Não percorridos; quadro abaixo |
| 7.2 Webhook | pass com ressalva | E11, E11b, `WebhookPagamentoTest`; ressalvas D-02, D-03, D-10 |
| 7.3 Cartão fora do banco e logs | pass com ressalva | Busca com Luhn e palavras-chave em `defonline`, `defonline_test` e `storage/logs`: nenhum PAN real. `4111…` no log é entrada sintética de `AlertaDeCobrancaTest`. Só bandeira e 4 dígitos no banco. Produção não verificada; D-18 |
| 7.4 Asaas fora do adaptador | pass | `PagamentoArchTest` e busca própria; só bind, config, Política e comentários |
| 7.5 Código fixo de catálogo | pass | Mesmo teste e busca própria |
| 7.6 Preço não afeta vigente | pass com ressalva | E16 ok; a cota muda na renovação (D-19) |
| 7.7 Interruptor em produção | pendente | Sem deploy. Local: E10 e E22 ok nas telas; agendadores falham (D-10) |
| 7.8 Termos descrevem o construído | **fail** | D-11 |

**Sandbox, apenas evidência declarada no ADR-010 e nos IDRs, não reproduzida aqui:**

| Fluxo | Declarado | Situação |
|---|---|---|
| Pix | Checkout e 30 min aceitos; expiração real não observada | pendente |
| Cartão | Checkout criado; pagamento na página hospedada nunca feito | pendente |
| Recusa e 3 retentativas | Régua própria; recaptura não provada | pendente |
| Renovação | 2ª cobrança não observada | pendente |
| Cancelamento | `INACTIVE`/`ACTIVE` respondem 200, testado em boleto | pendente |
| Reembolso | Cartão `REFUNDED`; Pix aguarda autorização; webhook não observado | parcial, declarado |
| Cupom de 100% | Sem provedor, por desenho; mínimo de R$ 5 confirmado | declarado |
| Webhook real | Nunca recebido | pendente |

## Defeitos

Caminhos relativos a `app/app/`. `E*` são cenários do arquivo exploratório.

### Críticos

**D-01: recorrência duplicada segue cobrando todo mês, sem registro.** `Services/Cobranca/ConfirmarAssinatura.php:128-147`, `:290-316`
- **Reprodução (E1, E1b):** abrir dois checkouts e pagar os dois.
- **Observado:** o segundo fica marcado `assinatura_duplicada`, mas a recorrência não é cancelada no provedor. A cobrança do mês seguinte só gera log: 2 Invoices antes e 2 depois.

**D-02: renovação paga é ignorada em silêncio após contestação ou estorno da 1ª Invoice.** `ConfirmarAssinatura.php:75-79`
- **Reprodução (E3):** contestar `pay_1`, depois confirmar `pay_2` com a referência herdada.
- **Observado:** `periodos_pagos` fica em 1, o evento vira `ignorado`, não nasce Invoice nem log. E3b: 2 períodos, esperado 3.
- **Condição:** o Asaas repassar `externalReference`. `AsaasAssinaturaTest:89` assume que sim, o comentário do fake diz que não, e nada foi confirmado. Tratar como real até o sandbox responder.

**D-03: estorno ou contestação do mês 2 cai na Invoice do mês 1.** `Services/Cobranca/ProcessarEvento.php:374-385` (usado em `:249`, `:275`)
- **Reprodução (E2):** `PAYMENT_REFUNDED` de `pay_2`.
- **Observado:** mês 1 `estornada`, mês 2 `paga`, assinatura `ativa`. O financeiro fica errado, o acesso segue e o estado errado dispara D-02. Mesma condição de D-02.

### Altos

**D-04: acesso acaba antes do dia do vencimento, a cada ciclo.** `Infrastructure/Cobranca/Asaas/AsaasPaymentProvider.php:296,306`, `ConfirmarAssinatura.php:106,117`
- **Causa:** data só com o dia é lida como meia-noite UTC (formato dos fixtures do projeto; conferir no sandbox).
- **Reprodução (E7):** pagamento em 05/11 às 15:00 de Brasília.
- **Observado:** início em 04/11 21:00 e vencimento em 04/12 21:00. Em 04/12 22:00 a carteira responde `plano_encerrado`, com o cartão só previsto para 05/12.

**D-05: upgrade dá a cota inteira por uma fração do preço.** `Services/Cobranca/TrocarDePlano.php:419-427`, `Domain/Cobranca/CalculoDeTroca.php`
- **E6:** Básico para Pro a 12 h do fim, a pagar R$ 2,49, abaixo do mínimo, então sai grátis. Cota de 1 para 10. Downgrade em seguida: o provedor volta a R$ 49,90 e a cota fica.
- **E6b:** a 3 dias do fim, R$ 14,91 pelas mesmas 9 análises.
- **Repetível** todo mês. Está conforme o CA-2 da 082, então a regra é decisão do PO.

**D-06: troca de cartão aberta antes de um upgrade deixa o preço antigo.** `Services/Cobranca/TrocarCartao.php:153` e seguintes, `ConfirmarAssinatura.php:357-365`
- **E5:** no Pro, a recorrência nova cobra R$ 49,90, esperado R$ 199,00.
- **Leitura de código:** a renovação aceita qualquer valor e só registra `warning`, então uma mudança de ciclo não aplicada no provedor renovaria um ano pelo preço de um mês.

**D-07: anual cancelado perde a cota mensal até o fim do ano pago.** `Domain/Cobranca/Carteira.php:218`
- **E25:** após `assinaturas:renovar-cota`, cota 0 e `cota_esgotada`, com vencimento em 2027. Contraria a cláusula 8 do Termo.

**D-08: pagamento sem efeito não entra na lista de devolução.** `TrocarDePlano.php:209-217`, `ConfirmarAssinatura.php:128`, `:187-207`
- **E4:** diferença de upgrade paga depois de renovar: só log.
- **E4b:** duas diferenças pagas, R$ 99,40 e R$ 89,46.
- **E21:** 2º checkout pago depois de cancelar: nasce outra assinatura sobre período vigente.
- **E13:** dois QR Codes pagos: dois anos a mais, sem aviso.

**D-09: o desenho depende de hipóteses não provadas** (ADR-010 `proposed`, Q1 a Q7 sem resposta). Falta provar:
- pagamento hospedado e chegada do webhook;
- se `nextDueDate` futuro cobra na hora, o que daria cobrança em dobro na troca de cartão (IDR-021);
- se `CHECKOUT_PAID` traz a assinatura;
- se `PUT /payments/{id}` recaptura o cartão;
- se `PUT /subscriptions/{id}` muda o valor de cartão;
- se `INACTIVE` impede a captura de cobrança já gerada (`GerirRenovacao.php:99`). O ADR diz que cobranças nascem até 40 dias antes e só o `DELETE` as remove.

### Médios

**D-10: sem provedor configurado, agendadores falham e o webhook dá 500, com a cobrança desligada.** `Providers/AppServiceProvider.php:53-59`
- `.env.prod` não tem `PAYMENTS_PROVIDER`, e o padrão `fake` é recusado em produção.
- Com `APP_ENV=production`, saem com exit 1: `cobranca:regua`, `cobranca:conciliar`, `assinaturas:cancelar-antigas`, `assinaturas:ajustar-no-provedor`, `assinaturas:ciclos-gratuitos`.
- E22: webhook sem token responde 500, não 401. Telas do beta seguem normais.

**D-11: Termo e Política `v2` divergem do construído.** `resources/views/legal/*-v2.blade.php`

| # | Texto | Sistema |
|---|---|---|
| a | Cl. 7: bloqueio "após a terceira falha" | Análises e DRM bloqueados na 1ª recusa (E17) |
| b | Cl. 7: encerra após a terceira falha | Encerra 48 h depois dela (`ReguaDeCobranca`) |
| c | Cl. 8: cancelar a qualquer momento, acesso até o fim do ciclo pago | Em atraso, encerra na hora (decisão do PO, não escrita no Termo) |
| d | Cl. 8: histórico dos "12 meses mais recentes"; DRM não citado | Nenhum limite encontrado por busca; DRM segue visível e exportável |
| e | Cl. 6: após o desconto, "preço vigente do plano" | Volta ao preço retratado na contratação (`AjustarValorDaAssinatura.php:43`) |
| f | Cl. 4: quantidade "exibida na contratação"; cl. 7: reajuste com aviso de 30 dias | Cota muda na renovação sem aviso; não há aviso de reajuste (busca sem resultado) |
| g | Cl. 9: arrependimento vale também para créditos | Meu plano só orienta quem tem assinatura (`MeuPlano.php:342`); Pix exige autorização no painel |
| h | Política §1 e §3 | Não cita bandeira e 4 dígitos; diz que enviamos nome, CPF e e-mail, mas o checkout não envia dado pessoal |

Sem divergência: renovação Pix, DRM em planos e não em créditos, trial e o texto da troca de plano (exceto D-05).

**D-12: crédito segue utilizável com estorno Pix aguardando autorização.** `Services/Cobranca/ReembolsarCobranca.php:200-213`. E12: a carteira ainda escolhe o lote.

**D-13: filtro de data do Financeiro perde assinaturas à noite, e a suíte fica vermelha.** `Livewire/Backoffice/Financeiro.php:259-260`
- O filtro passa data sem offset; o de cobranças usa `format('Y-m-d H:i:sP')`.
- `FinanceiroTest.php:101` falhou em todas as execuções entre 21h07 e 21h29. A janela das 21h às 24h vem da leitura do código e não foi testada em outro horário.

**D-14: documentação de operação desatualizada.** Item 6.1, mais frontmatter divergente do índice. `.env.example` sem `CARTAO_EXPIRACAO_MINUTOS`, `PAGAMENTO_VALOR_MINIMO_CENTAVOS`, `LOTE_PERTO_DE_VENCER_DIAS`, `ASAAS_TIMEOUT`.

**D-15: plano grátis por cupom, após upgrade pago, expira sem saída.** `TrocarDePlano.php:438-442`, `CiclosGratuitos::tratar`, `TrocarCartao.php:60-64`. E23: paga R$ 99,50, a tela promete R$ 199,00 na próxima cobrança, e no vencimento expira, sem como cadastrar cartão (leitura de código).

**D-16: eventos sem tratamento.** `AsaasPaymentProvider.php:49-64`. E15: `PAYMENT_CHARGEBACK_REVERSED`, `PAYMENT_RESTORED`, `PAYMENT_PARTIALLY_REFUNDED`, `PAYMENT_REFUND_DENIED` viram `outro` (conferir os nomes na documentação). `Contestada` é estado final, e estorno negado fica aguardando para sempre (leitura de código).

### Baixos

- **D-17:** cupom sem limite de tentativas e digitável por qualquer conta, mesmo sem concessão. `Livewire/Cobranca/ComCupom.php:42`, `CupomDoCheckout.php:64` (leitura de código).
- **D-18:** `creditCardHolderInfo` (nome, CPF) fica no payload guardado, se vier. `AsaasPaymentProvider.php:66` (E14).
- **D-19:** cota reduzida no catálogo atinge a renovação sem aviso. `ConfirmarAssinatura.php:349` (E16b: de 10 para 5).
- **D-20:** downgrade agendado em ciclo gratuito não é aplicado. `CiclosGratuitos.php:88-90` (E24).
- **D-21:** chamada de rede dentro de transação com a linha travada. `GerirRenovacao.php:65-99,125`, `TrocarDePlano.php:456` (leitura, sem medição).

## O que falta para ligar a cobrança

1. Corrigir D-01 a D-08, D-10 e D-12. O PO decide D-05, D-11 e D-19.
2. Abrir conta de sandbox só do DEFOnline. A atual tem webhook do Serene.
3. Fechar as pendências do ADR-010 com pagamento real e URL pública: D-09 e herança de `externalReference`. Aprovar o ADR e responder Q1 a Q7.
4. Percorrer no sandbox: Pix, cartão, recusa, renovação, 3 retentativas, cancelamento, reembolso e cupom de 100%.
5. Conta Asaas de produção da EB Parcerias, com tokenização e checkout recorrente habilitados, mínimo conferido e SAQ enquadrado.
6. **Chaves de produção** em `APP_ENV_FILE` e `.env.prod`, depois `scripts/segredos.sh push`:
   - `PAYMENTS_PROVIDER=asaas`
   - `ASAAS_BASE_URL=https://api.asaas.com/v3`
   - `ASAAS_API_KEY` (`$aact_prod_`)
   - `ASAAS_WEBHOOK_TOKEN`, com 32 ou mais caracteres

   O compose não muda; Telegram já existe no `.env.prod`.
7. Cadastrar o webhook em `https://defonline.zalk.com.br/webhooks/pagamentos`, com o token e os eventos do adaptador.
8. Publicar desligado e conferir em produção, só leitura, que o beta faz diagnóstico como antes.
9. **Cupom do beta:** definir desconto e duração, usar código longo e aleatório, limite igual ao número de contas, e conceder às contas. Ao ligar, o beta fica sem análises e sem DRM até passar pelo checkout, então avise antes.
10. Revisar o Termo (D-11). A revisão por advogado segue adiada pelo PDR-020.
11. Conferir catálogo e trial na tela.
12. Definir a operação: quem autoriza estorno Pix, nota fiscal manual, lista "a devolver" e alertas.
13. Repetir esta validação e só então ligar o interruptor.

## Recomendação ao PO

Manter o épico em `in_review`. Rodar primeiro um spike de sandbox com pagamento real (D-09), porque ele define D-02, D-03 e D-04. Agrupamento sugerido:

| Grupo | Defeitos | Tamanho |
|---|---|---|
| Recorrência e referência | D-01, 02, 03, 16 | M |
| Datas | D-04 | S |
| Regra do upgrade | D-05 | S |
| Valor no provedor | D-06, 15, 20 | M |
| Cota do anual | D-07 | S |
| Pagamento sem efeito e estorno pendente | D-08, 12 | M |
| Prontidão | D-10, 13, 14, 17, 18, 21 | S |
| Termos | D-11, 19 | S |

## Limitações

- Sem sandbox e sem produção, então sem busca em logs e banco de produção.
- O comportamento do Asaas em D-02, D-03, D-04 e D-16 foi inferido do adaptador, dos testes e do ADR.
- O `APP_ENV_FILE` real não é legível; vi só o `.env.prod` local.
- As corridas de D-08 foram reproduzidas em sequência, não em paralelo real.

## Arquivos

Todos em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/`:
- `V087ExploratorioTest.php` (para rodar, copie para `app/tests/Feature/Cobranca/`)
- `exp1.log` (só a última execução, E25; as saídas anteriores foram sobrescritas)
- `pest-all.log`
- `cov-all.log`
- `cov-domain.log`
- `dusk.log`
- `seg.log`
