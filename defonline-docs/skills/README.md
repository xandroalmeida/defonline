# Skills do projeto DEFOnline

Este diretório contém as skills que definem como agentes de IA atuam neste projeto. Cada skill representa um **papel** com fronteiras claras de decisão.

## Skills disponíveis

| Skill | Papel | Status |
|---|---|---|
| [`po/`](po/SKILL.md) | **Product Owner** — decide o quê, por que, para quem, em que ordem; mantém o estado do projeto | **completa** |
| [`arquiteto/`](arquiteto/SKILL.md) | **Arquiteto** — decisões técnicas de alto nível, ADRs | **completa** |
| [`designer/`](designer/SKILL.md) | **Designer** — UX/UI das telas; mantém Design System; registra DDRs; spec de tela mobile-first | **completa** |
| [`programador/`](programador/SKILL.md) | **Programador** — executa estórias, implementa código com qualidade exigida | **completa** |
| [`validador/`](validador/SKILL.md) | **Validador** — valida fim de épico, produz relatório `approved`/`rejected` | **completa** |

## Fronteiras de papel (resumo)

```
PO ──────── O QUÊ + POR QUÊ + QUANDO + QUALIDADE EXIGIDA
            │
            │  (escreve estória)
            │
   ┌────────┴────────┐
   ▼                 ▼
Designer       Programador ──── COMO em baixo nível (implementação)
(UX/UI, DS,    (em paralelo                │
DDR, spec      com o Designer              │  (consulta ADRs vigentes
de tela)       na mesma estória)           │   do Arquiteto)
   │                 │                     ▼
   └────────┬────────┘              Arquiteto ──── COMO em alto nível
            │                                      (stack, padrões, ADRs)
            ▼  (entrega código + testes + spec coerente)
       Validador ───── VERIFICA tudo no fim do épico
```

Um papel **nunca** cruza para a área do outro. O PO não programa, o Programador não decide produto, o Arquiteto não escreve testes E2E, o Designer não escolhe stack nem altera CA da estória, o Validador não conserta nada.

**Designer e Programador trabalham em paralelo** na mesma estória de UI — rabisco inicial do Designer + sync curto antes do código começar (ver `designer/references/collaboration-with-developer.md`). O Designer **revisa** o PR contra o spec, mas **não** emite veredito independente — isso é do Validador.

**O Arquiteto entra antes** quando o PO abre estória de spike arquitetural, e suas ADRs vigentes restringem o que Designer e Programador podem decidir.

## Como o agente carrega uma skill

Quando uma sessão começa para trabalhar no DEFOnline, o agente:

1. Lê esta página.
2. Identifica seu papel da conversa (ou da estória atribuída).
3. Carrega a skill correspondente (ex: invoca a skill `po`).
4. A skill traz instruções operacionais e referências detalhadas.

## Estado do projeto

O estado vivo (épicos, estórias, sprints, decisões) fica em `../project-state/`. Veja `../project-state/README.md`.
