# Done Checklist — antes de marcar a estória como pronta

> "Done" significa **done**. Não "está quase pronto". Não "passa em quase todos os testes". Não "depois eu ajusto". Pressão de tempo não muda a verdade — se algum item abaixo está ❌, a estória **continua em `in_progress` ou `blocked`**.

Este checklist te protege e protege o projeto. Se já caiu em "pegadinha" alguma vez, ele está aqui para você não cair de novo.

---

## Versão sintética — as 5 perguntas

Para estória pequena e rotineira, este filtro rápido cobre o essencial. Se você responde **sim com confiança** para todas, está OK. Se hesita em alguma, vá para a versão completa abaixo.

1. **Suíte completa do projeto verde** (não só meus testes)?
2. **Cobertura atingida** — incluindo testes para casos inválidos, exceções e bordas (não só caminho feliz)?
3. **E2E em browser real** rodando (se a estória mexe em FE web)?
4. **CI verde no PR, deploy em homologação verificado funcionando** (smoke manual rápido)?
5. **Eu colocaria isso em produção real e dormiria tranquilo?**

Se sim para as 5 → marca `in_review` e segue.

Se não → use a versão completa abaixo para identificar o que falta.

---

## Versão completa — quando precisa de checklist detalhado

Use a versão completa quando:

- A estória é **grande ou crítica** (núcleo de negócio, segurança, integração nova).
- Você está fechando estória depois de muito tempo (esquecer detalhe é mais provável).
- Algum item das 5 perguntas acima deu desconforto e você quer ser sistemático.

### Antes de começar a fechar — uma respirada

Pare 2 minutos. Pergunte:

- Eu entendi os critérios de aceite **inteiros**, não só o título?
- O que eu implementei resolve **o problema do usuário**, ou só faz os testes passarem?
- Tem coisa que eu sei que está fragiozinha e estou esperando ninguém perceber?

Se algo soou desconfortável, **investigue antes**.

---

#### Bloco 1 — Critérios de aceite cobertos

- [ ] **CA-1:** existe ao menos um teste que falha sem o código e passa com o código.
- [ ] **CA-2:** idem.
- [ ] **CA-N:** idem.
- [ ] Nenhum CA da estória ficou "implícito" — todos têm referência clara a um ou mais testes.

**Como verificar:** abra a estória. Para cada CA, identifique o teste que o exercita. Se você consegue dizer o nome do teste para cada CA, está OK.

---

### Bloco 2 — Não só caminho feliz

Para cada funcionalidade nova/alterada, eu cobri:

- [ ] **Caminho feliz** — comportamento esperado com input válido.
- [ ] **Casos inválidos** — input malformado, ausente, tipo errado, fora de range.
- [ ] **Exceções esperadas** — falhas de rede, banco indisponível, integração externa falhando.
- [ ] **Bordas** — valores limite, lista vazia, primeiro/último, encoding inesperado.

**Como verificar:** olhe a lista de testes da funcionalidade. Se você só vê `test_funciona_com_dados_validos`, **está faltando muita coisa**. Veja `testing-discipline.md`.

---

### Bloco 3 — Cobertura

- [ ] Rodei o relatório de cobertura **local**.
- [ ] Meta geral (80%) atingida no código novo desta estória.
- [ ] Meta de núcleo/regras de negócio (98%) atingida onde aplicável.
- [ ] Olhei as **linhas descobertas** e cada uma tem justificativa concreta (não é caso de exceção esquecido).

**Como verificar:** comando de cobertura do projeto, ler relatório, abrir cada arquivo descoberto.

**Não inflame cobertura** com testes que tocam o código sem asserção — isso é se enganar.

---

### Bloco 4 — E2E

- [ ] Se a estória envolve fluxo de usuário, há **pelo menos um cenário E2E** novo cobrindo.
- [ ] Se há frontend web tocado, o E2E roda em **browser real via automação** (não simulado).
- [ ] O E2E novo passa **localmente** (não só em CI).
- [ ] O E2E novo cobre tanto **fluxo de sucesso quanto pelo menos um fluxo de erro** do ponto de vista do usuário.

**Como verificar:** rode o E2E local. Se não consegue, está quebrado (lembre que princípio arquitetural #6: tudo sobe local).

---

### Bloco 5 — Suíte completa verde

- [ ] **Rodei a suíte inteira do projeto** (unit + integração + E2E), **não só os meus testes**.
- [ ] Está toda verde.
- [ ] Para cada teste vermelho, **identifiquei se eu quebrei ou se já estava quebrado**.
- [ ] Tests que **eu quebrei** (passavam antes do meu commit, falham depois): **consertei como parte da estória**.
- [ ] Tests que **já estavam quebrados** ou flaky **antes** do meu trabalho: **registrei como bug com PO**, **não bloqueei minha estória** por causa deles.
- [ ] Não há teste em `skip` / `pending` introduzido por mim sem justificativa explícita.

**Como verificar:** comando que roda a suíte completa. Lê o resultado **inteiro**. Para cada teste vermelho, rode `git checkout <commit-antes-de-eu-mexer>` (ou rode na branch `main`) e veja se passava. Se passava → você quebrou. Se já estava vermelho → pré-existente.

**Por que importa a distinção:** você é responsável **pelo que você quebra**, não por toda dívida pré-existente do projeto. Tratar pré-existente como "meu" trava sua estória; tratar regressão sua como "não é meu" empurra problema pra frente. Veja `testing-discipline.md`, seção "Eu quebrei vs já estava quebrado".

---

### Bloco 6 — Lint, formatação, build

- [ ] Linter limpo (zero warnings/errors).
- [ ] Formatador rodou — arquivos com formatação consistente.
- [ ] Build do projeto roda sem erro (tipos, transpilação, etc).
- [ ] Não há código comentado, `console.log`/`print` esquecido, ou `TODO` órfão.

**Como verificar:** comando do linter, do formatador, do build do projeto.

---

### Bloco 7 — Bibliotecas adicionadas

> Aplicável só se adicionou lib(s) nesta estória.

- [ ] Cada lib nova passou pelo roteiro mental (`library-discipline.md`).
- [ ] Lib **local**: justificativa no PR (descrição + commit).
- [ ] Lib **transversal**: IDR criado **antes** de adicionar, no `decisions/idr/`.
- [ ] Versão fixada (não `^` ou `~` ambíguo se a convenção do projeto for fixar; siga a convenção).
- [ ] Adicionei no manifesto correto (`package.json`/`requirements.txt`/etc) e o lock file foi commitado.

---

### Bloco 8 — Migrações de banco

> Aplicável só se a estória mexe em schema.

- [ ] Migração **escrita** (não direto no banco).
- [ ] Migração **idempotente** ou testada que roda em ambiente do zero.
- [ ] Migração **reversível** (down) escrita, mesmo se a chance de usar for baixa.
- [ ] Migração testada em **banco de homologação** antes do merge — não só local.
- [ ] Se a migração toca dados (não só schema), tem plano de execução documentado.

---

### Bloco 9 — Deploy de homologação

- [ ] PR aberto, linkado à estória, citando os CAs cobertos.
- [ ] CI verde no PR.
- [ ] **Após merge:** deploy automático para homologação aconteceu **e foi verificado funcionando**.
- [ ] Smoke manual rápido em homologação: a funcionalidade nova está acessível e o fluxo principal funciona.
- [ ] Logs em homologação não mostram erro anormal logo após o deploy.

**Como verificar:** abra a URL de homologação, navegue até o fluxo, exercite o caminho principal. Veja painel de logs/observabilidade.

**Importante:** "merguei o PR e o pipeline está verde" **não** é o mesmo que "verifiquei funcionando". O pipeline garante automação; você garante o **resultado**.

---

### Bloco 10 — Estado do projeto

- [ ] Frontmatter da estória atualizado: `status: in_review`, `updated_at: <hoje>`.
- [ ] **Notas do agente** preenchidas:
  - [ ] Decisões locais tomadas.
  - [ ] Descobertas relevantes.
  - [ ] Bloqueios que enfrentei e como resolvi.
  - [ ] IDRs criados (com link).
  - [ ] Cobertura final atingida.
  - [ ] Links de evidência (PR, pipeline, deploy).
- [ ] `index.json` atualizado refletindo o novo status.
- [ ] Se criei IDRs, eles estão registrados em `index.json` na seção `decisions.idr`.

---

### Bloco 11 — Honestidade final

Antes de marcar `in_review`, responda **honestamente**:

- [ ] Eu rodaria essa estória em **produção real** e dormiria tranquilo?
- [ ] Eu deixaria um colega revisar isso sem vergonha de explicar o porquê de cada decisão?
- [ ] Eu encontraria isso em código de outra pessoa e ficaria satisfeito?

Se a resposta para qualquer uma é **não**, **conserte primeiro**, marca pronto depois.

---

## Quando uma estória entra em revisão e algo é apontado

- Recebeu feedback do revisor humano ou de teste de validação: trata como **trabalho não terminado**. Volta a status `in_progress`, resolve, repete o checklist inteiro de novo, sobe pra `in_review` de novo.
- Não trate sugestões como opcionais sem alinhar — se você acha que o feedback está errado, **conversa antes** de ignorar.

---

## Mantra do programador sênior

> **Não diga "está pronto" quando ainda não está.**

Adiar 30 minutos para fazer direito hoje vale dias de retrabalho amanhã. Esse é o cálculo que vale.
