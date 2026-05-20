# Priorização — framework, decisões difíceis, gestão de débito

Priorizar é a decisão mais frequente do PO. Toda semana há mais "o que fazer" do que capacidade. Sem framework, você decide por gosto ou pelo que está mais barulhento — ambos enganam.

Esta reference dá ferramentas práticas para: **escolher o que entra**, **dizer não** com base em raciocínio, e **gerenciar débito técnico** sem fazer ele apodrecer nem virar refém dele.

---

## A mentalidade

- **Priorizar é dizer "não" mais que dizer "sim".** Toda escolha de "vamos fazer X" implica "não vamos fazer Y, Z, W agora".
- **Capacidade é finita.** O time tem N estórias por sprint. Priorizar não cria capacidade — escolhe o que entra na N existente.
- **Custo de oportunidade é real.** A estória que você inclui custa as outras que ela exclui — não só o tempo dela.
- **Estimativa é guia, não verdade.** Use estimativa de tamanho, mas saiba que estimativas erram. Mantenha viés a sub-estimar e prepare-se para ajustar.

---

## Critérios fundamentais

Toda decisão de priorizar pondera, em alguma medida:

| Critério | Pergunta concreta |
|---|---|
| **Valor para o usuário** | Quanto isso melhora a vida de quem usa? |
| **Valor para o negócio** | Quanto isso aproxima a métrica de norte (north star)? |
| **Custo** | Quanto esforço (tempo + complexidade + risco) custa? |
| **Risco** | Quanto pior fica se NÃO fizermos? Quanto pior se fizermos errado? |
| **Urgência / janela** | Existe gatilho temporal (prazo, evento, oportunidade)? |
| **Dependências** | Outras coisas dependem dessa? Está bloqueando? |
| **Aprendizado** | Quanto aprendemos sobre o produto/usuário com isso? |

**Bom hábito:** para cada item significativo do backlog, classifique cada critério em **alto/médio/baixo** mentalmente. Itens com vários "alto" sobem; vários "baixo" descem.

---

## RICE leve

Framework comum, útil em formato simplificado:

| Letra | Significa | Como estimar |
|---|---|---|
| **R**each | Quantas pessoas afetadas | Ordem de magnitude: poucas / muitas / todas |
| **I**mpact | Quanto melhora a vida delas | Marginal / médio / forte / transformador |
| **C**onfidence | Quão confiantes estamos no impact | Achismo / hipótese / sinal / dado |
| **E**ffort | Esforço para entregar | S/M/L/XL ou pessoa-sprint |

**Score = (R × I × C) / E**.

Não obsess sobre números exatos — comparar **ordens de magnitude** entre itens já filtra ruído.

**Uso prático**: pegue 5-10 itens candidatos a entrar em sprint, classifique mentalmente cada um nos 4 eixos, e veja quais se destacam. Itens "altos em R+I, baixos em E" são prioridade óbvia. Itens "altos em E, baixos em R+I" provavelmente saem.

---

## Cost of Delay (custo de adiar)

Inverte a pergunta: em vez de "quanto vale fazer isso?", pergunte "quanto custa **não** fazer isso agora?".

Cost of Delay alto quando:

- Há janela de oportunidade que vai fechar (lançamento de concorrente, regulação, sazonalidade).
- O custo aumenta com tempo (débito que apodrece, integração que vai ficar mais cara de fazer).
- Usuários estão frustrados e vão embora.
- Está bloqueando outras estórias com bom retorno.

Cost of Delay baixo quando:

- Pode esperar 1-2 ondas sem prejuízo.
- O contexto está incerto e clarear depois.
- A solução barata ad-hoc atende por enquanto.

**Quando você tem dois itens "empatados" em valor:** o de maior cost of delay vai primeiro.

---

## MoSCoW pragmático

Classificação rápida para listas:

| Classe | Significa | Exemplo |
|---|---|---|
| **M**ust have | Sem isso, o épico não fecha | Cadastro mínimo, validação básica de CNPJ |
| **S**hould have | Importante mas não bloqueia o épico | Mensagens de erro polidas, validação de dígito verificador |
| **C**ould have | Bom ter, fica se sobrar tempo | Animações de feedback, formato amigável de telefone |
| **W**on't have (this time) | Explicitamente fora | Edição em massa, integração com Receita |

Use para **alinhar** uma lista grande rapidamente. Não use para tomar decisão final — MoSCoW divide em 4 grupos, mas decisões dentro de cada grupo ainda exigem julgamento.

**Hábito útil:** ao decompor épico, classifique cada estória M/S/C/W. Itens "W" você documenta no `fora de escopo` do épico — saem do backlog ativo.

---

## Como dizer não (sem matar relacionamento)

Você vai receber pedidos: do Alexandro, de stakeholders futuros, de você mesmo ("ah seria tão legal se..."). Dizer sim a tudo é receita pra entregar nada.

### Princípios

- **Sempre reconheça o pedido.** A pessoa pediu por motivo — entender o motivo antes de responder importa.
- **Explique o porquê, não o como.** "Vai contra o objetivo da onda" > "vai dar muito trabalho".
- **Ofereça alternativa quando possível.** "Não para agora, mas posso colocar na próxima onda" > "não".
- **Seja firme sem ser frio.** Você decidiu por critério — defenda o critério.

### Padrões úteis

**"Não agora, mas..."**
> "Esse pedido faz sentido, mas a onda atual está focada em X. Coloco no rascunho da próxima onda; se ainda for prioridade quando chegarmos lá, entra."

**"Vamos investigar antes"**
> "Antes de comprometer, preciso entender [Y]. Pode demorar uma semana — depois disso, te dou resposta firme."

**"Não vejo o ganho"**
> "Considerei e o custo me parece maior que o ganho — me ajude a entender o que estou perdendo?"

**"Fora do escopo do produto"**
> "Esse pedido seria valioso, mas vai contra a tese central do produto (servir MPE, não médias). Não vou fazer."

### Quando recuar do "não"

- Pessoa traz **dado novo** que invalida sua análise inicial.
- Você descobre que **subestimou** o valor.
- Custo mostra-se muito menor do que pensava.

**Recuar não é fraqueza** — é honestidade. Mas recuar por insistência (sem dado novo) é. Cuidado.

---

## Tradeoff entre feature, débito e bug

Toda sprint tem 3 candidatos competindo pela capacidade:

| Tipo | O que entrega | Quando ganha |
|---|---|---|
| **Feature** | Valor novo para o usuário | Default em fase de crescimento |
| **Bug** | Conserto do que deveria funcionar | Quando afeta usuário ativo ou bloqueia outra coisa |
| **Débito técnico** | Capacidade de entregar mais rápido depois | Quando atrasa entrega real ou aumenta risco |

### Como pensar o equilíbrio

**Orçamento informal sugerido para sprint típico:**

- **~70% feature** — entregando valor (default em crescimento).
- **~20% bug** — corrigindo o que está quebrado e impacta usuário.
- **~10% débito** — investindo em capacidade futura.

Esses números variam **por contexto**:

- Sprint **pós-incidente**: pode ser 50/40/10 (mais bug, menos feature).
- Sprint **pós-EPIC grande**: pode ser 50/10/40 (pagando débito acumulado, menos feature).
- Sprint **enxuta**, near de validação importante: 80/15/5 (focada).

**Não vire feature factory** (0% débito por meses) — você está vendendo seu futuro pelo presente. Não vire **eternamente refatorando** (50%+ débito) — você está consertando algo que não justifica.

### Sinais de que débito está pedindo atenção

- Programador escreve IDR descrevendo workaround que custou tempo significativo.
- Arquiteto sinaliza "ADR X está mostrando limite" (gatilho de revisão, veja `arquiteto/references/adr-lifecycle.md`).
- Estimativa de estórias começa a inflacionar (M virando L sem motivo claro de produto).
- Incidentes recorrentes na mesma área.
- Onboarding de novo dev/agente demora porque "tem que aprender essa coisa estranha".

Quando 2+ sinais aparecem na mesma área, agende **estória de débito** explícita — não espere virar incêndio.

### Quando débito vira PDR

Se o débito é grande e estrutural ("estamos repensando como módulo X funciona inteiro"), isso não é estória de débito — é **decisão de produto** sobre investir tempo em melhoria estrutural vs entregar feature nova. Vira **PDR**: registra a decisão, o porquê, o custo aceito, o que destrava.

Sem PDR, débito grande vira "vou pegando aos pouquinhos" e nunca acaba.

---

## Quando escalar para o Alexandro

Você (PO) decide priorização tática. Mas algumas decisões exigem **conversa com Alexandro** antes:

- **Mudança de tese de produto.** Algo na realidade desafia a direção do produto. Não é "qual feature primeiro" — é "estamos indo na direção certa?".
- **Trade-off estratégico grande.** Pegar débito enorme vs entregar feature crítica. Você tem opinião, mas a decisão final é dele.
- **Decisão que envolve resource externo** (orçamento extra, contratar pessoa, integrar com fornecedor).
- **Conflito não-resolvível por critério.** Você analisou e há duas opções equivalentes — preferência dele desempata.

**Formato de escalação:**

> "Preciso da sua decisão sobre [tema]. Resumo:
> - **Opção A:** ... (prós/contras curtos)
> - **Opção B:** ... (prós/contras curtos)
> - **Minha recomendação:** A (ou "sem preferência forte") porque [Y].
>
> Vai impactar [Z]. Quando podemos conversar?"

Apresente como pergunta com proposta, não como dúvida solta.

---

## Anti-padrões clássicos

**1. "Quem grita mais, ganha."**
Priorizar pelo barulhento é tomar decisão por viés. Mantenha critério. Pessoa insistente sem dado novo: revisite os critérios, responda firmemente.

**2. "Tudo é urgente."**
Quando tudo é prioridade, nada é. Force ranking — só 1 pode ser a #1.

**3. "Sunk cost na priorização."**
"Já trabalhamos tanto nisso, vamos seguir" — sunk cost é falácia. A pergunta é: dado o estado atual, o que tem maior retorno daqui pra frente?

**4. "Refazer roadmap toda semana."**
Priorização instável transmite a mensagem de que não há plano. Defina o que entra no sprint **na abertura** e respeite — mudança no meio é exceção, não regra (veja `sprint-mechanics.md`).

**5. "Feature factory sem medir."**
Empilhar feature sem validar nenhuma é forma de não aprender. Tenha pelo menos algum esforço de medição depois da entrega (veja `measurement-and-learning.md`).

**6. "Priorizar pelo desejo do PO."**
Você tem preferências. Reconhece — mas decide pelo critério, não pelo desejo.

**7. "Não decidir e ver no que dá."**
Não decidir **é** uma decisão — a de seguir o status quo. Se você está adiando decisão importante por desconforto, force a decisão.

---

## Resumo operacional

Para cada momento de priorização:

1. **Identifique os candidatos** (até ~10; mais que isso, filtre antes).
2. **Classifique** cada um nos critérios fundamentais ou em RICE/MoSCoW.
3. **Considere cost of delay** para desempatar.
4. **Verifique o equilíbrio** feature/bug/débito da sprint.
5. **Tome a decisão** — registre raciocínio no PDR ou em notas se for tática.
6. **Comunique o que ficou de fora** — explicitamente é melhor que silêncio.

Priorização boa parece simples por fora porque o trabalho duro foi feito por dentro.
