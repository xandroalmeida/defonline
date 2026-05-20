# Disciplina de testes do Programador

Este é o documento que separa um programador profissional de um amador. Padrões transversais (cobertura mínima, exigência de E2E) vêm do PO (`defonline-docs/skills/po/references/quality-standards.md`). **Aqui** estão **como** você atinge esses padrões na prática — e por que o caminho feliz **nunca é suficiente**.

## A mentalidade de teste do sênior

Testar não é "atingir 80% de cobertura". Testar é **provar que o código se comporta corretamente nos cenários que importam** — incluindo os que ninguém quer pensar:

- O input inválido que o usuário vai mandar amanhã.
- O caso de borda que ninguém pensou na sprint planning.
- A exceção do serviço externo que vai acontecer na primeira semana de produção.
- O race condition que aparece em produção e desaparece quando você tenta reproduzir.

Cobertura é **piso**, não meta. **Testar só o caminho feliz é equivalente a não testar** — você só fez o compilador rodar duas vezes.

---

## TDD na prática

> Para cada critério de aceite (CA) da estória, ciclo **red → green → refactor**.

1. **Red.** Escreva o teste primeiro. Ele deve falhar (porque o código ainda não existe ou está incompleto). Se ele passa imediatamente, o teste está mal escrito — não está testando o que pensou.
2. **Green.** Escreva o código mínimo que faz o teste passar. Não tente fazer "todo certo" agora — só passar.
3. **Refactor.** Com o teste verde, refatore o código (e o teste, se necessário) para qualidade. Os testes te protegem nesse passo.

**Por que TDD funciona:**
- Te força a pensar no **comportamento desejado** antes de mergulhar em implementação.
- Te dá uma rede de segurança para refatorar sem medo.
- Te força a escrever código testável (acoplamento baixo, dependências explícitas).
- O histórico de commits mostra disciplina — testes acompanhando ou precedendo código.

**Não é fetichismo:** se você está em modo "exploratório" (entendendo uma lib nova, debugando), pode escrever código antes. Mas no fim da exploração, antes do PR, **os testes precisam estar lá**, cobrindo o que ficou.

### Quando suspender TDD (legitimamente)

TDD vale para **implementar comportamento conhecido**. Existem fases onde escrever teste antes é improdutivo:

- **Exploração técnica**: você está aprendendo como uma lib se comporta, testando ideias, descobrindo a API. Escreva código, mexa, valide; depois apague o que era só investigação e codifique a versão final **com testes**.
- **Debugging**: você está caçando bug em código existente. Adicione log, reproduza, entenda. Quando entender a causa, escreva o teste que falha → conserte → teste passa (TDD volta nessa hora).
- **Spike arquitetural**: a estória é explicitamente um spike — você está validando viabilidade. Nem tudo precisa virar produção; o output é entendimento. Mas se o spike vira código que fica, ele precisa de testes antes do merge.

**A regra:** TDD se suspende durante atividades **exploratórias** com saída descartável. Quando o código vai pra ficar, os testes voltam **antes do PR**.

---

## Tipos de teste e quando usar

### Unitário
- **Escopo:** uma função, uma classe, um pequeno conjunto coeso.
- **Velocidade:** milissegundos. Suíte completa em segundos.
- **Cobertura esperada:** 80% geral, 98% em núcleo/regras de negócio (definição do PO).
- **Quando escrever:** sempre. É o padrão.
- **Quando NÃO escrever:** código gerado por framework (modelos ORM puros, etc — confie no framework).

### Integração
- **Escopo:** múltiplas peças conversando (módulo + banco, módulo + outro módulo, etc).
- **Velocidade:** centenas de milissegundos a poucos segundos por teste.
- **Quando escrever:** quando o valor está na interação (ex: query complexa contra banco real; chamada entre dois módulos).
- **Banco em teste:** prefira **Postgres real** (em container/Docker) a SQLite-com-truques. Você quer testar o que vai rodar em produção.

### End-to-End (E2E)
- **Escopo:** fluxo completo do usuário — frontend até banco e de volta.
- **Velocidade:** segundos por cenário. Suíte completa pode tomar minutos.
- **Quando escrever:** todo fluxo de usuário tocado pela estória.
- **Padrão obrigatório (PO):** todo CA que envolve interação do usuário tem pelo menos um E2E.

---

## O foco crítico: caminho feliz NÃO é suficiente

Esta é a parte que define se você é sênior. Para cada funcionalidade você escreve, **no mínimo**, testes em 4 categorias:

### 1. Caminho feliz
O óbvio. O comportamento esperado quando tudo está certo.
- Cadastro com dados válidos → empresa cadastrada.
- Cálculo de liquidez com valores positivos → resultado correto.

### 2. Casos inválidos
Input que não atende as regras. **Cada validação merece pelo menos um teste**.
- CNPJ malformado (12 dígitos, letras, etc).
- Campo obrigatório ausente ou em branco.
- Tipo errado (string onde se espera número).
- Valor fora de range (idade negativa, percentual > 100).
- Tamanho extremo (string de 10000 caracteres).
- Encoding inesperado (emoji, caracteres especiais).

### 3. Exceções esperadas
Erros que vão acontecer em produção. Você testa que o sistema se comporta **bem** quando acontecem.
- Banco indisponível durante uma chamada → erro retornado com sinalização correta, não 500 silencioso.
- Serviço externo (gateway de pagamento, Receita) timeout → retry/fallback apropriado.
- Conflict (versão otimista, ex: dois usuários editando ao mesmo tempo) → erro tratado.
- Tentativa de operar em recurso inexistente ou não autorizado.
- Limite de quota/rate excedido.

### 4. Bordas
Casos limite onde implementação simples costuma quebrar.
- Lista vazia, lista de 1 elemento, lista no limite máximo.
- Strings: vazia, só espaços, com BOM, com line ending diferente.
- Datas: virada de ano, fuso horário, horário de verão, ano bissexto.
- Números: zero, negativo, ponto flutuante imprecisão, overflow.
- Concorrência: dois requests simultâneos no mesmo recurso (quando relevante).

### Padrão prático

Para uma estória de cadastro de empresa, isso significa, **no mínimo**:

```
✅ test_cadastro_com_dados_validos_persiste_empresa            (feliz)
✅ test_cadastro_falha_com_cnpj_invalido                       (inválido)
✅ test_cadastro_falha_com_cnpj_ja_existente                   (inválido / conflito)
✅ test_cadastro_falha_com_email_invalido                      (inválido)
✅ test_cadastro_falha_com_nome_vazio                          (inválido)
✅ test_cadastro_retorna_erro_amigavel_se_banco_indisponivel   (exceção)
✅ test_cadastro_aceita_caracteres_acentuados_no_nome          (borda)
✅ test_cadastro_normaliza_cnpj_com_pontuacao_e_sem            (borda)
```

8 testes para uma funcionalidade aparentemente simples — e ainda é o mínimo razoável. Se sua estória de cadastro tem só `test_cadastro_funciona`, **a estória não está testada**.

---

## Frontend web: automação de browser real, sempre

**Não simule DOM em unit test e diga que validou a UI.**

Frameworks tipo jsdom rodam JavaScript fora de um browser real. Eles são úteis para algumas validações leves de componentes, mas:

- Não rodam CSS de verdade — layout não é validado.
- Não disparam eventos como browser real (mousedown, touch, foco real).
- Não validam acessibilidade (foco por teclado, leitor de tela).
- Não pegam diferenças entre browsers.

**Por isso:** para todo fluxo de usuário FE, o E2E roda em **browser real via automação** (Playwright, Cypress, Puppeteer — qual ferramenta é decisão de ADR).

**O que o E2E de FE deve validar:**

- Caminho feliz do fluxo (clica botão, preenche form, vê resultado).
- Validação visual de feedback (mensagem de erro aparece, loading aparece).
- Estados intermediários (botão fica desabilitado durante submit).
- Acessibilidade básica (todo input tem label, foco por teclado funciona).
- **Casos inválidos do ponto de vista do usuário** (submete form vazio, vê erro; CNPJ ruim, vê erro específico).

Não é necessário um E2E para cada caso minúsculo — os casos inválidos detalhados ficam no unit/integração da camada lógica. Mas o **fluxo principal de erro** (usuário tenta algo errado, sistema responde) deve estar coberto em E2E.

---

## Mocks: com critério, não para esconder acoplamento

Mock é útil **e** perigoso. Útil para isolar testes de dependências externas (rede, serviços de terceiros, tempo). Perigoso quando vira muleta para esconder que seu código tem acoplamento ruim.

**Quando mock é apropriado:**
- Serviço externo via rede (gateway de pagamento, API da Receita).
- Tempo (`Date.now()`, `time.now()`) — para testar comportamento em data específica.
- Aleatoriedade (`Math.random()`, UUIDs) — para resultado determinístico.
- Coisas caras (envio real de e-mail, escrita em S3 real).

**Quando mock é red flag:**
- Você precisa mockar 10 colaboradores pra testar uma função → o **problema é o acoplamento**, não o teste. Refatore.
- Você está mockando partes do próprio módulo que está testando → quase sempre erro de desenho.
- O teste só passa se o mock retornar exatamente uma sequência mágica → o teste virou espelho do mock, não do código.

**Padrão prático para serviços externos:**
- Em **unit/integração**: mock no nível de **cliente HTTP** ou interface do serviço.
- Em **E2E local**: o mock dedicado em container (que o ambiente local usa — princípio arquitetural #6) atende. Não precisa mockar de novo.
- **Teste de contrato** (separado) roda contra sandbox real periodicamente. Mantém o mock fiel.

**Anti-patrão de mock famoso:** "mock everything". Você acaba testando o mock, não o código.

---

## Rodar a suíte completa antes de marcar pronto

> **A suíte completa, não só os seus testes.**

Princípio fundamental: você é responsável pelo todo do **que você quebra**. Se um teste antigo, que não é seu, começou a falhar **depois do seu commit** — é seu problema até estar resolvido.

**Como rodar:**

- Localmente, antes do PR: rode unit + integração + E2E (todos).
- No PR (CI): a pipeline roda automaticamente. Se cair, **não force merge**. Investigue.
- Para mudanças grandes: além da suíte, rode smoke manual no app local — coisas que testes não pegam (visual, percepção, latência).

### "Eu quebrei" vs "já estava quebrado e descobri agora"

Distinção importante: você é responsável por **regressões que você introduziu**, não por toda dívida pré-existente do projeto. Como diferenciar:

| Cenário | Responsabilidade | O que fazer |
|---|---|---|
| Teste passava antes, falha depois do seu commit | **Você quebrou** | Conserte como parte da estória — não marca `done` até resolver. |
| Teste estava em `skip` por outra pessoa antes | Pré-existente | Pode ficar como estava — não obrigação sua nesta estória. |
| Teste falha de forma intermitente (flaky), seu commit não tem relação clara | Provavelmente pré-existente | **Investigue rápido** para confirmar. Se confirmar que é pré-existente, **registre bug com PO**, mas **não bloqueie** sua estória por causa disso. |
| Teste falha consistentemente mas não tem relação aparente com seu código | Verificar | Cheque histórico: passou antes do seu commit? Se sim, **é seu** (pode ser efeito indireto que você não previu). Se sempre falhou, **registre bug com PO**, não bloqueia. |

**Como verificar**: rode a suíte na branch **antes do seu primeiro commit** (ou na `main`/`master`). Se já falhava lá, é pré-existente; se passava, você introduziu.

**Anti-padrão a evitar dos dois lados:**

- "Quebrei mas vou marcar `done` porque não estava no escopo" → não. Quebrou, conserta.
- "Achei 3 testes flaky pré-existentes e agora não consigo fechar minha estória até resolver tudo" → não. Registre como bugs separados, comunique PO, fecha sua estória.

**Se um teste antigo está flaky** (passa às vezes, falha às vezes) e não foi você que introduziu:

- Não ignore — abra estória de bug com PO descrevendo o flakiness.
- Não marque skip silenciosamente.
- Em casos extremos: se o flaky atrapalha CI e está provadamente pré-existente, peça ao PO autorização para skip temporário com comentário explicando + IDR. **Não é o caminho default — só quando atrapalha mesmo.**

---

## Cobertura como ferramenta de feedback

A cobertura te diz **o que ainda não foi testado**. Não te diz se o que está coberto está testado **bem**.

**Como usar cobertura corretamente:**

- Rode relatório de cobertura local antes do PR. Veja **as linhas que não estão cobertas**.
- Para cada linha descoberta, pergunte: "isto é uma exceção esperada que precisa de teste?". Quase sempre sim.
- **Não inflame cobertura artificialmente** com testes que exercem o código sem asserções fortes.
- Cobertura 100% com testes ruins é pior que 80% com testes bons.

**Metas (do PO, `quality-standards.md`):**
- Geral: 80%
- Núcleo do sistema e regras de negócio: 98%

**Se você não atingir a meta:** ou você falta teste (provavelmente), ou parte do código é genuinamente não-testável (raro — registre justificativa no PR).

---

## Performance dos testes

Suíte lenta é suíte ignorada. Testes precisam ser **rápidos o suficiente** para você rodá-los com frequência durante o desenvolvimento.

**Padrões:**
- Suíte de unit: **segundos** para o módulo, **dezenas de segundos** no projeto inteiro.
- Suíte de integração: até alguns minutos no projeto inteiro.
- Suíte de E2E: até ~10 minutos no projeto inteiro.

**Se ficar muito acima:**
- Examine se há testes que estão fazendo trabalho que deveria ser unit (subindo app inteiro pra testar uma função).
- Examine se há fixtures pesadas reutilizáveis sendo recriadas a cada teste.
- Paralelize quando possível (a maioria dos runners modernos faz nativamente).

---

## Resumo operacional

Ao terminar de implementar uma funcionalidade, **antes** de marcar `in_review`:

1. ✅ Cada CA da estória tem ao menos um teste cobrindo.
2. ✅ Para cada CA, existem testes de: caminho feliz, casos inválidos, exceções esperadas, bordas.
3. ✅ Se FE web, existe E2E em browser real cobrindo o fluxo.
4. ✅ Mocks só estão onde precisam estar (serviços externos, tempo, aleatoriedade).
5. ✅ Cobertura local atinge as metas.
6. ✅ Suíte **completa** do projeto roda verde — não só os meus testes.
7. ✅ Smoke manual rápido no app local foi feito.

**Se algum item ❌ → estória não está pronta. Continue.**
