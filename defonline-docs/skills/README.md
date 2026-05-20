# Skills do projeto DEFOnline

Este diretório contém as skills que definem como agentes de IA atuam neste projeto. Cada skill representa um **papel** com fronteiras claras de decisão.

## Skills disponíveis

| Skill | Papel | Status |
|---|---|---|
| [`po/`](po/SKILL.md) | **Product Owner** — decide o quê, por que, para quem, em que ordem; mantém o estado do projeto | **completa** |
| [`arquiteto/`](arquiteto/SKILL.md) | **Arquiteto** — decisões técnicas de alto nível, ADRs | **completa** |
| [`programador/`](programador/SKILL.md) | **Programador** — executa estórias, implementa código com qualidade exigida | **completa** |
| [`validador/`](validador/SKILL.md) | **Validador** — valida fim de épico, produz relatório `approved`/`rejected` | **completa** |

## Fronteiras de papel (resumo)

```
PO ──────── O QUÊ + POR QUÊ + QUANDO + QUALIDADE EXIGIDA
            │
            ▼  (escreve estórias)
Arquiteto ─ COMO em alto nível (stack, padrões, contratos)
            │
            ▼  (decide via ADR)
Programador ─ COMO em baixo nível (implementação concreta)
            │
            ▼  (entrega código + testes)
Validador ── VERIFICA tudo no fim do épico
```

Um papel **nunca** cruza para a área do outro. O PO não programa, o Programador não decide produto, o Arquiteto não escreve testes E2E.

## Como o agente carrega uma skill

Quando uma sessão começa para trabalhar no DEFOnline, o agente:

1. Lê esta página.
2. Identifica seu papel da conversa (ou da estória atribuída).
3. Carrega a skill correspondente (ex: invoca a skill `po`).
4. A skill traz instruções operacionais e referências detalhadas.

## Estado do projeto

O estado vivo (épicos, estórias, sprints, decisões) fica em `../project-state/`. Veja `../project-state/README.md`.
