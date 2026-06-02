---
artifact: story-briefing
target_role: programador
story_id: STORY-038
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
written_by: po (Alexandro)
written_at: 2026-06-02
status: ready-for-pickup
---

# Briefing — STORY-038 (Sócios como lista dinâmica)

## Por que esta estória existe

O Edvan (analista financeiro EBC) testou o quiz e relatou que foi obrigado a **repetir o próprio CPF 3 vezes** para conseguir submeter, porque o quiz exige 3 sócios. Empresas unipessoais (perfil dominante do Roberto) não têm 3 sócios. Sem este ajuste, o dado do bloco 4 nasce contaminado e o uso real fica travado.

## Fonte autoritativa

- **Devolutiva:** 2 mensagens WhatsApp Edvan + áudio 2026-05-27 + `.docx` "DEF on line - MVP 270526 - observações ebc".
- **Spec:** Anexo A Q23 (que será aditivada — esta estória autoriza o aditivo).
- **Persistência:** IDR-010 (snapshot imutável; canonicalização determinística).

## Decisões de produto já tomadas

- **Mínimo:** 1 sócio obrigatório.
- **Máximo:** 10 (cap de sanidade, não regra externa).
- **Ações disponíveis:** adicionar, editar, remover (último não removível).
- **Schema:** `quiz_payload.socios = array<cpf>` (sem cargo/percentual nesta estória).
- **Canonicalização:** **alfabética pelo CPF** no `payload_hash` (independe de ordem de digitação).
- **Rascunhos em `quiz_rascunhos` pré-W26 em homol:** descartar. Sem migration de conversão.
- **Duplicidade de CPF na lista:** **bloqueante** ao submit.

## Decisões abertas — preciso escalar para mim (PO) antes ou durante a execução

1. **Bump de `motor_version` por mudança no schema do payload (1.2.0 → 1.3.0?).** Default: **não bumpar** — CPF não entra em indicador nem em texto de veredito; goldens do motor não são afetados. Confirme com Arquiteto se enxergar consequência colateral.
2. **IDR-010 aditivo.** Vale registrar um aditivo na IDR-010 explicando que listas (`socios[]`) são ordenadas alfabeticamente no canonicalize? Default: **sim, aditivo curto** no fim do arquivo da IDR-010 com data 2026-06-02. Se preferir IDR-011 separada, ok.
3. **Layout do componente em desktop.** Stack vertical (igual mobile, ocupando colunas estreitas) ou inline em grid? Default: **stack vertical sempre** — economiza esforço de layout duplo e mantém consistência. Se quiser inline, registre.

## Critério de sucesso visível

- Em homologação, Roberto consegue submeter o quiz com **1 sócio**.
- Ao adicionar/remover sócios, a UI não trava nem mostra erros falsos.
- O `payload_hash` é idempotente em relação à ordem de digitação dos sócios.
- Diagnósticos legados (versões 1.0.0–1.2.0) continuam abrindo sem erro.

## Fora de escopo

- Cargo/percentual do sócio.
- Enriquecimento via RFB por CPF.
- Migration de conversão de rascunhos antigos.

## Hand-off

Decisões de arquitetura, naming, paths, estrutura do canonicalizer e estratégia de teste ficam com você. As restrições não-negociáveis: IDR-010 (snapshot imutável), ordem alfabética no canonicalize, mín 1 / máx 10.

Para dúvidas de domínio ou ambiguidade da spec/Anexo A: me chama.

— PO (Alexandro)
