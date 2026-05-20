# Mecânica de sprint

Sprint é o intervalo fixo de execução dentro de uma onda (`agile-workflow.md`). Para o time funcionar bem, sprint precisa de mecânica clara: como você seleciona estórias na abertura, o que valida no fim, como aprende, e o que fazer quando algo muda no meio.

Esta reference cobre o ofício de **conduzir um sprint** — não a estrutura formal (essa está em `agile-workflow.md`).

---

## A mentalidade

- **Sprint é compromisso**, não desejo. As estórias selecionadas são o que o time vai entregar — não "o que talvez consigamos".
- **Tamanho honesto.** Comprometer mais do que cabe é teatro. Sub-comprometer é desperdiçar capacidade.
- **Mudança no meio é exceção**, não regra. Cada exceção tem custo (contexto interrompido, retrabalho, planejamento perdido).
- **Sprint que "falha" é informação.** Time entregou menos? Algo aprendido — sobre estimativa, sobre realidade, sobre dependências. Não é vergonha.

---

## Sprint Planning — abrindo o sprint

Antes de o sprint começar, você (PO) seleciona o conjunto de estórias.

### Pré-requisitos

- **Onda definida** (`roadmap/current-wave.md`).
- **Backlog do épico ativo** detalhado em estórias `ready` (atendendo Definition of Ready do `story-craft.md`).
- **Capacidade estimada** — para time pequeno, "X estórias S/M por sprint baseado no histórico" é o suficiente. Sem histórico, comece conservador.
- **Bloqueios resolvidos** ou previstos para o sprint (ex: ADR pendente do Arquiteto que destrava estória).

### Como selecionar estórias

1. **Comece pelo topo do épico atual** — estórias `ready` na ordem prioritária.
2. **Verifique dependências** — não pegue estória que depende de outra ainda `in_progress` em outro sprint, a menos que o timing case.
3. **Equilibre o mix** (cruza com `prioritization.md`): proporção feature/bug/débito do sprint.
4. **Considere capacidade real**, não nominal — se você tem 2 sprints sem feriado e o time está descansado, é diferente de sprint pós-incidente.
5. **Pare quando atingir o teto realista.** Adicionar "mais uma estória" só para encher o sprint quase sempre se vira contra o time.
6. **Defina o `goal` do sprint** — 1 frase. Sem goal, sprint vira lista solta.

### O que entra na abertura

- **Estórias do épico ativo** — default.
- **1-2 estórias de bug** importantes, se houver fila.
- **1 estória de débito**, se algum sinal está pedindo (`prioritization.md`).
- **Estória de validação do épico**, se este sprint vai fechar o épico.

### O que NÃO entra

- Estórias **não-`ready`** (faltam CAs, dependência aberta, ambiguidade). Volte para detalhamento, não force.
- Estórias de épicos diferentes "porque é rápido" — fragmenta o foco.
- Trabalho não-estória (operacional, exploratório sem timebox).

### Como você comunica

Em chat ou em mensagem dedicada para o(s) agente(s):

> "**SPRINT-2026-W21 aberto.**
> **Goal**: <frase única>.
> **Estórias incluídas**: STORY-XXX, STORY-YYY, STORY-ZZZ (links).
> **Ordem sugerida de execução**: por dependência + prioridade.
> **Decisões pendentes que podem afetar**: <se houver>.
> **Próximo agente a iniciar**: comece por STORY-XXX (sem dependência aberta)."

Crie ou atualize o arquivo `defonline-docs/project-state/sprints/SPRINT-YYYY-WNN.md` usando o template (`templates/sprint.md`).

---

## Durante o sprint — manutenção contínua

Sprint não é "esqueço até o fim e vou ver o resultado". Você (PO) está continuamente:

- **Olhando o índice** (`index.json`) — uma vez por dia minimamente — para ver o que está `in_progress`, `blocked`, `done`.
- **Desbloqueando**: agentes que escalaram dúvida de produto recebem resposta rápida.
- **Coordenando com o Arquiteto**: ADRs pendentes que bloqueiam estória ganham prioridade explícita (veja `agile-workflow.md`, seção engajamento proativo com Arquiteto).
- **Removendo ruído**: pedido novo do mundo externo entra **na próxima sprint**, não na atual. Resista a "só isso", "rapidinho", "enquanto está aberto".

**Tempo investido no acompanhamento diário** é o que faz sprint terminar bem. Sprint que ninguém olha vira surpresa ruim na sexta.

---

## Mudança no meio do sprint — como tratar

Vai acontecer. Como lidar:

### Categorias

| Mudança | Resposta |
|---|---|
| **Bug crítico em produção** (afeta usuário ativo, perde dado, vaza segurança) | Para o sprint, trata o bug, depois retoma. Aceito como exceção. |
| **Decisão de produto invalida estória em andamento** | Para a estória, conversa com o time, decide reescopar ou cortar. |
| **Estória mostrou-se maior que estimado** | Avalia se cabe terminar; se não, parcializa ou tira do sprint (com aprendizado). |
| **Pedido novo "urgente" do mundo externo** | Default: **não entra**. Para próximo sprint. Exceção rara documentada. |
| **Dependência externa que se desbloqueou** (ADR aprovada, integração liberada) | Estórias dependentes podem ser puxadas; reavalie ordem. |
| **Time descobriu que ADR vigente está errada para o caso** | Pausa estória, escala para Arquiteto, abre nova ADR; estória eventualmente é reescrita. |

### Regra geral

- **Mudança não declarada vira caos.** Toda mudança no escopo do sprint é registrada no `SPRINT-YYYY-WNN.md` ("mid-sprint change") + `index.json`.
- **Mudança custa.** Reconheça o custo (estória X não vai sair) em vez de fingir que dá pra acrescentar coisa sem soltar outra.

---

## Sprint Review — fechando o sprint

Sprint termina. Pergunta: o que aconteceu de verdade?

### O que revisar

1. **Estórias `done`** — quais entregaram, com qual qualidade.
2. **Estórias que ficaram para trás** — quais e por que.
3. **Mudanças do escopo no meio** — registradas (se houve).
4. **Funcionalidade observável em homologação** — você consegue mostrar/demonstrar?
5. **Métricas do sprint**:
   - Velocidade (quantas estórias / pontos completadas).
   - Cobertura de testes do código novo.
   - Pipeline (verde / vermelho na maioria do sprint).
   - Bloqueios reportados.

### Output da review

- Preencher seção "Fechamento do sprint" em `SPRINT-YYYY-WNN.md` (`templates/sprint.md`).
- Atualizar `index.json`.
- **Comemorar o que foi entregue** — explicitamente. Não é frescura — é cultura de produto saudável.

---

## Retrospectiva — aprendendo do sprint

Diferente da review (o quê), a retro é sobre **o como**: o que funcionou bem, o que custou caro, o que vamos mudar.

### Formato leve

Três perguntas, respondidas honestamente:

1. **O que funcionou e queremos continuar?**
2. **O que custou caro e queremos mudar?**
3. **Um experimento concreto para o próximo sprint.**

### Para time pequeno (incluindo IA + humano)

Em time de 1-3 pessoas, retro pode ser **registrada por escrito** em vez de reunião — a seção "Aprendizados" e "Ajustes para o próximo sprint" do `templates/sprint.md` cumpre.

### Tópicos comuns em retros DEFOnline (projetados)

- **Estimativa erra muito?** Pode ser estória mal detalhada (volta para `story-craft.md`) ou dependência subestimada.
- **Bloqueios frequentes** com Arquiteto? Talvez engajamento proativo (veja `agile-workflow.md`) precisa de mais cedo.
- **Mudanças no meio do sprint**? Pode ser priorização externa fraca, ou DoR sendo violado.
- **PR ficando aberto demais**? Cruza com `programador/references/pr-discipline.md` — PR muito grande, descrição fraca.

### Anti-padrões em retros

- **Culpar pessoa específica** — retro é sobre processo, não pessoa.
- **Tudo OK, próximo!** — se nada deu pra melhorar, ou não está olhando direito, ou o sprint foi excepcional (raro).
- **Ações sem dono nem prazo** — "melhorar comunicação" não faz nada. "PO faz checkpoint com Arquiteto na quarta da abertura do sprint" faz algo.

---

## Quando sprint "falha" — e está ok

Sprint "fracasso" típico:

- Menos da metade das estórias entregue.
- Goal do sprint não atingido.
- Bloqueios consumiram capacidade.

**Falha não é vergonha — é informação.** O que pode estar acontecendo:

- **Estimativa ruim**: time ainda calibrando. Normal nos primeiros sprints.
- **Ambiente em construção**: EPIC-000 e fundação cobram tempo de aprendizado.
- **Dependência externa**: bloqueio fora do controle do time.
- **Mudança de prioridade no meio**: aconteceu, registrar.
- **Estória ambígua**: DoR não foi respeitado — pause, ajuste, continue.

**O que fazer:**

1. **Não esconda**. Status report e retro registram a realidade.
2. **Aprenda**. Cada falha é um insight sobre o processo.
3. **Ajuste**. O próximo sprint incorpora o aprendizado (DoR mais rígido, capacidade ajustada, etc).
4. **Não panique**. Um sprint ruim não significa projeto ruim.

**Quando sinais se acumulam** (3+ sprints consecutivos com problemas similares): pause para diagnóstico mais profundo. Pode ser hora de **PDR de processo** ("estamos vendo padrão X; vamos mudar para Y").

---

## Cadência sugerida

Para DEFOnline (sugestão para validar):

| Atividade | Quando |
|---|---|
| Sprint planning | Manhã do dia 1 do sprint |
| Acompanhamento diário | Curto (~10 min de olhada no índice + chat) — diário |
| Mid-sprint check | Meio do sprint — verifica se goal vai bater |
| Sprint review + retro | Fim do sprint (último dia) |
| Detalhamento de estórias do próximo sprint | Ao longo do sprint atual (continuamente) |

Tamanho do sprint: 1-2 semanas. Para time muito pequeno e MVP cedo, **1 semana** dá ritmo melhor (feedback mais rápido); 2 semanas quando há estórias maiores.

---

## Resumo operacional

Cada sprint passa por:

1. **Abertura**: estórias selecionadas atendem DoR, goal definido, comunicado.
2. **Execução**: acompanhamento contínuo, desbloqueio rápido, mudanças no meio registradas.
3. **Review**: o que entregou, o que ficou, métricas.
4. **Retro**: o que funcionou, o que custou, ação concreta para próximo sprint.
5. **Fechamento**: arquivo de sprint completo, `index.json` atualizado, próximo sprint planejado.

**Sprint bem conduzido entrega mais que estórias — entrega ritmo, previsibilidade e aprendizado contínuo.**
