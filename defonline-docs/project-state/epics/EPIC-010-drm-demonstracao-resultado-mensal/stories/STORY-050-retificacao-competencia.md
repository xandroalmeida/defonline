---
story_id: STORY-050
slug: retificacao-competencia
title: "Retificação de competência — corrigir um mês já lançado sem perder o histórico"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: done
owner_agent: programador (claude-sonnet-5-5)
created_at: 2026-08-04
updated_at: 2026-11-05
estimated_session_size: M
checkpoint: CP2
---

# STORY-050 — Retificação de competência

## Contexto

O usuário lança setembro na primeira semana de outubro e, ao conciliar o extrato, descobre que errou a taxa da maquininha. Sem retificação, ele tem três opções ruins: conviver com o número errado, abandonar o módulo, ou recomeçar do zero. Todas custam a recorrência que é a métrica primária do épico.

A mecânica de versionamento foi decidida na STORY-045. Esta estória a materializa.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: **`decisions/adr/ADR-007-modelo-dados-drm-versionamento-retificacao.md`** (**contrato desta estória** — leia §*O requisito de produto, demonstrado*, que percorre exatamente este cenário linha a linha no banco); `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` §K.9; `decisions/idr/IDR-010-*`

**Sequência transacional já fechada pelo `ADR-007`** — reproduza-a, não a reinvente:

1. Reabrir cria rascunho com `origem = 'retificacao'` e `base_versao = N`, semeado com a vigente. Rascunho abandonado **nunca** toca na versão vigente.
2. No submit, numa transação: `SELECT … FOR UPDATE` da vigente → confere `base_versao == versao` da vigente (concorrência otimista; divergiu, recusa e pede reabertura) → confere que o `payload_hash` **mudou** (retificação no-op é bloqueada, confirmado pelo PO) → `INSERT versao = N+1` → `UPDATE` do `substituido_por_id` da versão N → audit.
3. `AuditLogger` com `action = 'drm.lancamento.retificado'`, `before/after` = `{versao, payload_hash}`. **Não** copie o payload financeiro para o audit — a versão anterior já é registro recuperável; duplicá-la criaria duas fontes da verdade.

## O quê

Permitir que o usuário corrija uma competência já fechada, preservando a versão anterior e sinalizando a retificação onde o número aparece.

## Por quê

Confiança. Um número que não pode ser corrigido é um número em que não se confia — e uma série histórica construída sobre erros conhecidos é pior do que série nenhuma.

## Critérios de aceite

- [ ] **CA-1 (Entrada):** a partir da demonstração de um mês fechado e a partir da série histórica, o usuário aciona a retificação e reabre o lançamento com os valores atuais preenchidos.
- [ ] **CA-2 (Preservação):** ao confirmar, a versão anterior é preservada conforme a decisão da STORY-045 e permanece recuperável. Nada é sobrescrito de forma destrutiva.
- [ ] **CA-3 (Recálculo da competência retificada):** a demonstração daquela competência passa a refletir os novos valores, com novo snapshot e nova versão. **Nenhuma outra competência é recalculada.**
- [ ] **CA-4 (Sinalização):** a demonstração e a série histórica indicam que aquele mês foi retificado, com data da última retificação. O usuário consegue ver quantas vezes o mês foi corrigido. Tudo sai da linha vigente, sem coluna extra e sem `COUNT`: retificado = `versao > 1`; quantas vezes = `versao - 1`; quando = `gerado_em`.
- [ ] **CA-5 (Efeito nos comparativos):** retificar um mês altera os comparativos dos meses seguintes que o usam como base. Esse recálculo derivado é esperado e deve ser consistente — sem valores obsoletos em cache. **Pelo `ADR-007` isso é impossível de violar:** comparativos não são persistidos, são derivados na leitura sobre as versões vigentes. Persisti-los obrigaria a reescrever os snapshots dos meses seguintes, que é o recálculo retroativo que o épico proíbe.
- [ ] **CA-6 (Mesmas regras):** todas as regras bloqueantes e alertas do Anexo K §K.6 valem na retificação, exceto B1 (competência duplicada), que não se aplica.
- [ ] **CA-7 (Auditoria):** cada retificação é registrada com autor, momento e origem, no padrão de audit log já existente no produto.
- [ ] **CA-8 (Escopo):** só o dono retifica. Cross-tenant retorna 404 silente.
- [ ] **CA-9 (Testes):** feature cobrindo retificação simples, retificação múltipla do mesmo mês, efeito nos comparativos do mês seguinte, e preservação da versão anterior; E2E percorrendo lançar → retificar → conferir sinalização.
- [ ] **CA-10 (Cobertura):** ≥ 80%; ≥ 98% no que for regra de negócio.

## Fora de escopo

- Tela de comparação lado a lado entre versões. **O `ADR-007` tornou isso barato** — cada versão é um registro completo, então comparar é ler duas linhas. Fica registrado como oportunidade: **proponha ao PO, não implemente por conta.**
- Exclusão de lançamento. Não está no MVP; se o usuário quiser zerar um mês, ele retifica com zeros.
- Retificação em lote.

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`.

## Dependências

- **Bloqueada por:** STORY-045, STORY-047, STORY-048, STORY-049.
- **Bloqueia:** STORY-051 (a série precisa saber exibir mês retificado).

## Decisões já tomadas (não as reabra)

- **ADR-007** (decisão da STORY-045) — mecânica de versionamento. Inclui, ratificados pelo PO: retificação no-op é bloqueada; **não** há campo "motivo da retificação"; **não** há exclusão de lançamento no MVP (zerar um mês = retificar com zeros).
- IDR-010 — sem recálculo retroativo de competências não retificadas. Retificar **não é** recálculo retroativo: é ato novo do usuário, calculado com o motor vigente no momento e carimbado com ele. Por isso a cadeia de versões de um mesmo mês pode exibir versões de motor diferentes — isso é rastreabilidade, não inconsistência.
- PDR-003, decisão 7.

## Definição de Pronto

- [ ] CA-1 a CA-10 passam.
- [ ] Pipeline verde; deploy em homologação verificado.
- [ ] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Plano (2026-11-05, antes de codar)

Lido: estória, ADR-007 (§1 retificação, §"O requisito de produto, demonstrado", esquema), IDR-016 (UPDATE do ponteiro antes do INSERT, FK adiável), IDR-010 (aditivo do DRM), Anexo K §K.9, specs `STORY-046-lancamento-mensal` §4.9/§5, `STORY-046-demonstracao-mes` §4.9/§7, notas das STORY-047, 048 e 049.

Plano: (1) `RetificarLancamento` (lock, base_versao, hash, ponteiro, INSERT, audit); (2) formulário do lançamento reaproveitado numa rota `drm.corrigir` (rascunho `origem=retificacao`); (3) ligar "Corrigir {mês}" (B1) e "Corrigir este mês"; (4) selo/rodapé na demonstração; (5) Pest da mecânica, da tela, concorrência com dois processos e Dusk 360x800.

### Decisões tomadas

- **Uma action, `App\Actions\Drm\RetificarLancamento`**, na ordem do IDR-016: `FOR UPDATE` da vigente → `base_versao == versao` (senão `RetificacaoConflitante`) → `payload_hash` mudou (senão `RetificacaoSemMudanca`) → `UPDATE` do ponteiro da N para o id da N+1 → `INSERT` da N+1 → audit, tudo numa transação (falha no audit desfaz tudo; teste). O motor roda com a versão de HOJE e carimba o snapshot (IDR-010). Nenhuma outra linha é tocada (teste compara byte a byte as demais competências e a de outra empresa).
- **Audit** `drm.lancamento.retificado`: `subject = DrmLancamento` (id da N+1), `before/after = {versao, payload_hash}`, `context = {origem: 'web', empresa_analisada_id, competencia}`; autor e momento vêm de `actor_id`/`occurred_at` e o `request_id` do `AuditLogger`. Sem payload financeiro (teste prova que o JSON do log não contém valor nem código de campo). Li "origem" do CA-7 como canal do ato (`web`) + `request_id`; se o PO quis dizer outra coisa, é uma linha.
- **Mesmo formulário, mesma componente Livewire** (`Lancamento`), com a rota `GET /empresas/{empresa}/drm/{competencia}/corrigir` (`drm.corrigir`). Modo retificação = `#[Locked] retificando / base_versao / competencia_fixa / setor_fixo` (o cliente não troca competência, setor nem versão-base; teste de `CannotUpdateLockedProperty`). Spec §4.9 inteira: título "Corrigir setembro/2026", aviso permanente `info`, valores da vigente **sem** marca de herança (e sem a orientação "primeiro mês"), B1 fora, conflito com `Abrir a versão atual` (sem forçar; o botão de fechar fica desabilitado) e no-op como `info` com `Voltar à demonstração`.
- **Rascunho de retificação**: abrir cria `origem='retificacao'`, `base_versao=N` semeado com a vigente (reusa `PreenchimentoInicial` com os modos DA VIGENTE, então tudo vem preenchido e nenhum total é inventado). Abandonar não toca a vigente (teste). Um rascunho `inicial` esquecido da mesma competência é ignorado e substituído. Um rascunho retomado depois de a vigente avançar mantém a `base_versao` antiga e só aparece como conflito no submit, como a spec manda; `Abrir a versão atual` descarta esse rascunho e leva à demonstração (sem isso, reabrir carregaria o mesmo rascunho velho em laço).
- **Setor e modos vêm da versão vigente**, não da empresa/preferência de hoje: a estrutura de campos depende do setor (ADR-007 grava o setor no lançamento justamente para isso), e abrir e fechar sem mexer em nada tem que dar no-op (teste item a item).
- **Retificar não grava `drm_preferencias`**: um mês antigo corrigido não deve mudar o que o próximo mês abre. Decisão local; contraria nada na spec, que só diz "o modo escolhido vale para os próximos meses" para o lançamento.
- **Sinalização** só da linha vigente (`DrmLancamento::foiRetificado/vezesRetificado/retificadoEm` = `versao > 1`, `versao - 1`, `gerado_em`): pill `Retificado` ao lado do título (`drm-demonstracao-retificado`; o `x-page-header` ganhou um slot opcional `badge`, aditivo) e `… · corrigido N× — última correção em dd/mm/aaaa` no rodapé. Texto do pill em `config/drm/tela.php`.
- **CA-5 (comparativos)**: a STORY-051 ainda não existe, então não há código de comparativo para testar. O teste mostra o que o ADR-007 promete: o mês seguinte não é reescrito (byte a byte), e uma variação derivada na leitura das vigentes muda com a retificação. Quando a 051 chegar, vale uma asserção equivalente sobre a leitura real.
- **Testes antigos alterados**: `DemonstracaoTest` ("Corrigir este mês está desligado" → agora é link para `drm.corrigir`) e `LancamentoTest` B1 (acrescentei o `href` do Corrigir; nada enfraquecido). A STORY-047/049 deixaram esses botões como "Em breve" justamente para esta estória.

### Descobertas

- **Bug real achado pelo teste de concorrência (dois processos, COMMIT real):** no `READ COMMITTED`, quem espera o `FOR UPDATE` reavalia o `WHERE substituido_por_id IS NULL` sobre a linha já substituída e volta **vazio** — a primeira versão devolvia `CompetenciaNaoLancada` ao perdedor da corrida em vez do conflito. Corrigido (vazio + existe versão = conflito) e registrado como aditivo do IDR-016. Esse ramo só é exercitado pelo teste de concorrência (processos filhos), por isso `RetificarLancamento` marca 98,5% e não 100%.
- Num teste normal (transação de teste) a FK adiável nunca é conferida; só o teste de concorrência (commit real) a exercita.
- Dusk: o link "editar" da conferência, depois de `scrollIntoView` no topo, é coberto por um elemento do cabeçalho do shell (avatar); o teste centraliza o elemento. Não investiguei se há um problema de UX real no shell (sticky header cobrindo âncoras).
- Pergunta ao PO (não bloqueia): "Fechar o mês" também é o rótulo do botão na retificação (a spec não dá outro). Talvez "Salvar a correção" fosse mais claro.

### Bloqueios encontrados

Nenhum.

### IDRs criados

Nenhum novo. Aditivo no `IDR-016` (lock do `FOR UPDATE` no READ COMMITTED).

### Cobertura final

- Suíte `All` com `php -d memory_limit=-1 ... --coverage --min=80`: **2026 passed** (45.146 assertions); cobertura total **98,4%**. `Actions/Drm/RetificarLancamento` 98,5% (a linha de fora é o ramo do conflito de concorrência, coberto pelo teste de dois processos), `RetificacaoConflitante`/`RetificacaoSemMudanca`/`CompetenciaNaoLancada` 100%, `Livewire/Drm/Demonstracao` 100%, `Livewire/Drm/Lancamento` 95,6%, `Models/DrmLancamento` 95,8%. `app/Domain` não foi tocado (a regra vive na action, coberta por feature).
- Dusk completo: **60 passed** (510 assertions), incluindo `DrmRetificacaoBrowserTest` (lançar → corrigir → no-op → corrigir de verdade → selo e rodapé "corrigido 1×" → de novo "2×", em 360×800 sem scroll horizontal; B1 com Corrigir clicável; 404 cross-tenant).
- Pint e PHPStan limpos.
- **Não verificado:** deploy/homologação (fora da pista); navegação só por teclado e varredura axe da tela de retificação; "a partir da série histórica" (CA-1) — a série é a STORY-051, a rota `drm.corrigir` já existe para o botão `Corrigir` dela.
