---
pdr_id: PDR-005
slug: encerramento-wave-2026-01
title: Encerramento da WAVE-2026-01 e abertura da WAVE-2026-02
status: accepted
decided_at: 2026-08-04
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-002, EPIC-003, EPIC-010]
related_pdrs: [PDR-001, PDR-003, PDR-004]
---

# PDR-005 — Encerramento da WAVE-2026-01

## Contexto

A WAVE-2026-01 ("Hipótese do Roberto") abriu em 20/05/2026 com cinco épicos e a meta de entregar em homologação o ciclo cadastro → diagnóstico → histórico no setor Indústria.

Em 04/08/2026, ao preparar a abertura da onda 2 com o módulo DRM, o levantamento de estado revelou três coisas que precisavam ser decididas antes de seguir:

1. O EPIC-002 estava em `done_under_review` havia mais de dois meses, travado por um gate normativo que acabou de ser aprovado.
2. O EPIC-003 nunca foi decomposto em estórias.
3. O `index.json` refletia o estado de 02/06, enquanto o repositório tinha commits substanciais até 04/08.

## Decisão

> **Encerramos a WAVE-2026-01 com objetivo parcialmente atingido, adiamos o EPIC-003 para a onda 2, e abrimos a WAVE-2026-02 tendo o EPIC-010 (DRM) como épico âncora.**

As estórias que sobraram do EPIC-002 não somem no fechamento: são transferidas para a `SPRINT-2026-W32`, que roda já na onda 2 e tem, entre seus compromissos, deixar o EPIC-002 sem nenhuma estória fora de `done`.

## Decisões que acompanham

### 1. EPIC-002 validado, mas ainda não `done`

O gate normativo do NRF §9.3 — validação externa do motor por especialista financeiro, `STORY-036` — foi aprovado em 04/08/2026. Era o único item bloqueante do épico, e a razão de ele ter ficado em `done_under_review`.

Dos 8 fails não-bloqueantes do relatório de validação, **6 já estavam resolvidos e não haviam sido registrados**, 1 tornou-se sem objeto com a aprovação da validação externa, e 1 foi adiado (item 3 abaixo). Detalhamento na reconciliação da `STORY-037`.

**Ainda assim, o épico fica em `in_review`, não em `done`.** Seis estórias seguem abertas: 039 e 040 aguardando verificação de deploy, e 041 a 044 transferidas para a `SPRINT-2026-W32`. Sobe para `done` quando aquela sprint fechar.

Isso diverge do precedente do EPIC-001, que foi marcado `done` com três débitos abertos. Divirjo de propósito: um daqueles débitos — a `STORY-023` — ficou parado 2 meses e meio justamente porque o épico "fechado" tirou a atenção de cima dele. Épico `done` com estória aberta é um convite a esquecer a estória.

**Pendência documental:** o parecer do especialista precisa ser arquivado em `validation/evidence/`. Enquanto não estiver no repositório, a aprovação está registrada apenas por declaração do PO — o que satisfaz o gate de produto, mas não a trilha de auditoria do NRF §9.3.

### 2. EPIC-003 (Histórico básico) adiado para a WAVE-2026-02

O épico nunca foi decomposto. Adiá-lo custa pouco e libera a onda para fechar.

A justificativa não é só de calendário. O histórico de diagnósticos serve a um uso **episódico** — o usuário típico terá um ou dois diagnósticos, e uma lista de dois itens não é um problema urgente. O EPIC-010 serve a um uso **mensal**, e é ele que cria a recorrência que o north star mede e que o EPIC-005 pressupõe.

**Não estou dizendo que a série histórica do DRM substitui o EPIC-003** — são objetos diferentes: diagnósticos anuais de um lado, competências mensais do outro, e uni-los criaria o acoplamento que o `PDR-003` evitou de propósito. É questão de ordem, não de mérito. O EPIC-003 entra na onda 2 depois do DRM.

### 3. Tag `v1.0.0` adiada até o go-live real

O item H-3 do relatório de validação cobrava a criação da tag `v1.0.0`. Adio deliberadamente.

Não existe ambiente de produção — o `release-production.yml` está *stubbed* e não há VPS provisionada. Cravar `v1.0.0` sobre um produto que só roda em homologação transforma o marco de versão em ficção, e queima o número que deveria marcar o primeiro acesso de um usuário pagante. A numeração `rc` sobre homologação está correta e continua.

A tag `v1.0.0` sai quando o produto for para produção de verdade.

### 4. A hipótese da onda não foi testada

Registro isto com franqueza porque é o resultado mais importante da onda, e o mais desconfortável.

A hipótese central era: *o dono de pequena indústria extrai valor real de um diagnóstico automatizado a ponto de voltar para um segundo diagnóstico em até 6 meses.* O critério de validação era **≥ 10 Robertos reais com diagnóstico concluído e NPS ≥ 30**.

Isso não aconteceu. O beta ficou bloqueado pelo gate do NRF §9.3 durante toda a onda, e o gate só caiu agora. **A onda entregou o produto, mas não testou a hipótese** — o que significa que abrimos a onda 2 sem a confirmação que a onda 1 deveria ter dado.

Consequência prática: o beta com Robertos reais continua sendo a próxima decisão de negócio, e agora está desbloqueado. Ele roda **em paralelo** ao EPIC-010, não antes — o DRM atende outra persona (o microempresário do Simples e do MEI) e sua construção não depende do resultado do beta de Indústria.

Se o beta voltar negativo, isso não invalida o DRM — mas reabre o `PDR-003` quanto ao posicionamento relativo dos dois produtos.

### 5. Trabalho não rastreado entre 02/06 e 04/08

Entre a última atualização do índice e hoje, oito commits entregaram trabalho real que nenhuma estória cobria: migração da homologação para VPS nova com DNS na Cloudflare, correção de CVEs em Guzzle e Laravel, reorganização do pipeline (pre-push proporcional, Dusk movido para o CI, deploy em um clique), backup do Postgres para Cloudflare R2 com alerta no Telegram, documentação viva do schema do banco e atualização dos runtimes do CI.

É trabalho legítimo e necessário — vários itens eram dívida de segurança e de operação. **O problema não é o trabalho; é ele ter acontecido fora do fluxo**, sem estória, sem sprint e sem registro no índice. O sintoma foi o `SPRINT-2026-W26`, planejado para uma semana de junho e nunca fechado.

Isso contradiz diretamente o princípio 5 do método ("estado registrado, sempre") e é o que produziu a defasagem de dois meses entre o que foi feito e o que estava anotado — com o efeito colateral de manter seis pendências "abertas" que já estavam resolvidas.

**Correção adotada:** trabalho de infraestrutura e manutenção passa a ter estória de tipo `enablement`, ainda que curta e escrita depois. Sprint que não fecha na data é encerrado explicitamente com o que sobrou transferido, em vez de ficar `planned` indefinidamente.

### 6. Riscos aceitos no fechamento

Dois débitos são encerrados por decisão explícita do PO, sem execução:

**Chave do cnpja.** A credencial exposta em 23/05 (transcript, histórico de shell e logs do agente) **não será rotacionada**. O PO avaliou e aceitou o risco. Escopo do que a chave dá acesso: consultas de CNPJ na API do cnpja, sob a conta contratada. Consequências de exposição: uso indevido da cota contratada por terceiro que tenha acesso ao histórico. O débito sai da lista de pendências e passa a constar como risco aceito. Reavaliar se a fatura do cnpja apresentar consumo anômalo.

**Parecer externo em arquivo.** O documento do especialista está em andamento e será arquivado em `validation/evidence/` quando disponível. Não bloqueia o fechamento da onda nem a abertura da seguinte — a aprovação de produto já está declarada. Fica como pendência documental aberta da trilha de auditoria do NRF §9.3.

### 7. Faixas de farol do DRM passam a ser parametrizáveis

A `STORY-052` estava bloqueada à espera das faixas de semáforo validadas pela EBC. Decisão do PO: **não esperar**. A estória entrega a estrutura paramétrica com as faixas em estado `nao_calibrada`, e a EBC popula os valores quando os tiver — edição de arquivo de configuração versionado, sem mudança de código.

A regra de segurança permanece intacta e é o que torna a decisão viável: **faixa não calibrada não produz farol.** O módulo pode ir a homologação sem semáforo nenhum, porque a demonstração, os comparativos e a leitura em prosa já entregam valor sozinhos. O que não se pode fazer é mostrar um semáforo baseado em palpite — dizer a um microempresário que a margem dele está vermelha sem base validada é o dano que o NRF §9.3 existe para evitar.

Com isso, **nenhuma estória do EPIC-010 está bloqueada.**

## Consequências

### Positivas

- A onda fecha e a onda 2 abre com estado confiável.
- O beta está desbloqueado depois de mais de dois meses travado.
- Seis pendências fantasma saíram da lista.
- A infraestrutura está mais madura do que o índice sugeria: homologação em VPS nova, backup automatizado com alerta, CVEs corrigidos, pipeline mais rápido.
- **Nenhuma estória do EPIC-010 fica bloqueada** — a parametrização das faixas removeu a última dependência externa do caminho crítico.

### Negativas / trade-offs aceitos

- **A onda 1 fecha sem validar sua hipótese central.** É o custo real do gate normativo ter ficado dois meses parado.
- O EPIC-003 fica sem data firme.
- O produto segue sem marco `v1.0.0` e sem ambiente de produção.

## Sinais de revisão

- **Se o beta de Indústria voltar negativo** (menos de 10 Robertos concluindo, ou NPS abaixo de 30), o `PDR-003` precisa ser reaberto quanto ao posicionamento: se nem o Diagnóstico nem o DRM prendem o usuário, o problema é anterior a ambos.
- **Se a fatura do cnpja apresentar consumo anômalo**, o risco aceito no item 6 se materializou e a rotação deixa de ser opcional.
- **Se o próximo sprint também não fechar na data**, a correção do item 5 não pegou, e o problema é de cadência, não de disciplina — cabe repensar o tamanho do sprint.
