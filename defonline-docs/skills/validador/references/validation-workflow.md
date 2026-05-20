# Workflow de validação — sequência operacional fim-a-fim

Esta reference cobre **como conduzir uma validação completa** de épico, em ordem, sem pular passos e sem improvisar. Validar bem é processo disciplinado — fora de ordem ou com atalho, você corre o risco de aprovar épico que não devia, ou reprovar por motivo errado.

---

## A mentalidade operacional

- **Ordem importa.** Cada passo prepara o seguinte. Não pule "porque parece óbvio".
- **Registre conforme vai.** Captura de evidência **no momento** vence "vou anotar depois" — depois você esquece detalhe.
- **Pause se algo parece inconsistente.** Estória `done` mas teste não existe? Pode ser que algo não foi feito. Investigue antes de continuar.
- **Sem multitasking.** Faça uma validação completa antes de começar outra. Misturar = erro.

---

## Pré-condições antes de começar

Não comece sem confirmar:

- [ ] **Épico está em status `in_review`** no `index.json`. Status `in_progress` significa que ainda não está pronto.
- [ ] **Todas as estórias do épico estão `done`**. Se uma está `blocked` ou `in_progress`, **pare** — notifique PO. Épico não está pronto para validação.
- [ ] **`validation/checklist.md` do épico existe**. Sem checklist, você não tem alvo claro. Notifique PO.
- [ ] **Ambiente de homologação está acessível**. Você vai precisar entrar para smoke manual.
- [ ] **Acesso a CI/CD, dashboards de observabilidade, banco** (read-only) está disponível.

Se qualquer pré-condição falha, **pare e notifique o PO no chat**:

> "Não consigo iniciar validação do EPIC-XXX porque [pré-condição falhou]. Detalhe: [...]. Quando isso for resolvido, retomo."

Não improvise para "começar mesmo assim".

---

## Etapa 1 — Carregar contexto (leitura)

Antes do primeiro teste rodando, **leia** (veja também a seção "Disciplina de leitura" na SKILL.md):

1. **`epic.md`** — outcome, métrica, entregável visível, fora de escopo.
2. **`validation/checklist.md`** — sua bateria operacional. Leia inteiro **antes** de iniciar; entenda o que cada item exige.
3. **Cada `STORY-XXX-*.md` do épico** — especialmente:
   - CAs declarados.
   - "Notas do agente" — o que foi descoberto durante implementação.
   - IDRs criados durante a estória.
4. **ADRs/PDRs/IDRs aplicáveis** — referenciados nas estórias ou no épico.

Faça uma **entrada inicial em "Notas do agente"** da estória de validação (se houver) ou em rascunho próprio:

```markdown
### YYYY-MM-DD — Início da validação
- Épico: EPIC-XXX
- Documentos lidos: epic.md, checklist.md, STORY-001, ..., ADR-005, PDR-003.
- Entendimento consolidado: o épico promete <X>; a entrega visível é <Y>; métrica de sucesso é <Z>.
- Itens do checklist por bloco: [contagem para acompanhar progresso].
```

---

## Etapa 2 — Verificar estado do índice

Antes de executar qualquer comando, valide consistência:

- [ ] `index.json` lista todas as estórias declaradas em `epic.md`.
- [ ] Todas em `status: done` com `updated_at` plausível.
- [ ] Nenhuma estória `blocked` que ainda esteja "no épico" mas fora do index.
- [ ] Validation report atual do épico está `null` (você está escrevendo o primeiro).

Se há divergência, **registre como ressalva** no relatório — mas isso por si só não reprova o épico (a menos que cause problema concreto adiante).

---

## Etapa 3 — Setup do ambiente de validação

Você não modifica nada do sistema. Mas precisa de **acesso a tudo que vai verificar**:

- [ ] **Acesso ao código** — para rodar testes localmente, conferir cobertura, ler implementação se necessário.
- [ ] **Acesso a CI/CD** — para ver pipeline status, baixar relatórios de cobertura, ver logs.
- [ ] **Acesso ao ambiente de homologação** — URL acessível, credenciais válidas.
- [ ] **Acesso a dashboards** de observabilidade (logs, métricas).
- [ ] **Ferramentas locais** — runner de teste, browser para automação E2E, ferramentas de cobertura.

Se algo está ausente, **pare e notifique o PO**. Não improvise.

---

## Etapa 4 — Executar o checklist em ordem

O `validation/checklist.md` tem 7 blocos (definidos pelo PO). Execute em ordem:

### Bloco 1 — Critérios de aceite das estórias

Para **cada CA** de **cada estória** do épico:

- [ ] Identifique o(s) teste(s) que cobre.
- [ ] Verifique se o teste **passa** localmente e na suíte completa.
- [ ] Verifique se o teste cobre o CA de fato (não apenas tem nome parecido).
- [ ] Capture evidência: nome do teste + commit hash + log do CI.

**Sinal de fail nesse bloco**: CA sem teste; teste com nome certo mas asserção fraca; teste em `skip`.

### Bloco 2 — Cobertura de testes

- [ ] Rode o relatório de cobertura do código novo do épico.
- [ ] Confirme **cobertura geral ≥ 80%**.
- [ ] Identifique módulos de núcleo/regras de negócio e confirme **cobertura ≥ 98%**.
- [ ] Para linhas descobertas, confirme que **cada uma tem justificativa concreta** (não caso de exceção esquecido).
- [ ] Confirme presença de testes para **caminho feliz + casos inválidos + exceções + bordas** (não só feliz — `testing-discipline.md` do Programador).

**Evidência**: relatório do CI ou comando local, com números + linhas descobertas listadas.

### Bloco 3 — Automação

- [ ] **Setup local**: clone do repo + um comando → app sobe completo sem internet (exceto pull inicial de imagens). Teste em máquina limpa se possível.
- [ ] **Pipeline CI verde** na branch principal após o épico.
- [ ] **Deploy automático para homologação** dispara após merge — verificado funcionando.
- [ ] **Deploy para produção** é automatizado (mesmo se gated por aprovação humana).
- [ ] **Provisionamento dos ambientes** é Infra-as-Code, não cliques manuais.

**Evidência**: log de pipeline, link para definição de IaC, screenshot/log do `docker compose up` se aplicável.

### Bloco 4 — Funcionalidade observável

Aqui você **vai a homologação** e **usa o sistema**:

- [ ] Entregável listado em `epic.md` está **acessível** em homologação.
- [ ] Você percorre o fluxo end-to-end **manualmente** — funciona.
- [ ] Para FE web: confirme que **E2E em browser real** está cobrindo o fluxo (não apenas teste unitário simulando DOM — `testing-discipline.md`).
- [ ] **Logs e métricas básicas** (saúde da aplicação, métricas RED) estão sendo coletados em homologação.

**Evidência**: screenshots do fluxo executado + link para dashboard de logs/métricas + nome dos cenários E2E.

### Bloco 5 — Qualidade transversal

- [ ] **Scanner de segurança** do CI: sem alerta crítico aberto introduzido pelo épico.
- [ ] **Migrações de banco**: reversíveis e testadas (no mínimo, declaração de que foram testadas em ambiente de homologação).
- [ ] **LGPD**: dados pessoais novos coletados respeitam o que está em `requisitos-nao-funcionais-e-juridicos.md` e foi alinhado com PO (PR comment ou PDR registra).
- [ ] **Segredos**: nenhum no código (scanner de pre-commit / CI confirma).
- [ ] **Logs**: nenhum dado sensível em log (PII, token, senha — `security-discipline.md`).

**Evidência**: log do scanner, link para arquivos de migração, busca específica por padrões problemáticos (se ferramenta permite).

### Bloco 6 — Documentação

- [ ] **README/documentação do componente** atualizada onde relevante.
- [ ] **ADRs e IDRs criados** durante o épico estão indexados em `index.json`.
- [ ] **Notas do agente** em cada estória estão preenchidas (decisões locais, descobertas, IDRs).
- [ ] **Diagramas** atualizados se aplicável (ADR Topológica, Persistência — `arquiteto/references/diagrams.md`).

**Evidência**: links para os documentos atualizados; ausências listadas como gaps.

### Bloco 7 — Veredito

Compile o que você observou nos blocos 1-6:

- Todos os itens **`pass` ou `n/a` justificado** → **`approved`**.
- Pelo menos um **`fail`** → **`rejected`**.

Esta é a etapa de **decisão final** — não no meio. Veja `verdict-criteria.md` para gravidade.

---

## Etapa 5 — Compilar evidência

Sua validação não vale se você não consegue **mostrar como chegou** a cada conclusão. Evidência por item:

- **`pass`**: link ou ID concreto (commit hash, PR number, dashboard URL, screenshot anexado, log com timestamp).
- **`fail`**: descrição do que observou + link/log + como reproduzir.
- **`n/a`**: prosa explicando por que o item não se aplica neste épico.

Detalhamento em `evidence-discipline.md`. **Sem evidência, sua afirmação não vale**.

---

## Etapa 6 — Escrever o relatório

Use `templates/validation-report.md`. Detalhamento em `reporting-craft.md`. Estrutura:

1. TL;DR (veredito + 3 linhas)
2. Resumo executivo (1 parágrafo)
3. Checklist preenchido (todos os blocos com pass/fail/n-a + evidência)
4. Fails categorizados por gravidade (bloqueante vs não-bloqueante)
5. Recomendação para o PO (próximos passos)
6. Apêndice de evidência

**Não submeta o relatório se algum item está vazio ou impreciso.** Releia uma vez antes de fechar.

---

## Etapa 7 — Atualizar `index.json`

Você atualiza **apenas o campo `validation_report`** do épico:

```json
{
  "id": "EPIC-XXX",
  ...,
  "validation_report": {
    "path": "epics/EPIC-XXX-<slug>/validation/report.md",
    "verdict": "approved" | "rejected",
    "validated_at": "YYYY-MM-DD",
    "validated_by": "validador (<id da sessão>)"
  },
  ...
}
```

**Não mude o `status` do épico** — isso é decisão do PO baseada no seu veredito. Você só registra o resultado da validação.

---

## Etapa 8 — Notificar o PO

No chat, ao terminar:

> "**Validação do EPIC-XXX concluída.**
> **Veredito: APPROVED** (todos os 7 blocos `pass` ou `n/a` justificado).
> **Resumo**: <3 linhas: o que foi entregue, pontos altos, eventuais ressalvas>.
> **Relatório**: [link]
>
> Aguardo sua leitura para fechar o épico."

ou

> "**Validação do EPIC-XXX concluída.**
> **Veredito: REJECTED** — 3 fails identificados, sendo 1 bloqueante.
> **Bloqueante**: <descrição curta>.
> **Não-bloqueantes**: <lista curta>.
> **Recomendação**: <resumo>.
> **Relatório completo**: [link]"

Curto, factual, acionável.

---

## Quando interromper a validação no meio

Se você descobrir algo **muito sério** durante a validação (vazamento de segurança, dado corrompido, sistema completamente quebrado em homologação):

- **Pare** a validação.
- **Notifique imediatamente** o PO (e talvez Arquiteto, se for tipo arquitetural).
- **Não tente continuar** validando o resto — o problema descoberto pode invalidar suposições do checklist inteiro.
- **Registre o que descobriu** em relatório preliminar marcado como "validação interrompida".

Isso é exceção. Não pare por fails comuns — esses entram no relatório normal.

---

## Quanto tempo uma validação demora

Depende do tamanho do épico:

- **Pequeno** (1-2 estórias): ~30 min - 1h.
- **Médio** (3-7 estórias): ~1-3h.
- **Grande** (8+ estórias): considere dividir em dois passes (testes automatizados primeiro, smoke manual + observabilidade depois).

**Se está demorando muito mais que isso**, geralmente é sinal de:
- Épico mal estruturado (muito grande sem ter sido dividido pelo PO).
- Checklist mal definido (você está improvisando o que verificar).
- Ambiente ruim (subir homologação demora demais).

Registre como observação no relatório — pode virar input de melhoria para o PO.

---

## Resumo operacional

Em ordem, sem pular:

1. Pré-condições atendidas → segue.
2. Lê contexto inteiro (epic, checklist, stories, ADRs aplicáveis).
3. Verifica estado do índice.
4. Setup do ambiente de validação.
5. Executa o checklist em ordem (7 blocos).
6. Compila evidência item por item.
7. Atribui veredito.
8. Escreve o relatório completo.
9. Atualiza `index.json` (campo `validation_report` apenas).
10. Notifica PO com resumo + link.

> **Validação boa parece rotineira por fora. Por dentro, é disciplina sem atalho.**
