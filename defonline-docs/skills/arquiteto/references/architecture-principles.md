# Princípios arquiteturais do DEFOnline

Estes são os princípios duráveis que o Arquiteto usa para avaliar **qualquer** decisão. Eles não substituem julgamento — informam o julgamento. Toda ADR deve ser defensável contra estes princípios; quando uma decisão viola um princípio, isso é registrado explicitamente como trade-off aceito, não escondido.

**A ordem importa.** Os primeiros pesam mais. Em conflito entre dois princípios, prefira o que está mais acima. Os princípios de 1 a 6 são os **comportamentos centrais** do Arquiteto do DEFOnline — eles definem o estilo. Os princípios de 7 a 12 são complementos importantes que reforçam os centrais.

---

## 1. Simples é o belo

> A solução mais simples que resolve o problema atual. Complexidade só quando estritamente necessária — e justificada com dor real, não imaginada.

**Por quê.** Complexidade não é grátis. Ela cobra em tempo de onboarding, em superfície de bug, em custo de manutenção, em horas perdidas com tooling, em "magia" que só uma pessoa entende. Para um time pequeno operando um SaaS para MPE, complexidade prematura é a morte por mil pequenos cortes. Simplicidade é uma **escolha ativa** — exige resistir à tentação de "preparar para o futuro" com soluções que esse futuro nunca exige.

**Como aplicar.**
- Antes de propor uma estrutura complexa, descreva o caminho simples e demonstre que ele não atende. Se não conseguir demonstrar, **o simples é a resposta** — não a complexidade.
- Toda ADR que adiciona complexidade deve nomear explicitamente a **dor real** que justifica. Não vale dor imaginada, dor prevista sem evidência, ou dor de outro projeto.
- Suspeite de soluções que se vendem com palavras como "escalável", "flexível", "extensível", "pluggable" sem números concretos por trás.
- Quando em dúvida entre duas opções, escolha a que tem **menos peças móveis**, menos dependências, menos abstrações.
- Métrica informal: você consegue explicar a solução em 2 minutos para alguém que não está no projeto? Se não, é complexa demais.

**Sinais de alerta.**
- "Vamos preparar para milhões de usuários" — você tem dezenas hoje.
- "Vamos fazer pluggable / configurável" — sem segundo plugin/config real previsto.
- A solução exige uma pessoa dedicada pra cuidar dela.
- Para entender o sistema, é preciso aprender 3+ abstrações inventadas pelo time.
- "Vamos usar X porque é o padrão da indústria" — sem contexto de qual indústria, qual tamanho, qual problema.

---

## 2. Tudo começa em monolito

> Toda arquitetura nasce monolítica. Microsserviços apenas quando as possibilidades do monolito se esgotarem com evidência concreta.

**Por quê.** Microsserviços resolvem problemas reais que monolitos não resolvem: deploy independente, escala independente, isolamento de falha, polyglot quando uma parte genuinamente precisa de outra linguagem. **Mas cobram um pedágio enorme:**

- Rede no caminho crítico (latência, falha de rede, retry, idempotência);
- Transações distribuídas (sagas, eventual consistency, conciliação);
- Complexidade de deploy (orquestração, service mesh, versão de contrato);
- Debugging distribuído (correlação de trace entre serviços);
- Provisão e custo de infra multiplicados por N serviços;
- Onboarding multiplicado pela quantidade de bases que o dev precisa entender.

Para um time pequeno, esse pedágio é frequentemente fatal. Monolito **bem feito** (modular, com bordas claras — ver princípio #5) entrega 90% dos benefícios sem o custo. **Só quebre quando a dor do monolito for concreta, mensurada, e não puder ser resolvida dentro dele.**

**Como aplicar.**
- Comece com **monolito modular**: módulos com bordas explícitas, contratos internos claros, banco compartilhado (com schemas/owners por módulo se útil), processo único, deploy único.
- Use coesão alta e acoplamento baixo (princípio #5) para garantir que o monolito **não vire bagunça**. Esse é o trabalho que merece o investimento — não a quebra prematura.
- Promova um módulo a serviço SOMENTE quando pelo menos UMA das seguintes for verdade **com evidência registrada**:
  - Precisa escalar independente (módulo X consome 80% do recurso enquanto outros, 5%).
  - Precisa de stack diferente por restrição genuína (ex: módulo de ML em Python, resto em outra linguagem que serve melhor o domínio).
  - Tem requisito de **isolamento de falha** forte (módulo crítico não pode cair junto com módulos opcionais, com SLA documentado).
  - Tem ciclo de releases genuinamente diferente, demonstrado.
- **Nunca** promova por: "é arquitetura moderna", "microsserviços são padrão", "vai escalar mais fácil depois", "vamos preparar".

**Sinais de alerta.**
- Time pequeno propondo 5+ serviços no MVP.
- "Vamos usar Kubernetes" antes de existir um app rodando.
- Mais ferramentas de orquestração que serviços.
- Sagas, consistência eventual ou outbox pattern sendo discutidos antes de existir um endpoint.
- Diagrama de arquitetura com mais boxes que pessoas no time.

---

## 3. PostgreSQL é nossa primeira opção (sempre)

> O Postgres faz muito mais do que se imagina. Antes de adicionar qualquer outro armazenamento ou serviço de dados, **prove com números** que o Postgres não dá conta.

**Por quê.** Postgres é decisão fechada como banco principal. Cada novo serviço de dados (Redis, ElasticSearch, MongoDB, vector DB, fila externa, S3 para dados quentes) adiciona um sistema novo pra operar, outra fonte de consistência pra raciocinar sobre, outro backup pra cuidar, outra fonte de incidente, outra biblioteca cliente, outra credencial pra gerenciar, outra cobrança no mês.

Postgres **subestima quem o subestima**. Boa parte da complexidade de stacks modernas vem de adicionar serviços para resolver problemas que o Postgres já resolveria bem. Antes de "vamos adicionar X", a pergunta a fazer é: **o Postgres não faz isso?**

**O que o Postgres faz nativamente ou com extensão estável:**

| Capacidade | Como o Postgres faz | Pular para outra coisa só se |
|---|---|---|
| Fila / job queue | `SELECT ... FOR UPDATE SKIP LOCKED` + tabela + worker | Volume sustentado > 10k msg/s ou roteamento complexo entre múltiplos consumidores |
| Full-text search | `tsvector` + `tsquery` + índice GIN | Volume massivo + ranking sofisticado (e ainda assim avalie pg + extensões antes) |
| JSON queryable | `jsonb` + operadores + índices GIN | Praticamente nunca |
| Vector / embeddings | `pgvector` extension | Bilhões de vetores com latência sub-ms (raríssimo no nosso domínio) |
| Time-series | `TimescaleDB` extension | Ingest sustentado acima do que Timescale aguenta |
| Cache temporário | `UNLOGGED TABLE` ou índice quente | Cache realmente compartilhado entre processos com TTL agressivo |
| Geo / spatial | `PostGIS` extension | Praticamente nunca |
| Audit log / event log | tabela append-only + triggers | Volume massivo de eventos com requisitos de replay sofisticado |
| Pub/Sub leve | `LISTEN` / `NOTIFY` | Pub/Sub durável, multi-consumer com replay e particionamento |
| Read replicas | Replicação nativa | Quando latência de réplica é problema de domínio |

**Como aplicar.**
- Toda ADR que propuser armazenamento adicional **deve** começar com uma seção "Por que o Postgres não dá conta", com evidência: números medidos, requisitos quantificados, restrição arquitetural específica. Sem isso, a ADR é rejeitada na origem.
- Saída legítima do Postgres: prove com volume concreto, requisitos de latência mensurados, ou restrição que o Postgres genuinamente não atende.
- Saída ilegítima:
  - "Redis é mais rápido pra cache" sem benchmark do caso real;
  - "MongoDB é mais flexível pra JSON" — Postgres tem `jsonb` (e o JSON pode ser indexado);
  - "ElasticSearch é o padrão pra busca" sem dimensionar a carga;
  - "Precisamos de vector DB pra IA" — `pgvector` na maioria absoluta dos casos resolve;
  - "Precisamos de fila" — Postgres faz fila bem para muitos casos.
- Quando uma extensão (`pgvector`, `PostGIS`, `TimescaleDB`, `pg_trgm`, etc) resolve, **prefira a extensão**. Você ganha tudo que o Postgres já te dá (backup, replicação, transações ACID, observabilidade, ferramentas) sem adicionar sistema.
- Saída assumida (registrada como ADR explícita): se você concluir que precisa de outro armazenamento, descreva no ADR o que vai para fora do Postgres e por quê — para que isso fique como precedente investigado, não habit.

**Postgres-first com bom senso — não é dogma cego.** O princípio é forte e tem que ser; mas há casos legítimos onde outra ferramenta vence Postgres mesmo quando ele "tecnicamente daria conta":

- **Ergonomia muito superior do dev**: a forma idiomática na outra ferramenta é dramaticamente mais natural (ex: fila gerenciada do provedor cloud com retry/DLQ embutidos, em vez de implementar tudo manualmente em Postgres).
- **Operação muito mais simples**: serviço gerenciado da cloud reduz superfície operacional significativamente (ex: S3 para arquivos vs `BYTEA` no Postgres — não é uma decisão difícil).
- **Prazo apertado real**: solução pronta atende restrição de cronograma quando a versão Postgres exigiria semanas de implementação.

**Quando uma dessas justifica saída:** registre o trade-off **honestamente** no ADR — "Postgres faria, mas X vence por Y motivo concreto". Isso não viola o princípio; o que violaria é fingir que Postgres não dá conta quando dá, ou cair em "Postgres é dogma, vamos sofrer". Reconhecer o trade-off é parte do contrato de honestidade da decisão.

**Sinais de alerta.**
- Stack proposta com 4+ armazenamentos no MVP.
- "Vamos usar Redis pra cache" antes de existir gargalo medido.
- Vector DB dedicado para domínio onde teremos milhares (não bilhões) de embeddings.
- Decisão "vamos sair do Postgres" sem benchmark contra ele.

---

## 4. Linguagens e frameworks opinativos

> Use linguagens e frameworks que **opinem** — que tomem decisões por você. Evite stacks que exigem montar tudo do zero.

**Por quê.** Toda decisão técnica não tomada é uma decisão futura tomada de forma rasa, sob pressão, por alguém em uma estória qualquer. Frameworks opinativos (do tipo "battery-included" como Django, Rails, Phoenix, Laravel, NestJS, Spring Boot, etc.) tomam centenas dessas micro-decisões por você: estrutura de pastas, autenticação, ORM, padrão de routing, validação, migrations, comandos administrativos, testes, configuração.

**Você ganha:**

- **Consistência.** Todo módulo segue o mesmo padrão. Onboarding de novo dev em horas, não dias.
- **Velocidade.** Dia 1 já tem auth, admin, ORM funcionando — não precisa reinventar a roda.
- **Manutenção.** Atualizações vêm em batch — atualizar o framework atualiza dezenas de subcomponentes alinhados.
- **Comunidade.** Problemas comuns já têm solução documentada, referenciada, debatida.
- **Menos discussões inúteis.** "Qual lib de validação?" "Qual padrão de routing?" "Como estruturar testes?" — o framework respondeu, vamos trabalhar.
- **Defaults seguros.** Quem decide defaults é gente experiente que pensou nisso por anos. Os defaults são quase sempre o certo.

Stacks não-opinativas (Express com 20 libs avulsas, microframeworks bare-metal, "vamos montar nossa stack do zero") parecem **flexíveis** no dia 1 e cobram caro do mês 6 em diante: tudo é decisão local, tudo é inconsistente entre módulos, tudo é mais fácil de errar. Flexibilidade não é virtude por si só — flexibilidade sem propósito é só descisão adiada.

**Como aplicar.**
- Quando avaliar opções de linguagem/framework em ADR, dê **peso explícito** à dimensão "quão opinativo é".
- Em ADR de stack, liste o que o framework já entrega de graça: auth, ORM, admin, migrations, geração de código, testes, observabilidade básica, deploy. Tudo isso conta como **trabalho economizado**.
- Em caso de tentação por stack flexível: liste o que terá que ser decidido caso a caso ao longo do projeto. Geralmente essa lista mata a tentação por si só.
- Aceite os **defaults** do framework escolhido. Se você está sobrescrevendo metade dos defaults, ou o framework escolhido foi errado, ou você está lutando contra ele à toa.
- Saídas legítimas: o framework opinativo escolhido tem uma restrição forte que mata o caso de uso (ex: modelo de concorrência incompatível com domínio, performance específica não atingida, segurança documentada como insuficiente).

**Sinais de alerta.**
- "Vamos usar microframework X porque dá controle" — controle do quê, exatamente?
- Stack proposta com 30+ dependências individuais em vez de 1 framework cobrindo.
- Tempo do primeiro endpoint funcionando >>> 1 dia.
- Cada módulo do código tem estilo diferente.
- Times perdendo tempo discutindo padrões internos.
- "Nosso framework caseiro é melhor que X porque..." — quase sempre é dívida disfarçada.

---

## 5. Coesão alta, acoplamento baixo

> Cada módulo faz uma coisa e faz bem. Módulos diferentes conversam o mínimo necessário e sempre por contratos explícitos.

**Por quê.** Estes são princípios clássicos porque funcionam.

- **Coesão alta** significa que código relacionado mora junto. Quando você precisa mudar uma regra de negócio, encontra tudo em um lugar.
- **Acoplamento baixo** significa que módulos têm dependências mínimas e explícitas. Você consegue trocar a implementação interna de um módulo sem cascata de mudanças nos outros.

Juntos, esses princípios são **o trabalho que torna o monolito sustentável** (princípio #2), o que mantém a complexidade contida em pedaços manejáveis (princípio #1), e o que torna o sistema testável sem heroísmo (princípio #10 — TDD/E2E).

**Definições operacionais:**

- **Coesão alta:** um módulo tem **uma única razão para mudar**. Tudo que pertence a essa razão está dentro do módulo — modelos, regras, validações, queries, testes. Nada irrelevante a essa razão está dentro.
- **Acoplamento baixo:** módulo A só conhece o módulo B através de um **contrato explícito** — interface, API interna, evento de domínio, função pública do módulo. A nunca importa diretamente de detalhes internos de B. Mudar internals de B não quebra A.

**Como aplicar.**

- Em ADR que define decomposição em módulos, **nomeie cada módulo pela razão única para mudar** ("Cadastro de Empresa", "Diagnóstico Financeiro", "Importação de Planilha"), **não** por camada técnica ("Controllers", "Services", "Repositories"). Camadas técnicas são padrão dentro de cada módulo; não são módulos.
- Defina **contratos explícitos** entre módulos. Em monolito modular: interfaces internas, eventos de domínio, funções públicas do módulo. Em microsserviços: APIs versionadas.
- **Proíba importação cruzada de detalhes internos.** Use linter ou teste arquitetural (princípio "automatizável > documentável") para garantir mecanicamente, não documentar.
- Quando dois módulos parecem precisar conhecer demais um do outro, considere:
  - (a) Talvez sejam o mesmo módulo — junte.
  - (b) Talvez falte um terceiro módulo cobrindo a interseção legítima.
  - (c) Talvez o contrato precise ser repensado — eventos em vez de chamadas síncronas, por exemplo.
- Toda mudança que **aumenta acoplamento** (módulo passa a depender de mais módulos ou de detalhes internos) é red flag — registre como trade-off em ADR ou refatore para evitar.
- **Conexão com testabilidade:** módulos com coesão alta e acoplamento baixo são triviais de testar isoladamente. Se um teste unitário precisa montar 10 colaboradores em mock, o problema não é o teste — é o acoplamento.

**Sinais de alerta.**
- Mudar uma regra de negócio exige mudar 5 lugares diferentes (coesão baixa).
- Mudar internamente o módulo X quebra teste do módulo Y (acoplamento alto).
- Módulo nomeado "Core", "Common", "Utils", "Shared" — geralmente vira lixeira de coisas que não acharam dono natural.
- Time conta histórias de "se você mexer aqui, lembre de mexer lá também".
- Diagrama de dependência entre módulos parece grafo completo (todos conhecem todos).
- ORM com modelos que conhecem todos os outros modelos do sistema.

---

## 6. Funcionamento 100% local

> O sistema sobe inteiro na máquina do desenvolvedor, sem dependência de nenhum serviço externo real. Docker e mocks são as ferramentas legítimas para simular o que em produção é externo.

**Por quê.** Desenvolvedor que depende de serviço externo real para trabalhar tem fluxo travado por: internet ruim, credencial vencida, cota estourada, ambiente compartilhado em mau estado, latência alta. Time pequeno não tem folga para esses obstáculos.

Além disso:
- Bugs descobertos só em homologação/produção são **muito caros**. Descobrir cedo, local, é dinheiro economizado.
- O ambiente local fiel a produção é o que torna **onboarding rápido**: clonou, rodou um comando, está rodando. Sem "ah, mas você precisa de credencial X, contato com pessoa Y, configurar conta Z".
- O ambiente local fiel é o que permite ao desenvolvedor **iterar com confiança**: se funciona local, vai funcionar em produção (ou a diferença está documentada).

**Como aplicar.**

- **Tudo que é interno** (banco, app, workers, frontend, observabilidade básica) sobe via **Docker Compose** (ou equivalente). Versões idênticas a produção quando possível.
- **Serviços externos verdadeiros** (gateway de pagamento, API de Receita Federal, e-mail transacional, provedor de SMS, cloud-specifics) são **simulados localmente** via:
  - **Mock dedicado** rodando como container, mantido pelo time. Preferido quando o contrato é estável e o uso é intenso. Mais fiel e controlável.
  - **Biblioteca de stub** integrada ao app, quando o uso é simples e o mock dedicado seria exagero.
  - **Sandbox público do fornecedor** apenas quando o fornecedor oferece sandbox 100% confiável e o time aceita a dependência (registre o risco no ADR de integração).
- **Critério firme — não-negociável:** clonou o repo, rodou **um comando**, app sobe completo sem precisar de internet (exceto para baixar imagens Docker na primeira vez). Se essa frase não for verdade, o setup está quebrado e precisa de estória de correção.
- Em ADR de integração com serviço externo, **sempre inclua** uma seção "Simulação local" descrevendo:
  - Qual mecanismo de mock/stub foi adotado.
  - Como mantê-lo fiel ao contrato real.
  - Como sair do mock para o real em produção (a configuração que troca um pelo outro).

**Como manter o mock fiel ao real.** Mocks viram mentira quando ficam desatualizados em relação ao serviço real. Combata isso com:

- **Testes de contrato** rodando contra sandbox real periodicamente (CI noturno, por exemplo). Quando o mock divergir, alguém é notificado automaticamente.
- **Atualização do mock como parte da estória** quando o serviço real mudar — não é tarefa "do futuro".
- **Versão do contrato registrada no mock** (qual versão da API estou simulando, qual data de captura).
- **Logs** explícitos no mock em modo dev ("[MOCK] payment.charge called with {...}") para o desenvolvedor saber que está em mock e não em real.

**Sinais de alerta.**
- "Você precisa pedir credencial pra Fulano antes de rodar o app".
- "Funciona em homologação, não consigo reproduzir local".
- Onboarding de novo dev demora mais de 1 dia.
- CI tem testes que falham aleatoriamente porque dependem de serviço externo real.
- Mock divergiu do serviço real e ninguém percebeu por semanas.
- Equipe diz "tudo bem, sobe local" mas exige passos manuais de configuração específicos para cada serviço externo.

---

## 7. Reversibilidade preferida

> Decisões reversíveis são baratas; decisões one-way ganham escrutínio extra.

**Por quê.** Você vai errar — não é "se", é "quando". Boa arquitetura sobrevive aos próprios erros sem precisar de reescrita. Isso vem da reversibilidade: se a decisão estiver errada em 6 meses, conseguimos voltar atrás sem custo proibitivo?

**Como aplicar.**
- Pergunte de cada decisão: "se isso estiver errado em 6 meses, qual o custo de mudar?" Categorize em **barato / médio / caro / irreversível**.
- Decisões caras/irreversíveis ganham mais opções consideradas, mais escrutínio na ADR, e idealmente um **spike de validação** antes do aceite humano.
- Prefira contratos que admitam evolução: versionamento, tolerância a campos extras, defaults seguros, retrocompatibilidade.

**Exemplos.**
- **Reversível:** escolha de biblioteca de validação de input (trocável módulo a módulo).
- **Caro:** escolha de linguagem principal.
- **Quase irreversível na prática:** modelo de dados público para clientes (eles dependem do shape).

---

## 8. Observabilidade é requisito

> Sem logs estruturados, métricas e traces, você está pilotando às cegas.

**Por quê.** Não dá pra evoluir um sistema que você não consegue observar. Bug em produção sem log = horas perdidas. Pico de latência sem métrica = você não sabe que está acontecendo. Decisão arquitetural sem plano de observação é decisão tomada no escuro.

**Como aplicar.**
- Toda ADR que adiciona componente novo descreve: que logs ele emite, que métricas expõe, se participa de trace distribuído.
- Padrão mínimo (cruza com `quality-standards.md` do PO): health check, logs estruturados, métricas RED (Rate, Errors, Duration).
- Custo de observabilidade entra no orçamento da decisão, não como adendo.

---

## 9. Automatizável > documentável

> Se uma regra arquitetural pode virar teste ou linter, prefira isso a um aviso em doc.

**Por quê.** Documentação envelhece, é ignorada, vira lenda do projeto. Teste/linter falha o build — é impossível de ignorar. Arquitetura evoluível é a que se defende sozinha.

**Como aplicar.**
- Toda ADR que define uma regra ("módulo X não pode importar de Y", "toda API expõe OpenAPI", "todo endpoint tem health check") inclui na seção "Plano de verificação" como a regra vira verificação automática.
- Aceite documentação só quando a automação é impraticável.
- Linters arquiteturais (ArchUnit, deptrac, scripts próprios) são bem-vindos e cruzam diretamente com o princípio #5 (coesão/acoplamento).

---

## 10. Compatibilidade com TDD e E2E

> Toda escolha técnica precisa permitir escrever testes sem heroísmo.

**Por quê.** TDD e E2E são exigência herdada e ratificada pelo PO. Stack que torna teste difícil é stack ruim para este projeto, não importa quão moderna. Frameworks que tornam mock difícil, ambientes que sobem em minutos, dependências sem stub — tudo isso é fricção que mata a disciplina.

**Como aplicar.**
- ADR de stack ou framework descreve **como a estratégia de testes funciona naquela stack** (ferramentas, padrão de teste por camada, suporte a TDD).
- ADR que introduz integração externa descreve estratégia de stub/mock — cruza com princípio #6 (funcionamento local).
- Veto silencioso para tecnologias famosamente difíceis de testar.

---

## 11. Custo importa

> SaaS para MPE não tem orçamento de unicórnio. Decisão arquitetural com custo recorrente precisa justificar o gasto.

**Por quê.** Cada decisão tem três custos: financeiro (cloud, licenças), operacional (horas-pessoa para cuidar), e cognitivo (complexidade para o time). Subestimar qualquer um deles transforma o produto em buraco. Para MPE, margem é apertada — temos que ser excelentes em custo-eficiência.

**Como aplicar.**
- Toda ADR que adiciona custo recorrente lista uma estimativa de ordem de magnitude (R$/mês, horas/mês de operação).
- Soluções "grátis até X" são marcadas com o custo após X.
- Lembre que custo zero hoje pode ser custo alto amanhã (lock-in, dependência).

---

## 12. Restrições são informação

> O que você decide **não** fazer importa tanto quanto o que decide fazer.

**Por quê.** ADR que diz "escolhemos X" sem nomear o que não escolheu (e por quê) deixa portas abertas para confusão futura. Quando alguém propuser "vamos usar Y aqui também?", a ADR antiga deve poder responder: "considerei Y e rejeitei porque...".

**Como aplicar.**
- Toda ADR enumera pelo menos 1 alternativa rejeitada com motivo.
- Toda ADR diz explicitamente **o que está fora do escopo dela** ("esta decisão não cobre Z; isso é matéria de outra ADR").
- Quando um princípio é violado pela decisão escolhida, isso é nomeado como trade-off aceito — não escondido.

---

## Como aplicar os princípios em uma ADR

Cada opção considerada deve ser avaliada **explicitamente** contra os princípios relevantes. Use a estrutura no template (`templates/adr.md`):

```markdown
### Opção A — <nome>
- ✅ Simples é o belo (1): a solução tem 3 componentes contra 7 da alternativa.
- ✅ Monolito (2): cabe dentro do monólito atual sem quebrar processo.
- ⚠️ Postgres-first (3): introduz dependência em armazenamento externo — justificada porque [...]
- ✅ Frameworks opinativos (4): aproveita autenticação e admin do framework.
- ✅ Coesão/acoplamento (5): bordas claras, contrato explícito.
- ✅ Funcionamento local (6): mock disponível, sobe em Docker.
```

Quando um princípio é violado, isso aparece como `❌` ou `⚠️` na opção escolhida. **Reconhecer o trade-off é parte do contrato de honestidade** da decisão — nunca esconda violações.

## Quando um princípio precisa ser revisto

Princípios são duráveis, mas não eternos. Se ao longo do tempo a evidência mostra que um princípio está atrapalhando mais que ajudando, abra uma ADR especial (`type: meta`) propondo a revisão deste documento. O Alexandro aprova. Princípios são alterados em git como qualquer outra decisão — versão, motivo, contexto.

A barreira para alterar **os 6 princípios centrais (1–6)** deve ser bem alta: eles são a identidade técnica do DEFOnline.
