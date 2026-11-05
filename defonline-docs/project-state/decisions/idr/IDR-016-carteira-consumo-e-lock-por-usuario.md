---
idr_id: IDR-016
slug: carteira-consumo-e-lock-por-usuario
title: Carteira do Usuário — tabelas, consumo transacional com lock por Usuário e interface de concessão
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: programador (claude-sonnet-5-5)
related_story: STORY-073
related_adrs: []
related_idrs: []
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-016 — Carteira do Usuário: consumo transacional com lock por Usuário

## Contexto

A STORY-073 cria a carteira (cota da assinatura e lotes de crédito) e faz cada análise consumir saldo. As estórias seguintes (trial 074, planos 075, pagamentos 076/077/079, DRM 084) precisam conceder cota e créditos e ler o motivo de um bloqueio, então a forma das tabelas e da interface vira contrato.

## Decisão

> **Decidi que a carteira vive em `app/Domain/Cobranca`, o consumo roda dentro da transação do Diagnóstico sob `FOR NO KEY UPDATE` na linha do Usuário, e quem concede saldo usa apenas os métodos de `Carteira`.**

- **Regra pura** (`RegrasConsumo::decidir(SnapshotCarteira): Decisao`): cota primeiro, senão o lote ativo, com saldo e não vencido, que vence antes (desempate por compra e id); senão `Bloqueio` com `MotivoBloqueio`, data e cota do ciclo. Sem banco, testada em `tests/Domain`.
- **Tabelas:** `cobranca_config` (interruptor, linha única, desligado), `assinaturas` (o trial é uma assinatura com `trial = true` e `plano_id` nulo; `status` em `ativa|em_atraso|cancelada|expirada`), `lotes_credito`, `consumos_analise` (um por Diagnóstico, com `origem`, `assinatura_id` ou `lote_credito_id`, `empresa_analisada_id` e `quiz_id`). CHECKs recusam cota ou lote negativo e consumo sem origem; índice parcial único garante uma assinatura vigente (`ativa|em_atraso`) por Usuário; `diagnostico_id` e `quiz_id` únicos em `consumos_analise` garantem uma cobrança por análise.
- **Consumo:** `Carteira::consumir($usuarioId, $empresaId, $quizId, fn () => Diagnostico)`: lock, confere `quiz_id` já cobrado (`AnaliseJaConcluida`), decide, debita com `UPDATE ... WHERE saldo > 0`, roda o callback e grava o consumo. `CalcularDiagnostico` chama isso só com o interruptor ligado. Falha em qualquer passo desfaz o débito.
- **Concessão (interface para as estórias seguintes):** `concederCota`, `renovarCota`, `mudarStatus`, `concederCreditos`, `expirarLotesVencidos`. Ninguém mais escreve nessas tabelas.
- **Consulta:** `Carteira::verificar($usuarioId): Decisao`, sem lock, devolve o motivo (não um booleano) para a tela de bloqueio (`App\Support\Cobranca\TextoBloqueio`).
- **Interruptor ausente = desligado:** `CobrancaConfig::ligada()` devolve falso se a linha sumir, e `atual()` recria a linha desligada.

## Por quê

- O lock `FOR NO KEY UPDATE` na linha do Usuário serializa só os consumos do mesmo Usuário e não conflita com o `FOR KEY SHARE` que as FKs tomam, então o resto do sistema não espera por ele. Provado com processos PHP separados no Postgres (`tests/Concorrencia`): sem o lock, os cinco testes falham.
- Consumir dentro da transação do Diagnóstico torna impossível cobrar sem emitir, ou emitir sem cobrar (CA-5, CA-6).
- Trial como assinatura com flag evita um terceiro caminho na regra de consumo e preserva a informação "já teve trial" depois que ele expira (status `expirada`), o que a elegibilidade precisa.

## Alternativas consideradas

- **Lock por linha da assinatura e do lote:** não cobre a primeira concessão nem a corrida entre cota e lote; mais difícil de provar.
- **Status `Trial` como valor do status (arquitetura §4.1):** perde a distinção quando o trial vence e vira `expirada`. Desvio consciente da lista de status do documento de arquitetura.
- **Advisory lock (`pg_advisory_xact_lock`):** funciona, mas esconde a dependência da tabela; o lock de linha é inspecionável e some com a transação.

## Consequências

### Para outros agentes
- STORY-074 (trial): `Carteira::concederCota(..., trial: true)`; a elegibilidade por CPF fica com ela (aqui só vale "este Usuário nunca teve trial").
- STORY-076/077/079/082: `concederCreditos`, `concederCota`, `renovarCota`, `mudarStatus`. Concessão de assinatura vigente quando já existe outra lança `AssinaturaVigenteJaExiste`: troca de plano encerra a anterior antes (`mudarStatus(..., Expirada)`).
- STORY-084 (DRM): reaproveita `TextoBloqueio` e o componente `x-bloqueio-sem-saldo` (prefixo `screen-bloqueio-raiox`); lançar no DRM não consome cota.
- Rotas das ações do bloqueio ficam em `TextoBloqueio::ROTAS`; quem criar a tela usa o nome ali e a ação aparece sozinha.
- Teste de concorrência com dados efetivados: `tests/Concorrencia` (sem a transação do `RefreshDatabase`; limpeza com o superusuário do ambiente de teste).

### Para o projeto
- Cobrança fora do ar por padrão; ligar é um ato do PO na tela de planos.
- Lotes vencidos só ganham a marca `expirado` quando um consumo conclui ou quando `expirarLotesVencidos()` roda; a decisão de consumo ignora vencido pela data, então a marca é só para relatório. Uma varredura agendada fica para quando houver painel de saldo.

### Trade-offs aceitos
- Um lock por Usuário serializa análises simultâneas do mesmo Usuário (raras, segundos).
- O motor roda antes da transação, então duas conclusões simultâneas podem calcular duas vezes; só uma é gravada e cobrada.

## Como verificar

`tests/Concorrencia/CarteiraConcorrenciaTest.php` (processos reais), `tests/Domain/Cobranca`, `tests/Feature/Cobranca`. Remover a linha `travar()` de `Carteira::consumir` faz os cinco testes de concorrência falharem.
