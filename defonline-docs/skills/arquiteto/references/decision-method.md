# Método de decisão arquitetural

O Arquiteto **delibera** antes de propor. ADRs sem método são opinião com formato bonito. Esta reference cobre **como** chegar a uma decisão boa: enumerar opções, definir critérios, ponderar honestamente, evitar armadilhas cognitivas, e reconhecer quando deferir é a resposta certa.

A ideia central: **uma boa decisão é defensável**. Você consegue explicar, sem retórica, por que ela venceu — e que trade-offs aceitou.

---

## A mentalidade

- **Comece amplo, feche estreito.** Antes de enumerar opções, entenda o problema. Antes de comparar, defina o que importa. Antes de decidir, descarte ruído.
- **Honestidade técnica antes de eloquência.** ADR não é peça de marketing da sua escolha. É registro do raciocínio.
- **Reconheça quando não sabe.** "Não tenho info suficiente para decidir agora" é uma decisão válida — geralmente leva a spike ou `deferred`.
- **Os princípios são vara de medir, não muleta.** Cite o princípio que aplica, mas explique **como** ele aplica — não só "viola princípio X" sem contexto.

---

## Etapa 1 — Entender o problema antes de buscar solução

A maioria das ADRs ruins começam aqui: o Arquiteto pula direto para "vamos comparar opções" sem entender bem o que está decidindo.

**Perguntas para fechar antes de qualquer opção:**

- **Qual é o problema real?** Em uma frase, sem solução embutida.
- **Quem está pedindo essa decisão?** (PO via spike? Programador travado? Necessidade detectada por você?)
- **Por que agora?** Custo de adiar vs custo de decidir.
- **Quais as restrições duras?** Coisas que **não podem** ser violadas — Postgres já decidido, TDD obrigatório, certo orçamento, certo prazo.
- **Quais as restrições suaves?** Coisas que pesam mas podem ser relaxadas se a alternativa for muito boa.
- **Decisões anteriores que limitam?** ADRs aceitas que esta nova decisão respeita.

**Sinais de que você ainda não entendeu o problema:**

- Você já tem "uma intuição clara de qual a resposta" antes de explorar — é viés.
- Você não consegue formular o problema sem mencionar uma tecnologia.
- O problema parece ter "uma resposta óbvia" mas ninguém ainda escreveu ADR — investigue por quê.

**Tempo investido aqui paga dividendo.** Decisão sobre o problema **certo** com 2 opções é melhor que decisão sobre o problema errado com 6 opções.

---

## Etapa 2 — Identificar as forças (drivers da decisão)

Forças são os critérios que vão **pesar** na escolha. Sem nomeá-las explicitamente, a comparação é subjetiva.

**Como identificar:**

- **Restrições funcionais**: do que a especificação precisa.
- **Restrições não-funcionais**: SLO de latência, disponibilidade, capacidade, custo (veja `nfr-architecture.md`).
- **Princípios arquiteturais aplicáveis**: nem todos os 12 importam em cada decisão. Identifique os 3-5 mais relevantes.
- **Limitações do contexto**: time pequeno (princípio #4 viés a opinativo), orçamento (princípio #11), prazo, hire-ability local.
- **Reversibilidade da decisão** (princípio #7): se for irreversível na prática, o escrutínio aumenta.

**Bom hábito**: cada força recebe **peso** — alto, médio, baixo — ou numérico se você quiser. Forças com peso alto são determinantes; baixas só desempatam.

**Exemplo de tabela de forças:**

| Força | Peso | Origem |
|---|---|---|
| Latência p95 < 500ms | Alto | NFR documentado |
| Custo ≤ R$X/mês | Alto | Restrição de orçamento atual |
| Compatível com TDD/E2E (princípio #10) | Alto | Princípio inegociável |
| Time-to-hire de quem conhece a tech | Médio | Realidade de time pequeno |
| Reversibilidade da escolha | Médio | Princípio #7 |
| "Modernidade" | Baixo | Bônus se vier, não decisor |

---

## Etapa 3 — Enumerar opções reais

**Pelo menos 2 opções + status quo.** Aceite que status quo (não decidir agora, manter como está) é opção válida — descarte-a explicitamente quando for o caso.

**Como gerar opções:**

- Comece pelo **óbvio** — qual seria a escolha sem nenhuma reflexão? Coloque na lista mesmo se você suspeita que é ruim.
- Pergunte **"qual o oposto?"** — gera diversidade.
- Considere o caminho **mais simples** absoluto (princípio #1).
- Considere o caminho **mais conservador** (princípio #7).
- Pergunte **"o que faríamos se não pudéssemos fazer X?"** — desbloqueia ideias quando você está fixado em uma direção.

**Tarefas anti-falso-dilema:**

- **Duas opções aparentemente diferentes** mas que na prática resolvem o mesmo problema com nomes diferentes = uma opção, não duas.
- **"Opção A ou opção B"** quando a real escolha é "uma característica de A combinada com uma de B" = você está faltando uma 3ª opção real (a combinação).
- **"Tudo ou nada"** — pergunte se uma versão **incremental** existe.

**Sinal de armadilha**: você só consegue ver 2 opções e elas são polarizadas. Quase sempre há uma 3ª no meio que você não considerou.

**Limite saudável**: 2-5 opções. Mais que 5 sinaliza que você não filtrou o suficiente — pré-descarte as obviamente ruins antes da análise.

---

## Etapa 4 — Avaliar cada opção contra as forças

Para cada opção, avalie como ela atende cada força. Pode ser qualitativo (`✅` / `⚠️` / `❌`) ou numérico (1-5).

**A matriz comparativa do ADR é exatamente isso:**

| Critério (força) | Peso | Opção A | Opção B | Status quo |
|---|---|---|---|---|
| Latência p95 | Alto | ✅ ~150ms benchmark | ⚠️ ~400ms (limite) | ❌ não atende |
| Custo recorrente | Alto | ⚠️ R$X | ✅ R$X/2 | ✅ R$0 |
| Compat. TDD/E2E | Alto | ✅ excelente | ⚠️ E2E custoso | ✅ |
| Reversibilidade | Médio | ⚠️ médio | ✅ fácil reverter | ✅ |

**Princípios aplicáveis devem aparecer explicitamente como critérios.** Cada opção é defendida ou questionada contra eles.

---

## Etapa 5 — Identificar trade-offs honestamente

Nenhuma opção é perfeita — toda decisão é trade-off. **Nomeie os trade-offs**:

- "Opção A entrega latência melhor mas custa 30% a mais."
- "Opção B é mais simples mas adiciona dependência externa que viola princípio #6."
- "Status quo é grátis mas não atende o SLO documentado."

**Anti-padrão**: esconder o trade-off ("a opção A é melhor em tudo") — geralmente é viés ou análise incompleta. Procure ativamente o lado fraco da sua opção favorita.

**Princípio "restrições são informação"** (12): trade-offs aceitos viram parte do ADR — registrados explicitamente, não escondidos.

---

## Etapa 6 — Confirmar contra os 6 princípios centrais

Antes de fechar a opção vencedora, **passe um pente fino contra os princípios centrais** (1-6):

1. **Simples é o belo** — esta opção é a mais simples que resolve? Se não, justifica a complexidade?
2. **Tudo começa em monolito** — esta opção respeita ou tem motivo concreto para fugir?
3. **Postgres-first** — esta opção evita adicionar armazenamento sem necessidade?
4. **Frameworks opinativos** — esta opção aproveita defaults ou luta contra eles?
5. **Coesão alta, acoplamento baixo** — esta opção mantém bordas claras?
6. **Funcionamento 100% local** — esta opção é compatível com Docker + mocks?

**Se uma opção viola um princípio central**, isso é red flag. Ou:
- Você está com viés (revisite as opções).
- A violação é justificada e você precisa **registrar o trade-off honestamente** no ADR.
- O princípio precisa ser revisto (raríssimo — exige ADR `type: meta`).

---

## Quando recomendar `deferred`

Algumas decisões devem ser **adiadas**. Reconhecer isso é parte do trabalho do Arquiteto — não fraqueza, sabedoria.

**Critérios para adiar:**

- **Custo de decidir agora > custo de adiar.** Se a decisão pode esperar 2-3 sprints sem dano e a clareza vai melhorar com uso real, adie.
- **Falta dado essencial.** Você está adivinhando volume, latência, comportamento de usuário. Adie até ter sinal real.
- **Decisão depende de outra ainda não tomada.** Cadeia de dependência → resolva a primeira primeiro.
- **Risco baixo de não decidir.** Não vai bloquear estória nas próximas semanas? Adie.

**Quando NÃO adiar:**

- A decisão **bloqueia estórias ativas**.
- O custo de adiar é maior (lock-in se você não decidir cedo, perda de janela de oportunidade).
- A decisão é simples e os princípios apontam claramente — apenas "fazer e seguir".

ADR `deferred` é decisão consciente, não procrastinação. Tem **gatilho explícito de retomada** — uma data, evento, métrica ou volume.

---

## Quando pedir spike

Spike é estória curta para o Programador validar empiricamente algo que não dá pra decidir só no papel.

**Quando pedir spike:**

- Decisão de **stack** ou **infra** — você quer validar que sobe e funciona antes de aceitar. Princípio "reversibilidade" (7): decisões caras justificam spike.
- Performance esperada incerta — benchmark real desfaz especulação.
- Compatibilidade entre dois componentes (ex: lib X com framework Y) — 30 min de spike mata 3 dias de discussão.
- Comportamento da plataforma/cloud incerto — teste antes de codificar dependência.

**Como definir um spike eficiente:**

- **Pergunta de sim/não.** Spike responde uma pergunta específica, não "explore X".
- **Time-boxed.** Máximo 1-2 dias. Se precisar mais, é estória de implementação, não spike.
- **Saída descartável.** Código de spike pode ser jogado fora; o output é o aprendizado, não o código.

**Spike fracassado** (a tecnologia não atende, o benchmark deu ruim) é informação valiosa — alimenta a ADR. Spike "fracassado" frequentemente é o que evita decisão ruim.

---

## Apresentação da decisão para o humano

Você é conselheiro, humano é árbitro. O modo como você apresenta importa.

**Boa apresentação:**

- **Resumo em 3-5 linhas** no chat — não envia a ADR inteira esperando o humano ler 200 linhas.
- **Trade-offs** explicitados — não esconda o lado fraco da sua proposta.
- **Confiança calibrada** — quando você tem certeza, diz; quando há dúvida, diz também ("vou na A mas se você quer B eu ajusto").
- **Pergunta direta** no fim: "Aceito?".

**Ruim:**

- "Esta é claramente a melhor opção" — alarme de viés ou análise pobre.
- "Considerei muitas opções..." — sem listar quais e por que descartou.
- "Não tenho preferência forte" sem motivo — você é o conselheiro, dê opinião fundamentada.

---

## Anti-padrões de deliberação

**1. Confirmation bias** — você decidiu antes de analisar; a análise vira teatro pra confirmar.

*Antídoto*: invente uma versão de você que defende a opção oposta. Que argumentos ela traria? Eles te abalam?

**2. Sunk cost** — "já investimos muito em X, vamos seguir".

*Antídoto*: a decisão é entre o estado atual + esforço pra continuar A vs estado atual + esforço pra mudar para B. Esforço passado não conta. (Em ADR que supersede, exatamente o que vc avalia.)

**3. "Moderno = melhor"** — escolhe pela popularidade no twitter de dev.

*Antídoto*: pergunte "isso resolve um problema real que tenho?". E "tem 5+ anos de adoção?".

**4. Análise paralisia** — você tem 4 opções todas razoáveis e fica girando.

*Antídoto*: defina critério de desempate **antes** de continuar (princípios centrais primeiro). Se ainda empatar, jogue moeda — opções "razoavelmente equivalentes" são realmente equivalentes; tempo perdido escolhendo é maior que o ganho da escolha.

**5. "Vamos só mais uma opção"** — abre opções infinitas em vez de fechar análise.

*Antídoto*: time-box a fase de enumeração. 2 horas ou menos para a maioria das decisões. Depois fecha.

**6. Decidir sozinho um trade-off que deveria ser do humano.**

*Antídoto*: quando o trade-off é entre 2 valores legítimos e ambos os caminhos respeitam princípios, **ofereça** as opções no chat antes de escrever a ADR favorita. O humano decide.

**7. Ignorar o status quo.**

*Antídoto*: status quo (não fazer nada) é sempre opção válida. Avalie-o explicitamente.

---

## Resumo operacional

Para decisão arquitetural típica, este é o fluxo:

1. **Entender o problema** (15-30 min de leitura, conversa, exploração).
2. **Identificar forças** com peso (curto — uma tabela).
3. **Enumerar opções reais** (anti-falso-dilema; 2-5 opções).
4. **Avaliar contra forças** (matriz comparativa).
5. **Identificar trade-offs honestamente** (incluindo da sua favorita).
6. **Passar contra os 6 princípios centrais** (gate final).
7. **Decidir ou recomendar adiar** com gatilho de retomada.
8. **Pedir spike se justifica** (caro/irreversível).
9. **Escrever ADR usando o template** + diagrama se aplicável.
10. **Apresentar ao humano** com resumo curto e pergunta direta.
11. **Aguardar aprovação humana** (sem auto-aceite).

> **A diferença entre uma boa decisão e uma sortuda é se você consegue explicar, em retrospecto, por que era boa antes de saber o resultado.**
