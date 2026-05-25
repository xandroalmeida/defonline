---
idr_id: IDR-004
slug: rfb-provider-abstraction-cnpja-receitaws
title: Abstração de provedor RFB com suporte a cnpja.com e receitaws.com.br, parametrizada em config e com rate-limit por provedor
status: accepted
decided_at: 2026-05-23
decided_by: PO (Alexandro)
owner_agent: PO (Alexandro)
related_story: STORY-015
related_idrs: []
related_adrs: ["ADR-001", "ADR-004"]
supersedes: null
superseded_by: null
created_at: 2026-05-23
updated_at: 2026-05-23
revision_history:
  - at: 2026-05-23
    by: PO (Alexandro)
    note: "PO confirmou que produção usará os mesmos dois provedores (cnpja + receitaws). Default `mock` em produção deixa de ser política permanente — passa a ser apenas estado inicial até a STORY-018 entregar e o Arquiteto definir qual será o primário."
---

# IDR-004 — Provedor da API RFB: abstração com `cnpja.com` e `receitaws.com.br`, seleção via config e rate-limit por provedor

## Contexto

A NRF §3.1 mantinha o item **"Provedor da API CNPJ"** como `[A DEFINIR]` desde a v2.0 da especificação. A STORY-015 entrega o **caminho técnico completo** (interface `RfbCnpjClient`, DTO, fallback transparente, métricas, alerta >5% erro) implementado contra um mock determinístico, deixando explícito que "quando o provedor real for escolhido, basta trocar a configuração e registrar um IDR".

Este IDR registra essa escolha — não com um único provedor, mas com **dois provedores reais suportados em paralelo via configuração**, mantendo a abstração já desenhada na STORY-015. A motivação:

1. **Não amarrar o produto a um único fornecedor externo.** A API da RFB é uma dependência crítica (Espec §3.3 Passo 2) e cada provedor de revenda tem suas próprias políticas de preço, limites e disponibilidade. Ter dois caminhos prontos reduz risco de fornecedor único (R5 do parecer CLAUDE) sem custo de desenvolvimento adicional relevante — a interface já existe.
2. **Permitir A/B de custo×qualidade em homologação.** A operação pode rodar uma janela de comparação real entre `cnpja` e `receitaws` antes de fechar contrato em qualquer um deles.
3. **Permitir failover manual sem deploy.** Se o provedor primário tiver incidente, basta trocar `RFB_PROVIDER` em produção e reiniciar o worker/web — sem alteração de código.
4. **Rate-limit precisa ser por provedor.** Os dois serviços têm **políticas e custos diferentes por janela de tempo**: o que é seguro em um é abusivo no outro. Hard-coding de RPM (requests por minuto) no cliente é receita para banimento ou conta zerada. A configuração precisa expor o teto de cada provedor independentemente.

## Decisão

**Adotar abstração `App\Services\Rfb\RfbCnpjClient` (já definida pela STORY-015) com três implementações concretas registradas:**

1. `MockRfbCnpjClient` — default em `local` e `testing`; dados sintéticos determinísticos; cenários de erro acionáveis por CNPJs específicos (entregue pela STORY-015).
2. `CnpjaRfbCnpjClient` — implementação real contra `https://api.cnpja.com/` (entregue pela STORY-018).
3. `ReceitawsRfbCnpjClient` — implementação real contra `https://receitaws.com.br/v1/cnpj/` (entregue pela STORY-018).

**Seleção do provedor:** via `config/services.php` → bloco `rfb`, chave `provider`, com valores aceitos `'mock' | 'cnpja' | 'receitaws'`. O `AppServiceProvider` faz o `bind` condicional de `RfbCnpjClient::class` baseado em `config('services.rfb.provider')`.

**Rate-limit por provedor:** cada provedor tem seu próprio teto de RPM (requests por minuto) configurável. O cliente HTTP respeita o limite via `Illuminate\Support\Facades\RateLimiter` (chave `rfb:provider:{provider}`), com fila bloqueante de no máximo o `timeout` configurado. Se o slot não estiver disponível dentro do timeout, a consulta é tratada como falha — o fallback transparente para preenchimento manual (NRF §3.1) absorve o impacto na UX.

**Defaults iniciais propostos (revisáveis sem novo IDR — apenas merge no `.env` de cada ambiente):**

| Provedor | RPM default | Origem do default | Observação |
|---|---|---|---|
| `mock` | sem limite | n/a | usado só em local/testing |
| `cnpja` | `3` | plano gratuito público (Open API, sem token) | aumentar conforme contratação de plano pago |
| `receitaws` | `3` | plano gratuito público | aumentar conforme contratação de plano pago |

Os valores acima são **conservadores e baseados nos planos gratuitos públicos** — qualquer plano pago contratado deve sobrescrever via env.

## Forma do bloco de configuração

```php
// config/services.php
'rfb' => [
    'provider' => env('RFB_PROVIDER', 'mock'),    // mock | cnpja | receitaws
    'timeout'  => env('RFB_TIMEOUT', 5),          // segundos — limite da requisição HTTP (NRF §3.1, STORY-015 CA-2)
    'cache_ttl' => env('RFB_CACHE_TTL', 300),     // segundos — cache de respostas por hash do CNPJ (STORY-015 CA-6)

    'providers' => [
        'cnpja' => [
            'base_url'   => env('RFB_CNPJA_BASE_URL', 'https://api.cnpja.com'),
            'api_key'    => env('RFB_CNPJA_API_KEY'),
            'rate_limit_per_minute' => (int) env('RFB_CNPJA_RPM', 3),
        ],
        'receitaws' => [
            'base_url'   => env('RFB_RECEITAWS_BASE_URL', 'https://receitaws.com.br/v1'),
            'api_key'    => env('RFB_RECEITAWS_API_KEY'),
            'rate_limit_per_minute' => (int) env('RFB_RECEITAWS_RPM', 3),
        ],
    ],
],
```

Variáveis correspondentes em `.env.example`:

```
RFB_PROVIDER=mock
RFB_TIMEOUT=5
RFB_CACHE_TTL=300

RFB_CNPJA_BASE_URL=https://api.cnpja.com
RFB_CNPJA_API_KEY=
RFB_CNPJA_RPM=3

RFB_RECEITAWS_BASE_URL=https://receitaws.com.br/v1
RFB_RECEITAWS_API_KEY=
RFB_RECEITAWS_RPM=3
```

## Consequências

- A NRF §3.1 deixa de marcar **"Provedor da API"** como `[A DEFINIR]` e passa a registrar que o provedor é **selecionável entre `cnpja` e `receitaws`** via configuração, com rate-limit por provedor. O item desaparece também do checklist §10.2.
- A STORY-015 segue **inalterada no escopo de implementação** (mock + caminho técnico): ela já estava arquitetada para esta decisão. A nota de rodapé "provedor real `[A DEFINIR]`" é atualizada para apontar este IDR e a STORY-018.
- **STORY-018 é criada** para entregar `CnpjaRfbCnpjClient` + `ReceitawsRfbCnpjClient` + `bind` condicional + RateLimiter por provedor + testes de integração contratuais (gravados com VCR/`Http::fake()`).
- Métricas existentes em `business_metrics` (`tipo: 'rfb_consulta'`) ganham dimensão `provider` (`'mock' | 'cnpja' | 'receitaws'`) para permitir A/B observacional sem mudar schema (já é tabela chave-valor).
- Alerta de >5% de erro em janela de 10 min (NRF §3.1, STORY-015 CA-5) passa a ser por provedor — quando dois forem ativados em paralelo em algum experimento futuro, isso deixa explícito qual provedor falhou.

## Quando reabrir esta decisão

- Se um terceiro provedor (ex.: SintegraWS, Casa dos Dados, Nubank API) entrar na lista de candidatos.
- Se a estratégia de **fallback automático entre provedores** (em vez de fallback transparente para preenchimento manual) for adotada — hoje a NRF §3.1 prescreve fallback manual; mudança exigiria PDR (impacto de UX) e nova story.
- Se a parametrização de RPM precisar de granularidade adicional (ex.: limites diferentes para sandbox vs. produção; teto diário além de por minuto).
- Se algum dos dois provedores for descontinuado ou tiver mudança de TOS que comprometa o uso.

## Itens não decididos por este IDR

- **Qual dos dois provedores será o primário em produção.** PO confirmou em 2026-05-23 que **produção usará os dois mesmos provedores** (`cnpja` e `receitaws`); o que falta é a definição operacional de qual entra como `RFB_PROVIDER` no `.env` de produção quando a STORY-018 for entregue. Decisão do Arquiteto, com base nos critérios da NRF §3.1 (custo, confiabilidade, limites de chamadas) e nos dados de A/B em homologação. Registrar como IDR separado (`IDR-005-rfb-provider-default-producao` ou similar) quando definido.
- **Plano contratado em cada provedor.** Fora do escopo técnico — fica com a operação (EBC Parcerias) registrar em planilha de fornecedores.

## Referências

- NRF v2.0 §3.1 — requisitos da integração com RFB (fallback, monitoramento, provedor `[A DEFINIR]`).
- Especificação funcional v2.0 §3.3 Passo 2 e §5 (cadastro de Empresa Analisada com pré-preenchimento via RFB).
- STORY-015 — caminho técnico (mock + fallback) que já contempla a abstração.
- STORY-018 — ativação dos provedores reais (criada por este IDR).
- ADR-001 — stack Laravel (justifica uso de `config/services.php`, `AppServiceProvider`, `RateLimiter`).
- ADR-004 — observabilidade (`business_metrics`, alerta Telegram).
- Documentação pública dos provedores: `https://cnpja.com/` e `https://receitaws.com.br/`.
