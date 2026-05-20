# Disciplina de observabilidade no código

O Arquiteto definiu observabilidade como requisito (princípio arquitetural #8). É **você que faz acontecer** — escrevendo os logs, instrumentando métricas, propagando contexto de trace.

Esta reference cobre os hábitos do dia a dia: quando logar, o que NÃO logar, como estruturar log, como adicionar métrica útil, e como pensar em observabilidade desde o código que você escreve.

## A mentalidade

- **Observabilidade é decisão de design**, não enfeite no fim. Pense nela enquanto desenha a função.
- **Você está deixando rastro para o seu eu de amanhã** (e para o time de plantão às 3 da manhã).
- **Log demais é tão ruim quanto log de menos.** Log demais ofusca o que importa; log de menos esconde o problema.
- **Métrica > log** quando o objetivo é monitorar comportamento agregado. Use cada um para o que serve.

## Logs estruturados

Log em texto livre (`print("user logged in: " + name)`) é OK no terminal de desenvolvimento. Em produção, **logs precisam ser estruturados** — JSON ou formato similar — para que sistemas de busca e alertas possam queryar.

Padrão mínimo de log estruturado:

```json
{
  "timestamp": "2026-05-20T14:32:08.123Z",
  "level": "info",
  "message": "diagnostico calculado",
  "service": "diagnostico-service",
  "request_id": "abc-123",
  "user_id": "u-987",
  "empresa_id": "e-456",
  "duration_ms": 142
}
```

**O que sempre vai junto:**

- `timestamp` (ISO 8601 com timezone).
- `level` (`debug`/`info`/`warn`/`error`).
- `message` curto e fixo (não interpolar — usar campos).
- **Contexto correlacionável**: `request_id`, `trace_id`, `user_id`, IDs de domínio relevantes.

Use o **logger do framework** — não invente. Frameworks opinativos têm logger estruturado por padrão (ou via plugin).

## Níveis de log — quando usar cada

- **DEBUG**: ruído útil para investigação local — variáveis, estado intermediário. **Não vai para produção**, ou vai filtrado.
- **INFO**: marcos esperados do fluxo normal — "request recebida", "diagnóstico calculado", "job concluído". Granularidade que permite reconstruir o que aconteceu **sem ser barulho**.
- **WARN**: situação anormal mas não-falha — retry sendo aplicado, fallback acionado, deprecação detectada, dado inesperado mas tolerável. **Vale acompanhar**.
- **ERROR**: falha que **impede** a operação — exceção não esperada, banco indisponível, integração externa falhou após retries. Erro vira candidato a alerta.

**Anti-padrões:**

- INFO para tudo (vira lixo).
- ERROR para erro de validação esperado de usuário (não é erro do sistema — é WARN ou INFO, conforme caso).
- Falta de WARN — "ou tá bom, ou explodiu" perde nuances valiosas.

## Quando logar

**Sempre logue:**

- **Entrada e saída de operações relevantes** (request HTTP, processamento de job, integração externa) — com `duration_ms`.
- **Falhas e exceções** (com stack trace estruturado, não no message).
- **Eventos de negócio significativos** ("empresa cadastrada", "diagnóstico calculado", "pagamento confirmado").
- **Decisões importantes do sistema** ("fallback acionado porque X", "retry 3/5 para chamada Y").

**Não logue:**

- Linha-a-linha do código (DEBUG em produção).
- Estado dentro de loop apertado (quantidade de log explode).
- Coisa que **não vai ser olhada** — log sem objetivo é só ruído pago em disco e parsing.

**Pergunta de bolso:** "se eu olhar esse log às 3 da manhã, ele me ajuda?". Se não, ele não devia existir.

## O que NUNCA logar (cruzamento com `security-discipline.md`)

Repetindo aqui porque é crítico:

- **Senhas** (mesmo "hash" ou "mascarado" — não loga e ponto).
- **Tokens** (JWT, API key, Bearer, OAuth, refresh).
- **CPF/CNPJ pleno** sem necessidade (mascare últimos dígitos).
- **Dados de cartão** (PAN, CVV).
- **Conteúdo de e-mail/SMS** transacional.
- **Body inteiro de request/response** em endpoints com dado sensível.
- **Strings de conexão de banco** ou URLs com credencial embutida.
- **Dados pessoais sensíveis** (saúde, religião, opinião política — LGPD).

**Use o mecanismo do framework** para mascarar campos sensíveis automaticamente (lista de campos que viram `[REDACTED]` no log). Princípio arquitetural #9: automatizável > documentável.

## Como escrever uma mensagem de log boa

- **Mensagem fixa**, contexto em campos. Não interpole valores no `message`.

```python
# ❌ ruim — mensagem variável, difícil de queryar
log.info(f"User {user_id} logged in from {ip}")

# ✅ bom — mensagem fixa, contexto em campos
log.info("user_login", extra={"user_id": user_id, "ip": ip})
```

- **Substantivo do evento** (`user_login`, `diagnostico_calculado`), não verbo no passado.
- **Curto** — detalhe vai nos campos.
- **Consistente** em vocabulário — não misture `user_id` e `userId` no projeto.

## Métricas — RED + alguns extras

Para cada serviço/endpoint relevante:

- **R**ate — quantas requisições por segundo (`http_requests_total` contador).
- **E**rrors — quantos retornaram erro (`http_requests_errors_total` contador).
- **D**uration — quanto demoram (histograma — p50, p95, p99).

Para operações específicas (jobs, integrações, cálculos pesados):

- Mesmo padrão RED em escopo dela.
- **Estado de filas**: `jobs_pending`, `jobs_in_progress`, `jobs_failed`.
- **Negócio**: contadores de eventos relevantes (`empresas_cadastradas_total`, `diagnosticos_calculados_total`).

**Instrumente desde o início** — não "depois quando precisar". Quando precisar, geralmente é durante incidente.

## Tracing distribuído

Quando uma request passa por múltiplos serviços/jobs (mesmo dentro de monolito, em job assíncrono ou chamada interna), propague um **trace_id**:

- Gerar no entry point (request HTTP).
- Propagar em headers de chamadas internas.
- Incluir em todo log da operação.
- Incluir em jobs enfileirados.

Isso te permite "ver toda a operação" — desde request original até resultado final — mesmo passando por workers em background. Sem isso, debug é arqueologia.

Framework opinativo geralmente tem middleware de tracing pronto. Use.

## Health checks

Todo serviço/app expõe pelo menos:

- `/health` (liveness): "estou rodando?". Resposta rápida, sem dependência.
- `/ready` (readiness): "consigo atender request?". Verifica banco, dependências críticas. Mais lenta.

Não use o mesmo endpoint para os dois — propósitos diferentes.

## Adicionando log durante debug

Comportamento aceitável durante investigação:

- Adicionar log temporário (`DEBUG` ou marcado com TODO) para entender estado.
- **Antes do PR final**: remover esses logs temporários ou promover os úteis para `INFO`/`WARN` permanente.
- **PR não deve incluir** `console.log("aqui chegou")` esquecido (parte do `done-checklist`).

Use log como **ferramenta** de investigação, não muleta. Bugs precisam ser **entendidos**, não só "logados até descobrir".

## Logs e performance

Log síncrono em loop apertado vira gargalo. Cuidados:

- **Logger assíncrono** quando o framework oferece — escrita em batch, não bloqueia caminho crítico.
- **Não serialize objetos enormes** em log de informação — só os campos relevantes.
- **Rate limit log de erro** quando uma falha está em cascata (1000 erros idênticos por segundo enchem disco e não ajudam).

## Auditoria vs log

Distinção importante (também em `security-discipline.md`):

- **Log de aplicação**: ferramenta de observabilidade, retenção curta, propósito técnico (debug, alerta).
- **Audit log**: registro de operações sensíveis para fim jurídico/operacional, retenção longa, propósito de prova.

São **dois sistemas separados**. Não use seu log de aplicação como audit log — ele tem retenção curta e pode ser inadvertidamente filtrado.

## Resumo operacional

Para cada estória que entrega comportamento novo no sistema:

- [ ] Pontos relevantes do código emitem log estruturado com contexto suficiente.
- [ ] Nenhum dado sensível ou PII passou para log (use mecanismo de mascaramento do framework).
- [ ] Operação que importa tem métrica RED associada (rate, erro, duração).
- [ ] Trace ID propaga entre chamadas relacionadas (sync ou async).
- [ ] Logs temporários de debug foram removidos antes do PR.
- [ ] Mensagens de log usam substantivo de evento (`user_login`) e contexto em campos, não interpolação no message.
- [ ] Nenhum log síncrono dentro de loop apertado.

> **Boa observabilidade é a diferença entre "o sistema parou e ninguém sabe por quê" e "vi exatamente o que aconteceu em 5 minutos".** Vale o investimento.
