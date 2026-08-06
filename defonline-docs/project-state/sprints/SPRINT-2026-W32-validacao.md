---
sprint_id: SPRINT-2026-W32
type: validation-report
escopo: estorias
validated_at: 2026-08-06
validated_by: validador (agente Claude — sessão W32)
verdict: approved_with_pending
rodada: 2
checklist_source: "CAs e DoD das STORY-052, STORY-059 e STORY-060 (não há checklist de épico — esta não é validação de fechamento de épico)"
---

# Relatório de Validação — SPRINT-2026-W32

## TL;DR

> **Veredito: APPROVED com pendências** (rodada 2).
> **Contagem:** 29 passes, 2 passes com ressalva, **0 fails**, 3 pendências fora do mandato do agente.
> **Próximo passo recomendado:** criar a tag `rc`, acompanhar o release e verificar em homologação os três itens pendentes. Só então as estórias vão a `done` e o `EPIC-002` é promovido.

**Duas rodadas.** A rodada 1 reprovou com dois fails não-bloqueantes — uma regressão de geometria do pill de farol que contrariava o próprio `DDR-006`, e um método público sem teste. Ambos foram devolvidos ao papel responsável, corrigidos e **reverificados** nesta rodada. O histórico da rodada 1 fica preservado abaixo, com o que cada achado ensinou: apagar o caminho é perder a parte útil do relatório.

---

## Escopo e limites desta validação

Validei as **três estórias entregues nesta sessão** — `STORY-060`, `STORY-059` e `STORY-052` — contra os critérios de aceite e a Definição de Pronto de cada uma.

**Isto não é validação de fechamento de épico.** Nenhum dos três épicos envolvidos tem `validation/checklist.md` para esta rodada, e o `EPIC-002` só é promovido a `done` quando a `STORY-060` chegar a `done` — o que depende de deploy. Registro isso porque confundir os dois tipos de validação é como uma dívida entra escondida: a régua do épico é outra, e maior.

**Não consertei nada.** Os dois fails da rodada 1 foram descritos com evidência e localização e devolvidos a quem implementou; a rodada 2 apenas **reverificou** as correções, com medição nova. Em nenhum momento o validador tocou em código.

---

## Resumo executivo

As três estórias entregam o que prometem, e as decisões difíceis foram tomadas onde deviam: a `STORY-060` levou a intenção editorial da EBC para as mensagens que o usuário realmente lê sem reverter a `STORY-032`; a `STORY-059` fechou os cinco achados de acessibilidade com dois DDRs ratificados pelo PO, e o gate saiu de 85 para 100; a `STORY-052` publicou o pacote de conteúdo do DRM com o provisório isolado e a regra do farol no motor.

A qualidade de teste está acima do que os CAs pediam em vários pontos — o diff dos golden hashes foi conferido por reconstrução em vez de "mudou/não mudou", e o contraste virou teste **calculado** a partir de `tokens.css` em vez de comparação de string. Isso é o oposto de teste decorativo.

**Os dois fails da rodada 1 eram de execução, não de decisão.** O primeiro era uma regressão visual real que passou despercebida porque os testes existentes medem contraste e semântica, não geometria — e a captura de tela, sozinha, não denuncia 2px de fonte. O segundo era um método público sem teste. Ambos foram corrigidos e reverificados na rodada 2, cada um com teste que impede a volta.

O que sobra são **três pendências de release**, todas fora do mandato do agente: as três estórias dependem do mesmo deploy, que nasce de uma tag e é ato do PO. Elas estão declaradas nas notas de cada estória em vez de mascaradas com `done` — que é o comportamento correto.

---

## Checklist preenchido

### Bloco 1 — STORY-060 (texto do NCG absoluto)

| Item | Status | Evidência |
|---|---|---|
| CA-1 — intenção editorial nas faixas MODERADO e ALTO | ✅ | `NcgAbsoluto::MSG_MODERADO/MSG_ALTO` contêm "na mesma proporção", "longo prazo" e "fornecedores"; 2 testes dedicados em `Indicadores/NcgAbsolutoTest.php` |
| CA-1 — FOLGA revisitada contra o cenário "negativa" do §F.13 | ✅ | Texto incorpora "o giro do negócio não exige dinheiro seu"; a sigla IOG foi omitida com justificativa registrada nas notas |
| CA-1 (borda) — orientação **não** aparece na faixa de folga | ✅ | Teste `not->toContain('na mesma proporção')` — conselho certo no cenário errado seria pior que a omissão |
| CA-2 — continuam três faixas | ✅ | Teste de unicidade das 3 constantes; `STORY-032` não revertida |
| CA-3 — bump de versão e hashes reemitidos | ✅ | `motor.version` 1.3.1 → 1.3.2; 5 hashes novos em `GoldenHashesTest` |
| CA-3 — diff dos hashes conferido | ✅ **acima do pedido** | Conferência por **reconstrução**: com a versão fixada em 1.3.1 e as 3 mensagens novas trocadas pelas antigas no JSON, os 5 hashes voltam bit a bit aos congelados. Método registrado no próprio teste e no `IDR-010` |
| CA-4 — diagnósticos legados preservam o texto | ✅ | 5 testes em `Feature/Http/DiagnosticoNcgLegadoTest.php`, incluindo o guard de que as mensagens antigas ≠ atuais (senão o teste não provaria nada) |
| CA-5 — entrada inerte da matriz resolvida | ✅ | Movida para `_referencia_editorial`; 3 testes em `MatrizArchTest`, incluindo o que prova que pedir a chave agora vira lacuna registrada em vez de texto silencioso |
| CA-5 — teste arquitetural conhece a exceção, não a acusa | ✅ | `CHAVES_NAO_CONSUMIDAS` é lista declarada; chave nova fora dela continua sendo acusada |
| CA-6 — rastreabilidade no docblock | ✅ | Docblock de `NcgAbsoluto` cita `STORY-060`, `PDR-006` §2 e `STORY-032` |
| CA-7 — testes das 3 faixas + legado + hashes | ✅ | 6 + 5 + 5 testes; suíte verde |
| CA-8 — cobertura ≥ 98% no núcleo | ✅ | Domínio 99,7%; `NcgAbsoluto` 100% |
| DoD — pipeline verde | ✅ | 984 Pest / 3.237 asserções; Pint (263 arquivos) e PHPStan limpos |
| DoD — deploy em homologação verificado | ⏸️ **pendente, fora do mandato** | Depende de tag `rc`, ato do PO. Não é fail do agente |

### Bloco 2 — STORY-059 (acessibilidade)

| Item | Status | Evidência |
|---|---|---|
| CA-1 — nome acessível no toggle de conta | ✅ | `aria-label="Menu da conta de {nome}"`; 2 testes em `Feature/Shell/A11yAppShellTest.php`; Lighthouse não acusa mais `button-name` |
| CA-2 — drawer fechado fora do foco e da árvore | ✅ | `inert` + `aria-hidden` via `sincronizarInerte()`; teste em browser real prova que `link.focus()` não move `document.activeElement` |
| CA-2 — segundo defeito, não nomeado na estória | ✅ **achado do agente** | A sidebar do **desktop**, visível, saía da árvore de acessibilidade porque o binding olhava só `navOpen`. Corrigido e coberto por teste próprio. Corrigir só o lado acusado pelo gate teria deixado este em pé |
| CA-3 — contraste dos pills e do badge ≥ 4,5:1 | ✅ | `DDR-006` ratificado; tintas medidas em 4,76–4,84:1; badge em 5,53:1 |
| CA-4 — sem hex literal; teste de tokens verde | ✅ | `DesignTokensTest` verde, com os 5 hex novos na lista de proibidos |
| CA-4 (ressalva) — o gate de hex é cego a `rgba()` | ⚠️ **pass com ressalva** | O badge violava o `IDR-008` com 3 `rgba(99,91,255,…)` inline e o teste não pegava, porque procura só hex. Corrigido no badge; **o furo do gate continua aberto** e está registrado nas notas da estória. Não é fail: nenhum CA pedia isso |
| CA-5 — farol comunica por cor, ícone e texto | ✅ | Teste verifica os 3 canais e que os 4 ícones são desenhos distintos |
| CA-6 — gate de acessibilidade passa | ✅ | Lighthouse 12.8.2, categoria `accessibility`, página autenticada do relatório: **100**, zero auditorias reprovadas. Era 85 |
| CA-6 — gate reexecutado em **homologação** | ⏸️ **pendente, fora do mandato** | Rodou em ambiente local. Homologação depende do deploy |
| CA-7 — sem regressão visual | ✅ **corrigido na rodada 2** | Reprovou na rodada 1 (F-1). Geometria restaurada e travada por `FarolGeometriaBrowserTest`; remedido em browser: fonte 12px, altura 24px, padding `4px 10px`, gap 6px. Capturas das três variantes em desktop e mobile |
| CA-8 — testes de nome acessível e de foco | ✅ | 10 + 5 testes; contraste virou teste **calculado** a partir de `tokens.css`, não comparação de string |
| CA-9 — alvo de toque de 44×44px sem inflar o círculo | ✅ | Teste em browser real mede os dois lados: acerta a 20px do centro, **não** acerta a 40px (não rouba toque do vizinho) |
| CA-10 — borda de campo ≥ 3:1 | ✅ | `DDR-007` ratificado pelo PO; `--color-input` #7C8798 → 3,64:1 sobre surface, 3,44:1 sobre neutral. Verificado que só `.input` e `.input-affix` consomem o token — nenhum uso como divisória |
| DoD — pipeline verde | ✅ | 984 Pest + 36 Dusk |

### Bloco 3 — STORY-052 (pacote de conteúdo do DRM)

| Item | Status | Evidência |
|---|---|---|
| CA-3 — rótulo do grupo de custo nos 3 setores | ✅ | CPV/CMV/CSP em `drm/rotulos.php`; teste dedicado |
| CA-4 — tooltip para cada campo do Anexo K | ✅ | 43 tooltips em `provisorio/help.php`; teste que reprova qualquer campo sem texto |
| CA-4 — os 5 pares de confusão do §K.10 | ✅ **acima do pedido** | Cada um **nomeia o outro lado do par** e diz onde ele vai; teste cobre os 5. Tooltip que só explica o próprio campo não desfaz confusão entre dois campos |
| CA-4 — campo sem tooltip não renderiza `<x-help>` | ✅ | `ConteudoDrm::help()` devolve `null`; teste registra que `null` é instrução, não falha |
| CA-5 — estrutura de faixas por setor e por linha | ✅ | 9 linhas × 3 setores; teste garante que os 3 setores têm o mesmo conjunto de linhas |
| CA-5 — faixa `nao_calibrada` não produz farol | ✅ | `Faixas::farol()` devolve `null`; teste varre as 27 combinações |
| CA-5 — regra no motor, não na tela | ✅ | `App\Domain\Drm\Faixas`, sem nenhuma dependência de view |
| CA-5 — popular faixa não exige código | ✅ | Teste calibra uma faixa **só por config** e verifica classificação e fronteiras (`min` inclusivo, `max` exclusivo) |
| CA-5 (extra) — proteções não pedidas | ✅ **acima do pedido** | Faixa calibrada com buraco devolve `null` em vez da cor mais próxima; faixa marcada `calibrada` sem limites não vira "serve para tudo", que pintaria tudo de verde |
| CA-6 — textos de leitura ausentes por decisão | ✅ | `leitura.php` vazio **e sem contrapartida em `provisorio/`**; teste verifica os dois |
| CA-7 — mensagens de bloqueio e alerta presentes | ✅ | 4 bloqueios + 6 alertas; teste exige título, texto e ação em todos; teste específico de que A3 (prejuízo) informa sem culpar |
| CA-8 — formato consumível e versionado | ✅ | `version` em cada arquivo; teste cobre os 7 |
| CA-9 — lacunas explícitas com default aplicado | ✅ | `design/conteudo-lacunas.md` com 53 chaves |
| CA-10 — resolução por sobreposição | ✅ | Teste prova que promover é **mover a linha**, sem tocar em código |
| CA-10 — teste falha se a chave existir nos dois | ✅ | `ConteudoDrmArchTest`; a mensagem de erro explica por que duplicata é pior que quebra |
| CA-10 — inventário legível pelo PO | ⚠️ **pass com ressalva** | Existe e funciona, mas **não escreve em ambiente local** — `defonline-docs/` fica fora do que o container monta. O agente registrou o achado e gerou o arquivo por outro caminho. Mesmo furo vale para `matriz_lacunas_path` do Diagnóstico desde a `STORY-032`. Não é fail desta estória: é anterior a ela |
| CA-10 — `conteudo_version` separada de `motor_drm_version` | ✅ | Teste dedicado |
| CA-11 — "Raio X do Negócio" na interface | ✅ | Em `rotulos.php` como conteúdo **validado**; teste arquitetural extra garante que "DRM" não vaza para nenhum texto de interface |
| DoD — STORY-048 e STORY-051 desbloqueadas | ✅ | Defaults do `PDR-009` §1 aplicados no pacote |

### Bloco 4 — Transversais

| Item | Status | Evidência |
|---|---|---|
| Suíte completa verde | ✅ | 984 Pest (3.237 asserções) + 36 Dusk (248) |
| Cobertura geral ≥ 80% | ✅ | 97,4% |
| Cobertura núcleo ≥ 98% | ✅ | 99,7% no domínio |
| Cobertura por arquivo novo | ✅ **corrigido na rodada 2** | Reprovou na rodada 1 (F-2). `Drm/ConteudoDrm` e `Drm/Faixas` em **100%**; domínio em 99,7% |
| E2E em browser real para FE | ✅ | 5 testes novos em Dusk cobrindo `inert`, foco e hit-testing |
| Lint e análise estática | ✅ | Pint 262 arquivos; PHPStan sem erros |
| Decisões registradas | ✅ | `DDR-006` e `DDR-007` `accepted` com aprovação nominal; `IDR-010` estendido; DS canônico em v1.1 |
| `index.json` coerente com os arquivos | ✅ | 3 estórias em `in_review`; 2 DDRs novos em `decisions.ddr[]` |
| Deploy em homologação | ⏸️ **pendente ×3** | As três estórias dependem do mesmo release |

---

## Achados da rodada 1 — os dois corrigidos e reverificados

### F-1 — O pill de farol cresceu de 12px para 14px — **RESOLVIDO**

**O que observei.** Medido em browser real na página do relatório:

```
font-size: 14px   (era 12px)
height:    28px   (era 24px)
```

**Onde.** `components/relatorio/farol.blade.php` deixou de usar as utilities `text-xs font-medium px-2.5 py-1 gap-1.5` e passou a herdar tipografia de `.farol` no `app.css`, que define `font-size: var(--text-body-sm)` — **0,875rem = 14px**. O `text-xs` do Tailwind é 0,75rem = 12px.

**Por que é fail.** O CA-7 pede ausência de regressão visual, e o `DDR-006` afirma, com todas as letras, que *"o pill mantém exatamente a aparência de hoje de longe — o que muda é a densidade da tinta"*. Cresceu 17% na fonte e 4px na altura. A decisão ratificada pelo PO foi sobre **cor**; o tamanho mudou de carona, sem estar em nenhum CA nem em nenhum DDR.

**Por que passou.** Os testes desta estória medem contraste, semântica e comportamento — nenhum mede geometria. E a captura de tela do CA-7 não denuncia 2px de fonte: o pill continua legível e distinguível nas três cores, que é o que o olho confere. Foi a medição em browser, feita nesta validação, que expôs.

**Não é bloqueante** porque não quebra acessibilidade (12px e 14px têm o mesmo piso de 4,5:1), não afeta a semântica do farol e não reprova o gate. É divergência entre o que foi decidido e o que foi entregue.

**Dono:** Programador, com ciência do Designer (é o `DDR-006` que está sendo contrariado). A correção que sugiro não é minha decisão: restaurar a tipografia original é o caminho óbvio, mas se o Designer preferir manter 14px, isso vira aditivo ao `DDR-006` — não silêncio.

**Recomendo também** um teste que trave a geometria, senão o próximo refactor de classe repete o episódio.

**Correção verificada na rodada 2.** `font-size` voltou a `0.75rem` e `line-height` a `1rem` — os valores exatos de `text-xs`, que era o que estava lá antes. Remedido em browser real: **fonte 12px, altura 24px**, padding `4px 10px`, gap 6px. `FarolGeometriaBrowserTest` trava os quatro números. O `DDR-006` ganhou adendo dizendo que ele decide **cor, não geometria**, e que mexer nesta exige aditivo — não refactor de classe. Gate reexecutado depois da correção: **100**, zero auditorias reprovadas.

### F-2 — `ConteudoDrm` em 97,1%, com método público sem teste — **RESOLVIDO**

**O que observei.** Cobertura por arquivo no relatório do domínio: `Drm/ConteudoDrm` **97,1%**, linha 101 descoberta. A linha é o corpo de `rotuloCampo()` — método público, nunca exercitado por teste.

**Por que é fail.** O padrão do PO exige **98% em núcleo e regras de negócio**, e o resolvedor de conteúdo é núcleo do módulo. O total do domínio (99,6%) mascara o arquivo.

**Não é bloqueante:** é um método de uma linha que delega ao mesmo `resolver()` já coberto por 18 testes. O risco real é baixo; a regra é que não é opcional.

**Dono:** Programador. Um teste.

**Correção verificada na rodada 2.** Dois testes, não um: o contrato de `rotuloCampo()` (rótulo existente e `null` — não string vazia — para campo inexistente) e a sobreposição validado × provisório aplicada a rótulos, que hoje não têm contrapartida provisória mas terão o mesmo comportamento no dia em que tiverem. `Drm/ConteudoDrm` foi de 97,1% para **100%**; domínio em 99,7%.

---

## Pendências fora do mandato do agente (não são fails)

As três estórias declaram, honestamente e nas notas, que **não houve deploy em homologação** — o release nasce do push de uma tag `rc`, ato do PO. Consequências:

1. `STORY-060` — deploy verificado (DoD).
2. `STORY-059` — deploy + gate de acessibilidade reexecutado em homologação (DoD).
3. `STORY-052` — deploy (a estória não tinha item de DoD para isso, mas o código vai no mesmo release).

Enquanto isso não ocorrer, **nenhuma das três pode ir a `done`**, e o `EPIC-002` não é promovido. Registro isso como estado, não como falha de execução: o agente fez o que estava sob seu mandato e sinalizou o resto em vez de marcar `done` por conta própria — que é o comportamento correto segundo o protocolo.

---

## Observações que não viram item

- **A conferência dos golden hashes por reconstrução merece virar padrão.** Comparar "hash mudou / não mudou" não prova o que o CA-3 pede. A técnica usada — desfazer só o texto e verificar que os hashes voltam bit a bit — prova. Está documentada no próprio teste e no `IDR-010`; sugiro ao PO promovê-la a padrão de re-emit.
- **O achado do fundo translúcido do pill é o tipo de coisa que só o gate acha.** A conta em papel assume um fundo por vez; o pill dentro do card do Resumo Executivo tinha dois. A regra que saiu disso — componente com fundo tingido usa cor opaca — vale além do farol e já está no DS.
- **O `EPIC-002` está a um deploy de fechar.** Com a `STORY-060` em `done`, o `promote_to_done_when` do épico é satisfeito.

---

## Recomendação ao PO

1. **Criar a tag `rc`** e acompanhar o `release-homolog.yml`. As três estórias entram no mesmo release.
2. Em homologação, verificar os três pendentes: o card de NCG com o texto novo (`STORY-060`), o gate de acessibilidade reexecutado na URL pública (`STORY-059`) e o carregamento do pacote de conteúdo (`STORY-052`).
3. Com isso verde, promover as três estórias a `done` — e aí o `promote_to_done_when` do `EPIC-002` fica satisfeito e o épico vai a `done`.
4. **Conferir a olho, em homologação e em celular real, as telas de formulário.** O `DDR-007` mudou a borda de campo em todo o produto, e o próprio DDR classifica isso como mudança visível que pede conferência humana — não é coisa que teste verde resolva.
5. **Ratificar ou derrubar a redação das três mensagens de NCG** da `STORY-060`. É conteúdo editorial; os testes checam os elementos da orientação, não a frase, então reescrever não quebra a suíte — mas implica novo bump de `motor_version` e novo re-emit dos hashes.
6. Avaliar se viram estória, nenhuma das duas desta sprint: (a) o gate de hex é **cego a `rgba()`** — o badge violava o `IDR-008` e passava; (b) o **inventário de lacunas nunca escreve em ambiente local**, porque `defonline-docs/` fica fora do que o container monta, e isso vale também para o `matriz_lacunas_path` do Diagnóstico desde a `STORY-032`.

## Registro das rodadas

| Rodada | Data | Veredito | Fails |
|---|---|---|---|
| 1 | 2026-08-06 | `rejected` | F-1 (geometria do pill), F-2 (cobertura de `ConteudoDrm`) — ambos não-bloqueantes |
| 2 | 2026-08-06 | `approved_with_pending` | nenhum; 3 pendências de release, fora do mandato do agente |
