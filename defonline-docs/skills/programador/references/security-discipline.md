# Disciplina de segurança

O DEFOnline lida com **dados financeiros de empresas reais**: CNPJ, faturamento, balanços, e potencialmente integrações com Receita, gateways de pagamento, emissores. Erro de segurança aqui custa confiança de pequenas empresas que têm pouco a perder. **A régua é alta porque o domínio cobra alto.**

Segurança não é uma fase no fim do projeto. É **hábito diário do código que você escreve**. Esta reference define os hábitos.

## A mentalidade

- **Defesa em profundidade.** Nenhuma camada confia que a outra fez seu trabalho. Frontend valida; backend valida de novo; banco tem constraint.
- **Fail secure, não fail open.** Se a verificação falhou ou está em estado indefinido, **negue acesso**. Nunca "vou liberar e ver no que dá".
- **Princípio do menor privilégio.** Cada componente/usuário/credencial tem **só** o que precisa para a função.
- **Desconfie do input.** Todo input do mundo externo é hostil até prova em contrário — usuário, API externa, fila, banco com dado legado.
- **Segredos não são código.** Senha, token, chave de API, string de conexão — nunca no repositório.

## Validação de input como linha de defesa

Validação não é só "data quality". É **linha de defesa contra ataque**. Tudo que entra:

- **Frontend valida** para UX (feedback rápido) — mas backend **não pode confiar** nessa validação. Quem chama a API direto pula o frontend.
- **Backend valida** todo input antes de tocar regra de negócio:
  - Tipo correto.
  - Tamanho dentro de limite (string de 1MB não deveria entrar onde se espera nome).
  - Formato esperado (CNPJ com 14 dígitos, e-mail com `@`, data em formato ISO).
  - Range válido (idade entre 0 e 150, percentual entre 0 e 100).
  - Conjunto fechado quando aplicável (status só pode ser X, Y ou Z — nunca `'; DROP TABLE`).
- **Banco tem constraint** quando aplicável (NOT NULL, CHECK, FOREIGN KEY, UNIQUE).
- **Sanitização vs validação.** Prefira **rejeitar** input inválido a "sanitizar" (limpar). Sanitização esconde o problema e cria bugs.

**Use a validação do framework opinativo** (princípio arquitetural #4) em vez de validação manual avulsa. O framework já protege contra muita coisa que validação caseira esquece.

## SQL injection — quase sempre prevenido pelo framework, **se você não atrapalha**

Frameworks opinativos com ORM ou query builders parametrizam queries por padrão. **Use sempre parameterized queries**. Anti-padrões a evitar:

- Concatenar string para montar SQL. **Nunca.**
- `f"SELECT * FROM users WHERE id = {user_id}"` em Python, `\`SELECT ... ${userId}\`` em JS — buracos abertos para injection.
- Passar query builder como string em vez de usar a API estruturada.

Quando precisar de SQL bruto (caso legítimo — query complexa que ORM não expressa bem), use o mecanismo de **bind parameters** da biblioteca (`?`, `$1`, `:nome`). **Sem exceção.**

Veja também `database-discipline.md`.

## XSS (Cross-Site Scripting) — frontend

Frameworks modernos de frontend (React, Vue, Angular, Svelte) escapam HTML por padrão. **Não desfaça isso:**

- Evite `dangerouslySetInnerHTML` (React), `v-html` (Vue), `innerHTML` direto.
- Se precisar exibir HTML user-generated (raríssimo no DEFOnline — quase nada justifica), **sanitize com biblioteca dedicada** (DOMPurify ou equivalente) e documente o porquê (IDR).
- Atributos `href` e `src` com URL vinda do usuário precisam de validação de esquema (rejeite `javascript:`, `data:`).

## CSRF (Cross-Site Request Forgery) — quase sempre prevenido pelo framework

Frameworks opinativos têm proteção CSRF ativa por padrão para forms autenticados. **Não desabilite sem motivo concreto** (IDR, com bênção do Arquiteto).

Para APIs JSON consumidas por SPA com token (Bearer/JWT em header), CSRF é menos relevante — mas verifique a documentação do seu framework.

## Autenticação vs Autorização

São coisas diferentes. Confundir é fonte de bug e brecha:

- **Autenticação:** quem é você. (login, sessão, token).
- **Autorização:** o que você pode fazer. (papéis, permissões, escopo de recurso).

Hábitos:

- **Sempre verifique autorização**, não só autenticação. Usuário X logado **não significa** que ele pode editar empresa Y.
- **Verifique no servidor**, sempre. Esconder botão no FE é UX — não é segurança.
- **Verifique no endpoint que faz a ação**, não no que carrega a tela.
- **Princípio do menor privilégio:** se uma operação só precisa ler, não dê permissão de escrita.
- **Verificação de propriedade:** ao editar/visualizar/deletar um recurso, verifique se o usuário **dono** é o que está pedindo (`empresa.user_id == request.user.id`).

Anti-padrão clássico: endpoint `GET /api/empresas/{id}` que **autentica** mas não **autoriza** — qualquer logado vê empresa de qualquer outro.

## Gestão de segredos

- **Nada no código.** Nem em comentário, nem em string default, nem em arquivo de teste.
- **Variáveis de ambiente** ou cofre dedicado (Vault, AWS Secrets Manager, etc — decisão do Arquiteto).
- **Arquivos `.env`** ficam fora do git (`.gitignore`). Commit do `.env.example` é OK.
- **Pre-commit hook** ou scanner CI detecta segredo acidentalmente commitado — princípio "automatizável > documentável".
- **Rotação:** segredos têm prazo de validade. Operação de rotação é parte do design (não é "decidiremos quando precisar").
- **Acidentalmente commitou um segredo?** Não basta deletar — você **revoga e reemite o segredo**. Está no histórico do git, qualquer pessoa com acesso ao repo já viu.

## O que NUNCA logar

Logs são lidos por mais gente do que você imagina (devs, ops, possíveis vazamentos). **Nunca deixe entrar no log:**

- **Senhas** — nem em forma "mascarada".
- **Tokens** (JWT, API key, Bearer, OAuth).
- **CPF/CNPJ pleno** sem necessidade (mascare últimos dígitos: `12.***.**8/0001-XX`).
- **Cartão de crédito** (PAN, CVV, validade — qualquer dado de PCI).
- **Conteúdo de e-mail/SMS** transacional.
- **Payloads de webhook** crus de pagamentos.
- **Dados pessoais sensíveis** (saúde, religião, opinião política — LGPD art. 5º II).
- **Body inteiro de request** em endpoints que recebem dados sensíveis.

**Use convenção do framework** para mascarar campos sensíveis no log automaticamente — isso é princípio "automatizável > documentável" aplicado.

Veja também `observability-discipline.md`.

## Audit log para operações sensíveis

Operações que importam (criação, alteração, deleção de dados de negócio; mudança de papel; acesso a dado sensível) viram **registro auditável** — quem fez, o quê, quando, de onde, antes/depois. Em banco mesmo (tabela `audit_log` append-only, ou similar — princípio Postgres-first).

Não confunda **audit log** com **log de aplicação**: audit é fonte da verdade jurídica/operacional; log de aplicação é debug/observabilidade. Ambos existem; têm propósitos diferentes; têm políticas de retenção diferentes.

## Dependências vulneráveis

- **Scanner de dependências** no CI (pip-audit, npm audit, dependabot, snyk — o que for decidido). Se vulnerabilidade alta abre, build vermelho.
- **Não ignore** alertas sem investigar. "É false positive" precisa ser **demonstrado**, não assumido.
- **Atualize** com cadência. Lib parada há 2 anos é risco.

## LGPD aplicada — hábitos do programador

Lei Geral de Proteção de Dados é restrição funcional concreta. O detalhe fica em `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md`. Hábitos do dia a dia:

- **Minimização:** colete só o que vai usar. Cada campo novo no formulário é dado pessoal a justificar.
- **Propósito:** se um endpoint coleta CNPJ para diagnóstico, ele não usa CNPJ para envio de marketing — propósitos diferentes precisam de bases diferentes.
- **Retenção:** dados têm prazo. Considere de início (mesmo que a deleção venha em outra estória).
- **Direitos do titular:** o sistema precisa ser capaz de exportar e deletar dados de um usuário (Art. 18 LGPD). Pense nisso ao modelar tabela nova.
- **Antes de adicionar coleta de dado pessoal:** **fale com o PO**. PRs adicionando coleta sem confirmação de cobertura no aviso de privacidade são rejeitados (regra do PO em `quality-standards.md`).

## Comunicação com APIs externas

- **Sempre HTTPS** — falha de TLS = erro, não warning.
- **Valide certificado**. Não desabilite verificação ("`verify=False`" / "rejectUnauthorized: false") **nem em desenvolvimento** — princípio funcionamento local com mocks/sandbox de confiança.
- **Timeouts** em toda chamada externa (sem timeout = thread/conexão pendurada para sempre).
- **Rate limit awareness** — respeite limites do fornecedor; implemente exponential backoff (veja `error-handling.md`).

## Endpoints públicos

Para qualquer endpoint sem autenticação:

- **Rate limiting** obrigatório (CSRF auth, password reset, cadastro). Já é diretriz nos RNFs.
- **Captcha** em endpoints que disparam ação custosa (envio de e-mail, criação de conta).
- **Validação rigorosa** — input público é o mais hostil.

## Princípios consolidados

1. Toda entrada externa é hostil — valide.
2. Toda saída para usuário (HTML, JSON, e-mail) é vetor — escape, sanitize, mascare.
3. Toda autorização verifica propriedade do recurso, não só identidade do usuário.
4. Toda credencial mora em segredo, nunca em código.
5. Toda operação sensível tem trilha auditável.
6. Toda dúvida de segurança escala — para o Arquiteto se for transversal, para o PO se for de propósito de dado.

## Quando você encontrar uma falha de segurança em código existente

- **Não publique no chat público.** Trate como confidencial.
- **Não tente "exploitar" para confirmar** em produção.
- **Comunique o PO/Arquiteto privadamente** com o necessário pra reproduzir.
- Provavelmente vira estória de correção priorizada (não vai pro backlog comum).

## Resumo operacional

Antes de marcar uma estória pronta, **se ela envolve input do usuário, dado pessoal, autenticação, autorização, ou integração externa**, faça esta passada:

- [ ] Input validado no backend (não só no FE).
- [ ] Queries parametrizadas (sem concatenação de SQL).
- [ ] Saída HTML escapada (sem `dangerouslySetInnerHTML` indevido).
- [ ] Autorização verificada — não só autenticação, propriedade do recurso checada.
- [ ] Nenhum segredo no código nem em commit.
- [ ] Logs não vazam PII, token ou outros dados sensíveis.
- [ ] Audit log emitido se operação sensível.
- [ ] HTTPS + timeout + retry sane em chamadas externas.
- [ ] LGPD: nenhum dado pessoal novo coletado sem alinhamento prévio com PO.
