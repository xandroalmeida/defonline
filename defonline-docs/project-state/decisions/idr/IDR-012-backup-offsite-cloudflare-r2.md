---
idr_id: IDR-012
slug: backup-offsite-cloudflare-r2
title: Provedor de backup off-site — Cloudflare R2 no lugar do Backblaze B2
status: accepted
decided_at: 2026-08-04
decided_by: PO (Alexandro)
owner_agent: programador (claude-opus-5)
related_story: null
related_adrs: ["ADR-005"]
related_idrs: ["IDR-002"]
supersedes: null
superseded_by: null
created_at: 2026-08-04
updated_at: 2026-08-04
---

# IDR-012 — Backup off-site em Cloudflare R2 (substitui Backblaze B2)

## Contexto

ADR-005 §4.2 fixou **Backblaze B2** como destino do backup off-site, com a
justificativa registrada em "Por que Backblaze B2 (e não AWS S3 ou Cloudflare R2)":
custo de storage, compatibilidade S3 e provedor distinto da VPS.

A integração foi codificada (`playbooks/backup.yml`, `templates/rclone.conf.j2`,
`templates/pg-backup.sh.j2`, `playbooks/restore.yml`) e o cron chegou a ser
provisionado na VPS de homologação, mas **nunca executou com sucesso**: em
2026-08-04 a inspeção da VPS mostrou o cron instalado (`0 3 * * *`),
`/opt/defonline/backups/` vazio e nenhum `backup.log` — e o vault de homologação
com `vault_b2_app_key_id` e `vault_b2_app_key` **em branco**. A conta B2 nunca foi
aberta. Ou seja: homologação está sem backup off-site desde o provisionamento, e
não havia nada a migrar.

O PO decidiu abrir a conta na Cloudflare em vez da Backblaze.

## Decisão

> **O destino do backup off-site passa a ser o Cloudflare R2**, via API
> S3-compatível, com **um bucket e um token por ambiente**:
> `defonline-backup-homolog` / `defonline-backups-producao`, cada um com um
> token R2 de permissão `Object Read & Write` escopado exclusivamente no seu
> bucket.

O mecanismo permanece o da ADR-005 §4.2 e não muda: `pg_dump --format=custom`
diário às 03:00 BRT pelo role read-only `defonline_backup`, `gzip`,
`gpg --symmetric --cipher-algo AES256` com passphrase em arquivo 0600, upload por
`rclone`. Muda o remote (`b2:` → `r2:`, `type = s3` + `provider = Cloudflare`) e o
layout de prefixos.

## Por quê

- **Egress zero.** A R2 não cobra saída de dados, nunca. A B2 cobra ~US$ 0,01/GB
  acima da franquia. O custo de egress é exatamente o custo de **restaurar** — e
  um desenho que torna o restore mais caro pressiona na direção errada num
  incidente. Restore trimestral de exercício, mais o restore real, ficam gratuitos.
- **Free tier cobre o MVP inteiro.** 10 GB-mês de storage, 1M ops classe A e 10M
  classe B. O volume projetado (um dump/dia na ordem de MBs + 12 mensais) cabe com
  folga; expectativa de **R$ 0/mês** nos dois ambientes. A B2 sairia por
  R$ 2–5/mês — diferença irrelevante em dinheiro, mas o free tier remove um
  cadastro de cobrança recorrente da operação.
- **Uma conta a menos.** A Cloudflare já é dependência do projeto (DNS
  autoritativo da zona `xandrix.com.br`, ADR-005 §5.2 / IDR-002) e o PO já tem
  conta e credencial ali. A Backblaze exigiria abrir e manter mais um fornecedor —
  custo operacional real para um time de uma pessoa.
- **Continua atendendo RNF §2.2 ("provedor distinto").** A VPS é DigitalOcean; o
  backup passa a viver na Cloudflare. Provedores distintos, requisito mantido.
- **Reversibilidade preservada.** Ambos são S3-compatíveis e falam `rclone`.
  Trocar de novo (Wasabi, S3, de volta para B2) é reescrever
  `rclone.conf.j2` e o nome do remote — a mitigação #5 da ADR-005 §"Sinais de
  revisão" segue valendo, agora com a R2 como titular.

## Consequências

- **A concentração na Cloudflare aumenta.** DNS e backup passam a depender do
  mesmo fornecedor. Aceito conscientemente: uma indisponibilidade da Cloudflare
  derrubaria a resolução do domínio, não o banco operacional nem os dumps já
  gravados; e uma perda **de conta** (suspensão) é mitigada pela cópia offline da
  passphrase e pela portabilidade S3. Reavaliar se a Cloudflare passar a hospedar
  também parte do runtime.
- **Sem região no Brasil.** A R2 não oferece localização BR (hints: ENAM, WNAM,
  WEU, EEU, APAC, OC). O raciocínio jurídico de ADR-005 §4.2 não muda de forma
  alguma: o que sobe é dado cifrado com AES-256 cuja passphrase só o administrador
  possui, o banco operacional continua na VPS BR, e a base legal declarada é
  legítimo interesse (Art. 7º IX). O parágrafo da ADR que fala em "Backblaze não
  tem acesso ao conteúdo" vale igual para a Cloudflare.
- **Correção de política embutida.** O `pg-backup.sh` anterior mandava tudo para
  um prefixo único e limpava com `rclone delete --min-age 30d` na raiz — o que
  teria apagado as cópias mensais junto com as diárias, tornando os "12 mensais"
  da ADR-005 §4.2 inalcançáveis. O script novo separa
  `daily/` e `monthly/`, faz a cópia mensal por *server-side copy* no dia 01 e
  aplica retenções independentes por prefixo. A política da ADR passa a ser
  cumprida de fato.
- **rclone deixa de vir do apt e passa a ser pinado.** O Ubuntu 26.04 empacota
  `rclone 1.60.1-DEV` (build de 2022), incompatível com a R2 de um jeito
  traiçoeiro: o `PUT` do objeto responde `200` com um `x-amz-version-id`, a 1.60
  refaz o HEAD de verificação com `?versionId=<id>`, e a R2 responde
  **501 Not Implemented** — ela não implementa versionamento de objeto. Como o
  upload já tinha sido concluído, a 2ª tentativa do rclone "acertava" e o script
  terminava com `Backup ok.`, deixando o erro apenas como ruído no log. Se as 3
  tentativas caíssem juntas, a falha seria silenciosa às 03:00. Reproduzido com
  `--dump headers` em 2026-08-04; a **1.75.0 sobe limpo, sem workaround**.
  `backup.yml` passa a instalar o `.deb` oficial da versão fixada em
  `rclone_version`. Custo aceito: rclone sai do ciclo de patches do apt — como
  é cliente outbound, sem porta exposta, o risco é baixo; a contrapartida é
  revisar `rclone_version` junto com as demais dependências.
- **Alerta de falha via Telegram, mais uma verificação independente.** O
  incidente do 501 mostrou o custo de não ter isso: o erro ficou invisível porque
  ninguém lê `backup.log`. Fechado na mesma sessão — o bot da ADR-004
  (`defonlineHom_bot`, grupo "DefOnline Alertas") já existia e estava vivo; o
  comentário do vault que o dava como não criado estava desatualizado.
  Dois mecanismos, porque um não cobre o outro:
  - `pg-backup.sh` alerta 🚨 P1 em qualquer saída não-zero (com etapa, código e
    as últimas linhas do log) e ⚠️ P2 quando conclui com ressalvas — retenção que
    falhou, execução anterior ainda em curso. Erros de limpeza deixaram de ser
    engolidos por `|| true`.
  - `pg-backup-verify.sh`, cron às 04:30, cobre o que o backup **não consegue
    reportar sobre si mesmo: não ter rodado**. Confere na R2 que existe dump com
    menos de 26h, baixa, decifra e passa pelo `pg_restore --list`. É um
    mini-drill de restore diário — barato porque a R2 não cobra egress — e é o
    que separa "o arquivo chegou" de "o arquivo serve".

  O alerta fala HTTP direto com a Bot API em vez de passar pelo Laravel: quando
  o backup falha, é plausível que o container `web` também esteja quebrado, e o
  canal de aviso não pode depender do que ele monitora. Credencial em
  `~/.backup-telegram` (0600), mesmo padrão do `.backup-gpg-pass`.

  Um resumo ✅ semanal (segundas) mantém o canal comprovadamente vivo — silêncio
  absoluto é indistinguível de canal quebrado. Desligável em group_vars.

- **Débito remanescente: VPS morta continua invisível.** Os dois mecanismos
  rodam *na própria VPS*. Se ela cair ou for reprovisionada sem o `backup.yml`,
  ninguém alerta. Um dead man's switch de verdade exige observador externo —
  o UptimeRobot da ADR-004 tem monitor tipo *heartbeat* no plano free, que
  resolveria com o `pg-backup-verify.sh` pingando uma URL ao terminar bem.
  Não implementado por depender de conta/configuração fora do repo.

## Alternativas descartadas

- **Manter Backblaze B2.** Descartada: nenhuma vantagem técnica sobre a R2 neste
  perfil de uso, custa egress no momento em que mais dói (restore), e adiciona um
  fornecedor. A justificativa original da ADR-005 não pesou egress zero.
- **AWS S3.** Descartada de novo, pelo mesmo motivo da ADR-005: complexidade de
  IAM e custo de egress, sem ganho para um dump/dia.
- **Snapshot da VPS como único backup.** Descartada: viola RNF §2.2 (mesmo
  provedor) e não dá restore granular de banco.

## Implementação

| Arquivo | Mudança |
|---|---|
| `infra/ansible/playbooks/templates/rclone.conf.j2` | remote `[r2]` `type = s3` / `provider = Cloudflare` / `no_check_bucket = true` |
| `infra/ansible/playbooks/templates/pg-backup.sh.j2` | prefixos `daily/` + `monthly/`, cópia mensal server-side, retenção por prefixo, `flock`, alerta Telegram em falha e em aviso |
| `infra/ansible/playbooks/templates/pg-backup-verify.sh.j2` | **novo** — verificação diária: dump recente na R2, decifra, `pg_restore --list`, resumo semanal |
| `infra/ansible/playbooks/backup.yml` | validação de acesso ao bucket (falha rápido) + rclone do `.deb` oficial pinado em `rclone_version`, fora do apt |
| `infra/ansible/playbooks/restore.yml` | caminho R2 + suporte a `-e dump_month=YYYYMM` |
| `inventories/homolog/group_vars/all/vars.yml` | `b2_*` → `r2_bucket` / `r2_account_id` / `r2_access_key_id` / `r2_secret_access_key` |
| `inventories/homolog/group_vars/all/vault.yml.example` | chaves `vault_r2_*` |
| `defonline-docs/project-state/runbooks/runbook-backup-r2.md` | runbook de criação de buckets, tokens, aplicação e validação |
