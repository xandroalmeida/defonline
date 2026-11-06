---
story_id: STORY-095
slug: correcao-digitacao-e-achados-da-validacao-drm
title: "Correção — dígitos perdidos ao digitar e achados da validação do DRM"
epic_id: EPIC-010
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: in_review
owner_agent: claude-sonnet-5-5 (pista DRM)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-095 — Correção — dígitos perdidos ao digitar e achados da validação do DRM

Origem: relatório `validation/report.md` da STORY-057 (rodada 1, reprovada por D-1). Leia a reprodução e as matrizes lá. A causa em D-1 é hipótese: confirme antes de corrigir.

## Critérios de aceite

- [x] **CA-1:** **D-1 (bloqueante):** digitar tecla a tecla em campo monetário ainda não preenchido, em 360x800, nunca perde dígitos, em qualquer combinação de intervalo entre teclas e latência da matriz do relatório, inclusive trocando de campo no bloco de 21 itens. O valor gravado é o digitado. Cubra também rascunho, alerta e bloqueio, que dependem do valor atualizado no servidor.
- [x] **CA-2:** Há teste Dusk que digita tecla a tecla com latência emulada, em campo novo e já preenchido, no lançamento e na retificação. Ele falha sem a correção. O roteiro do Validador em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v57/V57ValidadorBrowserTest.php` pode ser a base.
- [x] **CA-3:** O mesmo padrão em `resources/views/livewire/diagnostico/partials/campo-brl.blade.php` e em qualquer outro campo com máscara e `wire:model.live` é testado e corrigido, se tiver o mesmo defeito. O PDF e o motor do Diagnóstico não mudam.
- [x] **CA-4:** **D-2:** linha omitida por estar zerada é diferente de item agregado em `SerieDrm`: L7b tem comparativos corretos, e o acumulado segue disponível a partir do 1º lançamento.
- [x] **CA-5:** **D-4 e O-5:** o botão `.btn--link` atinge contraste AA, com medida via axe; "1 mês lançado" no singular.
- [x] **CA-6:** **D-6:** corrigir o Anexo K (§K.11, D16, §K.3.6, §K.12 e a contagem de campos) e `estrutura-drm.md` §1.2.
- [x] **CA-7:** **O-1:** o aceite de um alerta cai quando o valor que o gerou muda, e o usuário precisa aceitar de novo. **O-3:** o botão "Fechar o mês" fica `disabled` com alerta pendente. **O-4:** o bloco "Teve algo fora do movimento normal" não recolhe sozinho com a resposta do servidor.

## Notas do agente

**Causa de D-1 (confirmada, não só hipótese).** Reproduzido tecla a tecla com latência emulada e rastreado no `livewire.js` 4.4.8 (log de cada escrita no `<input>` com pilha). A hipótese do relatório estava certa e é mais precisa: chave AUSENTE em `valores` vira, no cliente, atualização do array INTEIRO (`REQ {"valores":{...}}`), e a conciliação da resposta troca o objeto reativo; o efeito do `wire:model` (`_x_forceModelUpdate`) regrava o `<input>` com o valor que a requisição levou (`0,01`), apagando o `2` digitado durante o voo. Campo já preenchido envia só `valores.D01` e nada é regravado. Segundo mecanismo achado ao cumprir o CA-3: o `updates` consolidado no pai leva uma REFERÊNCIA ao estado reativo (lista de sócios), e o que se digita depois do envio muda o "enviado". Detalhes e alternativas descartadas na IDR-034.

**Correção.** `Lancamento::dehydrate()` mantém D01..D42 em `valores` (vazio equivale a ausente no domínio; o rascunho continua sem os vazios) e `resources/js/app.js` copia o `updates` de toda mensagem do Livewire no envio.

**Testes e CAs.**
- CA-1: `DrmDigitacaoTeclaATeclaBrowserTest` (Dusk, 360x800, CDP `Network.emulateNetworkConditions`): campo novo em 6 combinações de latência e intervalo da matriz do relatório; bloco de 21 itens trocando de campo em 4 combinações; campo já preenchido; retificação (campo preenchido e item recém-detalhado); bloqueio B4 citando os dois valores que o servidor viu; alerta A3 citando o prejuízo calculado; rascunho conferido no banco em todos. Valor gravado = digitado. Pest: `LancamentoTest` ("D-1: todo campo existe em `valores`").
- CA-2: o mesmo arquivo. Sem a correção falhou: `134,56` (campo novo), `17,02` (bloco de 21) e retificação. Campo já preenchido passou nos dois estados, como no relatório.
- CA-3: `DiagnosticoDigitacaoTeclaATeclaBrowserTest`. `campo-brl.blade.php` (BRL), dias e % NÃO têm o defeito (propriedades escalares): passam sem mudança. O CPF/nome do sócio TINHA o defeito (`52998224725` virou `599.822.472-5`) e foi corrigido pelo `app.js`. PDF e motor intactos. `documento` de `empresa/cadastrar` não foi testado tecla a tecla (propriedade escalar, mesma máscara; fica como não verificado).
- CA-4 (D-2): `MesDaSerie::nominal()/percentual()` tratam a L7b omitida (D41 = D42 = 0 no payload) como zero; `SerieDrm` usa os dois. `SerieDrmTest`, bloco "linha omitida por estar zerada x item agregado". Item agregado continua `grupo_agregado`.
- CA-5 (D-4, O-5): `.btn--link` usa `tertiary-hover`; `DrmAchadosDaValidacaoBrowserTest` mede 4,445:1 antes e passa depois; axe-core 4.10.2 (o do Validador) com `color-contrast` na demonstração: 0 violações (rodada avulsa, arquivo descartado). Plural em `Formato::mesesLancados()`. O teste antigo `ComparativosDemonstracaoTest` afirmava "1 meses lançados" e foi corrigido para "1 mês lançado".
- CA-6 (D-6): Anexo K (§K.11 coluna D, D16 remete a D40, §K.3.6 e §K.12, 7 obrigatórios + 2 opcionais) e `estrutura-drm.md` §1.2 (L8a/L8b no modo dentro).
- CA-7: O-1: `Lancamento` guarda a assinatura (campo, valor e texto) de cada alerta aceito; mudou, volta a pedir (`LancamentoTest`, "O-1"). Aceite sem assinatura (rascunho antigo) também volta a pedir. O-3: causa confirmada diferente da hipótese: o `wire:submit` desabilita o formulário durante o "Próximo" e, ao terminar, desfaz no mesmo nó que o morph reaproveitou como "Fechar o mês", apagando o `disabled` do servidor; `wire:key` próprio nos dois botões. O-4: o bloco aberto à mão agora avisa o servidor (`nao_operacionais_aberto`, sem requisição). Os dois em `DrmAchadosDaValidacaoBrowserTest`; falharam antes.

**Desvios e dúvidas.** Pest antigos que comparavam `valores` inteiro (`assertSet('valores', [])`, três) passaram a filtrar o vazio com o helper `preenchidos()`: testam o mesmo comportamento, a representação é que mudou. Nenhuma decisão de produto pendente. IDR-034 criada.

