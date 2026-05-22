---
story_id: STORY-009
slug: phppgadmin-dev-only
title: PhpPgAdmin exclusivo do ambiente local de desenvolvimento
epic_id: EPIC-000
sprint_id: null
type: implementation
target_role: programador
status: ready
owner_agent: null
created_at: 2026-05-22
updated_at: 2026-05-22
estimated_session_size: S
---

# STORY-009 — PhpPgAdmin exclusivo do ambiente local de desenvolvimento

> **Para o agente Programador que vai executar:** leia esta estória inteira antes de codificar. O escopo é deliberadamente pequeno — ferramenta de conveniência para o dev local, com uma restrição **inegociável**: PhpPgAdmin **NUNCA** pode existir em homologação ou produção. A restrição vale como regra arquitetural: vide ADR-005 §1.1 (perfil mínimo da VPS), ADR-005 §6 (diferenças entre ambientes) e o princípio do menor privilégio.

## Contexto

A fundação técnica entregue na STORY-007 já dá ao desenvolvedor um Postgres rodando em container (`defonline-db`, porta `5436` no host). Hoje, para inspecionar o banco em dev, o caminho é `docker compose exec db psql` ou um cliente desktop apontando para `localhost:5436`. Funciona, mas custa fricção em duas situações que aparecem com frequência durante o EPIC-001 a EPIC-003:

- Depurar uma migration que rodou diferente do esperado (olhar o esquema, contar linhas, conferir índices).
- Inspecionar a tabela `evento_produto` ou `audit_logs` enquanto se está reproduzindo um fluxo na página viva.

Uma interface web embarcada (PhpPgAdmin) resolve isso sem instalar nada na máquina do desenvolvedor e sem expor o banco para fora do container. **A finalidade desta estória é exclusivamente facilitar a vida do desenvolvedor em dev local; não é ferramenta de operação.**

Critério não-funcional acoplado: PhpPgAdmin é uma ferramenta administrativa que, em produção, viola o princípio do menor privilégio (acesso direto ao schema, à `audit_logs` e ao `evento_produto`, todos com GRANTs restritivos definidos pela ADR-003 §Decisão 4, ADR-004 §2.4 e ADR-005 §7.5) e cria superfície de ataque desnecessária. Portanto: existe **apenas** no `docker-compose.yml` (dev local). Os playbooks Ansible de homologação e produção **não devem instanciar PhpPgAdmin sob nenhuma circunstância**.

- Épico: `epics/EPIC-000-foundation/epic.md`
- Estórias relacionadas: STORY-007 (Phase 1 já entregue: dev local funcional; Phase 3 pendente: playbooks Ansible — esta estória estabelece a restrição **antes** dos playbooks de homol/prod serem escritos).
- ADRs canônicas (não reabrir):
  - ADR-005 §1.1, §6 — perfil dos ambientes; homol/prod usam Ansible, não Docker Compose.
  - ADR-003 §Decisão 4, ADR-004 §2.4 — GRANTs restritivos em `audit_logs` e `evento_produto`.
  - ADR-005 §7.5 — separação de roles Postgres (`postgres` superuser apenas para migrations; runtime usa `defonline_app` sem SUPERUSER).

## O quê

Adicionar um serviço `phppgadmin` ao `docker-compose.yml` da raiz do repositório, com porta publicada localmente, apontando para o serviço `db` da própria composição. Documentar no README e no `up.sh` como acessá-lo. Garantir, por inspeção do código de infra atual e do que será produzido na Phase 3 da STORY-007, que **nenhum** playbook Ansible (`playbooks/app.yml`, `playbooks/deploy.yml`, role de docker, role de app etc.) instancie PhpPgAdmin nos inventários `homolog` ou `production`.

## Por quê

Ergonomia do desenvolvedor é um princípio do Arquiteto (#6 — ambiente local em paridade com produção, com 1 comando). Ferramenta administrativa exposta em homol/prod é anti-padrão de segurança e fere o princípio do menor privilégio (ADR-005 §7.5). Esta estória resolve as duas tensões: ganha-se conveniência onde é seguro ganhar (dev local), e bloqueia-se explicitamente o vazamento dessa conveniência para os ambientes onde ela é proibida.

## Critérios de aceite

- [ ] **CA-1:** O serviço `phppgadmin` está presente no `docker-compose.yml` da raiz, usando uma imagem oficial/estável (a escolha exata da imagem é decisão técnica do programador — sugestão: `dpage/pgadmin4` ou `phppgadmin/phppgadmin` se preferir a UI clássica; justifique em "Notas do agente").
- [ ] **CA-2:** O serviço aponta para o container `db` por nome de serviço (`host=db`, `port=5432`), **não** para `localhost:5436`. O acesso ao Postgres acontece dentro da rede do Docker Compose.
- [ ] **CA-3:** A porta do PhpPgAdmin é publicada apenas em `127.0.0.1` (loopback) — `127.0.0.1:8091:80` (ou porta equivalente decidida pelo programador), **nunca** em `0.0.0.0`. Justificativa: a ferramenta vive na máquina do desenvolvedor; não há motivo para escutar em outra interface.
- [ ] **CA-4:** Credenciais de acesso ao PhpPgAdmin (usuário/senha do app, não do Postgres) são valores de desenvolvimento óbvios (`admin@defonline.local` / `dev`), documentados no README. Nenhuma credencial real, nenhuma referência a Vault. Esta é uma ferramenta de dev local, não tem segredo.
- [ ] **CA-5:** O `up.sh` continua subindo o ambiente local com um único comando (princípio #6 do Arquiteto); o PhpPgAdmin sobe junto, sem comandos adicionais.
- [ ] **CA-6:** O README do repositório raiz ganha uma seção curta "Acessando o banco em dev" com a URL local (`http://localhost:8091`), credenciais de dev e a frase literal **"PhpPgAdmin é exclusivo do ambiente de desenvolvimento local. Não existe em homologação nem em produção."**.
- [ ] **CA-7:** O `docker-compose.yml` recebe um comentário explícito acima do serviço — algo como `# PhpPgAdmin — DEV LOCAL APENAS. Nunca portar para playbooks de homol/prod (ADR-005 §6).` — para que qualquer leitor futuro entenda a intenção, mesmo sem ler esta estória.
- [ ] **CA-8:** Inspeção dos arquivos de infra (`infra/`, `playbooks/` se já existirem, e qualquer arquivo Ansible criado nesta sessão ou em sessões anteriores) confirma que **não há referência a `phppgadmin`, `pgadmin`, `pgadmin4`, `adminer`, `dbgate` ou qualquer outra ferramenta administrativa equivalente** em playbooks que rodem contra inventários `homolog` ou `production`. Se a Phase 3 da STORY-007 ainda não foi executada, esta estória apenas firma a regra; quando os playbooks forem escritos, eles já nascerão sem essa ferramenta.
- [ ] **CA-9:** Testes/verificação: subir `./up.sh` em máquina limpa, acessar `http://localhost:8091`, logar com as credenciais de dev, abrir a conexão `db`, listar as tabelas `evento_produto`, `request_metrics` e `job_metrics`. Anexar screenshot ou log em "Notas do agente".
- [ ] **CA-10:** Teste de regressão automatizado **leve** que falhe o CI se um playbook Ansible vier a referenciar `phppgadmin`/`pgadmin` no futuro. Forma sugerida: um teste Pest arquitetural (na pasta `tests/Architecture/`) ou um script shell rodado pelo workflow `pr.yml` (Phase 2 da STORY-007) que faz `grep -ri 'pgadmin\|phppgadmin' playbooks/ infra/ansible/ 2>/dev/null` e retorna não-zero se encontrar dentro de pastas/roles que pertencem aos inventários `homolog` ou `production`. A pasta `inventories/dev/` (se existir) é exceção explícita.

## Fora de escopo

- Configuração avançada de PhpPgAdmin (temas, plugins, perfis). É ferramenta de conveniência — instalar e deixar funcionando basta.
- Adicionar PhpPgAdmin para os outros desenvolvedores acessarem remotamente (anti-padrão; cada um tem o seu dev local).
- Substituir o `docker compose exec db psql` — quem prefere CLI continua usando.
- Adminer, pgAdmin4 web em produção, túneis SSH para PhpPgAdmin em homol/prod — explicitamente proibidos. Quem precisar inspecionar o banco de homologação usa `ssh + psql` autenticado pelo Ansible Vault (procedimento operacional, não interface web).
- Auditoria/log das ações do PhpPgAdmin — irrelevante em dev local; em homol/prod, a regra é "não existe".

## Padrões de qualidade exigidos

Esta estória segue `defonline-docs/skills/po/references/quality-standards.md`. Por ser uma adição de ferramenta de dev (sem regra de negócio nova), os pontos relevantes são:

- **Sem código não testado ao final** — o CA-10 cobre a regressão crítica (a regra "nunca em homol/prod"). Não há lógica de domínio a cobrir com Pest unitário.
- **Toda automação rodando como automação** — o serviço sobe via `up.sh` sem passo manual extra.
- **Documentação no README** — CA-6.

## Dependências

- **Bloqueada por:** STORY-007 Phase 1 (já concluída em 2026-05-22 — dev local funcional com `db` no Docker Compose).
- **Acopla-se com STORY-007 Phase 3 (pendente):** quando os playbooks Ansible forem escritos, esta estória já terá fixado a regra "nunca em homol/prod" e o teste de regressão do CA-10 já estará no CI. Pode ser executada em paralelo com Phase 2 (CI/CD) e Phase 3 (Ansible) da STORY-007, desde que o programador da Phase 3 leia esta estória antes de escrever os playbooks.
- **Bloqueia:** STORY-008 (validação final) — o checklist ganhará um item de verificação (ver "Atualização do checklist de validação" abaixo).

## Decisões já tomadas (não as reabra)

- ADR-005 §1.1, §6 — homologação/produção rodam via Ansible em VPS BR; Docker Compose é exclusivo do dev local.
- ADR-005 §7.5 — separação de roles Postgres; runtime nunca usa o superuser.
- ADR-003 §Decisão 4 + ADR-004 §2.4 — `audit_logs` e `evento_produto` têm GRANTs restritivos (REVOKE UPDATE/DELETE). Em dev local, o `postgres` superuser ignora os GRANTs — daí a importância de PhpPgAdmin não existir em homol/prod, onde os GRANTs são a linha de defesa.

## Liberdade técnica do agente Programador

Você decide:
- Imagem Docker exata (`dpage/pgadmin4` vs `phppgadmin/phppgadmin` vs `adminer` etc.) — justifique em "Notas do agente".
- Porta específica no host (sugestão `8091`, mas pode ser outra se houver conflito documentado).
- Forma do teste de regressão do CA-10 (Pest arquitetural ou script shell no workflow). Recomendação: script shell — não exige carregar Laravel só para fazer grep.
- Texto exato do comentário do CA-7 (desde que a intenção fique inequívoca).

Você NÃO decide:
- Se PhpPgAdmin pode "só desta vez" entrar em homol para uma depuração emergencial. Resposta: não. O canal para isso é `ssh + psql + credencial do Vault`.
- Mudar a porta para `0.0.0.0` "para acessar do celular" — viola CA-3.
- Adicionar PhpPgAdmin em playbook Ansible com `tags: dev-only` esperando que ninguém rode com aquela tag — viola CA-8. A regra é dura: a ferramenta não existe em arquivo nenhum que toque homol/prod.

## Definição de Pronto (DoD)

- [ ] CA-1 a CA-10 satisfeitos com evidência.
- [ ] `docker compose ps` mostra o container `phppgadmin` rodando após `./up.sh`.
- [ ] `http://localhost:8091` responde com a tela de login em máquina limpa.
- [ ] Inspeção `grep -ri 'pgadmin' .` retorna apenas: o serviço no `docker-compose.yml`, o comentário do CA-7, a documentação no README e (se aplicável) o teste de regressão.
- [ ] README atualizado conforme CA-6.
- [ ] `index.json` atualizado: `status: in_review` (validador da STORY-008 vai confirmar).
- [ ] "Notas do agente" preenchidas com decisões locais (imagem escolhida e por quê, porta usada, forma do teste de regressão) e link/screenshot da tela do PhpPgAdmin logada.

## Atualização do checklist de validação

A STORY-008 (validação final do EPIC-000) ganha, na seção "5. Qualidade transversal", **um novo item de checklist** a ser adicionado pelo PO quando esta estória for para `ready`:

> - [ ] Inspeção dos arquivos do repositório confirma que PhpPgAdmin (ou ferramenta administrativa equivalente como Adminer/pgAdmin4) **não está referenciado** em nenhum playbook Ansible que rode contra inventário `homolog` ou `production`. O serviço existe **apenas** no `docker-compose.yml` da raiz, com porta publicada em `127.0.0.1`, e está claramente comentado como dev-only.

O validador da STORY-008 executa esse item junto com o resto do checklist.

## Protocolo do agente (obrigatório)

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Em resumo:

1. **Ao iniciar:** edite o frontmatter — `status: in_progress`, `owner_agent: <id>`, `updated_at: <hoje>`. Atualize `index.json`.
2. **Durante:** TaskList interna; commit único e pequeno (essa estória é `S`); execute o teste de regressão localmente antes do commit.
3. **Se travar:** `status: blocked`, descreva o bloqueio.
4. **Ao terminar:** preencha "Notas do agente", `status: in_review`, atualize `index.json`, abra PR. Validador (STORY-008) confirma junto com o checklist principal.

## Notas do agente
(preenchido durante/após execução)

### Decisões tomadas
- <data> — imagem Docker escolhida e por quê.
- <data> — porta exata no host.
- <data> — forma do teste de regressão do CA-10.

### Descobertas
- <data> — <descoberta>

### Bloqueios encontrados
- <data> — <bloqueio> ou "Nenhum"

### Links de evidência
- Screenshot da tela do PhpPgAdmin logada listando tabelas `evento_produto`, `request_metrics`, `job_metrics`: <link>
- Output do `grep -ri 'pgadmin' playbooks/ infra/ansible/` (esperado: vazio ou apenas referências dev-only documentadas): <link>
- PR: <link>
