---
story_id: STORY-062
slug: inventario-de-lacunas-nao-escreve-em-dev
title: "Inventário de lacunas — o mecanismo silencia em desenvolvimento e ninguém percebe"
epic_id: EPIC-002
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-08-06
updated_at: 2026-08-06
estimated_session_size: S
origem: "Achado da STORY-052, aprovado pelo PO em 2026-08-06 para virar estória"
---

# STORY-062 — O inventário de lacunas não escreve em desenvolvimento

## Contexto

Dois mecanismos do produto registram, em arquivo, o que está servido sem validação da EBC:

| Mecanismo | Desde | Config |
|---|---|---|
| `MatrizRecomendacoes` — pares `(indicador, farol)` sem texto | `STORY-032` | `motor.matriz_lacunas_path` |
| `ConteudoDrm` — chaves servidas de `config/drm/provisorio/` | `STORY-052` | `drm.lacunas_path` |

Os dois apontam para dentro de `defonline-docs/`, que fica **fora do que o container monta** (`./app:/var/www/html`). Ambos têm um guard `is_dir()` que silencia o I/O quando o diretório não existe — pensado para não quebrar teste por motivo de filesystem. O efeito colateral: **em desenvolvimento local, os dois nunca registram nada**, e ninguém é avisado disso.

Descoberto na `STORY-052`, ao tentar gerar o inventário inicial: o resolvedor rodou, o log de debug saiu, e o arquivo não apareceu. O arquivo daquela entrega foi gerado apontando o path para dentro do container e movido à mão — solução de uma vez, não mecanismo.

**Por que importa.** O inventário é o instrumento que o `PDR-009` §2 deu ao PO para responder *"o que ainda não passou pela EBC?"* sem ler código. Um instrumento que só funciona em produção não serve para planejar; e o `PDR-009` tem um gatilho de revisão que depende justamente de ler esse arquivo ("se o inventário continuar apontando tooltips provisórios por mais de um ciclo…").

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md` (o mecanismo nasceu aqui; a segunda instância é do `EPIC-010`)
- Ler antes: `PDR-009` §2 item 4; `App\Domain\Motor\MatrizRecomendacoes::appendLacunaIdempotente`; `App\Domain\Drm\ConteudoDrm::registrarLacuna`; `docker-compose.yml` (o que é montado)

## O quê

Fazer o inventário funcionar em desenvolvimento local, nos dois mecanismos, sem quebrar teste nem poluir o repositório.

## Por quê

Ver a lacuna no dia em que ela aparece é o ponto do mecanismo. Descobrir só em produção — ou, pior, nunca — transforma um instrumento de planejamento em decoração.

## Critérios de aceite

- [ ] **CA-1 (Escreve em desenvolvimento):** rodar o produto localmente e exercitar um par lacunoso do Diagnóstico ou uma chave provisória do DRM produz linha no arquivo de inventário correspondente. Hoje não produz.
- [ ] **CA-2 (Um caminho, não dois):** a solução vale para os **dois** mecanismos. Consertar só o do DRM deixaria o do Diagnóstico quebrado do mesmo jeito e mais escondido, porque ninguém está olhando para ele.
- [ ] **CA-3 (Silêncio deixa de ser silencioso):** quando o destino não for gravável, o mecanismo **avisa** — log em nível de warning, com o path tentado. O guard `is_dir()` continua evitando exceção, mas para de engolir o fato sem deixar rastro.
- [ ] **CA-4 (Teste não escreve no repositório):** a suíte continua sem tocar em `defonline-docs/`. O padrão atual (path vazio desliga o I/O) é bom e permanece.
- [ ] **CA-5 (Escolha justificada):** a solução é decisão local do Programador, entre montar `defonline-docs/` no container, mover o inventário para `storage/` com cópia versionada, ou outra. **Registre o porquê nas notas** — inclusive o que foi descartado. Montar o diretório de documentação dentro do container tem implicação de imagem e de deploy que merece uma frase.
- [ ] **CA-6 (Regressão):** teste que falha se o mecanismo voltar a silenciar quando o destino é gravável.

## Fora de escopo

- Mudar o formato do inventário ou o que ele registra.
- Rever o `PDR-009` ou a política de provisório.

## Dependências

- **Bloqueada por:** nada.
- **Relacionada:** `STORY-032` (origem do mecanismo) e `STORY-052` (segunda instância).

## Decisões já tomadas (não as reabra)

- `PDR-009` §2 item 4 — o inventário é entregável ao PO, legível sem código.
- O append é **idempotente**: a mesma linha não é gravada duas vezes.
- Escrever no arquivo **não pode** alterar o valor retornado — determinismo do motor (`IDR-010` §sub-decisão 3).

## Definição de Pronto

- [ ] CA-1 a CA-6 passam.
- [ ] Suíte completa verde; pipeline verde.
- [ ] `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Se a solução exigir mudar o que o container monta, isso toca `docker-compose.yml` e possivelmente a imagem de produção — **pare e escale** antes de seguir: é decisão com alcance maior que a estória.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### Cobertura final
