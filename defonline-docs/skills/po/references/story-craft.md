# Craft de escrita de estória

O template de estória (`templates/story.md`) dá o formato. **Esta reference dá o ofício** — como escrever uma estória que um agente de programação consegue executar em uma sessão sem voltar com perguntas, e que o validador consegue verificar sem ambiguidade.

Estória boa não é estória "completa" — é estória **suficientemente clara**. A diferença está nos detalhes.

---

## A mentalidade

- **Estória é contrato com o agente programador.** Se ela está ambígua, ele inventa. Se ela está prolixa, ele se perde. Você está sendo claro o suficiente?
- **Cada estória atravessa o stack ou entrega algo observável.** Não é unidade técnica — é unidade de valor.
- **Pequeno > grande.** Estória pequena é executada, revisada, validada e deployada rápido. Estória grande acumula risco em cada dia que demora.
- **Acabe a estória antes de começar a próxima.** Spread (mexer em N estórias ao mesmo tempo) é receita pra retrabalho.

---

## INVEST — os 6 critérios da estória boa

Toda estória que você manda para sprint deveria atender, na medida do possível:

| Letra | Significa | Pergunta para se cobrar |
|---|---|---|
| **I**ndependent | Independente | Posso executar essa estória sem ter que executar outra primeiro? |
| **N**egotiable | Negociável | A solução exata é discussável até a implementação, ou está prematuramente travada? |
| **V**aluable | Valiosa | Entrega algo observável de valor para o usuário (ou destrava algo que entrega)? |
| **E**stimable | Estimável | Tenho informação suficiente para o agente saber o esforço (S/M/L)? |
| **S**mall | Pequena | Cabe em uma sessão de execução (idealmente até 2h, máximo 4h)? |
| **T**estable | Testável | Os critérios de aceite são observáveis e podem virar testes? |

**INVEST não é checklist mecânica — é vara de medir.** Se 4 dos 6 estão bem e 2 são "mais ou menos", provavelmente OK. Se 3+ falham, **reescreva ou divida** a estória antes de mandar para sprint.

### Detalhamento prático de cada critério

**Independent — independência**
- Sinal de problema: estória X só faz sentido se Y for feita antes, e Y se Z, e Z se W. Sequência longa = você está modelando incremento, não atomicidade.
- Como melhorar: declare dependências explicitamente (`blocked_by`) em vez de assumir ordem. Reordene quando possível.
- Aceitável: dependências simples e claras (a primeira estória cria a tabela; as seguintes usam — natural). Inaceitável: cadeia de 5+.

**Negotiable — negociável**
- Sinal de problema: a estória prescreve **como** fazer ("crie a tabela X com colunas Y, Z, W"). Isso é decisão do Programador, não sua.
- Como melhorar: escreva o **quê** + **por quê**; deixe o **como** explícito como "decisão do Programador".
- Você prescreve: comportamento observável, critérios de aceite, padrões de qualidade.
- Você **não** prescreve: nome de coluna, estrutura de função, biblioteca específica (a menos que tenha decisão arquitetural cobrindo).

**Valuable — valiosa**
- Sinal de problema: estória cuja única justificativa é "destrava a próxima". Isso é estória de **enablement** — válida, mas precisa ser nomeada como tal (`type: enablement`).
- Como melhorar: para estórias de funcionalidade, articule valor concreto para o usuário. Para estórias de enablement, articule **o que destrava** e **por que destravar isso importa**.
- Exemplo bom: "usuário consegue salvar diagnóstico parcial e voltar depois" → valor claro.
- Exemplo ruim: "implementar mecanismo de persistência intermediária" → o quê, sem o por quê.

**Estimable — estimável**
- Sinal de problema: agente não consegue dizer se é S, M ou L porque a estória deixa zonas escuras.
- Como melhorar: zonas escuras precisam virar **dúvidas explícitas** que você responde ou um spike para o Arquiteto.
- Tamanho prático: **S** (até ~30min de trabalho efetivo), **M** (até ~2h), **L** (até ~4h — considere quebrar). Acima de L: divida.

**Small — pequena**
- Sinal de problema: agente precisa criar 5 arquivos novos, mexer em 8 existentes, atravessar 3 módulos.
- Como melhorar: técnicas de splitting (próxima seção).
- **Não confunda "pequena" com "trivial".** Estória pequena pode ter complexidade técnica relevante; ela só precisa ser **focada**.

**Testable — testável**
- Sinal de problema: AC tem palavras como "rapidamente", "bem", "robusto", "amigável".
- Como melhorar: cada palavra subjetiva vira critério observável. "Rápido" → "p95 < 500ms". "Bem" → "passa nos cenários X, Y, Z".
- Se não dá pra testar, não dá pra validar — o épico não fecha.

---

## Como escrever critérios de aceite (AC)

AC são o coração da estória. Eles viram testes. Eles viram o critério de validação no fim do épico.

### Padrão 1 — Given/When/Then

Bom quando o comportamento é **uma transição de estado** ou **uma operação com pré-condições**:

```
CA-1 — Cadastro de empresa com dados válidos persiste a empresa.
- Dado que estou autenticado como usuário sem empresa cadastrada,
- Quando envio formulário de cadastro com CNPJ válido (com 14 dígitos) e nome,
- Então a empresa é persistida e fica visível na minha lista.

CA-2 — Cadastro de empresa com CNPJ inválido retorna erro específico.
- Dado que estou autenticado,
- Quando envio CNPJ malformado (12 dígitos, letras, ou dígitos verificadores incorretos),
- Então o sistema retorna mensagem "CNPJ inválido — verifique e tente novamente",
  e a empresa não é persistida.
```

### Padrão 2 — Declarativo

Bom para comportamentos sem transição clara ou propriedades do sistema:

```
CA-3 — A listagem de empresas paginada com 20 itens por página.
CA-4 — A senha do usuário nunca aparece em log nem em response da API.
CA-5 — A consulta de diagnóstico responde em menos de 800ms em p95 com dataset de 1k empresas.
```

Use o padrão que mais cabe — **misturar é OK** dentro da mesma estória, desde que cada CA fique claro.

### Hábitos para AC

- **Cada CA é observável.** Posso escrever um teste que verifica? Posso, manualmente, ver isso acontecendo em homologação?
- **Cobre o caminho feliz E pelo menos 2 desvios.** Casos inválidos, exceções, bordas — não delegue para "o programador vai pensar em casos".
- **Não detalhe implementação.** "CA: a tabela `empresas` tem coluna `cnpj`" é errado — implementação é do Programador.
- **Use linguagem do domínio.** "Diagnóstico", "indicador", "MPE" — não "registro", "entidade", "objeto".

---

## Story splitting — quando a estória ficou grande

Estória estimada **L** ou maior deve ser dividida. Técnicas:

### Por workflow (fluxo)

Você tem fluxo do início ao fim. Divida em passos sequenciais.

> **Antes:** Cadastro completo da empresa (formulário com 15 campos, validação, salvar, listar, editar, deletar).
>
> **Depois:**
> - STORY-A: Cadastro mínimo (nome + CNPJ) com listagem básica.
> - STORY-B: Adicionar endereço ao cadastro.
> - STORY-C: Adicionar dados financeiros básicos.
> - STORY-D: Editar empresa existente.
> - STORY-E: Deletar empresa (com soft delete).

Cada uma é deployável em homologação. Cada uma entrega algo observável.

### Por regra de negócio

Você tem uma feature com regras de validação complexas. Divida pelo nível de validação.

> **Antes:** Validar CNPJ na criação de empresa.
>
> **Depois:**
> - STORY-A: Validar formato (14 dígitos numéricos).
> - STORY-B: Validar dígitos verificadores (algoritmo Receita).
> - STORY-C: Validar contra base externa (consulta Receita) — caso decidamos integrar.

### Por variação de dado

Você suporta múltiplos tipos do mesmo dado. Comece com um.

> **Antes:** Importação de planilha (xlsx, csv, ods).
>
> **Depois:**
> - STORY-A: Importação de xlsx (formato mais comum).
> - STORY-B: Importação de csv.
> - STORY-C: Importação de ods.

### Por interface

Mesma feature, múltiplas formas de invocar. Comece com a principal.

> **Antes:** Diagnóstico via app, API e CLI.
>
> **Depois:**
> - STORY-A: Diagnóstico via app web (caminho principal).
> - STORY-B: Diagnóstico via API (clientes terceiros — depois).
> - STORY-C: Diagnóstico via CLI (interno — depois).

### Por caminho feliz vs erros

Em estória complexa, separe robustez de funcionalidade.

> **Antes:** Cadastro com validação completa + erro amigável + retry em falha de rede + idempotência.
>
> **Depois:**
> - STORY-A: Cadastro caminho feliz + validações principais (CA exclui retry/idempotência).
> - STORY-B: Tratamento de erros + retry + idempotência.

Só faça isso quando a primeira estória ainda entrega valor sozinha — caso contrário, mantenha junto.

---

## Spike: como dimensionar (1 ADR = 1 spike)

Estória de spike (`type: spike`, `target_role: arquiteto`) tem regra de dimensionamento própria, **mais estrita** que estória de implementação. Spike grande costuma esconder múltiplas decisões heterogêneas que deveriam estar separadas.

### Regra dura

> **Uma estória de spike concentra no máximo 1 ADR — ou 2 ADRs fortemente correlatas.**

ADRs **fortemente correlatas** são as que decidem o mesmo subsistema e cuja decisão de uma trava a decisão da outra:

- ✅ "autenticação" e "estratégia de sessão" — uma decide a outra; cabem juntas.
- ✅ "fila de jobs" e "broker de mensagens" — acoplamento direto; cabem juntas.

ADRs **heterogêneas** são as que decidem subsistemas diferentes e podem rodar em paralelo:

- ❌ "stack principal" + "CI/CD" + "observabilidade" + "testes" — quatro subsistemas, quatro spikes.
- ❌ "autenticação" + "e-mail transacional" + "consulta CNPJ" — três áreas, três spikes.

ADRs heterogêneas viram **estórias de spike separadas**, executáveis em paralelo. Concentrá-las viola o princípio "tarefa autocontida em uma sessão" e cria gargalo artificial: tudo que depende da spike espera **todas** as ADRs em vez de só a sua.

### Sinais de spike inchada

- CA-1 e CA-2 pedem ADRs sobre áreas que um humano descreveria como "assuntos diferentes".
- A estimativa saiu "L" mesmo o trabalho sendo "só deliberar".
- O título usa plural genérico: "ADRs de fundação", "ADRs de auth e mais coisas".
- A justificativa para juntar é "tudo é arquitetura mesmo" — não é.

### Quando faz sentido manter junto

Quando as ADRs são tão acopladas que decidir uma sem a outra produz inconsistência (raro). Mesmo nesses casos, considere se não é melhor uma **ADR composta** (uma decisão arquitetural única cobrindo o par) em vez de uma **estória composta** (uma spike concentrando decisões separáveis).

### Spikes e cobertura de testes

Spike não exige cobertura unitária nem E2E — não escreve código de produção. Declare a exceção explicitamente na seção "Padrões de qualidade exigidos" da estória, citando `quality-standards.md`.

---

## Definition of Ready (DoR) — quando a estória pode entrar em sprint

Estória pronta para entrar em sprint atende:

- [ ] **Tem ID e título claro** no formato `STORY-XXX — <título>`.
- [ ] **Frontmatter completo**: epic_id, type, target_role.
- [ ] **Contexto e referências** preenchidos com paths concretos (não "ver na spec").
- [ ] **O quê / por quê** articulados (não apenas "o quê" técnico).
- [ ] **CAs testáveis**: cobertura mínima de feliz + 2 desvios; cada CA observável.
- [ ] **Fora de escopo** explicitado — pelo menos uma menção.
- [ ] **Padrões de qualidade aplicáveis** referenciados (incluindo exceções explícitas se for spike, validation, etc).
- [ ] **Dependências resolvidas** ou explicitamente listadas (`blocked_by`).
- [ ] **ADRs/PDRs aplicáveis** listados — agente não precisa ir caçar.
- [ ] **Liberdade técnica do agente** preservada — você não prescreveu implementação.
- [ ] **Definition of Done** presente.
- [ ] **Protocolo do agente** presente (geralmente por referência).
- [ ] **Tamanho estimado** (S/M/L) preenchido — se L, justifique não dividir.

**Sem DoR, estória entra em sprint, vira `blocked` no meio, atrasa a sprint inteira.** Investir 15 minutos em DoR poupa horas.

---

## Estórias que resistem ao vertical slicing

A regra geral é estória vertical (atravessa o stack). **Mas alguns trabalhos são horizontais por natureza** — forçar verticalidade neles cria atrito artificial:

- **Setup de infraestrutura inicial** (provisionar ambiente, configurar pipeline).
- **Observabilidade transversal** (configurar logger, métricas, tracing — não pertence a um fluxo específico).
- **Sistema de autenticação base** (antes de existir feature que usa).
- **Audit log do sistema** (mecanismo, não consumidor).
- **Refatoração estrutural** (mover módulo, renomear convenção em toda a base).

Para essas, **vertical slicing é exceção justificada**, não regra. O que importa:

- O épico que contém ainda entrega **algo observável no fim** (a primeira feature que **usa** a infra).
- A estória horizontal é nomeada como tal (`type: enablement` no frontmatter).
- A justificativa para horizontalidade está escrita na estória ("Esta estória é horizontal porque setup de pipeline não pertence a fluxo de usuário específico. Destrava STORY-XXX, STORY-YYY.").

**Sinal de abuso**: tudo virou enablement, nada virou feature. Aí o problema é outro — provavelmente o PO está postergando entregar valor.

---

## Anti-padrões clássicos a evitar

### "As a user" vazio

```
❌ Como usuário, eu quero clicar no botão para enviar o formulário.
```

"As a user" sem persona específica e benefício real é só estrutura cosmética. Use quando há persona genuína e valor concreto.

```
✅ Cadastro mínimo de empresa (nome + CNPJ válido) para que o usuário possa iniciar uso da plataforma sem ter que preencher todo o cadastro.
```

### Estória técnica disfarçada de user story

```
❌ Como usuário, eu quero que o sistema use Postgres para que os dados persistam.
```

Postgres é decisão arquitetural — ADR. Persistência confiável é NFR — não user story. Use o template apropriado.

### AC ambíguo

```
❌ CA: O sistema deve ser rápido e amigável.
```

"Rápido" e "amigável" não testam. Quantifique:

```
✅ CA: A listagem responde em p95 < 500ms com 1k empresas.
✅ CA: Mensagens de erro citam o campo problemático e sugerem correção.
```

### Estória que prescreve implementação

```
❌ Criar tabela `empresa` com colunas id (UUID), nome (varchar), cnpj (char 14 unique), criada_em (timestamp).
```

Isso é decisão do Programador (com IDR se virar padrão transversal). A estória diz **o quê**:

```
✅ Persistir cadastro de empresa (nome + CNPJ) com CNPJ único por sistema, com timestamp de criação.
```

### "Tudo ou nada"

```
❌ Cadastro completo de empresa com 15 campos, validações, edição, deleção, listagem, busca.
```

Divida (próxima seção). Estória grande é estória que **não entra em sprint**.

### Critério de aceite que vira teste único enorme

```
❌ CA: O cadastro funciona conforme spec.
```

Cada CA é uma asserção. Múltiplos CAs cobrindo o quê precisa funcionar. Não delegue ambiguidade para o teste.

---

## Resumo operacional

Antes de marcar uma estória como `ready`:

- [ ] Passa nos 6 critérios INVEST (com bom senso, não mecanicamente).
- [ ] CAs cobrem feliz + ao menos 2 desvios.
- [ ] Tamanho está adequado (S ou M; L apenas se justificado).
- [ ] Definition of Ready atendido (lista acima).
- [ ] Se for estória horizontal, justificou por quê.
- [ ] Releu uma vez como se fosse o agente que vai executar — está claro?

Estória bem escrita poupa idas e voltas. Investir 15 minutos a mais na escrita economiza horas no ciclo de execução.
