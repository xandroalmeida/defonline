# Disciplina de bibliotecas

Antes de `npm install` / `pip install` / `bundle add` / equivalente, **pare**. Esta é uma das decisões mais subestimadas no dia a dia de programação e uma das mais custosas em projetos sustentáveis.

Cada lib que entra no projeto cobra **manutenção contínua**:
- Atualizações regulares (segurança, compatibilidade).
- Alertas de vulnerabilidade pra triar.
- Mais bundle / mais tempo de build / mais memória.
- Mais uma coisa que um futuro dev precisa entender e aprender.
- Risco de abandono (mantenedor saiu, projeto morre, vc carrega).
- Risco de mudança de licença (raro mas existe).

Lib não é "grátis pra adicionar, vamos ver". Lib é compromisso.

---

## Roteiro mental antes de instalar (pense 2 ou 3 vezes)

Faça esta sequência **na ordem**. Pare no primeiro "sim" — você acabou de evitar uma dependência.

### Pergunta 1 — A biblioteca padrão da linguagem já faz?

A stdlib moderna de qualquer linguagem decente cobre bem mais do que se pensa. Antes de adicionar lib utilitária, verifique:

- **JavaScript/TypeScript moderno:** `Array.prototype.flatMap`, `String.replaceAll`, `Object.entries`, `structuredClone`, `Intl.DateTimeFormat`, `Intl.NumberFormat`, `fetch`, `URL`, `URLSearchParams`, `crypto.subtle`, `AbortController`. **Boa parte do que justificava Lodash/Moment/Axios em 2015 hoje é nativo.**
- **Python:** `dataclasses`, `pathlib`, `itertools`, `functools`, `collections`, `datetime`, `decimal`, `urllib.parse`, `re`, `json`, `subprocess`, `concurrent.futures`. **A stdlib do Python é gigante e poderosa.**
- **Outras linguagens:** mesma ideia. Releia a stdlib.

### Pergunta 2 — O framework opinativo já faz?

O framework escolhido pelo Arquiteto **opina** sobre como resolver problemas comuns. Antes de adicionar lib externa, verifique o que o framework te dá:

- **Validação?** Framework opinativo tem (Django forms/serializers, Rails strong params, NestJS class-validator integrado, Laravel form requests, Spring Validation). **Não traga Joi/Yup avulso.**
- **Autenticação?** O framework tem alguma forma de auth nativa ou integração padrão recomendada. **Use a do framework.**
- **HTTP client?** Frameworks back-end normalmente têm um wrapper. Linguagens modernas têm cliente HTTP nativo.
- **Migrations / ORM?** O framework opinativo tem.
- **Testes?** O framework tem ferramentas oficiais ou recomendação clara.
- **Geração de código admin?** Django, Rails têm de fábrica; Laravel também. **Use.**

Buscar `awesome-<lang>` lists ou "best lib for X" no Google é frequentemente o **caminho errado** — você acaba adicionando lib pra um problema que o framework já resolveu.

### Pergunta 3 — Eu consigo fazer em 30 minutos com clareza?

Para problemas pequenos, escrever o código é **mais barato** que adicionar dependência. Exemplos:

- **Função pequena de validação** (regex de CNPJ, formatação de telefone) → escreva. 5 linhas é menos manutenção que uma lib.
- **Helper de array/string específico** → escreva. Vai ficar mais legível que uma lib genérica.
- **Wrapper sobre fetch / HTTP client** → escreva. Você ganha API exatamente do jeito que precisa.
- **Debounce / throttle simples** → escreva. 10 linhas, sem dependência.

**Regra prática:** se o problema é pequeno **e** a solução é estável (você não precisa de updates regulares), escreva.

### Pergunta 4 — A lib resolve um problema real e durável?

A lib precisa resolver um problema que:
- **Existe agora** (não "vai existir quando escalarmos").
- **É durável** (vai continuar existindo).
- **Justifica uma dependência** (vs. as alternativas acima).

**Sinais de que a lib NÃO se justifica:**
- "É mais bonitinho."
- "Vai economizar 2 linhas de código."
- "É moda."
- "Vi em outro projeto."

**Sinais de que se justifica:**
- Resolve problema complexo onde implementar do zero seria irresponsável (criptografia, parsing de formato complexo, integração com fornecedor específico).
- Mantida ativamente (commits recentes, releases regulares, comunidade viva).
- API estável, bem documentada.
- Bus factor decente (não é projeto de uma pessoa só).

### Pergunta 5 — Custo de carregar essa lib

Para libs que passaram nas perguntas anteriores, faça um sanity check de custo:

| Dimensão | O que verificar |
|---|---|
| **Manutenção** | Último commit < 6 meses? Releases regulares? Issues respondidas? |
| **Tamanho** | Bundle size impact (para FE), tempo de instalação, profundidade de árvore de dependências |
| **Licença** | Compatível com nosso uso? (MIT, Apache, BSD, ISC são seguros; GPL exige análise) |
| **Segurança** | Histórico de vulnerabilidades? Hoje tem alguma aberta? |
| **Dependências transitivas** | Esta lib instala 50 outras? Cuidado. |
| **Bus factor** | Quantos mantenedores ativos? Projeto de uma só pessoa é risco. |

Para libs **transversais** (vão aparecer em muitos lugares), essa análise vai no IDR. Para libs **locais** (um módulo), basta verificar e mencionar no PR.

---

## Tabela: antes de instalar X, avalie alternativas e o caso de uso

Lista não-exaustiva. Não é "lista de proibições" — é **ponto de partida**: para cada lib popular, considere primeiro a alternativa nativa, e veja quando a lib **ainda é a melhor escolha**.

### JavaScript / TypeScript

| Lib popular | Alternativa nativa moderna | **Quando a lib continua sendo a melhor escolha** |
|---|---|---|
| **Lodash** | `Array.prototype.*`, `Object.entries`, `structuredClone`, `Array.from({length:n}, ...)`, `Object.freeze` | `_.cloneDeep` com cenários complexos (referências circulares antes de `structuredClone` ser disponível), `_.debounce`/`_.throttle` em uso intenso, `_.merge` profundo com customização. Para uso pesado e variado, lib paga seu peso. |
| **date-fns / dayjs / Luxon** | `Intl.DateTimeFormat`, `Intl.RelativeTimeFormat`, `Date` nativo | Manipulação de datas e fusos extensiva (parsing de muitos formatos, aritmética de datas, locale, formatos brasileiros). `date-fns` é tree-shakeable — vale considerar. **Moment.js está deprecated**; não comece projeto novo com ele. |
| **Axios** | `fetch` nativo + `AbortController` | Quando precisa de interceptors globais (auth, retry, log), cancelamento sofisticado, integração com testes via adapters. Para chamadas simples, `fetch` resolve. |
| **jQuery** | DOM API moderna | Manutenção de código legado. Em projeto novo, praticamente nunca. |
| **Underscore** | Quase tudo é nativo hoje | Igual Lodash — raramente justificável em projeto novo. |
| **uuid** | `crypto.randomUUID()` nativo | Quando precisa de UUID v1/v3/v5 específicos (geração com namespace, timestamp). `randomUUID` cobre só v4. |
| **classnames / clsx** | Template literal + função pequena | Quando o uso é intenso em FE com muitos componentes — `clsx` (350 bytes) tem ergonomia melhor que helper caseiro. |
| **dotenv** | Framework já lê `.env` (Next.js, Vite, NestJS, etc) | Scripts Node standalone fora de framework opinativo. |
| **zod / yup / joi** | Validação do framework opinativo (NestJS class-validator, Hono validator etc) | Quando o framework escolhido **não** tem validação integrada bem feita. Aí escolher um e ser consistente — IDR. |
| **request** (deprecated) | `fetch` nativo ou `undici` (Node) | Não use — está deprecated. Para projetos legados, plano de migração. |

### Python

| Lib popular | Alternativa nativa | **Quando a lib continua sendo a melhor escolha** |
|---|---|---|
| **requests** | `urllib.request` nativo, `httpx` (recomendado para projetos novos) | Vale em projeto que já usa — `requests` é qualidade. Para projeto novo, `httpx` tem API similar + async + HTTP/2; vale considerar. `urllib` cru é raramente o caminho. |
| **arrow / pendulum** | `datetime` + `zoneinfo` (3.9+) | Manipulação de fuso muito sofisticada, parsing/formatação extensa. Caso a caso. |
| **simplejson** | `json` nativo | Casos específicos (decimais com precisão exata, encoding incomum). Geralmente `json` resolve. |
| **attrs** | `dataclasses` nativo (3.7+) + `pydantic` para validação | Cenários muito sofisticados (slots, converters, validators complexos). Para a maioria, `dataclasses` resolve. |
| **python-dotenv** | Configuração do framework opinativo (Django settings, Flask config, FastAPI BaseSettings) | Scripts standalone, ou framework sem solução de config integrada. |
| **marshmallow / pydantic / dataclass-validation** | Validação do framework (DRF serializers, FastAPI Pydantic embutido) | Para projetos sem framework opinativo, ou onde o que vem com o framework não atende. Escolha um, IDR. `pydantic` é referência atual; muitos frameworks já a integram. |

### Validação transversal (todas as linguagens)

- **Primeiro**, veja o que o framework opinativo já tem.
- Se não tem ou é limitado: escolha **uma** lib de validação e mantenha consistente no projeto → IDR.
- Não use múltiplas libs de validação no mesmo projeto.

### Como ler estas tabelas

Para qualquer lib aqui:

1. Considere primeiro a alternativa nativa.
2. Se seu caso de uso bate na coluna "quando ainda vale" — a lib é provavelmente a escolha certa.
3. Se seu caso é **mais simples** que isso — escreva você mesmo, evite a dependência.
4. Em dúvida → 30min de experimento com a alternativa nativa é o suficiente para decidir.

---

## Quando vai entrar uma lib

### Lib local de um módulo (afeta só ali)

- Verifique as 5 perguntas acima.
- Adicione no `package.json` / `requirements.txt` / `Gemfile`.
- No PR, na descrição: **seção "Nova dependência: <nome>"** com 2 linhas — por que precisava, alternativas consideradas.
- Em "Notas do agente" da estória: linha registrando a adição.

### Lib transversal (vai virar padrão)

- Verifique as 5 perguntas acima **com rigor**.
- **Antes** de adicionar, crie um IDR (`templates/idr.md`) com tipo "Padrão transversal".
- No IDR, registre: contexto, alternativas (incluindo "não usar lib, fazer manualmente"), por que vence, consequências, como verificar.
- O nome da lib agora é padrão — outros agentes vão saber pelo IDR. Quem trouxer uma lib equivalente diferente está violando o IDR (registre como gap no PR).

### Quando o Arquiteto deve entrar

Se a escolha da lib **muda padrão arquitetural** (ex: trocar a forma como o sistema faz HTTP cliente, ou mudar a lib de cripto), aí não é mais IDR — vira ADR. Escale para o Arquiteto.

**Como saber?** Pergunta de bolso: "Essa lib afeta como módulos diferentes se comunicam, ou como o sistema interage com o mundo externo de forma estrutural?". Se sim, é ADR.

---

## Quando remover uma lib

Tão importante quanto adicionar com critério é **remover quando não está mais sendo usada** ou quando foi substituída.

- Toda vez que você remove o último uso de uma lib, **remova a dependência também** (do `package.json`/`requirements.txt`).
- Periodicamente (fim de épico, idealmente em estória de "limpeza"), audite dependências sem uso e remova.
- Lib instalada mas não usada é **risco gratuito**.

---

## Resumo operacional

Antes do `npm install` / `pip install`:

1. ✅ stdlib faz?
2. ✅ Framework faz?
3. ✅ Consigo fazer em 30min com clareza?
4. ✅ Resolve problema real e durável?
5. ✅ Custos verificados (manutenção, tamanho, licença, segurança, bus factor)?

Se passou nos 5 e vai entrar:

- Local? Justificativa no PR + "Notas do agente".
- Transversal? IDR antes do install. Sem IDR, não instala.
