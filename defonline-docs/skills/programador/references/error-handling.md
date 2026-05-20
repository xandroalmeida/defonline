# Disciplina de tratamento de erros

Tratamento de erro é onde código fica realmente sênior ou realmente amador. Junior trata erro como exceção rara que não vai acontecer; sênior **espera que tudo dê errado em algum momento** e desenha pra isso.

Esta reference cobre como pensar e escrever tratamento de erro no DEFOnline.

## A mentalidade

- **Erros vão acontecer.** Banco fica indisponível, rede falha, fornecedor externo retorna 500, usuário manda input inesperado. **Você desenha pra isso.**
- **Falhe cedo, falhe alto.** Input inválido → erro imediato, claro. Não comportamento estranho mais tarde.
- **Distinção fundamental:** erro esperado (validação, regra de negócio) **≠** bug (exceção não prevista, estado impossível).
- **Mensagens contam.** Erro mudo é incidente. Erro com contexto é problema resolvido.
- **Recuperar quando faz sentido**, falhar quando faz sentido — não confunda.

## A distinção fundamental: erro esperado vs bug

| | Erro esperado | Bug / exceção não prevista |
|---|---|---|
| Exemplo | CNPJ inválido, conflito de versão otimista, fornecedor 503 | NPE, divisão por zero, índice fora do array, "estado impossível" |
| Mensagem para usuário | Específica e acionável | Genérica ("erro inesperado — código X") |
| Resposta HTTP | 4xx (cliente) ou específico (409, 422) | 500 |
| Log level | INFO ou WARN | ERROR + alerta |
| Trata em código? | Sim — fluxo explícito | Captura genericamente; investiga depois |
| Testa? | Sim — caso de teste para cada | Sim — para garantir que **não** acontece |

**Anti-padrão:** misturar os dois — fazer `try/catch` genérico envolvendo lógica de negócio e tratar tudo como "erro de processamento". Você esconde o bug **e** descarta a oportunidade de tratar o erro esperado bem.

## Falhar cedo

Input inválido deve falhar **no primeiro ponto** onde é detectado, não a 4 chamadas de função depois.

```python
# ❌ tarde demais
def calcular_liquidez(empresa):
    # ... processa ...
    # ... mais processamento ...
    return empresa.ativo_circulante / empresa.passivo_circulante
    # KABOOM, ZeroDivisionError, sem contexto

# ✅ falha cedo
def calcular_liquidez(empresa):
    if empresa.passivo_circulante == 0:
        raise ValueError(
            f"Não dá pra calcular liquidez com passivo zero (empresa {empresa.id})"
        )
    return empresa.ativo_circulante / empresa.passivo_circulante
```

Falhar cedo poupa investigação. O erro aponta para a **causa**, não para o sintoma.

## Mensagens de erro: usuário vs interno

**Mensagem para usuário** é diferente de **mensagem para log/dev**.

| | Usuário | Log / interno |
|---|---|---|
| Audiência | Não-técnica, eventualmente leigo | Dev / ops / você de amanhã |
| Objetivo | Explicar o que houve, dizer o que fazer | Capturar contexto técnico para diagnóstico |
| Tom | Educado, neutro, claro | Direto, técnico |
| Conteúdo sensível | Nunca expor stack trace, query, ID interno | Pode ter detalhe (mas sem segredo) |

**Bom:**

```
[Usuário]: "CNPJ inválido — esperamos 14 dígitos numéricos. Verifique e tente novamente."
[Log]:     "validation_error" + {"field": "cnpj", "value_redacted": "12.345...XX", "reason": "length_mismatch"}
```

**Ruim:**

```
[Usuário]: "ERROR: ValidationError at line 42 of validators.py: assertion failed (len(cnpj) == 14)"
[Log]:     "deu erro"
```

## Idempotência

Operação que **pode ser repetida** sem causar efeito duplicado. Crítico para:

- **Pagamentos**: usuário clicou "pagar" duas vezes → cobrança **única**.
- **Webhooks de fornecedor**: fornecedor reenviou o mesmo evento (estratégia de "at-least-once" deles) → você processa **uma vez**.
- **Jobs assíncronos**: worker faz retry → resultado não duplica.

**Como conseguir:**

- **Idempotency key**: identificador único da operação (no frontend ou em header). Backend verifica se já processou — se sim, devolve o resultado anterior.
- **Estado verificável**: antes de fazer a ação, verifique se já foi feita (`if pedido.status == 'pago': return`).
- **Operação naturalmente idempotente**: "setar X = Y" é idempotente; "incrementar X" não é.

Operações sensíveis no DEFOnline (pagamento, envio de e-mail, notificação) **devem ser idempotentes** — eventualmente vão ser repetidas, garantido.

## Retry

Quando uma operação externa falha **temporariamente** (rede, 503, timeout), tentar de novo às vezes resolve. Quando vale e como fazer:

**Quando faz sentido retry:**

- Erro **transitório** (network, 503, 504, timeout).
- Operação **idempotente** (ou ela já é, ou você implementou idempotency).
- Tempo total tolerado pelo caller é maior que tempo de retry.

**Quando NÃO faz sentido:**

- Erro **permanente** (400, 401, 403, 404, 422 — o problema é a request, não o servidor).
- Operação não-idempotente sem proteção.
- Janela total já se esgotou (não retry depois de 60s para algo que tem timeout de 30s).

**Como fazer:**

- **Exponential backoff**: espera 1s, 2s, 4s, 8s entre tentativas — não martele o serviço já frágil.
- **Jitter** (aleatoriedade): se 1000 clientes tiveram timeout no mesmo segundo, sem jitter eles vão retryar no mesmo segundo — cascata. Adicione `± 30%` aleatório.
- **Max attempts**: limite (3-5 tentativas típicas). Não retry para sempre.
- **Log com nível certo**: cada retry vira WARN com contagem; falha final vira ERROR.

**Pseudocódigo:**

```
for attempt in 1..max_attempts:
    try:
        return call_external(...)
    except TransientError as e:
        if attempt == max_attempts:
            log.error("external_call_failed_finally", ...)
            raise
        delay = min(MAX_DELAY, base * 2**(attempt-1)) * (1 + random.uniform(-0.3, 0.3))
        log.warn("external_call_retry", attempt=attempt, delay=delay)
        sleep(delay)
```

Use lib que já implementa isso bem, no idioma da stack (princípio "framework opinativo").

## Circuit breaker (quando aplicável)

Para integração externa que pode estar **completamente fora** por minutos/horas, retry não resolve — só piora. Circuit breaker:

- Conta falhas recentes.
- Se passar de threshold, **abre o circuito**: chamadas seguintes falham imediatamente, sem tentar.
- Depois de cooldown, **half-open**: tenta uma chamada; se OK, fecha; se falha, abre de novo.

Vale quando você tem dependência externa pesada (gateway de pagamento, integração crítica). **Não vale para tudo** — adicionar circuit breaker em integração simples é over-engineering. Use quando o problema for real.

## Engulir exceção — quase sempre errado

```python
# ❌ anti-padrão clássico
try:
    fazer_algo()
except Exception:
    pass  # 🚨
```

Você acabou de:
- Esconder o motivo de uma falha futura.
- Quebrar a habilidade de monitorar o sistema.
- Criar um bug "intermitente" para alguém investigar daqui a 6 meses.

**Quando engulir é OK** (raríssimo):

- Operação **opcional** explicitamente — "se falhar enviar notificação push, tudo bem, o usuário pega depois". Mas **logue como WARN**, não silencie.
- Cleanup em finally que não deve mascarar o erro original.

Mesmo assim: **comente o porquê** no código, ou criou IDR. Engulir silencioso é red flag.

## Erro do cliente (4xx) vs erro do servidor (5xx)

- **4xx**: a request está errada. Cliente precisa corrigir. Não emite alerta a menos que volume seja anormal (indica abuso ou bug do FE).
- **5xx**: o servidor está com problema. Cliente não pode fazer nada. **Emite alerta**.

Usar 4xx onde deveria ser 5xx **mascara** problemas reais. Usar 5xx onde deveria ser 4xx **dispara alertas** sobre input ruim de usuário.

Códigos específicos a saber:

- **400** Bad Request — input malformado.
- **401** Unauthorized — falta autenticação.
- **403** Forbidden — autenticado mas não tem permissão.
- **404** Not Found — recurso não existe.
- **409** Conflict — conflito de estado (ex: já cadastrado).
- **422** Unprocessable Entity — payload parseado mas semanticamente inválido (alternativa popular a 400 para validação).
- **429** Too Many Requests — rate limit.

## Hierarquia de exceções

Use a hierarquia da linguagem/framework. **Não invente classe de exceção para problemas mundanos.**

- Validação → use a exceção de validação do framework (`ValidationError` em Django/DRF, `ArgumentError` em Ruby, etc).
- Recurso não encontrado → exceção padrão do framework (`Http404`, `NotFoundException`, etc).
- Operação não autorizada → exceção padrão (`PermissionDenied`, `ForbiddenException`).

**Quando criar exceção customizada:**

- Erro **de domínio** com tratamento específico (`DiagnosticoNaoCalculavelError` porque dados insuficientes — vai ter tratamento dedicado).
- Erro de **integração específica** (`GatewayPagamentoTimeoutError` — quer distinguir de outros timeouts).

Exceção customizada herda da hierarquia padrão. Não invente raiz nova.

## Asserções vs exceções

- **Exceção:** algo que **pode** acontecer com input válido — tratado.
- **Assertion:** algo que **nunca deveria** acontecer — se acontecer, é bug.

```python
# Exceção — pode acontecer
if cnpj_invalido:
    raise ValidationError(...)

# Assertion — não deveria acontecer; se acontece, código está bugado
assert empresa.id is not None, "empresa.id virou None inesperadamente"
```

Asserções servem para documentar invariantes do código — "esse ponto do fluxo X é sempre verdade". Quando falham, você descobriu bug. Em produção, asserções podem ou não estar habilitadas (depende da linguagem) — não use assertion para validar input externo.

## Erros assíncronos (jobs, workers)

Job que falha:

- **Errors transitórios**: deixe o mecanismo de retry do job runner cuidar (com backoff + jitter).
- **Erros permanentes**: marque o job como `failed`, não retry — vai gastar recursos.
- **Após N falhas**: envie para **dead letter queue** (ou tabela equivalente em Postgres) — humano investiga depois.

## Resumo operacional

Antes de marcar uma estória pronta que envolva tratamento de erro:

- [ ] Validação de input falha cedo, com mensagem específica.
- [ ] Erro esperado é distinguido de bug — status code, log level, mensagem certa para cada.
- [ ] Mensagens para usuário são acionáveis; mensagens internas têm contexto suficiente.
- [ ] Operação sensível (pagamento, envio, etc) é idempotente.
- [ ] Chamadas externas têm timeout + retry com backoff/jitter quando aplicável.
- [ ] Nenhuma exceção é engolida silenciosamente.
- [ ] Códigos HTTP usados corretamente (4xx vs 5xx).
- [ ] Hierarquia de exceções respeitada — não inventou raiz.
- [ ] Casos inválidos / exceções estão **testados** (não só caminho feliz — veja `testing-discipline.md`).
