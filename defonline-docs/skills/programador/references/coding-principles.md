# Princípios de código do Programador

Estes princípios complementam — não substituem — os princípios arquiteturais (`defonline-docs/skills/arquiteto/references/architecture-principles.md`). Princípios arquiteturais falam de **estrutura macro**; estes falam de **como escrever o código dentro** dessa estrutura.

Em conflito, princípios arquiteturais ganham. Mas a maior parte do tempo eles concordam — porque os dois respiram a mesma ideia: simples ganha de complexo, previsível ganha de esperto.

---

## 1. KISS — Keep It Simple, Stupid

> A solução mais simples que resolve o que está pedido. Sem cleverness, sem "preparar para o futuro" que não existe.

**O que significa na prática:**

- **Resolva o problema atual**, não o problema imaginado. Se a estória pede "validar CNPJ", você valida CNPJ. Não cria um framework genérico de validação de documentos brasileiros.
- **Defaults antes de configuração.** Se vai funcionar com defaults do framework/lib, deixe assim. Configurabilidade prematura é dívida.
- **Função simples antes de classe.** Classe simples antes de hierarquia. Hierarquia antes de padrão de design clássico. Cada degrau pra cima exige justificativa concreta.
- **Lógica explícita antes de mágica.** Código que faz o que parece fazer ganha de código que requer entender 3 abstrações primeiro.

**Comparações concretas:**

```python
# ❌ Esperto — "configurável"
def calcular_indicador(empresa, tipo, opts=None):
    opts = opts or {}
    strategy = STRATEGIES.get(tipo, opts.get('default_strategy', strategy_padrao))
    return strategy.execute(empresa, **opts)

# ✅ Simples — resolve o que pediram
def calcular_liquidez_corrente(empresa):
    return empresa.ativo_circulante / empresa.passivo_circulante
```

Quando aparecer um segundo indicador, você acha um padrão. Quando aparecer o terceiro, talvez você abstraia (ver "regra de três" abaixo). Antes disso, é especulação.

**Pergunta para você mesmo antes de qualquer construção mais elaborada:** "Eu consigo explicar essa solução em 2 minutos para alguém de fora? Eu mesmo entendo isso em 3 meses?"

**Sinais de alerta no seu próprio código:**
- Camadas de indireção que não pagam o próprio peso.
- Nomes genéricos demais (`Manager`, `Handler`, `Processor`, `Engine`).
- Decorators/middlewares/interceptors empilhados onde uma função explícita resolveria.
- Configuração que tem só um valor possível na vida real.

---

## 2. DRY com bom senso — a regra de três

> Duplicação real de **intenção** é débito. Mas abstração prematura também é débito — e é mais difícil de remover.

DRY (Don't Repeat Yourself) é frequentemente mal aplicado. A leitura simplista diz "vi código parecido, abstraio". A leitura sênior é diferente: duplicar é barato; abstrair errado é caro.

**A regra de três** (Rule of Three):
- **1ª ocorrência:** escreva o código.
- **2ª ocorrência:** copie e adapte. Note a semelhança, mas **não abstraia ainda**.
- **3ª ocorrência:** agora você sabe o que é igual e o que muda. Aí abstraia.

A razão é simples: com só 2 exemplos, você ainda não vê o padrão real. Você vê coisas que **parecem** iguais mas mudam no terceiro caso. Abstrair com 2 dados leva a abstração que precisa ser reescrita no terceiro.

**Duplicação que é OK:**
- Código que **parece** igual mas representa intenções diferentes (validação de CNPJ no cadastro vs validação de CNPJ em consulta — talvez tenham regras diferentes amanhã).
- Estruturas paralelas de teste (cada teste é independente, repetir setup curto é mais legível que helper genérico).
- Strings de configuração (uma constante explícita é mais clara que importar de outro lugar).

**Duplicação que NÃO é OK:**
- A mesma regra de negócio escrita em 3 lugares (se a regra mudar, vai mudar em todos? então centralize).
- O mesmo bug copiado em vários lugares (caminho rápido pra propagar problemas).
- Magic numbers/strings repetidos (já é constante esperando pra nascer).

**Diferenciador chave:** duplicação de **código** que representa **mesma intenção** é débito. Duplicação de código que **por acaso parece igual** mas representa **intenções diferentes** é correta — não DRYfique.

**Sinais de alerta:**
- "Vou criar um helper genérico para esses 2 casos" → espere o terceiro.
- A abstração que você criou tem mais parâmetros opcionais que código.
- O método abstrato faz `if tipo == 'A' ... elif tipo == 'B' ...` — você abstraiu coisas que não são realmente a mesma.

---

## 3. Siga o framework opinativo

> Se o framework tem um jeito de fazer, faça desse jeito. Lutar contra defaults é red flag.

O Arquiteto escolheu o framework por ele ser opinativo — isso poupa centenas de decisões. Quando você sobrescreve um default, você está jogando fora parte desse valor. Faça-o conscientemente, e raramente.

**Como aplicar:**

- **Estrutura de pastas:** use a sugerida pelo framework. Não "melhore".
- **Padrão de routing/controllers/services/etc:** se o framework tem opinião, siga.
- **Geração de código / scaffolding:** use. É exatamente do jeito que o framework espera.
- **Configuração default:** mantenha, a menos que tenha motivo concreto pra mudar.
- **Convenções de naming, formatação, organização:** as do framework.

**Quando sobrescrever default é OK:**

- Restrição **concreta e demonstrável** do nosso domínio (não opinião pessoal).
- A decisão é registrada (PR comment + IDR se virar padrão).
- Você sabe exatamente o que está perdendo ao sobrescrever (ex: "ao fazer isso, perco a integração com X — aceito porque...").

**Quando NÃO é OK:**

- "No projeto Y eu fazia diferente" — você não está no projeto Y.
- "Eu prefiro assim" — preferência sem dado não justifica fricção.
- "Vai ser mais flexível" — flexibilidade sem propósito é decisão adiada.
- "Esse default tem problema" — talvez. Mas o framework tem 10 anos e milhares de devs. **A barra para esse argumento é alta.**

**Sinais de alerta:**
- Você está reescrevendo metade dos defaults → o framework é o errado, ou você é.
- Seu código tem mais "configuração do framework" do que código de negócio.
- Devs novos no projeto perguntam "por que vocês fazem diferente da doc?"

---

## 4. Código previsível ganha de código esperto

> O próximo dev (ou você em 3 meses) precisa entender em 2 minutos.

Cleverness é satisfatório de escrever e doloroso de manter. Código sênior é frequentemente **chato**: faz o óbvio, de jeito óbvio, com nomes óbvios.

**Heurísticas:**

- **Funções com responsabilidade única — com bom senso.** SRP (Single Responsibility Principle) é diretriz, não imperativo cego. Funções pequenas costumam ser boas; mas **levado ao extremo, SRP vira fragmentação** — 50 funções de 3 linhas cada, função-A chama função-B chama função-C, e ninguém entende o todo. **Legibilidade do conjunto importa mais que pureza individual.** Critério prático: se você consegue ler a função inteira em uma tela e entender o que ela faz, está OK — mesmo que ela faça "duas coisas relacionadas".
- **Nomes longos > nomes curtos com mistério.** `calcular_liquidez_corrente()` ganha de `calc()`.
- **Variáveis intermediárias com nome** ganham de expressões aninhadas em uma linha.
- **Early return** ganha de aninhar 4 níveis de `if`.
- **Comentário onde o "por quê" não está óbvio do código.** Comentário não é tradução do código pra português — é o **motivo** quando ele não dá pra inferir.

**Comparações concretas:**

```javascript
// ❌ Esperto demais
const r = items.reduce((a, i) => i.t === 'A' ? a + i.v : a, 0);

// ✅ Previsível
const totalDeTipoA = items
  .filter(item => item.tipo === 'A')
  .reduce((soma, item) => soma + item.valor, 0);
```

```python
# ❌ Mágica de Python
config = type('Config', (), {**os.environ, **defaults})()

# ✅ Explícito
class Config:
    debug = os.environ.get('DEBUG', defaults['debug']) == 'true'
    db_url = os.environ.get('DB_URL', defaults['db_url'])
    # ... explícito, fácil de ler, fácil de testar
```

**Sinais de alerta:**
- Você está orgulhoso de quão pouca linha o código tem → suspeite.
- Code review pediu pra você "explicar essa parte" → essa parte está obscura.
- Você usa feature avançada da linguagem para resolver problema mundano.

---

## 5. Coesão alta, acoplamento baixo — também no nível de função e módulo

Os princípios arquiteturais #5 (`architecture-principles.md`) falam de coesão/acoplamento entre módulos. **A mesma ideia vale dentro do código:**

- Funções **coesas:** fazem uma coisa.
- Funções **desacopladas:** dependem do mínimo de outras coisas. Recebem o que precisam via parâmetro; não buscam contexto global.
- Módulos **coesos:** todo o código relacionado a uma intenção mora junto.
- Módulos **desacoplados:** importam o mínimo de outros módulos. Quando importam, é de uma interface clara.

**Hábito que ajuda:** ao terminar de escrever uma função/classe, pergunte:
- "Quantas razões diferentes esta função tem pra mudar?" → idealmente 1.
- "Se eu trocar a implementação interna de X (que esta função usa), quebra?" → idealmente não.

**Conexão com testes:** este é o ingrediente que torna o código **trivial de testar**. Se um teste unitário precisa montar 10 colaboradores em mock, o problema é acoplamento — não o teste.

---

## 6. Sem premature optimization

> Conhecida frase de Knuth: "premature optimization is the root of all evil". Vale aqui.

- Escreva o código simples e correto primeiro.
- Meça antes de otimizar.
- Otimize o gargalo real, não o suposto.
- Toda otimização que sacrifica clareza precisa de justificativa medida (IDR `type: otimização`).

**Quando otimização precoce é OK:**
- Quando o problema é matematicamente óbvio (ex: O(n²) em lista de 1M itens).
- Quando o framework/lib oferece uma alternativa idiomática naturalmente mais eficiente (use a alternativa idiomática, sem cleverness).

**Quando NÃO é OK:**
- "Cache aqui pra ficar mais rápido" sem medida do tempo atual.
- Algoritmos exóticos para volumes que não justificam.
- Estruturas de dados especializadas onde lista comum resolve.

---

## 7. Comentários: para o "por quê", não para o "o quê"

- ✅ "Usamos batch de 100 aqui porque a API do fornecedor rejeita lotes maiores (ver IDR-007)."
- ❌ "// incrementa o contador" sobre `counter += 1`.

Código bom **mostra** o quê faz. Comentário existe para o que o código **não consegue mostrar**: motivo, contexto, restrição, referência externa, surpresa.

**Quando comentar é necessário:**
- Workaround para bug externo (referencie issue/IDR).
- Decisão contraintuitiva (explicar a intuição que está sendo violada).
- Trade-off aceito conscientemente.
- TODO com responsável e contexto (não TODO órfão).

**Quando comentar é ruído:**
- Tradução do código pra português.
- Comentário de autor/data (use git).
- Comentário que vai desatualizar e ninguém vai atualizar.

---

## 8. Erros: explícitos, descritivos, recuperáveis quando faz sentido

Tratamento de erro é onde código fica realmente sênior ou realmente amador. **Esta disciplina ganhou uma reference dedicada** — veja `references/error-handling.md`. Resumo do essencial:

- Distinguir **erro esperado** (validação, regra de negócio) de **bug** (exceção não prevista).
- **Falhar cedo, falhar alto.** Input inválido → erro explícito imediato.
- Mensagens **para usuário** ≠ mensagens **para log** — públicos diferentes, conteúdos diferentes.
- **Idempotência** em operações que podem ser repetidas.
- **Retry com backoff + jitter** em chamadas externas transitórias.
- **Não engulir exceção** silenciosamente — quase sempre errado.
- Usar a **hierarquia de exceções** do framework/linguagem.

---

## 9. Mensagens de commit: explicam o porquê

Commit é histórico do projeto. Bons commits ajudam você de amanhã, o revisor de hoje, e quem investiga incidente daqui a 1 ano. Bons hábitos:

- **Mensagem explica o porquê, não só o quê.** O `git diff` mostra o quê — você não precisa repetir. O contexto e a motivação **só vivem na mensagem**.
- **Imperativo, presente.** "Add validação de CNPJ", não "Added" nem "Adding".
- **Linha 1 curta** (até ~72 caracteres) — é o que aparece em `git log --oneline`.
- **Corpo explica contexto** quando necessário — separado por linha em branco.
- **Referencie a estória** ou ADR/PDR/IDR quando aplicável.

**Exemplos ruins:**

```
fix
update code
WIP
asdf
trabalho
remove bug
```

**Exemplos bons:**

```
feat(cadastro): valida CNPJ contra dígitos verificadores

Inclui módulo de validação que verifica os dois dígitos verificadores
do CNPJ além do formato. Estória: STORY-005. Conforme regra em
especificacao-funcional.md seção 3.2.
```

```
fix(diagnostico): trata passivo zero retornando erro de domínio

Antes calcular_liquidez levantava ZeroDivisionError sem contexto.
Agora retorna DiagnosticoNaoCalculavelError com mensagem específica.
Cobre CA-3 da STORY-008.
```

```
refactor(empresa): extrai validação de CNPJ para módulo dedicado

Reaproveitada por cadastro, consulta e importação de planilha
(terceira ocorrência — segue regra de três em coding-principles.md).
```

```
chore(deps): atualiza driver de Postgres 4.2 → 4.3

Versão 4.3 corrige vazamento de conexão em pool sob alta
concorrência (changelog upstream). Suíte completa verde após
upgrade.
```

**Padrão de prefixos** (convenção Conventional Commits — boa quando o projeto adota):

- `feat`: nova funcionalidade
- `fix`: correção de bug
- `refactor`: mudança que não altera comportamento externo
- `test`: adição/correção de teste
- `docs`: documentação
- `chore`: tarefa operacional (deps, build, infra)
- `perf`: melhoria de performance

O projeto pode ou não adotar formalmente Conventional Commits — siga a convenção decidida em ADR ou IDR. Mas **o princípio de explicar o porquê vale sempre**.

---

## Como aplicar tudo isso quando você está escrevendo

Não tente lembrar de 8 princípios o tempo todo. Internalize a pergunta única:

> **"O próximo dev (ou eu em 3 meses) vai entender e modificar isso com facilidade?"**

Se a resposta é não, alguma coisa nos princípios acima foi violada — geralmente KISS ou previsibilidade. Refatore.
