# Protocolo: como o agente programador trabalha com estórias

Este documento descreve o **contrato de execução** entre o PO e o agente programador. O PO o referencia em toda estória. O agente programador deve ler este documento ao começar a primeira estória dele no projeto.

## Princípio central

Uma estória deve ser executável em **uma sessão**. Toda a informação necessária está na estória + nos documentos que ela cita. Sem cadeia de "conversa anterior", sem dependência da memória de outro agente.

## Ciclo de uma estória

```
[ready] → o agente assume
       → status: in_progress, owner_agent: <id>, updated_at: hoje
       → atualiza index.json
       ↓
[in_progress] → agente trabalha
              → escreve testes primeiro (TDD)
              → commits pequenos
              → atualiza "Notas do agente" conforme descobre coisas
              ↓
[in_progress] ─ se bloqueado → [blocked] → registra bloqueio em "Notas do agente" → para
[in_progress] ─ se decisão arquitetural surgir → para e escala para PO/Arquiteto
              ↓
[in_progress] → CA cobertos por testes, cobertura atingida, E2E passando
              → status: in_review, abre PR
              → atualiza index.json
              ↓
[in_review] → revisão humana/automática
            → merge, deploy automatizado para homologação
            → status: done
            → atualiza index.json
```

## Ao começar (passos 1–4)

1. **Carregue a skill apropriada.** Se a estória tem `target_role: programador`, carregue a skill `programador`. Se `arquiteto`, carregue `arquiteto`. Se `validador`, carregue `validador`. Se `designer`, carregue `designer`. Se a estória tem `requires_design: true` mas `target_role: programador`, o **Programador é dono** da estória — mas o **Designer entra em paralelo** desde o início (ver `designer/references/collaboration-with-developer.md`); o Programador não toca a UI até o sync inicial com o Designer (rabisco + ≤15 min de alinhamento).
2. **Leia a estória inteira** antes de qualquer outra coisa. Identifique referências, dependências, decisões já tomadas (PDRs/ADRs).
3. **Leia os documentos referenciados** — não confie só no que está na estória. A spec é mais fundo.
4. **Edite o frontmatter da estória:**
   ```yaml
   status: in_progress
   owner_agent: <seu_identificador_ou_session_id>
   updated_at: YYYY-MM-DD
   ```
   E atualize `index.json` correspondentemente. Isso protege de outro agente pegar a mesma estória.

## Durante a execução

- **TaskList interna.** Use o tracker de tarefas para se organizar. Não é obrigatório expor isso na estória — é seu rascunho.
- **TDD.** Para cada CA, escreva teste antes do código. Os critérios da estória são guia direto para os testes.
- **Commits pequenos e nomeados.** Cada commit faz sentido isolado.
- **Sem decisão fora do escopo.** Se durante a execução você perceber que precisa decidir algo que afeta outras estórias (mudar contrato de API público, mudar esquema de tabela compartilhada, escolher tecnologia transversal), **PARE**. Registre em "Notas do agente" com a tag `[ESCALONAMENTO]` e mude status para `blocked`.
- **Descobertas.** Toda surpresa relevante (gotcha, comportamento inesperado da plataforma, requisito implícito) entra em "Notas do agente" assim que você descobre. Não confie em lembrar depois.

## IDR — Implementation Decision Record

Crie um IDR quando você tomou uma decisão técnica de baixo nível com **impacto futuro** (outros agentes vão se basear nela). Exemplos:

- Adotei um padrão de tratamento de erro X em todas as APIs.
- Defini que toda função pública do módulo Y vai assinar `Result<T, E>` em vez de jogar exceção.
- Escolhi uma estrutura de migração reversível com convenção Z.

Crie em `defonline-docs/project-state/decisions/idr/IDR-XXX-<slug>.md` usando uma estrutura similar ao PDR (contexto, decisão, consequência). Referencie o IDR no `index.json` e em "Notas do agente" da estória.

Não crie IDR para:

- Escolhas locais sem impacto fora do módulo (nome de variável, ordem de funções, etc).
- Coisas já cobertas por um ADR.

## Se travar

1. Tente desbloquear sozinho por **até 15 minutos** (configuração de ambiente, lookup de documentação, etc).
2. Se persistir, identifique a categoria:
   - **Falta decisão de produto** → escalonar para o PO (mude status para `blocked`, registre dúvida em "Notas").
   - **Falta decisão arquitetural** → escalonar para o Arquiteto (mesma coisa).
   - **Limitação técnica concreta** → IDR descrevendo a limitação + proposta + estado `blocked`.
3. **Não invente decisão.** Você é o agente programador. Suas decisões são locais — código, testes, refatoração. Decisões de produto/arquitetura são de outros papéis.

## Ao terminar (passos 1–5)

1. **Confirme a DoD da estória.** Leia a Definição de Pronto e marque cada item.
2. **Preencha a seção "Notas do agente"** com decisões locais, descobertas, IDRs, links de evidência.
3. **Atualize o frontmatter:**
   ```yaml
   status: in_review
   updated_at: YYYY-MM-DD
   ```
4. **Atualize `index.json`** — status da estória, ponteiros para IDRs novos.
5. **Abra PR** e linke na estória. O épico só fecha após o validador rodar sobre o conjunto.

## Como o agente lê o `index.json` para descobrir o que fazer

Quando uma sessão começa para "executar a próxima estória", o agente pode:

1. Abrir `defonline-docs/project-state/index.json`.
2. Filtrar estórias com `status: ready` e `target_role: programador`.
3. Ordenar por `priority` ou `epic_order`.
4. Verificar `blocked_by` — pular se as bloqueadoras não estão `done`.
5. Assumir a primeira disponível.

Esse fluxo é o que torna o sistema escalável — não precisa de um PO no loop pra cada estória, só pra revisão e desbloqueio.

## Limites firmes

- **Nunca** mude critério de aceite da estória sem aprovação do PO. Se o critério está errado, registre em "Notas" e bloqueie.
- **Nunca** edite estórias de outros agentes (`status: in_progress` por alguém ≠ você).
- **Nunca** marque `done` sem PR mergeado e deploy verde em homologação.
- **Nunca** suprima cobertura de teste só para passar — peça pro PO baixar a meta (e ele vai dizer não).
