---
idr_id: IDR-XXX
slug: nome-curto-kebab
title: Título humano da decisão de implementação
status: proposed  # proposed | accepted | superseded
decided_at: YYYY-MM-DD
decided_by: programador
owner_agent: <id/sessão do agente que decidiu>
related_story: STORY-XXX
related_adrs: []
related_idrs: []
supersedes: null
superseded_by: null
created_at: YYYY-MM-DD
updated_at: YYYY-MM-DD
---

# IDR-XXX — <título>

> **O que é um IDR.** Implementation Decision Record. Registra uma decisão técnica **local** que vai impactar outras estórias ou outros agentes — porque vira padrão do projeto ou porque é um workaround que ninguém deve refazer. **Não** registra decisões puramente locais a um módulo (essas vão no PR e nas "Notas do agente" da estória). Quando em dúvida, pergunte: "Se outro agente abrir o código daqui a um mês, ele precisa saber disso?". Se sim, é IDR.

## Contexto

<1–2 parágrafos. Que situação você encontrou? Por que precisou decidir algo? Se for workaround, descreva o comportamento da plataforma/lib que motivou.>

## Decisão

> **Decidi <X>.**

<Frase única em modo afirmativo do que foi escolhido.>

## Por quê

<Por que esta opção e não outras. Conecte com princípios (`references/coding-principles.md`), ADRs relacionados, ou com a natureza específica do problema.>

## Alternativas consideradas

> Não é necessário ser exaustivo como ADR — basta nomear 1 ou 2 alternativas reais que você considerou e descartou, com motivo.

- **Alternativa A — <nome>**: <motivo do descarte>
- **Alternativa B — <nome>**: <motivo do descarte>

## Consequências

### Para outros agentes
- <ex: "todo módulo novo que faça X deve seguir este padrão">
- <ex: "esta lib agora é a padrão para Y; não introduzir outra equivalente">

### Para o projeto
- <ex: "+1 dependência transversal">
- <ex: "+5min no build CI">
- <ex: "workaround documentado — pode ser removido quando a lib Z chegar na versão N+1">

### Trade-offs aceitos
- <reconheça honestamente as desvantagens>

## Como verificar (se aplicável)

> Como saberíamos se essa decisão está sendo respeitada ou se virou obsoleta? Sem isso, IDR vira ruído.

- <ex: "lint custom verifica que ninguém importa lib X sendo Y o padrão">
- <ex: "se a lib Z lançar versão N+1, reabrir esta IDR">

## Tipo

> Marque a categoria principal — ajuda a pesquisar IDRs depois.

- [ ] **Padrão transversal**: lib/abordagem que vira default no projeto.
- [ ] **Workaround**: contornar bug/limitação documentado.
- [ ] **Convenção interna**: padrão de código local que precisa ser seguido (formato de erro, naming de evento, etc).
- [ ] **Otimização**: mudança feita por motivo de performance, com medição.
- [ ] **Refatoração estrutural**: mudança que afeta vários módulos por motivo de qualidade.

---

## Histórico

- YYYY-MM-DD — criada como `proposed` por programador (sessão <id>) durante STORY-XXX
- YYYY-MM-DD — <mudança>
