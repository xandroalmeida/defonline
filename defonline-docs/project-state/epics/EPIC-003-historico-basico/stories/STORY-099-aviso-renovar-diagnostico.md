---
story_id: STORY-099
slug: aviso-renovar-diagnostico
title: "Aviso para renovar o diagnóstico na plataforma e por e-mail"
epic_id: EPIC-003
sprint_id: null
type: feature
target_role: programador
requires_design: true
status: backlog
owner_agent: null
created_at: 2026-11-06
updated_at: 2026-11-06
estimated_session_size: M
---

# STORY-099 — Aviso para renovar o diagnóstico na plataforma e por e-mail

Origem: pedido do PO em 2026-11-06. O requisito está em `especificacao/V2/especificacao-funcional.md` §4.12.1.

## Critérios de aceite

- [ ] **CA-1:** O Usuário escolhe 3, 6, 9 ou 12 meses para cada Empresa Analisada. Sem escolha, vale 6 meses.
- [ ] **CA-2:** A contagem parte do último diagnóstico concluído da Empresa. Empresa sem diagnóstico concluído não recebe aviso.
- [ ] **CA-3:** Com o prazo vencido, a Empresa mostra um alerta com a ação de fazer um novo diagnóstico. O alerta some com um novo diagnóstico ou quando o Usuário o dispensa.
- [ ] **CA-4:** O Usuário recebe um e-mail quando o prazo vence e um lembrete 30 dias depois, sem nenhum outro envio no mesmo ciclo, mesmo que a rotina rode de novo ou atrase.
- [ ] **CA-5:** Dispensar o alerta não cancela o e-mail pendente. Fazer um novo diagnóstico cancela e reinicia a contagem.
- [ ] **CA-6:** Todo e-mail tem texto e HTML, cita a Empresa e traz um link de descadastro que funciona sem login. Descadastrado não recebe mais esses e-mails, e o alerta na plataforma continua.
- [ ] **CA-7:** Conta excluída, anonimizada ou com e-mail não confirmado não recebe e-mail.
- [ ] **CA-8:** O Usuário nunca vê alerta nem recebe aviso de Empresa de outra conta, e o "Logar como" não altera prazo, dispensa nem descadastro.
- [ ] **CA-9:** O alerta e a escolha do prazo seguem o RNF-UI-02.
- [ ] **CA-10:** Quem já estiver com o prazo vencido no lançamento recebe um único e-mail, sem o lembrete de 30 dias, em cada Empresa.
- [ ] **CA-11:** Cada Empresa gera o próprio e-mail, sem agrupar Empresas, mesmo que os prazos vençam no mesmo dia.

## Pendências antes de produção

- **Jurídico:** validar o envio como legítimo interesse com descadastro. A especificação exige opt-in para marketing, e este e-mail convida a uma ação que pode consumir crédito.
- **Designer:** definir onde ficam a escolha do prazo e o alerta.

## Decisões do PO (2026-11-06)

- O Usuário escolhe o prazo por Empresa, em vez de avisos fixos nos quatro marcos ou de um prazo único definido pelo admin.
- Todos recebem o e-mail, com descadastro.
- São dois e-mails por ciclo: o aviso e um lembrete 30 dias depois.
- No lançamento, prazo já vencido gera um único aviso.
- O e-mail é por Empresa, sem agrupamento.

## Fora de escopo

- Metas, alertas de deterioração de indicador e o painel de evolução do roadmap (§1.2).
