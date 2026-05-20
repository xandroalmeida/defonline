# Disciplina de banco de dados

O Arquiteto definiu "PostgreSQL é nossa primeira opção" (princípio arquitetural #3). Isso só funciona se o Programador **exercer bem o Postgres** — caso contrário a decisão arquitetural vira ADR letra-morta e o time acaba reclamando "tinha que ser outra coisa".

Esta reference cobre os hábitos diários: como escrever queries que escalam, como projetar migrations seguras, e como evitar os anti-padrões clássicos.

## A mentalidade

- **Banco é estado real e durável.** Bug em código você corrige no próximo deploy; bug em migração você convive por anos.
- **Volume cresce.** Código que funciona com 100 linhas em desenvolvimento pode quebrar com 100k em produção. Pense em volume **sempre**.
- **Postgres é poderoso.** Antes de inventar, pergunte: "isso o Postgres já faz?".
- **Transações importam.** Operação que envolve múltiplas escritas relacionadas precisa estar em transação — ou você terá inconsistência em algum ponto.

## ORM vs SQL bruto

Frameworks opinativos vêm com ORM (Django ORM, ActiveRecord, Ecto, Eloquent, Prisma, TypeORM, SQLAlchemy etc). Use ORM por padrão — você ganha:

- Parameterização automática (defesa contra SQL injection, veja `security-discipline.md`).
- Migrações geradas a partir do modelo.
- Validações no nível de modelo.
- Helpers úteis (paginação, relacionamentos, eager loading).

**SQL bruto é justificado quando:**

- Query envolve operação que o ORM expressa mal (window functions complexas, CTEs encadeados, lateral joins, full-text search, JSON paths sofisticados).
- Performance precisa controle fino que o ORM não dá.
- Operação de manutenção pontual (backfill controlado, correção de dado).

**Quando usar SQL bruto:**

- Sempre com **bind parameters** (`?`, `$1`, `:nome`). **Nunca concatene** string.
- Comente o porquê (por que o ORM não atende).
- Encapsule em função/módulo claro; não espalhe SQL bruto pelo código.

## N+1 query — o anti-padrão clássico

Você tem uma lista de N itens, e para cada item você dispara uma query adicional para buscar relacionado. Resultado: 1 + N queries em vez de 1 ou 2.

Exemplo conceitual:

```python
# ❌ N+1
empresas = Empresa.objects.all()                   # 1 query
for e in empresas:
    print(e.diagnosticos.count())                  # N queries, uma por empresa
# total: 1 + N

# ✅ Com prefetch / eager loading
empresas = Empresa.objects.prefetch_related('diagnosticos')  # 2 queries no total
for e in empresas:
    print(len(e.diagnosticos.all()))               # nenhuma query adicional
```

Cada ORM tem seu jeito (`prefetch_related`/`select_related` em Django, `includes` em Rails, `with` em Laravel, `preload` em Ecto, `include` em Prisma, `joinedload` em SQLAlchemy).

**Como detectar:**

- Em **desenvolvimento**, ligue logging de queries SQL. Se ver 50 queries em uma view de listagem, há N+1.
- Em **CI**, use ferramenta que detecta N+1 nos testes (ex: Bullet em Rails, django-silk em Django, etc).
- Em **produção**, observe latência crescendo com volume — sintoma típico de N+1 que escapou.

N+1 é uma das primeiras coisas a olhar em qualquer code review.

## Índices — quando criar, quando não

**Crie índice quando:**

- Coluna é usada frequentemente em `WHERE`, `JOIN`, `ORDER BY`.
- Coluna tem alta cardinalidade (muitos valores distintos) — índice em coluna booleana raramente ajuda.
- Foreign key (FK) — Postgres não cria automaticamente; índice em FK é quase sempre desejável.
- Coluna de busca textual com `tsvector` (índice GIN).
- Coluna JSON que vai ser queryada (índice GIN em `jsonb`).

**NÃO crie índice quando:**

- Tabela é pequena (algumas centenas de linhas) — full scan é tão rápido quanto.
- Coluna é raramente filtrada.
- Tabela tem write rate alto e leitura é tolerável sem índice — índice penaliza INSERT/UPDATE/DELETE.
- Você está "preparando para o futuro" — adicione quando a query for real, não imaginada.

**Composite index importa:** índice em `(a, b)` ajuda buscas por `a` ou `(a, b)` — mas **não** por `b` sozinho. Ordem das colunas no índice importa.

**EXPLAIN ANALYZE é seu amigo:** quando uma query está lenta, rode `EXPLAIN ANALYZE` antes de palpitar sobre causa. O plano te diz se está fazendo full scan, se está usando índice, se está fazendo nested loop caro.

## Transações

Operação que envolve múltiplas escritas relacionadas: **transação**. ORMs frequentemente abrem transação por request HTTP, mas verifique a configuração do seu projeto.

```python
# Conceitual — exato depende do framework/ORM
with transaction.atomic():
    empresa = Empresa.create(...)
    diagnostico = Diagnostico.create(empresa=empresa, ...)
    Pagamento.create(empresa=empresa, ...)
# se qualquer um falhar, tudo é desfeito
```

**Heurísticas:**

- **Curtas.** Transações longas seguram locks e bloqueiam outras escritas. Faça o trabalho dentro da transação ser estritamente necessário ali.
- **Sem I/O externo dentro.** Não chame API HTTP dentro de transação — se a API demorar 30s, a transação demora 30s. Faça a chamada antes ou depois, ou empurre para job assíncrono.
- **Isolation level:** Postgres default é READ COMMITTED. Para a maioria dos casos, OK. Quando precisar consistência mais forte (concorrência crítica), considere SERIALIZABLE ou explicit locking — mas **registre em IDR** com motivo.

## Locking e concorrência

Operação concorrente em mesmo recurso pode causar **race condition**. Padrões úteis:

- **Optimistic locking** (versioning): tabela tem coluna `version`; update inclui `WHERE version = X`; se não atualizou linha, alguém alterou enquanto você processava. Ideal para baixa contenção.
- **Pessimistic locking** (`SELECT ... FOR UPDATE`): trava a linha até a transação acabar. Ideal para alta contenção. Use com cuidado — pode causar deadlock.
- **SKIP LOCKED** para queues: padrão para job queue em Postgres (princípio arquitetural #3). Worker pega "próximo job não locked" sem bloquear outros workers.

Para a maioria das estórias, transação normal resolve. Quando você sentir cheiro de race condition, **pare e pense** — não é a hora de inventar.

## Migrações

Migração mexe em produção. Erro aqui é caro. Hábitos:

### Sempre

- **Escrita em código** (não cliquete manual no banco).
- **Versionada** com a feature que precisa dela.
- **Reversível**: escreva o `down` mesmo achando improvável usar. Eventualmente você vai precisar.
- **Idempotente quando possível**: se rodar duas vezes, segunda não quebra.
- **Testada em ambiente similar a produção antes do PR de produção** — não só local.

### Cuidados com volume

Em tabela grande (milhões de linhas), operações inocentes ficam perigosas:

- **`ADD COLUMN NOT NULL` com default**: pode reescrever tabela inteira (em versões antigas de Postgres) — verifique o comportamento da sua versão. Geralmente seguro em Postgres ≥ 11.
- **`ADD COLUMN` com default não-volátil**: seguro em Postgres moderno.
- **`ADD COLUMN` seguido de backfill**: separe em duas migrações:
  1. Adiciona coluna `NULL`.
  2. Backfill em **batches** (`UPDATE ... WHERE id BETWEEN x AND y` ou job em background) — não em UPDATE único.
  3. Eventualmente adiciona `NOT NULL` quando estiver populada.
- **Adicionar índice em tabela grande**: use `CREATE INDEX CONCURRENTLY` (Postgres) — leva mais tempo mas não trava escrita.
- **Renomear coluna**: quebra app que ainda usa o nome antigo. Padrão "expand-contract": adicionar nova → fazer app escrever em ambas → migrar leitura → parar de escrever na antiga → remover antiga. Várias migrações, mas zero downtime.
- **DROP COLUMN/TABLE**: praticamente irreversível em produção (rollback de migração não traz dado de volta). Pense duas vezes.

### Backfill de dado existente

Quando a migração precisa preencher coluna nova com dado calculado:

- **Não faça em uma transação gigante**. Quebre em batches.
- **Faça em background job** quando o volume justifica.
- **Tolere parada**: o backfill deve poder ser pausado e retomado.
- **Verifique resultado** com query de consistência antes de remover passos antigos.

## Paginação

Listagem sem paginação = bomba de tempo. Mesmo em "tabela que não vai crescer".

**Offset pagination** (simples mas ruim em volume):
```sql
SELECT ... LIMIT 20 OFFSET 5000
```
Postgres precisa **ler e descartar** as 5000 primeiras. Em página fundo, fica lento.

**Cursor pagination** (escalável):
```sql
SELECT ... WHERE id > $cursor ORDER BY id LIMIT 20
```
Salta direto para o cursor — performance constante.

Para listagens administrativas ou de baixo volume, offset OK. Para listagens de usuário em volume crescente, cursor.

## Soft delete vs hard delete

- **Soft delete** (coluna `deleted_at`): registro fica no banco, é marcado como deletado. Vantagem: rastreabilidade, recuperação, auditoria. Desvantagem: todo query precisa filtrar deleted, índices crescem, complexidade.
- **Hard delete**: registro é removido. Vantagem: simplicidade, performance. Desvantagem: dado perdido.

Para domínio DEFOnline (dados financeiros sob LGPD), **default = soft delete** para entidades de negócio. Excluir dado de usuário (direito do titular LGPD) provavelmente exige hard delete em algumas tabelas e mascaramento em outras (audit log). Decisão caso a caso, em alinhamento com PO/legal.

## JSON / jsonb

Postgres `jsonb` é poderoso. Quando usar:

- ✅ Conteúdo de schema flexível (configuração, payload de evento externo, dados que mudam por integração).
- ✅ Cache de dado calculado complexo.
- ❌ **Não para campo que tem schema estável** — modele como coluna normal.
- ❌ **Não como "fugir" de modelar o domínio** — colunas dedicadas continuam melhor para regras de negócio claras.

`jsonb` (não `json`) tem índices GIN; pode ser indexado por chave; queries com operadores `->`, `->>`, `@>`. Funciona.

## Full-text search

Antes de adicionar ElasticSearch (que viola princípio arquitetural #3 sem prova robusta), olhe Postgres:

- `tsvector` + `tsquery` + índice GIN.
- `pg_trgm` para busca por similaridade.

Para 95% dos casos de busca no DEFOnline, isso resolve.

## Operações pesadas

- **Não rode COUNT(*) em tabela enorme** sem condição — pode ser lento. Considere `EXPLAIN` ou aproximação via `pg_stat`.
- **DELETE em massa**: faça em batches, com `LIMIT`, em loop com pausa — evita lock contention.
- **ANALYZE** após backfill grande para que o planner tenha estatística atualizada.

## Connection pooling

Sua aplicação não abre conexão direto no Postgres a cada request — usa pool. Postgres tem limite duro de conexões; sem pool, esgota rápido.

- Frameworks opinativos têm pool integrado.
- Para alto volume, considere pgbouncer (mas isso é decisão arquitetural, não sua).
- **Não vaze conexão**: garantir que cada conexão pega volta para o pool (frameworks fazem isso por você se você usar API normal).

## Backup e migration awareness

Você não opera o backup, mas escreve código compatível com ele:

- Não invente esquemas de "guardar arquivo no banco" (BYTEA gigante) sem alinhamento com Arquiteto — afeta backup.
- Sequences/IDs gerados pelo banco têm comportamento específico em restore — confie no que o ORM e o backup do projeto fazem.

## Resumo operacional

Antes de marcar pronta uma estória que mexe em banco:

- [ ] Queries verificadas — sem N+1, sem concatenação de SQL.
- [ ] Índices criados onde necessário (e **não** onde desnecessário).
- [ ] EXPLAIN ANALYZE rodado em queries críticas em ambiente representativo.
- [ ] Migrações reversíveis e testadas em ambiente equivalente a produção.
- [ ] Migrações em volume: backfill em batches, índice com `CONCURRENTLY` se aplicável.
- [ ] Transações curtas, sem I/O externo dentro.
- [ ] Paginação onde lista cresce.
- [ ] Soft delete onde domínio exige rastro.
