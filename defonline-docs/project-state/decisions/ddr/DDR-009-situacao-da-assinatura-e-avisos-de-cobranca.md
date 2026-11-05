---
id: DDR-009
title: Situação do plano em seis estados com vocabulário fixo, e avisos de cobrança sem urgência fabricada
status: proposed
created_at: 2026-11-05
decided_at: ~
approved_by: ~
supersedes: ~
superseded_by: ~
related_ddrs: [DDR-001, DDR-005]
related_adrs: []
related_pdrs: [PDR-020]
scope: transversal (cobrança)
affects_screens: [SCREEN-STORY-070-trial, SCREEN-STORY-070-meu-plano, SCREEN-STORY-070-bloqueio-sem-saldo, SCREEN-STORY-070-planos-e-precos, SCREEN-STORY-070-troca-de-plano, SCREEN-STORY-070-cupom-no-checkout]
---

# DDR-009 — Situação do plano e avisos de cobrança

## Contexto

A cobrança cria estados que o produto não tinha: teste em andamento, plano ativo, renovação cancelada com acesso até uma data, em atraso, encerrado, sem nada. Eles aparecem em Meu plano, na página de planos, no bloqueio da análise e em avisos pelo app. Sem vocabulário fixo, "cancelado" vai significar "ainda tenho acesso" numa tela e "perdi o acesso" em outra, e o usuário vai abrir reclamação por acesso que acreditava ter.

Leitura: STORY-070, 073, 074, 079, 081, 083; spec funcional §2.3, §2.4, §2.5, §3.7; DDR-001 (três severidades) e DDR-005 (estado não culpa ninguém); `voice-and-tone.md`.

## Forças (drivers)

- **Cancelamento claro** (alto): CA-4 da STORY-070 pede achar fácil e saber até quando vai o acesso.
- **Calma** (alto): dinheiro e falha de pagamento já são tensos; urgência fabricada piora.
- **Reuso** (médio): `aviso-inline` e `pill` cobrem; não criar componente de banner.

## Opções consideradas

### Opção A — Rótulos livres por tela
Contras: o descompasso descrito no contexto.

### Opção B — Seis situações com rótulo, frase e severidade fixos (escolhida)

### Status quo
Não existe.

## Decisão

> **Opção B.**

1. **Seis situações**, cada uma com `pill` neutro (sem token de farol) e uma frase factual:

| Situação | Pill | Frase | Acesso às funções pagas |
|---|---|---|---|
| Teste | `Teste` | "Seu teste termina em {data}." | sim |
| Ativo | `Ativo` | "Renova em {data}." | sim |
| Renovação cancelada | `Cancelado` | "Você cancelou a renovação. Seu acesso continua até {data}." | sim, até {data} |
| Em atraso | `Em atraso` | "Não conseguimos cobrar a renovação. Atualize o cartão para manter o acesso." | sim durante as retentativas |
| Encerrado | `Encerrado` | "Seu plano terminou em {data}. Seus diagnósticos continuam disponíveis." | não |
| Sem plano | (sem pill) | oferta de planos | não |

2. **"Cancelado" nunca aparece sem a data de fim do acesso na mesma linha.** É a regra que impede o mal-entendido do contexto.
3. **Aviso é `aviso-inline`**, com a severidade de DDR-001 pela consequência real: `info` (fim do teste em 2 dias, renovação em 7 dias, desconto acaba em 7 dias, créditos perto de vencer), `alerta` (em atraso), `bloqueio` (sem saldo, plano encerrado). Nenhum aviso de cobrança usa exclamação, "último aviso" ou contagem regressiva em segundos.
4. **Um aviso de cobrança por vez** no topo da Home e de Meu plano, o de maior severidade; os demais ficam em Meu plano. Quem tem teste acabando e créditos vencendo vê o primeiro; o segundo o espera em Meu plano.
5. **Todo aviso termina numa ação que resolve**, em verbo: `Assinar um plano`, `Atualizar cartão`, `Comprar créditos`. Nunca "Saiba mais".
6. **Cancelar fica na primeira dobra de Meu plano**, no cartão do plano, como botão `ghost` com rótulo literal `Cancelar renovação`. Nunca em menu de três pontos, nunca no rodapé da página, nunca dependente de e-mail para suporte.
7. **Cancelar é reversível até o fim do ciclo** e a tela diz isso: no estado `Cancelado` a ação principal do cartão passa a ser `Reativar renovação`.
8. **A explicação nunca culpa** ("Não conseguimos cobrar", não "Seu pagamento falhou por sua culpa") e não afirma o motivo do banco, que o provedor nem sempre informa.

## Consequências

### Positivas
- "Cancelado" passa a significar uma coisa só. A pergunta "até quando eu posso usar?" tem resposta em toda tela que mostra a situação.

### Negativas / trade-offs aceitos
- Um aviso por vez esconde o segundo. Aceito: dois avisos concorrentes competiriam por atenção sem que o usuário resolvesse nenhum.

### Impacto no Design System
- Nenhum componente novo. Vocabulário entra em `voice-and-tone.md` quando aceito.
