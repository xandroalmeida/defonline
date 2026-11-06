---
story_id: STORY-095
slug: correcao-digitacao-e-achados-da-validacao-drm
title: "Correção — dígitos perdidos ao digitar e achados da validação do DRM"
epic_id: EPIC-010
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-095 — Correção — dígitos perdidos ao digitar e achados da validação do DRM

Origem: relatório `validation/report.md` da STORY-057 (rodada 1, reprovada por D-1). Leia a reprodução e as matrizes lá. A causa em D-1 é hipótese: confirme antes de corrigir.

## Critérios de aceite

- [ ] **CA-1:** **D-1 (bloqueante):** digitar tecla a tecla em campo monetário ainda não preenchido, em 360x800, nunca perde dígitos, em qualquer combinação de intervalo entre teclas e latência da matriz do relatório, inclusive trocando de campo no bloco de 21 itens. O valor gravado é o digitado. Cubra também rascunho, alerta e bloqueio, que dependem do valor atualizado no servidor.
- [ ] **CA-2:** Há teste Dusk que digita tecla a tecla com latência emulada, em campo novo e já preenchido, no lançamento e na retificação. Ele falha sem a correção. O roteiro do Validador em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v57/V57ValidadorBrowserTest.php` pode ser a base.
- [ ] **CA-3:** O mesmo padrão em `resources/views/livewire/diagnostico/partials/campo-brl.blade.php` e em qualquer outro campo com máscara e `wire:model.live` é testado e corrigido, se tiver o mesmo defeito. O PDF e o motor do Diagnóstico não mudam.
- [ ] **CA-4:** **D-2:** linha omitida por estar zerada é diferente de item agregado em `SerieDrm`: L7b tem comparativos corretos, e o acumulado segue disponível a partir do 1º lançamento.
- [ ] **CA-5:** **D-4 e O-5:** o botão `.btn--link` atinge contraste AA, com medida via axe; "1 mês lançado" no singular.
- [ ] **CA-6:** **D-6:** corrigir o Anexo K (§K.11, D16, §K.3.6, §K.12 e a contagem de campos) e `estrutura-drm.md` §1.2.
- [ ] **CA-7:** **O-1:** o aceite de um alerta cai quando o valor que o gerou muda, e o usuário precisa aceitar de novo. **O-3:** o botão "Fechar o mês" fica `disabled` com alerta pendente. **O-4:** o bloco "Teve algo fora do movimento normal" não recolhe sozinho com a resposta do servidor.

## Notas do agente
