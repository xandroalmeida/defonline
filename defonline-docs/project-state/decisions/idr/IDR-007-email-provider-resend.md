---
idr_id: IDR-007
slug: email-provider-resend
title: Provedor de email transacional em homologação e produção — Resend
status: accepted
decided_at: 2026-05-24
decided_by: PO (Alexandro)
owner_agent: programador (claude-opus-4-7)
related_story: STORY-018
related_adrs: ["ADR-002", "ADR-005"]
related_idrs: ["IDR-004"]
supersedes: null
superseded_by: null
created_at: 2026-05-24
updated_at: 2026-05-24
---

# IDR-007 — Provedor de email transacional: Resend (homologação e produção)

## Contexto

`inventories/homolog/group_vars/all/vars.yml:34` documentava o estado vigente como `mail_mailer: log` com a nota _"homol: log apenas; sem provedor SMTP real ainda"_. A sprint SPRINT-2026-W22 listava na sua tabela de riscos: _"SMTP de homologação rejeita emails de teste por reputação / DNS de domínio"_ (probabilidade média, impacto médio), e o NRF reconhecia que a STORY-013 (confirmação de email) dependia de "validar no início que SMTP de homol entrega para ao menos 1 domínio de teste".

Esse débito ficou aberto até hoje, e veio à tona em 2026-05-24 quando o PO precisou inspecionar emails enviados por homologação durante o smoke manual da STORY-018 e descobriu que (a) Mailpit não está em homol (por desenho — vide comentário no `docker-compose.production.yml.j2:2`), (b) `mail_mailer: log` joga os emails no log estruturado da aplicação, sem entrega real.

Decisão de provedor pendente desde então.

## Decisão

> **Adotamos Resend como provedor de email transacional para homologação e produção.**

Em `mail_mailer=resend` via `config/mail.php` (driver `'transport' => 'resend'` já presente no scaffold do Laravel 13). Pacote `resend/resend-php` adicionado como dependência transversal do projeto. Chave de API gerenciada por Ansible Vault no mesmo padrão das demais secrets (mecanismo de ADR-005 §7.7; padrão consolidado em IDR-006).

## Por quê

- **Free tier suficiente para WAVE-2026-01.** 3.000 emails/mês e 100/dia no plano gratuito do Resend cobrem com folga o volume MVP previsto pelo PDR-001 (beta fechado por convite, ≤ centenas de cadastros). Sem custo até a curva de Roberto provar tração.
- **DKIM automático ao verificar domínio.** Resend gera os registros DNS (SPF/DKIM/DMARC) prontos para colar no provedor de DNS — reduz o custo de bring-up que historicamente atrasa SMTP em projetos pequenos. Mitigação direta do risco "SMTP rejeita por reputação/DNS" da sprint.
- **Driver nativo do Laravel.** `config/mail.php` já tinha `'resend' => ['transport' => 'resend']`, e o `config/services.php` já tinha `'resend' => ['key' => env('RESEND_API_KEY')]`. Apenas o pacote `resend/resend-php` precisa ser adicionado — sem custo de configuração adicional ou IoC personalizado.
- **API simples e bem documentada.** Curva de aprendizado curta para o time atual (1 pessoa + agentes). Diferente do SES (que exige sandbox approval + IAM), Resend é "criar conta, verificar domínio, gerar key".
- **Reversibilidade.** Trocar provedor depois é mudar `MAIL_MAILER` no `.env` + adicionar driver alternativo. Sem mudança de código de negócio (Laravel Mail é abstração).

## Alternativas consideradas

- **Postmark** — descartado para esta janela. Reputação premium para transacional (mais confiável que Resend em casos extremos), mas free tier de apenas 100 emails/mês não cobre testes contínuos. Quando o volume crescer e a entrega 100% importar, vale reavaliar.
- **Amazon SES** — descartado por custo de bring-up. $0.10/1000 emails é imbatível em escala, mas o caminho até sair do sandbox + configurar IAM + verificar domínio é desproporcional ao volume atual. Reavaliar quando o volume justificar (>50k/mês).
- **Mailpit em homol atrás do Caddy com basic-auth** — descartado em 2026-05-24 pelo PO. Resolve só o problema de "ver emails", não o de "entregar email real" (que ainda viria como `mail_mailer=log`). Além disso, expor ferramenta administrativa em homologação tinha o trade-off de ferir o princípio do STORY-009 CA-10 (regressão arquitetural contra `pgadmin/phppgadmin/adminer/dbgate` em homol/prod) — espírito do mesmo princípio se aplica.
- **Manter `mail_mailer=log` + leitura via SSH** — descartado por fricção operacional. PO precisa testar fluxos que dependem de email (confirmação, futuro reset de senha) sem virar dependente de SSH para cada teste.

## Consequências

### Para outros agentes
- **Padrão default para email em homol e prod**: `MAIL_MAILER=resend` no `.env` renderizado pelo Ansible. Outros mailers (`smtp`, `log`, `array`) continuam disponíveis para uso pontual em desenvolvimento ou em testes (`tests/.env.testing` usa `MAIL_MAILER=array`).
- **`MAIL_FROM_ADDRESS=noreply@{{ app_domain }}`** continua o template (já estava em `env.j2`). Quando outro subdomínio for usado para outra rota de email (suporte, marketing), abrir IDR específico — não inventar From distinto sem registro.
- **Toda nova mailable do projeto** assume Resend como provedor. Não introduzir SDK alternativo (Symfony Mailer direto, swiftmailer, etc.) sem nova IDR.

### Para o projeto
- **+1 dependência transversal:** `resend/resend-php`. Lib pequena (~200KB), mantida pela empresa Resend, sem dependências circulares com Symfony Mailer (usa HTTP API, não SMTP).
- **+1 secret no vault de cada ambiente real:** `vault_resend_api_key`. Mesmo template de outras chaves (Ansible Vault cifrado, render no `.env` chmod 600 — ADR-005 §7.7, IDR-006).
- **Requisito operacional novo (pré-deploy):** domínio verificado no painel Resend (SPF/DKIM/DMARC publicados no DNS) **antes** do primeiro deploy com `mail_mailer=resend`. Sem isso, Resend recusa o envio.
- **Custo recorrente: zero até 3.000/mês.** Quando ultrapassar, plano pago começa em US$ 20/mês para 50k emails — registrar IDR de revisão financeira se isso acontecer dentro da WAVE-2026-01.

### Trade-offs aceitos
- **Vendor lock-in leve:** abstração do Laravel Mail mitiga, mas se o Resend mudar TOS ou subir preço, troca exige novo provedor verificado (DNS) + nova chave. Aceitável para MVP.
- **Domain reputation cresce só depois do uso real:** primeiros envios podem cair em spam até o domínio "aquecer". Mitigação: começar com volume baixo (homol), monitorar bounce rate no painel Resend.
- **Free tier tem rate-limit (100/dia, 2/s).** Suficiente para MVP, mas se um burst de testes E2E disparar muitos emails em paralelo, alguns podem ser throttled. Aceito; rodar testes E2E sequenciais ou usar `MAIL_MAILER=array` em CI.

## Como verificar

- **Pest test arquitetural** (sugestão para próxima estória ou hotfix): em `staging`/`production`, asserta `config('mail.default') === 'resend'` e `config('services.resend.key') !== null && !== ''`.
- **Smoke pós-deploy** do release-homolog ganha um passo `POST /test-email` (atrás de auth + feature flag para não vazar) ou um envio manual via tinker (`php artisan tinker --execute="Mail::raw('smoke', fn(\$m) => \$m->to(env('MAIL_SMOKE_TARGET')));"`) que confirma entrega real.
- **Sinais de revisão (quando reabrir esta IDR):**
  1. Volume mensal ultrapassa 3.000 emails (cair no plano pago — avaliar se mudança vale ou se vamos pagar).
  2. Bounce rate > 5% sustentado em janela de 7 dias (sinal de problema de reputação — pode exigir provedor com reputação dedicada).
  3. Resend muda TOS/preço de forma adversa.
  4. Necessidade de envio inbound (parsing de respostas) entra no roadmap — Postmark é mais forte aí.

## Tipo

- [x] **Padrão transversal**: lib/abordagem que vira default no projeto.
- [ ] **Workaround**: contornar bug/limitação documentado.
- [ ] **Convenção interna**: padrão de código local que precisa ser seguido (formato de erro, naming de evento, etc).
- [ ] **Otimização**: mudança feita por motivo de performance, com medição.
- [ ] **Refatoração estrutural**: mudança que afeta vários módulos por motivo de qualidade.

---

## Histórico

- 2026-05-24 — criada como `accepted` por programador (claude-opus-4-7) após escolha explícita do PO no chat entre Resend / Postmark / SES; PO autorizou prosseguir e fechou a janela de "log apenas; sem provedor SMTP real ainda" que estava em `inventories/homolog/group_vars/all/vars.yml:34` desde a STORY-013. Decisão tomada sob método curto (3 alternativas listadas, trade-offs declarados, sem spike — coberto pelo princípio "reversível via mudança de env"). Pendência operacional: PO ainda precisa criar conta, verificar domínio defonline.xandrix.com.br e gerar API key (chave gravada no vault separadamente).
