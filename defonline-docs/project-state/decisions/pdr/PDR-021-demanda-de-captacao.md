---
pdr_id: PDR-021
slug: demanda-de-captacao
title: Demanda de captação avisa a equipe e promete retorno em 5 dias úteis
status: accepted
decided_at: 2026-11-18
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-002, EPIC-011]
related_adrs: []
related_idrs: []
---

# PDR-021 — Demanda de captação

## Contexto

O bloco 4 do quiz pergunta se a empresa precisa captar recursos (Q17) e, quando sim, coleta o valor, o endividamento, as vendas com cartão e os sócios. Até aqui a resposta ficava parada no diagnóstico: ninguém da equipe era avisado. O PO pediu que a tela informe o retorno da análise em 5 dias úteis, e isso só faz sentido se alguém do outro lado receber a demanda.

## Decisão

1. Todo diagnóstico concluído com Q17 = sim registra uma demanda de captação.
2. **Consentimento opt-out.** No bloco 4, com captação, aparece a autorização para a equipe analisar a demanda e entrar em contato pelo e-mail ou WhatsApp cadastrado. Ela vem marcada. Desmarcada, a demanda fica registrada, mas ninguém é avisado nem contatado, e o prazo não é prometido. Isso também atende à ressalva da PDR-019 sobre usar o contato para comunicação.
3. **Prazo:** retorno em até 5 dias úteis, mostrado no bloco 4, no resultado do diagnóstico e no e-mail de confirmação ao cliente.
4. **E-mail da equipe é configurável** no backoffice. Sem e-mail cadastrado, nenhum e-mail sai para a equipe. A demanda continua na lista do backoffice e o alerta da equipe (Telegram, só com o id) continua saindo.
5. A análise é gratuita e não rende comissão para o DEFOnline nem para parceiros.

## Consequências

- A equipe passa a ter compromisso de prazo. A lista do backoffice mostra a data limite de cada demanda. Para o cálculo interno, dia útil é de segunda a sexta, sem feriados.
- Ainda não há fila com status (nova, em análise, respondida). Ela entra quando houver volume.
- A autorização não entra no payload do motor e não muda o hash do diagnóstico.
