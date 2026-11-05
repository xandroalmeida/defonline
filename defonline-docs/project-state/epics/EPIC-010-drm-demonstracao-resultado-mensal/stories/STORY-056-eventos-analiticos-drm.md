---
story_id: STORY-056
slug: eventos-analiticos-drm
title: "Eventos analíticos do DRM — instrumentar recorrência, atrito e alcance"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: false
status: in_review
owner_agent: programador (claude-sonnet-5-5)
created_at: 2026-08-04
updated_at: 2026-11-05
estimated_session_size: S
checkpoint: CP3
---

# STORY-056 — Eventos analíticos do DRM

## Contexto

O PDR-003 decidiu entregar o módulo **sem cobrança** com uma condição explícita: medir adesão antes de investir em gateway de pagamento. Essa condição só se cumpre se os eventos existirem. Sem esta estória, o épico fecha sem conseguir responder se a hipótese se confirmou — e a decisão do EPIC-005 volta a ser um chute.

A instrumentação já tem padrão consolidado no produto, incluindo bloqueio de dado pessoal em tempo de execução e teste arquitetural que impede emissão fora do canal oficial. Siga esse padrão.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: `decisions/adr/ADR-004-*` (observabilidade); a instrumentação de eventos do Diagnóstico já em produção; `product/north-star.md`

## O quê

Emitir os eventos de produto que permitem calcular as três métricas do épico e alimentar a decisão de precificação do EPIC-005.

## Critérios de aceite

- [ ] **CA-1 (Recorrência — métrica primária):** eventos suficientes para calcular a taxa de usuários que concluem o 2º lançamento em até 45 dias após o 1º, e a distribuição de quantos lançamentos cada empresa acumula.
- [ ] **CA-2 (Atrito — métrica secundária):** eventos que permitam calcular taxa de conclusão dos lançamentos iniciados, tempo até a conclusão, e ponto de abandono quando o lançamento não é concluído.
- [ ] **CA-3 (Alcance — métrica secundária):** todo evento de lançamento carrega o setor da empresa, permitindo medir a participação de Comércio e Serviços.
- [ ] **CA-4 (Uso dos mecanismos anti-atrito):** eventos que revelem quantos grupos ficam em modo agregado vs. detalhado, quanto do pré-preenchimento é aceito sem alteração, e quantos lançamentos passam por rascunho retomado. **É o que vai dizer se os mecanismos do PDR-003 funcionaram.**
- [ ] **CA-5 (Qualidade do dado):** eventos de retificação, de alerta aceito e de regra bloqueante acionada — insumo direto do sinal de revisão do PDR-003 sobre taxa de retificação acima de 30%.
- [ ] **CA-6 (Exportação e leitura):** eventos de exportação, por formato.
- [ ] **CA-7 (Sem dado pessoal):** nenhum evento carrega valor financeiro absoluto, nome, documento ou qualquer dado que identifique pessoa ou empresa. Faixas e contagens, não valores. Garantido pelo bloqueio em tempo de execução já existente.
- [ ] **CA-8 (Idempotência):** eventos idempotentes, no padrão já adotado — reenvio não duplica.
- [ ] **CA-9 (Canal único):** emissão exclusivamente pelo canal oficial de eventos, coberta pelo teste arquitetural existente.
- [ ] **CA-10 (Testes):** feature verificando emissão de cada evento no fluxo correspondente e ausência de dado pessoal.
- [ ] **CA-11 (Cobertura):** ≥ 80%.

## Fora de escopo

- Painel ou relatório de análise. Os eventos ficam disponíveis para consulta; construir visualização é outra conversa.
- Integração com ferramenta externa de analytics.

## Dependências

- **Bloqueada por:** STORY-047, STORY-049, STORY-050, STORY-051.
- **Bloqueia:** STORY-057 — a validação confere se as métricas do épico são calculáveis.

## Decisões já tomadas (não as reabra)

- ADR-004 — observabilidade sobre a base já existente.
- Sem dado pessoal em evento, sem exceção.
- PDR-003, decisão 8 — medir antes de cobrar.

## Definição de Pronto

- [ ] CA-1 a CA-11 passam.
- [ ] **Demonstração de que as três métricas do épico são calculáveis** a partir dos eventos emitidos — consulta de exemplo registrada nas notas.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Plano (2026-11-05, antes de codar)

Lido: estória, ADR-004 (decisão 2: `EventLogger`, síncrono na transação do agregado, PII barrada na origem), `EventLogger`, `EmissaoEventoArchTest`, os eventos do Diagnóstico (`quiz_iniciado`, `diagnostico_concluido`) e como se tornam idempotentes (emissão presa à transição do agregado, chave = id do fato), notas das STORY-047 a 051, 054, 055 e da STORY-084 (CA-7 pendente: "emitir só dentro de ações liberadas por `AcessoDrm`").

Entendimento: um catálogo de eventos do DRM, todos pelo `EventLogger`, que responda às três métricas do épico; sem valor absoluto nem dado pessoal; idempotentes; e a tentativa bloqueada por `AcessoDrm` nunca produz evento de lançamento.

### Decisões tomadas

- **Catálogo** em `App\Observabilidade\EventosDrm` (um método por evento, esquema e chave de idempotência declarados ali). Eventos: `drm_lancamento_iniciado`, `drm_bloco_concluido`, `drm_rascunho_retomado`, `drm_regra_bloqueante_acionada`, `drm_lancamento_concluido`, `drm_lancamento_retificado`, `drm_alerta_aceito`, `drm_demonstracao_visualizada`, `drm_exportado`.
- **Idempotência real (CA-8):** `evento_produto.chave_idempotencia` (migration `2026_11_05_100030`) + índice único parcial `(nome_evento, chave_idempotencia) WHERE chave IS NOT NULL` e `EventLogger::emitOnce()`. A chave é o FATO (`lancamento:{id}`, `rascunho:{id}:bloco:{n}`, `exportacao:{request_id}:{formato}`…). A consulta prévia poupa o erro no caso comum; a corrida cai no índice, dentro de um savepoint, sem abortar a transação do agregado. `emit()` (eventos da STORY-035) segue igual. Teste: duplicata recusada pelo banco e corrida simulada.
- **Atomicidade (ADR-004 §2.3):** `lancamento_concluido` e `lancamento_retificado` são emitidos dentro da transação de `FecharLancamento`/`RetificarLancamento`; se o evento falha, o mês não fecha (teste).
- **Sem valor absoluto (CA-7):** só ids opacos, contagens, ordinais, dias, setor e competência. Os grupos viajam como VALORES de lista (`grupos_agregados: ["custo", …]`) porque o `EventLogger` recusa chave `custo*` em qualquer nível. Teste varre os eventos de um fluxo completo atrás de valores digitados, nome da empresa, e-mail e documento.
- **Recorrência (CA-1):** `drm_lancamento_concluido.numero_lancamento` (ordinal dos meses lançados da empresa) e `dias_desde_anterior`. Taxa do 2º lançamento em ≤ 45 dias e distribuição por empresa saem de `numero_lancamento`/`dias_desde_anterior`.
- **Atrito (CA-2):** `iniciado` na primeira gravação do rascunho (mesmo critério do `quiz_iniciado`), `bloco_concluido` por bloco validado, `duracao_seg` no concluído (do primeiro salvamento ao fechamento). Ponto de abandono = último `bloco_concluido` de um rascunho sem `concluido`. **Limite conhecido:** quem abandona antes de concluir o bloco 1 não deixa rastro (o rascunho só existe depois); igual ao Quiz.
- **Alcance (CA-3):** `setor` em todos os eventos de lançamento (teste).
- **Anti-atrito (CA-4):** modo por grupo (`grupos_agregados`/`grupos_detalhados`), `campos_herdados` e `campos_herdados_aceitos` (herdados do mês anterior que fecharam com o mesmo valor), `rascunho_retomado`. Retomada = voltar ao rascunho depois de ≥ 30 min do último salvamento (`config/drm/eventos.php`, recalibrável): atualizar a página logo após salvar não é voltar.
- **Qualidade (CA-5):** `lancamento_retificado` (com `campos_alterados` e `dias_desde_lancamento`: o numerador da taxa de retificação de 30% do PDR-003), `alerta_aceito` (um por regra, com `ocorrencias`) e `regra_bloqueante_acionada` (B1–B4, uma vez por regra e campo em cada abertura do formulário; erro de campo vazio ou formato não é regra bloqueante).
- **Exportação e leitura (CA-6):** `drm_exportado` com `formato` = `xlsx` (controller) ou `impressao` (clique no botão, via `wire:click=registrarImpressao`); `drm_demonstracao_visualizada` na abertura.
- **Canal único (CA-9):** tudo passa por `EventLogger`; o `EmissaoEventoArchTest` existente segue verde e cobre o novo código.
- **STORY-084 CA-7 (acesso):** a tentativa bloqueada nunca monta o formulário (middleware `drm.acesso`) e, se o acesso acaba com a página aberta, `Lancamento::evento()` consulta `AcessoDrm` antes de emitir iniciado, bloco, retomada e regra bloqueante, e `fechar()` já redirecionava antes de gravar. Testes: bloqueio na entrada (lançar e corrigir) sem evento algum; acesso perdido no meio: `fechar` não grava nem emite `concluido`/`alerta_aceito`, e avançar/bater em regra não emitem; com plano os eventos fluem; sem plano, ler e exportar o histórico continuam emitindo os eventos de leitura (exportar é leitura, STORY-084 CA-3). O CA-7 da STORY-084 fica cumprido por esta estória.

### Consultas de exemplo (as três métricas do épico)

Rodadas num teste (`EventosDrmTest`, "as três métricas do épico são calculáveis") sobre `evento_produto`:

```sql
-- Recorrência: empresas com 2º lançamento a ≤ 45 dias do 1º / empresas com 1º lançamento
select count(*) filter (where segundo.empresa_id is not null) as com_segundo, count(*) as com_primeiro
from evento_produto primeiro
left join evento_produto segundo
  on segundo.empresa_id = primeiro.empresa_id and segundo.nome_evento = 'drm_lancamento_concluido'
 and (segundo.propriedades->>'numero_lancamento')::int = 2
 and (segundo.propriedades->>'dias_desde_anterior')::int <= 45
where primeiro.nome_evento = 'drm_lancamento_concluido' and (primeiro.propriedades->>'numero_lancamento')::int = 1;

-- Distribuição de lançamentos por empresa
select max((propriedades->>'numero_lancamento')::int) as lancamentos, count(*) from evento_produto
where nome_evento = 'drm_lancamento_concluido' group by empresa_id;

-- Atrito: iniciados x concluídos (rascunho_id é a chave de correlação) e último bloco dos abandonados
select count(distinct i.propriedades->>'rascunho_id') iniciados, count(distinct c.propriedades->>'rascunho_id') concluidos
from evento_produto i left join evento_produto c on c.nome_evento = 'drm_lancamento_concluido'
 and c.propriedades->>'rascunho_id' = i.propriedades->>'rascunho_id'
where i.nome_evento = 'drm_lancamento_iniciado' and i.propriedades->>'origem' = 'inicial';

-- Alcance: participação por setor
select propriedades->>'setor', count(*) from evento_produto where nome_evento = 'drm_lancamento_concluido' group by 1;
```

Taxa de retificação (PDR-003, sinal de 30%): `count(drm_lancamento_retificado) / count(drm_lancamento_concluido)`. Tempo até a conclusão: `avg(duracao_seg)` do concluído.

### Descobertas

- **O `PDOException` intermitente no Dusk/Pest tem causa: o `.env.dusk.local` aponta para o MESMO banco dos testes Pest (`defonline_test`).** O `RefreshDatabase` do Pest recria o schema (`migrate:fresh`) e o `DatabaseTruncation` do Dusk trunca tabelas nesse mesmo banco; se os dois rodam ao mesmo tempo, um apaga o chão do outro. Reproduzido nesta sessão rodando Dusk (3 arquivos de DRM) com o Pest em laço: o Dusk falhou com `SQLSTATE[42P01] relation "public.drm_lancamentos" does not exist` em 3 testes (inclusive o de prejuízo) e deu timeout em outros 3. Rodados em série, 25 execuções seguidas do `DemonstracaoTest` não falharam. Um Pest passado para segundo plano pelo timeout da ferramenta enquanto o Dusk sobe é o gatilho provável. Providência sugerida (fora desta estória): banco próprio para o Dusk (`defonline_dusk`), ou um bloqueio no `verificar.sh`; até lá, nunca sobrepor as duas execuções.
- **Teste de outra estória com dependência de relógio:** `DrmRetificacaoBrowserTest` (STORY-050) falha quando o horário UTC passa de 21:00. O Postgres local roda em `America/Sao_Paulo` e a aplicação em UTC (IDR-018), então o `gerado_em` gravado volta 3 h adiante e o rodapé "última correção em 06/11/2026" já é outro dia, enquanto o teste espera `now()`. Passou às 20:20 UTC e falhou às 21:10 UTC (container: 21:10 UTC); some às 00:00 UTC. Não é desta estória e não foi alterado.
- `Carbon::setTestNow` não alcança o relógio do Postgres: testes que medem duração precisam de `SET LOCAL TIME ZONE 'UTC'` (padrão já usado em `SnapshotDrmTest`).

### Bloqueios encontrados

Nenhum.

### IDRs criados

Nenhum (decisões locais; o esquema dos eventos mora no código e nestas notas).

### Cobertura final

- Suíte `All`: 2624 passed (47.650 assertions) com `php -d memory_limit=-1`; `--coverage --min=80`: total 98,7%; `EventosDrm` e `EventLogger` 100%. Pint e PHPStan limpos.
- Dusk completo: 82 passed, 1 failed (`DrmRetificacaoBrowserTest`, STORY-050, dependência de relógio após 21:00 UTC; ver Descobertas). O Dusk da 055 (83 passed) rodou antes das 21:00 UTC.
- **Não verificado:** consulta de eventos em volume real; o ajuste fino de `retomada_apos_minutos` (30) é palpite recalibrável; abandono antes de concluir o bloco 1 não é observável.
