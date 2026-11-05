---
idr_id: IDR-028
slug: eventos-e-alertas-de-cobranca
title: Eventos de produto da cobrança por EventosCobranca, canal único de alerta da equipe e alerta derivado de Log::error com module=cobranca
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-086)
related_story: STORY-086
related_adrs: ["ADR-004", "ADR-010"]
related_idrs: ["IDR-020", "IDR-027"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-028 — Eventos e alertas de cobrança

## Contexto

O épico precisa medir conversão (trial, checkout) e churn, e a equipe precisa saber quando a cobrança para ou o pagamento falha (NRF §6.3 e §6.4). O padrão existe: `EventLogger::emitOnce` (STORY-056), o catálogo `EventosDrm` e o `RfbAlerter` (Telegram com queda para log). As estórias 076 a 085 já marcam o que exige olhar humano com `Log::error(..., ['module' => 'cobranca'])`, e o abuso de trial alertava pelo `RfbAlerter`.

## Decisão

- **Catálogo `App\Observabilidade\EventosCobranca`**, no padrão do `EventosDrm`: um método por evento, esquema exato em `ESQUEMA` (um teste compara o que sai com a lista), chave de idempotência do FATO, tudo por `EventLogger::emitOnce`. Eventos: `trial_ativado`, `trial_encerrado` (motivo `prazo` ou `contratou`), `checkout_iniciado`, `contratacao_concluida`, `contratacao_abandonada`, `cupom_aplicado`, `cancelamento` (origem `cliente`, `cliente_em_atraso`, `inadimplencia`, `pix_nao_renovado`, `reembolso`), `cancelamento_revertido`, `bloqueio_sem_saldo` (origem `analise` ou `drm`), e os fatos `pagamento_confirmado` e `pagamento_recusado`, que alimentam o alerta de taxa de falha. `cancelamento_revertido` e os dois fatos de pagamento são acréscimos à lista do CA-1: sem a reversão o churn conta quem reativou, e sem os fatos não há como medir a taxa de falha.
- **Sem dado pessoal nem de cartão:** só identificadores opacos, enumerações, contagens e o valor cobrado em centavos. O `EventLogger` recusa as chaves proibidas em execução; o teste de esquema fixa as chaves permitidas; um teste de fluxo completo procura e-mail, CPF, IP, bandeira e final de cartão no que foi gravado.
- **Onde cada evento nasce:** na transação do fato quando ele concede ou retira acesso (`trial_ativado`, `trial_encerrado` ao contratar, `contratacao_concluida`, `cancelamento`), seguindo a ADR-004 §2.3. Fora dela, com falha engolida em `Log::warning`, o que é só medida: os fatos de pagamento (depois do processamento do webhook) e `bloqueio_sem_saldo` (ao montar a tela que explica o bloqueio). `trial_encerrado` por prazo vem de `trial:registrar-encerramento` (horário), porque nada acontece no instante em que o prazo acaba; a chave de idempotência é a mesma da contratação, então nunca se duplica.
- **Métricas (`MetricasDeCobranca`, comando `cobranca:metricas`):**
  - trial → pago em 30 dias: coorte de `trial_ativado` num período, convertido quem tem `contratacao_concluida` de **assinatura** (não crédito, não renovação) do mesmo Usuário em até 30 dias; só entra a coorte já madura;
  - conversão do checkout: `checkout_iniciado` × `contratacao_concluida` ligados por `invoice_id`, cortada por tipo, meio, ciclo e renovação (Pix anual é um segmento como outro);
  - churn mensal (mês em Brasília): dos assinantes que abriram o mês pagando, os que têm `cancelamento` não revertido até o fim dele; quem assina e cancela no mesmo mês não entra. O estado de cada assinatura é o do **último** evento de cancelamento ou reversão, então cancelamento duplicado (cliente e depois reembolso) conta uma vez.
- **Pix anual:** a renovação por Pix estende a mesma assinatura e grava `contratacao_concluida` com `renovacao=true`; o Pix da renovação pago depois do fim do plano cria assinatura nova e grava `renovacao=false` (é contratação, entra na base do churn). Pix vencido que o banco confirma depois fica com `contratacao_abandonada` e `contratacao_concluida` na mesma Invoice; a conversão, que conta por Invoice, fica certa.
- **Canal único `App\Observabilidade\AlertaDaEquipe`** (nome genérico): é o antigo `RfbAlerter` (Telegram, queda para `Log::warning`), agora com a última defesa de conteúdo: mascara e-mail, CPF, CNPJ, número de cartão e o final do IP no título, na mensagem e no contexto. `RfbAlerter` virou subclasse que só mantém o módulo `rfb` nos logs; `Trial` passou a usar o canal genérico, e o alerta de IP em excesso deixou de levar o IP inteiro (vai `198.51.100.x`). O canal registra a falha do Telegram só com a classe da exceção: a mensagem dela traz a URL com o token do bot.
- **Alertas de cobrança por ouvinte de log (`AlertaDeCobranca`):** todo `Log::error` (ou acima) com `module = cobranca` vira alerta P2 (P1 se o ponto de log passar `prioridade = P1`). Não foi preciso mexer nos ~35 pontos das estórias anteriores, e um novo `Log::error` do módulo já alerta. O ouvinte só repassa identificadores (`*_id`), contagens, ação, tipo e nomes de classe; texto livre (`motivo`, `message`) nunca vai. Silêncio de 30 minutos por falha (ADR-004), com a chave sem o `request_id` e o `user_id` que o middleware põe em toda linha.
- **CA-4:** `cobranca:alertar-falha-de-pagamento` (a cada 5 min) calcula `recusados / (recusados + confirmados)` na última hora e alerta P2 acima de 10%, com mínimo de 5 tentativas (1 recusa em 1 tentativa não é incidente). Limite, janela, mínimo e silêncio em `config/cobranca/observabilidade.php`. Conta uma recusa por cobrança (recusa e vencimento da mesma cobrança de renovação são uma só). Pix que apenas venceu não é falha, é abandono.
- **CA-5:** o webhook rejeitado (token errado ou corpo ilegível) agora faz `Log::error('cobranca.webhook_rejeitado')` com P1, sem corpo, token nem IP, e uma rajada gera um alerta só. Erros de processamento já alertam pelo ouvinte (`cobranca.evento_com_erro`, `cobranca.provedor_indisponivel`, `cobranca.evento_nao_processado`).
- **`cupom_aplicado`** está definido e testado (`EventosCobranca::cupomAplicado`), sem ponto de emissão: a STORY-083 (cupom) corre em outra pista. Quem a juntar chama o método onde o cupom reduz um pedido (checkout de assinatura e de créditos), com o `invoice_id` da Invoice criada.

## Consequências

- Quem criar um novo `Log::error` com `module = cobranca` gera alerta de equipe; para erro que não deva acordar ninguém, use `Log::warning`.
- Quem criar uma nova forma de a assinatura paga deixar de renovar precisa chamar `EventosCobranca::cancelamento`, senão o churn não a vê.
- A taxa de falha de pagamento só cobre o que chega por webhook ou conciliação; a retentativa da régua que o provedor recusa na hora, sem webhook, não conta ainda. Revisar quando o sandbox mostrar se o Asaas avisa cada retentativa.
- Telegram é o único canal real; sem `TELEGRAM_BOT_TOKEN` e `TELEGRAM_CHAT_ID` o alerta cai para o log (`alerta.alert`). Configurar antes de ligar a cobrança em produção.
