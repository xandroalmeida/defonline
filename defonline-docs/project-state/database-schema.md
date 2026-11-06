# DEFOnline — Schema do Banco de Dados

> **Documento vivo.** Reflete o schema **real** aplicado (fonte da verdade: banco + migrations),
> não a intenção. Regenerar após cada migration com a skill `analisar-banco` (`/analisar-banco`)
> ou manualmente (ver *Como atualizar* no fim).
>
> - **SGBD:** PostgreSQL 18 (ADR-001/002/003 — Postgres-first, fonte única de estado)
> - **Timezone do servidor:** `America/Sao_Paulo`
> - **Bancos:** `defonline` (app) · `defonline_test` (Pest/Dusk, isolado — [[project-banco-test-isolado]])
> - **Última sincronização:** 2026-11-05 (banco local `defonline`, após `migrate`) · **migrations aplicadas:** 46 · **tabelas:** 42
> - **Extensões:** `citext` (email case-insensitive), `pgcrypto`, `pg_trgm`, `pg_stat_statements` — instaladas no Postgres **local** pelo initdb; no ZalkVPS (ADR-009) não há `pg_stat_statements`

---

## Convenções (padrões transversais)

- **PK das tabelas de domínio:** `uuid` (UUID v7 — ordenável no tempo). Tabelas de métrica/framework usam `bigint` serial.
- **Soft delete:** `deleted_at` nas tabelas de domínio (`usuarios`, `empresas_analisadas`, `quiz_rascunhos`, `diagnosticos`).
- **Enums no banco:** validados por `CHECK` (não por tipo enum nativo) — ver cada tabela. Typo em app falha no INSERT.
- **Unique parcial** (`WHERE deleted_at IS NULL`): permite recriar registro após soft delete sem violar unicidade.
- **Índices por tipo de carga:** `btree` (lookup), `gin jsonb_path_ops` (busca em jsonb), `brin` (séries temporais append-only, ordenadas por tempo).
- **FKs:** `ON DELETE RESTRICT` por padrão; **exceção** `term_acceptances → usuarios` é `CASCADE`.
- **Timestamps:** `timestamp(0)`. Domínio/métrica em geral `with time zone`; framework e `usuarios`/`empresas_analisadas.created_at/updated_at` em `without time zone` (inconsistência histórica — atenção ao comparar).
- **Correlação cross-process:** `request_id` (UUID v7 do middleware) presente em métricas, eventos e audit.

---

## Mapa de relacionamentos (domínio)

```
usuarios (1) ──< empresas_analisadas (1) ──< diagnosticos
   │                     │
   │                     └──< quiz_rascunhos
   ├──< diagnosticos                 (FKs RESTRICT — não apaga usuário/empresa com filhos)
   ├──< quiz_rascunhos
   └──< term_acceptances  (CASCADE — apaga aceites junto com o usuário)
```

---

## Tabelas de domínio (regra de negócio)

### `usuarios` — contas (auth)
PK `id uuid`. Persona "Roberto". Soft delete + anonimização LGPD.

| coluna | tipo | notas |
|---|---|---|
| `id` | uuid | PK |
| `email` | **citext** | **UNIQUE** — case-insensitive |
| `cpf` | varchar(11) | **UNIQUE**, nullable |
| `nome` | varchar(255) | NOT NULL |
| `telefone` | varchar(255) | nullable |
| `senha_hash` | varchar(255) | NOT NULL (bcrypt) |
| `email_confirmed_at` | timestamptz | null = email não confirmado (bloqueia login) |
| `remember_token` | varchar(100) | |
| `anonimizado_at` | timestamp | LGPD — marca conta anonimizada |
| `permissoes_admin` | jsonb | nullable — permissões de admin do backoffice (STORY-067); null = não é admin, salvo `BACKOFFICE_EMAILS` |
| `created_at`/`updated_at`/`deleted_at` | timestamp | soft delete |

### `glossario_termos` — glossário do relatório (STORY-067)
PK `id uuid`. `termo` varchar(120) **UNIQUE**, `definicao` text, `ordem` integer (índice), timestamps. Mantido na tela de Glossário do backoffice.

### `recomendacoes` — matriz de recomendações (STORY-068, PDR-016)
PK `id uuid`. **UNIQUE** `(setor, indicador, farol)`. `setor` varchar(16), `indicador` varchar(40), `farol` varchar(10) (no `ncg_absoluto`, `positiva` ou `negativa` — PDR-018), `faixa` varchar(40) (só rótulo da tela), `texto` varchar(300), `editado_em` timestamptz (null = texto de origem; o maior valor do segmento define a versão da matriz), timestamps. 123 linhas, mantidas na tela de Recomendações do backoffice.

### `empresas_analisadas` — empresas do usuário
PK `id uuid`. FK `usuario_id → usuarios` (RESTRICT). **UNIQUE parcial** `(usuario_id, documento) WHERE deleted_at IS NULL`.

| coluna | tipo | notas |
|---|---|---|
| `tipo_documento` | varchar(8) | CHECK `cnpj\|cpf` |
| `documento` | varchar(14) | CNPJ/CPF sem máscara |
| `razao_social` | text | NOT NULL |
| `nome_fantasia` | text | nullable — valor da Receita em CNPJ; nome comercial digitado em CPF |
| `nome_fantasia_informado` | text | nullable — nome informado pelo usuário em CNPJ; prevalece sobre `nome_fantasia` na exibição (PDR-017) |
| `cnae` | varchar(7) | nullable |
| `municipio` | varchar(255) / `uf` char(2) | NOT NULL |
| `situacao_cadastral` | varchar(16) | CHECK `ativa\|inapta\|baixada\|suspensa\|nao_informada` (default `nao_informada`) |
| `fonte_enriquecimento` | varchar(8) | CHECK `manual\|rfb` (default `manual`) — origem RFB via cnpja (IDR-004/005) |
| `data_fundacao` | date / `enriquecido_at` timestamptz | |
| `ficha_rfb` | jsonb | nullable — ficha RFB completa (natureza, porte, capital, CNAEs secundários, endereço, sócios, `simples`/`simei`). Só escrita por consulta à Receita (PDR-010, STORY-063); null em CPF e em empresas anteriores à STORY-063 |
| `observacoes` | text | nullable — anotações livres do usuário (até 2.000 caracteres, validado na app) |
| `email` | varchar(255) | nullable — contato da empresa, informado pelo usuário (PDR-019) |
| `telefone` | varchar(11) | nullable — telefone/WhatsApp da empresa, só dígitos (PDR-019) |
| `personificado_por_id` | uuid | nullable, FK `usuarios` (RESTRICT) — admin da última gravação pelo "Logar como" (STORY-066); null se o próprio usuário gravou por último |

### `quiz_rascunhos` — rascunhos do quiz (23 campos, Anexo A)
PK `id uuid`. FKs `usuario_id`, `empresa_analisada_id` (RESTRICT). **UNIQUE parcial** `(usuario_id, empresa_analisada_id) WHERE deleted_at IS NULL`.

| coluna | tipo | notas |
|---|---|---|
| `quiz_payload` | jsonb | NOT NULL — respostas parciais |
| `ultimo_bloco_preenchido` | smallint | CHECK `1..4` |
| `expires_at` | timestamptz | NOT NULL — TTL do rascunho (índice `(usuario_id, expires_at)`) |
| `personificado_por_id` | uuid | nullable, FK `usuarios` — como em `empresas_analisadas` |

### `diagnosticos` — diagnóstico gerado (imutável)
PK `id uuid`. FKs `usuario_id`, `empresa_analisada_id` (RESTRICT). Índice `(empresa_analisada_id, gerado_em)`.

| coluna | tipo | notas |
|---|---|---|
| `motor_version` | varchar(16) | CHECK regex `^\d+.\d+.\d+$` (versão do motor de cálculo) |
| `matrix_version` | varchar(16) | CHECK regex `^(jan..dez)-\d{4}$` (matriz de recomendações mensal) |
| `setor` | varchar(16) | CHECK `industria\|comercio\|servicos` (MVP: só `industria`) |
| `quiz_payload` | jsonb | snapshot das respostas |
| `payload_hash` | varchar(64) | CHECK sha-256 hex — dedupe/idempotência |
| `indicadores_calculados` | jsonb | saída do motor |
| `resumo_executivo` | jsonb | destaques (STORY-040) |
| `gerado_em` | timestamptz | NOT NULL |
| `personificado_por_id` | uuid | nullable, FK `usuarios` — admin que gerou pelo "Logar como" |

### `term_acceptances` — aceites LGPD (append-only)
PK `id uuid`. FK `usuario_id → usuarios` **CASCADE**. Índice `(usuario_id, termo_tipo, aceito_at)`.

| coluna | tipo | notas |
|---|---|---|
| `termo_tipo` | varchar(32) | CHECK `termo_adesao\|lgpd\|marketing` |
| `aceito` | boolean | |
| `versao` / `conteudo_hash` | varchar | versão do termo + hash do conteúdo aceito |
| `ip` | **inet** / `user_agent` text | evidência de consentimento |
| `aceito_at` | timestamptz | default now |

---

## Cobrança, planos e DRM (EPIC-005 e EPIC-010)

> Gerado do catálogo do banco **local** em 2026-11-05 (o servidor não foi consultado). Regras de negócio: ADR-010, IDR-018 a 021 e as estórias 069 a 086. Valores em **centavos**; datas em UTC (exibição em Brasília, IDR-025). `invoices.provedor` e `provider_events.provedor` guardam `asaas`, `fake` ou `interno` (ciclo gratuito). `invoices.devolucao_motivo` marca o pagamento que entrou e não concedeu nada (lista "a devolver" do Financeiro).

Relacionamentos principais: `usuarios` 1—N `assinaturas`, `invoices`, `lotes_credito`, `consumos_analise`; `assinaturas` 1—N `invoices` e `trocas_de_cartao`; `invoices` 1—N `lotes_credito`; `cupons` 1—N `cupom_itens`, `cupom_usos`, `cupom_concessoes`; `provider_events` aponta para `invoices`.

### `planos`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `codigo` | varchar(60) | não |  |
| `nome` | varchar(60) | não |  |
| `cota_mensal` | integer | não |  |
| `preco_mensal_centavos` | integer | não |  |
| `preco_anual_centavos` | integer | não |  |
| `ativo` | boolean | não | `true` |
| `ordem` | integer | não |  |
| `alterado_por_usuario_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |

Índices:
- `IDX planos_ativo_ordem_index ON public.planos` (btree (ativo, ordem))
- `UNIQUE IDX planos_codigo_unique ON public.planos` (btree (codigo))

### `pacotes_credito`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `codigo` | varchar(60) | não |  |
| `nome` | varchar(60) | não |  |
| `quantidade_creditos` | integer | não |  |
| `preco_centavos` | integer | não |  |
| `validade_meses` | integer | não |  |
| `ativo` | boolean | não | `true` |
| `ordem` | integer | não |  |
| `alterado_por_usuario_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |

Índices:
- `IDX pacotes_credito_ativo_ordem_index ON public.pacotes_credito` (btree (ativo, ordem))
- `UNIQUE IDX pacotes_credito_codigo_unique ON public.pacotes_credito` (btree (codigo))

### `cobranca_config`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `ativa` | boolean | não | `false` |
| `alterado_por_usuario_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |

### `assinaturas`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `usuario_id` | uuid | não |  |
| `plano_id` | uuid | sim |  |
| `trial` | boolean | não | `false` |
| `status` | varchar(20) | não |  |
| `ciclo` | varchar(10) | sim |  |
| `data_inicio` | timestamp with time zone | não |  |
| `proximo_vencimento` | timestamp with time zone | não |  |
| `data_cancelamento` | timestamp with time zone | sim |  |
| `cota_snapshot` | integer | não |  |
| `cota_restante_ciclo` | integer | não |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |
| `aviso_d2_em` | timestamp with time zone | sim |  |
| `aviso_d0_em` | timestamp with time zone | sim |  |
| `provedor` | varchar(20) | sim |  |
| `id_externo` | varchar(120) | sim |  |
| `preco_snapshot_centavos` | integer | sim |  |
| `periodos_pagos` | smallint | não | `'1'::smallint` |
| `proxima_cota_em` | timestamp with time zone | sim |  |
| `cartao_bandeira` | varchar(20) | sim |  |
| `cartao_ultimos4` | varchar(4) | sim |  |
| `aviso_renovacao_periodo` | smallint | sim |  |
| `atraso_desde` | timestamp with time zone | sim |  |
| `atraso_cobranca_id` | varchar(120) | sim |  |
| `tentativas_cobranca` | smallint | não | `'0'::smallint` |
| `proxima_tentativa_em` | timestamp with time zone | sim |  |
| `atraso_aviso_em` | timestamp with time zone | sim |  |
| `encerramento_pendente_no_provedor` | boolean | não | `false` |
| `encerrada_em` | timestamp with time zone | sim |  |
| `meio_pagamento` | varchar(10) | não | `'cartao'::character varying` |
| `aviso_renovacao_pix_para` | timestamp with time zone | sim |  |
| `troca_plano_id` | uuid | sim |  |
| `troca_ciclo` | varchar(10) | sim |  |
| `troca_preco_centavos` | integer | sim |  |
| `troca_agendada_em` | timestamp with time zone | sim |  |
| `ajuste_provedor_pendente` | boolean | não | `false` |
| `cupom_id` | uuid | sim |  |
| `preco_cheio_centavos` | integer | sim |  |
| `desconto_centavos` | integer | não | `0` |
| `desconto_periodos` | smallint | sim |  |
| `desconto_aviso_em` | timestamp with time zone | sim |  |
| `valor_pendente_no_provedor` | boolean | não | `false` |
| `troca_preco_cheio_centavos` | integer | sim |  |
| `troca_desconto_centavos` | integer | sim |  |

FKs: `(usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT`; `(plano_id) REFERENCES planos(id) ON DELETE RESTRICT`; `(cupom_id) REFERENCES cupons(id) ON DELETE SET NULL`

CHECKs:
- `assinaturas_status_ck`: `CHECK (((status)::text = ANY ((ARRAY['ativa'::character varying, 'em_atraso'::character varying, 'cancelada'::character varying, 'expirada'::character varying])::text[])))`
- `assinaturas_cota_ck`: `CHECK (((cota_restante_ciclo >= 0) AND (cota_restante_ciclo <= cota_snapshot)))`
- `assinaturas_troca_ck`: `CHECK ((((troca_plano_id IS NULL) = (troca_ciclo IS NULL)) AND ((troca_plano_id IS NULL) = (troca_preco_centavos IS NULL))))`
- `assinaturas_meio_pagamento_ck`: `CHECK (((meio_pagamento)::text = ANY ((ARRAY['cartao'::character varying, 'pix'::character varying, 'nenhum'::character varying])::text[])))`

Índices:
- `IDX assinaturas_usuario_id_data_inicio_index ON public.assinaturas` (btree (usuario_id, data_inicio))
- `UNIQUE IDX assinaturas_vigente_unica ON public.assinaturas` (btree (usuario_id) WHERE ((status)::text = ANY ((ARRAY['ativa'::character varying, 'em_atraso'::character varying])::text[])))
- `UNIQUE IDX assinaturas_id_externo_unico ON public.assinaturas` (btree (provedor, id_externo) WHERE (id_externo IS NOT NULL))
- `IDX assinaturas_regua_idx ON public.assinaturas` (btree (proxima_tentativa_em) WHERE ((status)::text = 'em_atraso'::text))
- `IDX assinaturas_encerramento_pendente_idx ON public.assinaturas` (btree (id) WHERE encerramento_pendente_no_provedor)

### `invoices`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `usuario_id` | uuid | não |  |
| `tipo` | varchar(20) | não |  |
| `pacote_credito_id` | uuid | sim |  |
| `descricao` | varchar(120) | não |  |
| `quantidade_creditos` | integer | sim |  |
| `validade_meses` | integer | sim |  |
| `valor_centavos` | integer | não |  |
| `status` | varchar(20) | não |  |
| `meio` | varchar(10) | não |  |
| `provedor` | varchar(20) | não |  |
| `checkout_id_externo` | varchar(120) | sim |  |
| `checkout_url` | text | sim |  |
| `checkout_expira_em` | timestamp with time zone | sim |  |
| `id_externo` | varchar(120) | sim |  |
| `pago_em` | timestamp with time zone | sim |  |
| `motivo_encerramento` | varchar(40) | sim |  |
| `falha_motivo` | varchar(200) | sim |  |
| `aceite_termo_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |
| `plano_id` | uuid | sim |  |
| `ciclo` | varchar(10) | sim |  |
| `cota_snapshot` | integer | sim |  |
| `assinatura_id` | uuid | sim |  |
| `renovacao` | boolean | não | `false` |
| `cartao_bandeira` | varchar(20) | sim |  |
| `cartao_ultimos4` | varchar(4) | sim |  |
| `periodo_base` | smallint | sim |  |
| `reembolso_estado` | varchar(24) | sim |  |
| `reembolso_motivo` | text | sim |  |
| `reembolso_por_id` | uuid | sim |  |
| `reembolso_solicitado_em` | timestamp with time zone | sim |  |
| `reembolsado_em` | timestamp with time zone | sim |  |
| `devolucao_motivo` | varchar(30) | sim |  |
| `nf_emitida_em` | timestamp with time zone | sim |  |
| `nf_emitida_por_id` | uuid | sim |  |
| `nf_numero` | varchar(40) | sim |  |
| `cupom_id` | uuid | sim |  |
| `cupom_ciclos` | integer | sim |  |
| `preco_cheio_centavos` | integer | sim |  |
| `desconto_centavos` | integer | não | `0` |
| `troca_anterior` | jsonb | sim |  |
| `plano_origem_id` | uuid | sim |  |
| `ciclo_origem` | varchar(10) | sim |  |
| `assinatura_id_externo` | varchar(100) | sim |  |
| `reembolsado_parcial_centavos` | integer | sim |  |
| `valor_esperado_centavos` | integer | sim |  |
| `cota_extra_troca` | smallint | sim |  |

FKs: `(cupom_id) REFERENCES cupons(id) ON DELETE SET NULL`; `(usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT`

CHECKs:
- `invoices_devolucao_motivo_ck`: `CHECK (((devolucao_motivo IS NULL) OR ((devolucao_motivo)::text = ANY ((ARRAY['assinatura_cancelada'::character varying, 'assinatura_antiga'::character varying, 'assinatura_duplicada'::character varying, 'checkout_encerrado'::character varying, 'troca_sem_efeito'::character varying, 'renovacao_repetida'::character varying, 'checkout_cancelado'::character varying])::text[]))))`
- `invoices_reembolso_estado_ck`: `CHECK (((reembolso_estado IS NULL) OR ((reembolso_estado)::text = ANY ((ARRAY['em_andamento'::character varying, 'aguardando_autorizacao'::character varying, 'concluido'::character varying])::text[]))))`
- `invoices_status_ck`: `CHECK (((status)::text = ANY ((ARRAY['pendente'::character varying, 'paga'::character varying, 'vencida'::character varying, 'falhou'::character varying, 'estornada'::character varying, 'estornada_parcial'::character varying, 'contestada'::character varying, 'cancelada'::character varying])::text[])))`
- `invoices_tipo_ck`: `CHECK (((tipo)::text = ANY ((ARRAY['credito'::character varying, 'assinatura'::character varying, 'troca_de_plano'::character varying])::text[])))`
- `invoices_valor_ck`: `CHECK (((valor_centavos > 0) OR ((valor_centavos = 0) AND ((meio)::text = 'nenhum'::text) AND (desconto_centavos > 0))))`

Índices:
- `IDX invoices_usuario_id_created_at_index ON public.invoices` (btree (usuario_id, created_at))
- `UNIQUE IDX invoices_id_externo_unico ON public.invoices` (btree (provedor, id_externo) WHERE (id_externo IS NOT NULL))
- `UNIQUE IDX invoices_checkout_externo_unico ON public.invoices` (btree (provedor, checkout_id_externo) WHERE (checkout_id_externo IS NOT NULL))
- `IDX invoices_status_pago_em ON public.invoices` (btree (status, pago_em))
- `IDX invoices_cupom_id_status_index ON public.invoices` (btree (cupom_id, status))
- `IDX invoices_provedor_assinatura_externa ON public.invoices` (btree (provedor, assinatura_id_externo))

### `lotes_credito`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `usuario_id` | uuid | não |  |
| `invoice_id` | uuid | sim |  |
| `quantidade_comprada` | integer | não |  |
| `quantidade_restante` | integer | não |  |
| `status` | varchar(10) | não |  |
| `data_compra` | timestamp with time zone | não |  |
| `data_expiracao` | timestamp with time zone | não |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |

FKs: `(usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT`

CHECKs:
- `lotes_credito_quantidade_ck`: `CHECK (((quantidade_comprada > 0) AND (quantidade_restante >= 0) AND (quantidade_restante <= quantidade_comprada)))`
- `lotes_credito_status_ck`: `CHECK (((status)::text = ANY ((ARRAY['ativo'::character varying, 'esgotado'::character varying, 'expirado'::character varying, 'estornado'::character varying])::text[])))`

Índices:
- `IDX lotes_credito_usuario_id_status_data_expiracao_index ON public.lotes_credito` (btree (usuario_id, status, data_expiracao))
- `UNIQUE IDX lotes_credito_invoice_unico ON public.lotes_credito` (btree (invoice_id) WHERE (invoice_id IS NOT NULL))

### `consumos_analise`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `usuario_id` | uuid | não |  |
| `diagnostico_id` | uuid | sim |  |
| `empresa_analisada_id` | uuid | sim |  |
| `origem` | varchar(10) | não |  |
| `assinatura_id` | uuid | sim |  |
| `lote_credito_id` | uuid | sim |  |
| `quiz_id` | uuid | sim |  |
| `consumido_em` | timestamp with time zone | não |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |

FKs: `(usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT`; `(diagnostico_id) REFERENCES diagnosticos(id) ON DELETE SET NULL`; `(empresa_analisada_id) REFERENCES empresas_analisadas(id) ON DELETE SET NULL`; `(assinatura_id) REFERENCES assinaturas(id) ON DELETE RESTRICT`; `(lote_credito_id) REFERENCES lotes_credito(id) ON DELETE RESTRICT`

CHECKs:
- `consumos_analise_origem_ck`: `CHECK (((((origem)::text = 'cota'::text) AND (assinatura_id IS NOT NULL) AND (lote_credito_id IS NULL)) OR (((origem)::text = 'credito'::text) AND (lote_credito_id IS NOT NULL) AND (assinatura_id IS NULL))))`

Índices:
- `IDX consumos_analise_usuario_id_consumido_em_index ON public.consumos_analise` (btree (usuario_id, consumido_em))
- `UNIQUE IDX consumos_analise_diagnostico_id_unique ON public.consumos_analise` (btree (diagnostico_id))
- `UNIQUE IDX consumos_analise_quiz_id_unique ON public.consumos_analise` (btree (quiz_id))

### `provider_events`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `provedor` | varchar(20) | não |  |
| `evento_id` | varchar(200) | não |  |
| `tipo` | varchar(40) | não |  |
| `cobranca_id_externo` | varchar(120) | sim |  |
| `checkout_id_externo` | varchar(120) | sim |  |
| `referencia_externa` | varchar(120) | sim |  |
| `invoice_id` | uuid | sim |  |
| `payload` | jsonb | não |  |
| `status` | varchar(12) | não |  |
| `erro` | text | sim |  |
| `recebido_em` | timestamp with time zone | não |  |
| `processado_em` | timestamp with time zone | sim |  |
| `assinatura_id_externo` | varchar(120) | sim |  |
| `valor_estornado_centavos` | integer | sim |  |

CHECKs:
- `provider_events_status_ck`: `CHECK (((status)::text = ANY ((ARRAY['recebido'::character varying, 'processado'::character varying, 'ignorado'::character varying, 'erro'::character varying])::text[])))`

Índices:
- `UNIQUE IDX provider_events_provedor_evento_id_unique ON public.provider_events` (btree (provedor, evento_id))
- `IDX provider_events_status_recebido_em_index ON public.provider_events` (btree (status, recebido_em))

### `trocas_de_cartao`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `usuario_id` | uuid | não |  |
| `assinatura_id` | uuid | não |  |
| `provedor` | varchar(20) | não |  |
| `status` | varchar(12) | não |  |
| `checkout_id_externo` | varchar(120) | sim |  |
| `checkout_url` | text | sim |  |
| `checkout_expira_em` | timestamp with time zone | sim |  |
| `cobra_agora` | boolean | não | `false` |
| `antiga_id_externo` | varchar(120) | sim |  |
| `nova_id_externo` | varchar(120) | sim |  |
| `concluida_em` | timestamp with time zone | sim |  |
| `antiga_cancelada_em` | timestamp with time zone | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |
| `valor_centavos` | integer | sim |  |
| `ciclo` | varchar(10) | sim |  |

FKs: `(assinatura_id) REFERENCES assinaturas(id) ON DELETE RESTRICT`; `(usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT`

CHECKs:
- `trocas_de_cartao_status_ck`: `CHECK (((status)::text = ANY ((ARRAY['pendente'::character varying, 'concluida'::character varying, 'expirada'::character varying, 'cancelada'::character varying, 'falhou'::character varying])::text[])))`

Índices:
- `IDX trocas_de_cartao_assinatura_id_status_index ON public.trocas_de_cartao` (btree (assinatura_id, status))
- `UNIQUE IDX trocas_de_cartao_checkout_unico ON public.trocas_de_cartao` (btree (provedor, checkout_id_externo) WHERE (checkout_id_externo IS NOT NULL))

### `cupons`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `codigo` | varchar(40) | não |  |
| `tipo` | varchar(20) | não |  |
| `valor` | integer | não |  |
| `vigencia_inicio` | date | não |  |
| `vigencia_fim` | date | não |  |
| `limite_usos` | integer | sim |  |
| `ciclos_duracao` | integer | sim |  |
| `ativo` | boolean | não | `true` |
| `alterado_por_usuario_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |

FKs: `(alterado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL`

CHECKs:
- `cupons_tipo_check`: `CHECK (((tipo)::text = ANY ((ARRAY['percentual'::character varying, 'valor_fixo'::character varying])::text[])))`
- `cupons_valor_check`: `CHECK (valor >= 1 AND (tipo <> 'percentual' OR valor <= 1000))`. Percentual em décimos (205 = 20,5%), valor fixo em centavos.
- `cupons_vigencia_check`: `CHECK ((vigencia_fim >= vigencia_inicio))`

Índices:
- `UNIQUE IDX cupons_codigo_unique ON public.cupons` (btree (codigo))

### `cupom_itens`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `cupom_id` | uuid | não |  |
| `plano_id` | uuid | sim |  |
| `ciclo` | varchar(10) | sim |  |
| `pacote_credito_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |

FKs: `(cupom_id) REFERENCES cupons(id) ON DELETE CASCADE`; `(plano_id) REFERENCES planos(id) ON DELETE RESTRICT`; `(pacote_credito_id) REFERENCES pacotes_credito(id) ON DELETE RESTRICT`

CHECKs:
- `cupom_itens_forma_check`: `CHECK ((((plano_id IS NOT NULL) AND ((ciclo)::text = ANY ((ARRAY['mensal'::character varying, 'anual'::character varying])::text[])) AND (pacote_credito_id IS NULL)) OR ((plano_id IS NULL) AND (ciclo IS NULL) AND (pacote_credito_id IS NOT NULL))))`

Índices:
- `IDX cupom_itens_cupom_id_index ON public.cupom_itens` (btree (cupom_id))
- `UNIQUE IDX cupom_itens_plano_unico ON public.cupom_itens` (btree (cupom_id, plano_id, ciclo) WHERE (plano_id IS NOT NULL))
- `UNIQUE IDX cupom_itens_pacote_unico ON public.cupom_itens` (btree (cupom_id, pacote_credito_id) WHERE (pacote_credito_id IS NOT NULL))

### `cupom_usos`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `cupom_id` | uuid | não |  |
| `usuario_id` | uuid | não |  |
| `desconto_centavos` | integer | não | `0` |
| `ciclos_restantes` | integer | sim |  |
| `usado_em` | timestamp with time zone | não |  |
| `invoice_id` | uuid | sim |  |

FKs: `(cupom_id) REFERENCES cupons(id) ON DELETE CASCADE`; `(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE`; `(invoice_id) REFERENCES invoices(id) ON DELETE SET NULL`

Índices:
- `UNIQUE IDX cupom_usos_cupom_id_usuario_id_unique ON public.cupom_usos` (btree (cupom_id, usuario_id))
- `IDX cupom_usos_usuario_id_index ON public.cupom_usos` (btree (usuario_id))
- `UNIQUE IDX cupom_usos_invoice_unico ON public.cupom_usos` (btree (invoice_id) WHERE (invoice_id IS NOT NULL))

### `cupom_concessoes`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `cupom_id` | uuid | não |  |
| `usuario_id` | uuid | não |  |
| `ciclos_restantes` | integer | sim |  |
| `concedida_por_usuario_id` | uuid | sim |  |
| `concedida_em` | timestamp with time zone | não |  |

FKs: `(cupom_id) REFERENCES cupons(id) ON DELETE CASCADE`; `(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE`; `(concedida_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL`

Índices:
- `UNIQUE IDX cupom_concessoes_cupom_id_usuario_id_unique ON public.cupom_concessoes` (btree (cupom_id, usuario_id))
- `IDX cupom_concessoes_usuario_id_index ON public.cupom_concessoes` (btree (usuario_id))

### `trial_config`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `ativo` | boolean | não | `true` |
| `dias` | integer | não |  |
| `analises` | integer | não |  |
| `alterado_por_usuario_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |

### `trial_ativacoes`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `usuario_id` | uuid | sim |  |
| `cpf_hash` | character(64) | não |  |
| `ativado_em` | timestamp with time zone | não |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |

FKs: `(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL`

Índices:
- `UNIQUE IDX trial_ativacoes_cpf_hash_unique ON public.trial_ativacoes` (btree (cpf_hash))

### `trial_empresas`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `cnpj_hash` | character(64) | não |  |
| `usuario_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |

FKs: `(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL`

Índices:
- `UNIQUE IDX trial_empresas_cnpj_hash_unique ON public.trial_empresas` (btree (cnpj_hash))

### `drm_lancamentos`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `usuario_id` | uuid | não |  |
| `empresa_analisada_id` | uuid | não |  |
| `competencia` | date | não |  |
| `versao` | integer | não |  |
| `substituido_por_id` | uuid | sim |  |
| `setor` | varchar(16) | não |  |
| `lancamento_payload` | jsonb | não |  |
| `payload_hash` | varchar(64) | não |  |
| `demonstracao` | jsonb | não |  |
| `alertas_aceitos` | jsonb | não | `'[]'::jsonb` |
| `motor_drm_version` | varchar(16) | não |  |
| `gerado_em` | timestamp with time zone | não |  |
| `personificado_por_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |
| `deleted_at` | timestamp with time zone | sim |  |

FKs: `(usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT`; `(empresa_analisada_id) REFERENCES empresas_analisadas(id) ON DELETE RESTRICT`; `(personificado_por_id) REFERENCES usuarios(id) ON DELETE RESTRICT`; `(substituido_por_id) REFERENCES drm_lancamentos(id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED`

CHECKs:
- `drm_lancamentos_dia01_check`: `CHECK ((EXTRACT(day FROM competencia) = (1)::numeric))`
- `drm_lancamentos_versao_check`: `CHECK ((versao >= 1))`
- `drm_lancamentos_setor_check`: `CHECK (((setor)::text = ANY ((ARRAY['industria'::character varying, 'comercio'::character varying, 'servicos'::character varying])::text[])))`
- `drm_lancamentos_hash_check`: `CHECK (((payload_hash)::text ~ '^[0-9a-f]{64}$'::text))`
- `drm_lancamentos_motor_check`: `CHECK (((motor_drm_version)::text ~ '^\d+\.\d+\.\d+$'::text))`
- `drm_lancamentos_demonstracao_check`: `CHECK (COALESCE(((jsonb_typeof(((demonstracao -> 'linhas'::text) -> 'fora'::text)) = 'array'::text) AND (jsonb_typeof(((demonstracao -> 'linhas'::text) -> 'dentro'::text)) = 'array'::text) AND ((demonstracao ->> 'motor_drm_version'::text) = (motor_drm_version)::text)), false))`

Índices:
- `IDX drm_lancamentos_usuario_idx ON public.drm_lancamentos` (btree (usuario_id))
- `IDX drm_lancamentos_motor_idx ON public.drm_lancamentos` (btree (motor_drm_version))
- `UNIQUE IDX drm_lancamentos_vigente_unique ON public.drm_lancamentos` (btree (empresa_analisada_id, competencia) WHERE ((substituido_por_id IS NULL) AND (deleted_at IS NULL)))
- `UNIQUE IDX drm_lancamentos_versao_unique ON public.drm_lancamentos` (btree (empresa_analisada_id, competencia, versao) WHERE (deleted_at IS NULL))

### `drm_rascunhos`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `usuario_id` | uuid | não |  |
| `empresa_analisada_id` | uuid | não |  |
| `competencia` | date | não |  |
| `origem` | varchar(16) | não | `'inicial'::character varying` |
| `base_versao` | integer | sim |  |
| `rascunho_payload` | jsonb | não |  |
| `expires_at` | timestamp with time zone | não |  |
| `personificado_por_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |
| `deleted_at` | timestamp with time zone | sim |  |

FKs: `(usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT`; `(empresa_analisada_id) REFERENCES empresas_analisadas(id) ON DELETE RESTRICT`; `(personificado_por_id) REFERENCES usuarios(id) ON DELETE RESTRICT`

CHECKs:
- `drm_rascunhos_base_versao_check`: `CHECK ((((origem)::text = 'retificacao'::text) = (base_versao IS NOT NULL)))`
- `drm_rascunhos_dia01_check`: `CHECK ((EXTRACT(day FROM competencia) = (1)::numeric))`
- `drm_rascunhos_origem_check`: `CHECK (((origem)::text = ANY ((ARRAY['inicial'::character varying, 'retificacao'::character varying])::text[])))`

Índices:
- `IDX drm_rascunhos_usuario_expires_idx ON public.drm_rascunhos` (btree (usuario_id, expires_at))
- `UNIQUE IDX drm_rascunhos_usuario_empresa_competencia_unique ON public.drm_rascunhos` (btree (usuario_id, empresa_analisada_id, competencia) WHERE (deleted_at IS NULL))

### `drm_preferencias`
PK `id`.

| coluna | tipo | nulo | default |
|---|---|---|---|
| `id` | uuid | não |  |
| `usuario_id` | uuid | não |  |
| `empresa_analisada_id` | uuid | não |  |
| `posicao_prolabore` | varchar(8) | não | `'fora'::character varying` |
| `modo_custo` | varchar(16) | não | `'detalhado'::character varying` |
| `modo_vendas` | varchar(16) | não | `'detalhado'::character varying` |
| `modo_administrativas` | varchar(16) | não | `'detalhado'::character varying` |
| `modo_financeiras` | varchar(16) | não | `'detalhado'::character varying` |
| `personificado_por_id` | uuid | sim |  |
| `created_at` | timestamp with time zone | sim |  |
| `updated_at` | timestamp with time zone | sim |  |

FKs: `(usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT`; `(empresa_analisada_id) REFERENCES empresas_analisadas(id) ON DELETE RESTRICT`; `(personificado_por_id) REFERENCES usuarios(id) ON DELETE RESTRICT`

CHECKs:
- `drm_preferencias_prolabore_check`: `CHECK (((posicao_prolabore)::text = ANY ((ARRAY['fora'::character varying, 'dentro'::character varying])::text[])))`
- `drm_preferencias_modo_custo_check`: `CHECK (((modo_custo)::text = ANY ((ARRAY['agregado'::character varying, 'detalhado'::character varying])::text[])))`
- `drm_preferencias_modo_vendas_check`: `CHECK (((modo_vendas)::text = ANY ((ARRAY['agregado'::character varying, 'detalhado'::character varying])::text[])))`
- `drm_preferencias_modo_administrativas_check`: `CHECK (((modo_administrativas)::text = ANY ((ARRAY['agregado'::character varying, 'detalhado'::character varying])::text[])))`
- `drm_preferencias_modo_financeiras_check`: `CHECK (((modo_financeiras)::text = ANY ((ARRAY['agregado'::character varying, 'detalhado'::character varying])::text[])))`

Índices:
- `UNIQUE IDX drm_preferencias_empresa_analisada_id_unique ON public.drm_preferencias` (btree (empresa_analisada_id))

---

## Observabilidade / métricas (ADR-004 — Postgres-first)

### `evento_produto` — eventos de produto (append-only, north star)
PK `id bigint`. `evento_id uuid` **UNIQUE** (idempotência). Emitido **só** via `EventLogger::emit()` (PII proibida → `PiiEmEventoException`).
- `nome_evento`, `ocorrido_em` (default now), `usuario_id`, `empresa_id`, `propriedades jsonb` (default `{}`), `request_id`.
- Índices: `(nome_evento, ocorrido_em)`, `nome_evento`, `usuario_id`, `empresa_id`, `request_id`, **GIN** `propriedades jsonb_path_ops`.
- `chave_idempotencia varchar(160) NULL` + índice único parcial `(nome_evento, chave_idempotencia) WHERE chave_idempotencia IS NOT NULL` (STORY-056): `EventLogger::emitOnce()` grava o fato no máximo uma vez. Eventos sem chave (`emit()`) não são afetados.
- Canônicos entregues: `quiz_iniciado`, `diagnostico_concluido` (STORY-035); do DRM (STORY-056, `App\Observabilidade\EventosDrm`): `drm_lancamento_iniciado`, `drm_bloco_concluido`, `drm_rascunho_retomado`, `drm_regra_bloqueante_acionada`, `drm_lancamento_concluido`, `drm_lancamento_retificado`, `drm_alerta_aceito`, `drm_demonstracao_visualizada`, `drm_exportado`; da cobrança (STORY-086, `App\Observabilidade\EventosCobranca`, IDR-028): `trial_ativado`, `trial_encerrado`, `checkout_iniciado`, `contratacao_concluida`, `contratacao_abandonada`, `cupom_aplicado`, `cancelamento`, `cancelamento_revertido`, `bloqueio_sem_saldo`, `pagamento_confirmado`, `pagamento_recusado` (esquemas em `EventosCobranca::ESQUEMA`; contas em `MetricasDeCobranca` / `php artisan cobranca:metricas`).
- Evento gerado no "Logar como" traz `propriedades.personificacao` com `admin_id` e `sessao_id` (STORY-066). Para excluir da métrica de produto: `NOT propriedades ? 'personificacao'`.

### `business_metrics` / `request_metrics` / `job_metrics` — séries temporais
Append-only, PK `bigint`, `request_id` de correlação, **índice BRIN** em `inserido_em` (barato p/ dados ordenados no tempo).
- `business_metrics`: `tipo`, `sucesso bool`, `duracao_ms`, `meta jsonb` (`meta.personificacao` na consulta à Receita feita pelo "Logar como").
- `request_metrics`: `path`, `method`, `status`, `duration_ms`, `usuario_id`, `empresa_id`, `personificado_por_id` (admin no "Logar como").
- `job_metrics`: `job_class`, `queue`, `status`, `duration_ms`, `tentativas`.

### `audit_logs` — trilha de auditoria
PK `uuid`. `actor_type/actor_id`, `usuario_id`, `action`, `subject_type/subject_id`, `before/after/context jsonb`. Índices em `action`, `request_id`, `usuario_id`.

### `scheduler_heartbeats` / `worker_heartbeats` — liveness (ADR-002)
- `scheduler_heartbeats`: `task` UNIQUE, `ultimo_em`.
- `worker_heartbeats`: `(hostname, queue)` UNIQUE, `ultimo_em`.

---

## Tabelas de framework (Laravel — infra, não editar à mão)

| tabela | função |
|---|---|
| `sessions` | sessão (driver `database`) — `user_id`, `last_activity` |
| `cache` / `cache_locks` | cache (driver `database`) |
| `jobs` / `job_batches` / `failed_jobs` | filas (`pdf,emails,lgpd,default`) |
| `features` | feature flags (Laravel Pennant) — UNIQUE `(name, scope)` |
| `password_reset_tokens` | reset de senha (PK `email`) |
| `migrations` | controle de migrations |

---

## Como atualizar este documento

Fonte da verdade = **banco aplicado** (não os arquivos de migration, que podem estar à frente do deploy).

**Via skill (recomendado):** `/analisar-banco` → peça "regenerar a doc do schema".

**Manual — dump do schema do servidor (ZalkVPS, ADR-009):**
```bash
ssh defonline-prod@<ZALK_HOST> \
  'cd /srv/zalk/projects/defonline-prod && . ./.env.zalk && pg_dump "$DATABASE_URL" --schema-only --no-owner --no-privileges'
```
**Manual — local (dev):**
```bash
docker compose exec -T db pg_dump -U postgres -d defonline --schema-only --no-owner --no-privileges
```
Depois reescreva as seções afetadas e atualize o cabeçalho (*Última sincronização*, contagem de migrations/tabelas).
