# Critérios de veredito — pass, fail, n/a, gravidade

Esta reference define **quando** cada classificação se aplica. O risco de cada lado:

- **Rígido demais** — você reprova épico bom por interpretação severa, time perde tempo refazendo o que era OK.
- **Frouxo demais** — você aprova épico ruim, qualidade erode silenciosamente e o problema explode em produção.

Calibração honesta é o trabalho. Esta reference dá o guia.

---

## Os três status por item

### `pass` — atende o critério

Usa quando:

- O item é cumprido **com evidência verificável** (`evidence-discipline.md`).
- A evidência é **clara o suficiente** para outro Validador chegar à mesma conclusão.
- Não há ressalva relevante.

**Exemplos:**

✅ "Cobertura geral 84.3% — relatório CI #1234 anexado." → `pass`.
✅ "Pipeline verde nos últimos 5 commits da branch principal — link." → `pass`.
✅ "Smoke manual em homologação: fluxo de cadastro funcionou em 2026-05-20 14:35 — screenshot anexado." → `pass`.

### `pass com ressalva` — atende o critério, mas algo merece nota

Existe para casos onde o critério **é cumprido** mas há detalhe que o PO ou time deveria saber:

- Evidência mais fraca do que ideal (mas suficiente).
- Cumprimento marginal (no limite do alvo).
- Algo adjacente parece estranho mas não impede aprovação.

**Exemplos:**

⚠️ "Cobertura geral 80.2% — atende o mínimo de 80% mas no limite. Sugiro reforço em próximas estórias." → `pass com ressalva`.
⚠️ "E2E em browser real cobrindo fluxo, mas apenas cenário feliz (sem cenário de erro)." → `pass com ressalva`.

**Quando usar**: você marca `pass` (não bloqueia o épico), mas registra a observação no relatório para o PO considerar em planejamento futuro.

**Quando NÃO usar**: para evitar reprovar algo que você acha que deveria reprovar. Se é fail, é fail. Ressalva existe para **nuance honesta**, não para diluir reprovação.

### `fail` — não atende o critério

Usa quando:

- O item **não é cumprido** com evidência clara.
- Você verificou diretamente e o resultado contradiz o esperado.

**Exemplos:**

❌ "CA-3 da STORY-007 não tem teste cobrindo. Função `validar_digitos_verificadores` com 0% de cobertura." → `fail`.
❌ "Deploy de homologação não acontece automaticamente — verificado em CI run #1235; etapa de deploy é manual." → `fail`.
❌ "Pipeline está vermelho nos últimos 3 commits — link." → `fail`.

### `n/a` — não se aplica a este épico

Usa quando:

- O item simplesmente **não tem aplicação** no escopo do épico.
- Você consegue justificar **em prosa específica** por que.

**Exemplos:**

🚫 "n/a — Este épico (EPIC-005) não tem frontend web. Validação E2E em browser real (item 2.3) não se aplica; substituído por E2E via cliente HTTP que está presente." → `n/a` com justificativa.
🚫 "n/a — Este épico (EPIC-009) não introduz dados pessoais novos. Bloco LGPD do checklist (item 5.3) não tem item a verificar." → `n/a` com justificativa.

**Anti-padrão**: `n/a — não se aplica`. Isso não é justificativa, é repetição. Sempre prosa específica.

---

## Gravidade de fails

Nem todo fail tem o mesmo peso. Classifique cada um:

### **Bloqueante** — épico não deveria fechar

Fail é bloqueante quando atende **uma ou mais** condições:

- **CA da estória não é cumprido** — funcionalidade prometida não funciona, ou tem teste em `skip`.
- **Cobertura abaixo do mínimo** (80% geral, 98% núcleo) — princípio do PO inegociável.
- **E2E não existe** em fluxo de usuário que foi tocado.
- **Pipeline está vermelho** ou deploy automático não funciona.
- **Funcionalidade não acessível** em homologação.
- **Vulnerabilidade de segurança crítica** descoberta.
- **Migração de banco** não-reversível ou não-testada.
- **Dado pessoal** novo sem alinhamento com PO (LGPD).
- **Segredo no código** descoberto.

Bloqueante = épico **rejected**. PO precisa abrir estórias de correção antes do épico fechar.

### **Não-bloqueante** — épico pode fechar, mas vira pendência

Fail é não-bloqueante quando:

- **Documentação** desatualizada em algum ponto não-crítico.
- **Convenção de log** ou métrica não totalmente seguida.
- **Refator** que ficou pela metade.
- **Aviso** do scanner de segurança em dependência não-crítica (com plano de tratamento).
- **Cenário E2E** falta um caso de erro (mas feliz está coberto).
- **Notas do agente** incompletas em uma estória.

Não-bloqueante = épico **rejected** mesmo assim **se o PO decidir** — ou aprovado **com pendências**. Decisão final é do PO, sua recomendação aparece no relatório.

### Como classificar quando está em zona cinza

Pergunta-chave: **"Se o épico passar como está, isso vai virar problema concreto em produção ou no próximo épico?"**

- Sim, claramente → **bloqueante**.
- Talvez, mas dá pra acompanhar → **não-bloqueante**.
- Não, é cosmético → **pass com ressalva** (não é fail).

**Em dúvida real**, prefira **bloqueante**. O custo de bloquear e corrigir é menor que o de aprovar e descobrir depois.

---

## Lidando com flaky / intermitente

Teste que passa às vezes e falha às vezes — sem causa clara.

### Diagnóstico

- Rode o teste **isolado**, várias vezes (5-10 execuções). Qual a taxa de falha?
- Rode com a suíte completa. Mudou a taxa?
- Verifique se depende de **timing, ordem, recurso externo, banco no estado certo**.

### Classificação

- **Flaky introduzido pelo épico** (não passava antes, passa agora intermitente, foi mexido nessa estória): **fail bloqueante**.
- **Flaky pré-existente** (já estava assim antes do épico — verifique no histórico): **registre como observação** no relatório, mas não bloqueia o épico — abra estória de bug com PO para tratar separado.
- **Flaky com taxa de falha alta** (>20% das execuções falha): tratar como **fail** mesmo se pré-existente; **com sugestão de skip temporário** + IDR explicando + estória de correção priorizada.

Veja `programador/references/testing-discipline.md` sobre "eu quebrei vs já estava quebrado" — mesma lógica aplica aqui na validação.

---

## Veredito final do épico

Compilação dos itens:

| Situação | Veredito do épico |
|---|---|
| Todos os itens `pass`, `pass com ressalva` ou `n/a` justificado | **APPROVED** |
| Pelo menos um `fail` **bloqueante** | **REJECTED** |
| Pelo menos um `fail` **não-bloqueante**, sem bloqueantes | **APPROVED com pendências** (recomendar ao PO; decisão final dele) |

**APPROVED com pendências** é estado intermediário honesto. Você está dizendo: "épico cumpre o essencial; estas pendências viram estórias futuras". PO decide se aceita ou pede correção antes do `done`.

---

## Casos especiais

### CA cumprido mas teste ausente

CA tem comportamento observável em homologação. Mas não há teste automatizado cobrindo o CA.

- **Tecnicamente cumprido** — você consegue ver funcionando.
- **Mas não atende padrão de qualidade do PO** — princípio "qualidade é requisito".
- **Classificação**: `fail bloqueante`. CA exige cobertura automatizada, não só funcionalidade manual.

### Funcionalidade existe mas está atrás de feature flag desligada

- Funcionalidade está em homologação mas não está ativada na configuração padrão.
- Você não consegue percorrê-la sem ligar manualmente.

- **Classificação**: depende. Se a feature flag desligada é parte do plano (rollout gradual planejado pelo PO), está OK — verifique com a flag ligada manualmente para confirmar funciona, marque `pass` com nota explicando.
- Se a feature flag desligada parece esquecimento, **fail não-bloqueante** — PO decide se vira correção.

### Cobertura "tecnicamente" no alvo mas testes ruins

Cobertura de 85% atingida, mas grande parte dos testes só executam o código sem asserções fortes. Cobertura inflada artificialmente.

- **Cobertura como métrica**: atinge.
- **Qualidade de teste**: ruim.
- **Classificação**: `fail bloqueante` no item de cobertura. Cobertura sem qualidade não é cobertura — princípio do `testing-discipline.md` do Programador. Aponte testes específicos que exemplificam o problema.

### Pipeline foi para verde mas o teste fail antes foi simplesmente removido

Histórico mostra teste que falhava — agora não falha mais, mas foi **removido** sem substituição.

- **Pipeline verde**: superficialmente OK.
- **Mas teste removido sem substituição**: cobertura caiu silenciosamente.
- **Classificação**: `fail bloqueante`. Levante isso explicitamente no relatório.

### Estória "done" mas você descobre que CA não foi de fato cumprido

Estória declara `done` no índice, mas você verifica e o CA não está funcionando.

- **Veredito do bloco 1**: `fail bloqueante`.
- **Implicação maior**: questione consistência do índice e do trabalho do Programador. Registre como ressalva no relatório — pode indicar problema de processo (Programador marcando done sem validar próprio trabalho — princípio "done means done" violado).
- **Recomendação ao PO**: além de corrigir o CA, considere retro com Programador sobre disciplina de done.

---

## Aspectos de gravidade — calibração interna

Você não é juiz que pune. Você é régua. Calibração interna:

- **Conservador no `pass`**: tenha evidência forte. Cinza vira ressalva ou fail, não pass.
- **Honesto no `fail`**: não invente fail por insegurança. Critério, evidência, classificação.
- **Específico no `n/a`**: prosa justificando, sempre.
- **Explícito na gravidade**: bloqueante e não-bloqueante são decisões diferentes do PO; comunique claramente qual é qual.

---

## Resumo operacional

Para cada item do checklist:

- [ ] Verifiquei diretamente, com evidência.
- [ ] Classifiquei como **uma** das opções: `pass`, `pass com ressalva`, `fail`, `n/a`.
- [ ] Se `fail`, classifiquei a **gravidade** (bloqueante ou não-bloqueante).
- [ ] Em dúvida cinza, preferi a classificação **mais conservadora** (fail > pass com ressalva > pass).
- [ ] `n/a` tem prosa específica justificando.

Para o veredito do épico:

- [ ] Compilei todos os itens.
- [ ] Veredito: **APPROVED** se zero fails; **REJECTED** se pelo menos um fail bloqueante; **APPROVED com pendências** se só não-bloqueantes.
- [ ] Recomendação ao PO sobre próximos passos.

> **Calibração honesta protege o produto. Carimbação corrompe a confiança. Rigor sem critério gera retrabalho.**
