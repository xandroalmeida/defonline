# Medição e aprendizado pós-entrega

Sem medir, entregar feature após feature vira teatro. Você gasta capacidade, agentes trabalham, código entra em produção — e ninguém sabe se algo melhorou. **Medir não é luxo, é fechamento honesto do ciclo de produto.**

Esta reference cobre: como definir métrica que importa, como medir, quando validar, e o que fazer com o resultado — incluindo o caso difícil em que a métrica **não bateu**.

---

## A mentalidade

- **Métrica é compromisso de aprendizado.** Você prometeu "este épico vai mudar X". Tem que olhar X depois.
- **Output ≠ outcome ≠ impact.** Confundir os três é o erro mais comum. Veja abaixo.
- **Achismo não conta.** "Acho que melhorou" sem dado é confirmation bias.
- **Métrica que não bateu é informação valiosa** — geralmente mais valiosa que métrica que bateu. Te força a aprender.

---

## Output vs outcome vs impact — distinção essencial

| Tipo | Significa | Exemplo |
|---|---|---|
| **Output** | O que o time **produziu** | "Entregamos cadastro com validação de CNPJ" |
| **Outcome** | O que **mudou no comportamento** do usuário | "75% dos usuários completam o cadastro" |
| **Impact** | O que **mudou no resultado do negócio** | "Empresas ativas crescem 20% no mês" |

**O erro clássico:** time celebra output ("entregamos!") e nunca olha outcome ou impact. Métricas de épico devem ser **outcome ou impact**, não output.

### Como reconhecer cada um na frase do épico

- "Implementamos X" → output (não use como métrica de sucesso).
- "Usuário consegue Y" → output (utilidade conceitual, ainda não validada).
- "X% dos usuários conseguem Y" → outcome.
- "Métrica de negócio Z subiu / desceu" → impact.

**Boa métrica de sucesso de épico**: pelo menos um outcome **observável** num prazo razoável (semanas, não meses).

---

## Como definir a métrica de sucesso no épico

O template de épico tem campo "Métrica de sucesso". Boa métrica:

- **Outcome ou impact** (não output).
- **Observável.** Você sabe **como** medir? Onde os dados moram?
- **Quantificável.** "Melhora" não conta — número conta.
- **Com janela temporal.** "Em 2 semanas após deploy", não "no futuro".
- **Realista.** 95% de adoção em uma semana é improvável.
- **Com baseline.** O que era antes? Se não tinha baseline (feature nova), pelo menos defina o **patamar mínimo aceitável**.

### Exemplos

**Ruim:**
> "Métrica de sucesso: usuários gostam mais da tela."

**OK:**
> "Métrica de sucesso: NPS médio sobre a tela de diagnóstico sobe de 30 (atual) para 40+ em pesquisa após 30 dias."

**Bom:**
> "Métrica de sucesso primária: 60%+ dos usuários que iniciam o fluxo de diagnóstico completam-no, medido por evento `diagnostico_concluido` / `diagnostico_iniciado`, janela 14 dias após deploy. Métrica secundária: latência p95 da tela de resultado ≤ 800ms."

### Tipos comuns de métrica útil

- **Funil**: % de usuários que avançam em cada passo (cadastro → primeiro diagnóstico → segundo uso).
- **Adoção**: usuários ativos que usam a feature nova / usuários ativos totais.
- **Retenção**: usuários que voltam em D7, D30 após primeiro uso.
- **Tempo até valor**: minutos entre cadastro e primeiro diagnóstico concluído.
- **Qualidade percebida**: NPS, CSAT (cuidado — caro de coletar com volume baixo).
- **Erro / abandono**: % de tentativas que falham, % de fluxos abandonados no meio.

---

## Como medir — onde os dados moram

### Telemetria de produto (eventos)

Eventos no app capturam comportamento. Padrão típico:

- Evento `<entidade>_<verbo>` (`diagnostico_iniciado`, `empresa_cadastrada`).
- Propriedades relevantes (user_id, contexto, propriedades da operação).
- Centralizadas em alguma stack de analytics (decisão do Arquiteto via ADR; pode ser Postgres mesmo para começar — princípio #3).

**Para épico com métrica de funil/adoção/retenção**: a estória que entrega a feature **já inclui** instrumentação dos eventos necessários. Não trate eventos como "vou adicionar depois" — fica esquecido.

### Métricas operacionais

Latência, taxa de erro, throughput — vêm da observabilidade do Arquiteto (`arquiteto/references/nfr-architecture.md` + `programador/references/observability-discipline.md`).

### Pesquisa qualitativa

Para outcome relacionado a percepção (clareza, confiança, NPS), pesquisa direta com usuário. Caro em volume baixo, mas valioso para validar hipótese de produto.

**Para DEFOnline (sugestão pragmática)**: começar com eventos básicos no Postgres (princípio #3), expandir para ferramenta dedicada quando volume justificar.

---

## Quando validar a métrica

**Erro comum**: olhar a métrica no dia seguinte do deploy. Dado nesse momento é ruído — usuário ainda não percebeu, não experimentou.

**Janela típica por tipo de métrica:**

| Tipo de métrica | Quando começa a fazer sentido olhar | Quando "fechar" |
|---|---|---|
| Funil em fluxo curto (cadastro, formulário) | 3-7 dias após deploy | 14 dias |
| Adoção de feature visível | 7-14 dias | 30 dias |
| Retenção D7 / D30 | 7 / 30 dias após cohort | 30 / 60 dias |
| Métrica operacional (latência, erro) | Imediato | Continuamente |
| NPS / pesquisa | 14-30 dias | Quando volume da pesquisa for relevante |

**Registre a janela no épico**: "Vamos olhar essa métrica em D+14". Sem isso, ou olha cedo demais (ruído) ou esquece.

---

## O que fazer com o resultado — 3 cenários

### Cenário 1: Bateu

Métrica atingiu ou superou o alvo. Hábitos:

- **Registre no status report** o resultado vs previsto.
- **Não comemore prematuramente** — confirme que o dado é confiável (volume mínimo, sem efeito sazonal estranho).
- **Aprenda do que funcionou** — articule o **porquê** funcionou. Vai informar próximas decisões.
- **Decida sobre o próximo passo natural** — feature evoluí? Aprendemos algo aplicável a outra área?

### Cenário 2: Parcialmente bateu

Atingiu uma parte, ficou aquém em outra. Hábitos:

- **Investigue o gap.** Por que parte funcionou e parte não? Específico de persona? De contexto?
- **Decida se vale segunda tentativa** (ajustar e medir de novo) ou **dar por resolvido** (entrega "suficientemente boa").
- **Documente o aprendizado** — pode evitar erro futuro.

### Cenário 3: Não bateu

Métrica falhou ou ficou bem abaixo. Este é o cenário **mais valioso** se você lida com ele bem.

**Hábitos:**

- **Não esconda.** "Não bateu" no status report é honestidade técnica. Esconder corrompe a cultura.
- **Investigue o porquê.** Causa possível em camadas:
  - **Hipótese errada**: usuário não quer isso (ou não da forma que oferecemos).
  - **Execução fraca**: a feature está ali mas a UX confunde / é lenta / quebra em casos.
  - **Distribuição ruim**: usuário não descobriu que existe.
  - **Métrica errada**: medimos a coisa errada.
  - **Janela curta**: ainda é cedo.
- **Decida o próximo passo:**
  - **Iterar**: pequena mudança baseada em insight, medir de novo.
  - **Investir mais**: dobrar aposta com mais features na mesma direção.
  - **Pivotar**: mudar abordagem.
  - **Kill / pausar**: aceitar que essa direção não está produzindo retorno.

**A pergunta mais difícil**: quando "não bateu" significa "vamos persistir" e quando significa "vamos parar"?

- Persistir se: hipótese ainda parece certa, insights apontam para ajustes claros, custo de continuar é baixo.
- Parar se: hipótese desconfirmada, custo crescente, melhor uso da capacidade em outro lugar.

**Não tenha medo de matar feature.** Capacidade enterrada em feature ruim é capacidade não disponível para feature boa.

---

## Build-Measure-Learn — o loop

Origem: Lean Startup. Aplicação prática:

```
       BUILD
         ↓
      (deploy)
         ↓
       MEASURE
         ↓
      (analisa)
         ↓
        LEARN
         ↓
    (decisão de produto)
         ↓
      ↻ (volta para BUILD)
```

Cada ciclo do épico passa pelos três. Pular `measure` ou `learn` quebra o ciclo — você só constrói, nunca aprende.

**No DEFOnline:**

- `BUILD` = onda + épico + estórias → entrega.
- `MEASURE` = janela de validação após deploy.
- `LEARN` = retro do épico + decisão sobre próximo passo (que vira input do `BUILD` da próxima onda).

A retrospectiva de fim de onda é o **momento estruturado** para o `LEARN`. Não pule.

---

## Métrica de norte (north star) e árvore de métricas

Cada produto deveria ter **uma métrica primária** — a "estrela do norte" — que representa **valor entregue ao usuário ao longo do tempo**. Para DEFOnline, possível candidata (a confirmar com Alexandro): "MPEs ativas que receberam pelo menos um diagnóstico no último mês".

A partir do north star, deriva-se uma **árvore de métricas**:

```
            NORTH STAR
             (1 métrica)
            /          \
       Drivers          Drivers
   (3-5 métricas)   (3-5 métricas)
       /  |  \         /  |  \
   Sub-métricas       Sub-métricas
```

Cada feature/épico contribui para algum driver. Se você não consegue mapear como uma feature move o north star (direta ou indiretamente), **revisite a feature**.

**Para DEFOnline**: este mapa vive em `defonline-docs/project-state/product/north-star.md` (criado quando definirmos formalmente — pode ser parte do plano de onda 1).

---

## Anti-padrões

**1. Vanity metrics** — métricas que sobem sempre, sem informar nada.
- "Total de cadastros desde o início" — só cresce, nunca dá sinal de nada.
- **Antídoto**: olhe métricas **acionáveis**: cohort, ativos no mês, retenção D7/D30, taxa de conversão por funil.

**2. "Hindsight bias"** — "eu sabia que ia funcionar / falhar".
- Olhando para trás, tudo parece óbvio. **Antídoto**: registre **antes** o que você esperava; compare com o que aconteceu.

**3. "Achismo travestido de dado"** — usar dado seletivamente para confirmar opinião pré-existente.
- **Antídoto**: defina critério **antes** de olhar o dado. Quando bate, comemore; quando não, aceite.

**4. "Otimização sem hipótese"** — olhar métricas, ver número baixo, "vamos melhorar".
- Melhorar **o quê especificamente**? Sem hipótese, vira atirar no escuro.
- **Antídoto**: cada iteração tem hipótese explícita ("acreditamos que mudar X vai aumentar Y porque Z").

**5. "Métrica sem ação"** — você mede mas nada acontece em função do que mediu.
- **Antídoto**: para cada métrica, defina **antes** o que você vai fazer com cada resultado (bateu, parcial, não bateu).

**6. "Janela curta"** — olhar métrica no dia seguinte ao deploy.
- **Antídoto**: respeite a janela da métrica. Para funil curto, ~7-14 dias. Para retenção, ~30+ dias.

---

## Resumo operacional

Para cada épico:

- [ ] Métrica de sucesso é **outcome ou impact**, não output.
- [ ] Métrica é **quantificável** com janela temporal.
- [ ] Instrumentação para coletar a métrica está **dentro do escopo do épico** (não "fica para depois").
- [ ] Janela de validação registrada no épico.
- [ ] **Hipótese explícita**: o que esperamos que aconteça e por quê.

Após a janela:

- [ ] Métrica observada vs prevista — registrado no status report.
- [ ] Para gap: investigação de causa.
- [ ] Decisão sobre próximo passo: persistir, iterar, pausar, matar.
- [ ] Aprendizado registrado em PDR ou retrospectiva da onda.

> **Medir custa tempo. Não medir custa direção.**
