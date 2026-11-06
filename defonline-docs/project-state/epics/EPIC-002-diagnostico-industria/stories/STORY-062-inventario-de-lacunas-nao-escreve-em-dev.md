---
story_id: STORY-062
slug: inventario-de-lacunas-nao-escreve-em-dev
title: "Inventário de lacunas — o mecanismo silencia em desenvolvimento e ninguém percebe"
epic_id: EPIC-002
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: done
owner_agent: programador
created_at: 2026-08-06
updated_at: 2026-11-05
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
- **Solução (CA-5):** o inventário passa a viver em `storage/app/private/lacunas/` (`matriz-lacunas.md`, `drm-conteudo-lacunas.md`), gravável em dev e em produção e visível no host porque `./app` já é montado. `scripts/sincronizar-lacunas.sh` (host, idempotente) acrescenta ao arquivo versionado em `defonline-docs/` as linhas que faltam. Um único componente, `App\Support\InventarioLacunas`, serve os dois mecanismos (CA-2).
- **Descartado:** montar `defonline-docs/` no container. Mexe em `docker-compose.yml` e, pela direção contrária, em imagem/deploy (a documentação não pertence à imagem de produção); o protocolo da estória manda escalar isso, e não era necessário. Também descartado: gravar direto em `defonline-docs/` por path absoluto do host, que não existe no container.
- **CA-3:** o guard deixou de ser silencioso. Diretório ausente é criado (`mkdir -p`); se não der para criar ou gravar, `Log::warning('lacunas.inventario.nao_gravavel', path, motivo)`, uma vez por path e processo para não inundar o log a cada cálculo. Nunca lança.
- **CA-4:** `.env.testing` fixa `MOTOR_MATRIZ_LACUNAS_PATH=` e `DRM_LACUNAS_PATH=` vazios; a suíte não toca o repositório. Testes que exercitam a escrita usam diretório temporário.
- Teste antigo alterado: `MatrizRecomendacoesTest` "auto-append silencia quando o diretório do path não existe" virou "não lança quando o destino não é gravável, e avisa". O contrato de não lançar permanece; a parte "silencia" é exatamente o que o CA-3 manda mudar.
- A checagem de idempotência da matriz passou a exigir a linha seguida de `\n` (igual à do DRM); como toda linha é gravada com `\n`, não muda o comportamento.

### Descobertas
- Os dois arquivos de inventário (`matriz-lacunas.md`, `conteudo-lacunas.md`) em `defonline-docs/` não são mais escritos em runtime; passam a ser atualizados por `scripts/sincronizar-lacunas.sh`. Quem precisar do inventário atual roda o script (não foi executado aqui para não alterar docs fora da estória).
- `ConteudoDrm` é do EPIC-010 (pista DRM): a mudança nele é pequena (corpo de `registrarLacuna`), mas pode dar conflito de merge se aquela pista também o editar.
- O README de `config/drm/provisorio/` foi atualizado com o novo local.

### Bloqueios encontrados
Nenhum.

### Cobertura final
- CA-1: `tinker` no container com os defaults reais criou os dois arquivos em `storage/app/private/lacunas/` (verificado, depois removido); teste "Diagnóstico e DRM gravam no inventário quando o destino é gravável".
- CA-2: mesmo teste (os dois mecanismos) e componente único. CA-3: testes de destino não gravável (um no componente, um na `MatrizRecomendacoes`). CA-4: teste "suíte roda com o inventário desligado". CA-6: teste "destino gravável recebe a linha, uma vez só" e o dos dois mecanismos, que falham se o mecanismo voltar a silenciar.
- Suíte completa: 1462 testes passando; Pint e PHPStan limpos.
