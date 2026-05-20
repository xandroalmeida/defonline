---
epic_id: EPIC-XXX
type: validation-checklist
created_at: YYYY-MM-DD
---

# Checklist de validação — EPIC-XXX

> Para o **validador**: execute cada item em ordem. Para cada um, registre status `pass | fail | n/a` e evidência (link, screenshot, log). Não invente resultados. Em caso de falha, **não tente consertar** — registre e devolva para o PO.

## 1. Critérios de aceite das estórias

- [ ] Todas as estórias do épico estão com `status: done` no `index.json`.
- [ ] Cada critério de aceite (CA) listado em cada `story.md` foi exercido por pelo menos um teste automatizado.

## 2. Cobertura de testes

- [ ] Cobertura unitária do código novo do épico ≥ **80%** (evidência: relatório do CI).
- [ ] Cobertura unitária de módulos de núcleo/regras de negócio ≥ **98%** (evidência: relatório).
- [ ] Há testes E2E cobrindo cada fluxo de usuário tocado pelo épico.
- [ ] Para frontend web: testes E2E rodam em browser real via automação (não simulado por unit).

## 3. Automação

- [ ] Setup de ambiente local automatizado (um comando) — testado em máquina limpa.
- [ ] Pipeline CI verde no branch principal após o épico.
- [ ] Deploy para homologação automatizado e disparado pelo pipeline.
- [ ] Deploy para produção automatizado (mesmo que ainda gated por aprovação humana).
- [ ] Provisionamento dos ambientes de homologação e produção é Infra-as-Code (não cliques manuais).

## 4. Funcionalidade observável

- [ ] Entregável visível listado no `epic.md` está acessível em homologação.
- [ ] Um usuário consegue percorrer o fluxo end-to-end manualmente em homologação.
- [ ] Logs e métricas básicas (saúde da aplicação) estão coletados.

## 5. Qualidade transversal

- [ ] Nenhum aviso crítico de segurança aberto introduzido pelo épico (scanner do CI).
- [ ] Migrações de banco são reversíveis e foram testadas em homologação.
- [ ] LGPD: tratamento de dados pessoais respeita o que está em `requisitos-nao-funcionais-e-juridicos.md`.

## 6. Documentação

- [ ] README/documentação do componente atualizada onde relevante.
- [ ] ADRs e IDRs criados durante o épico estão indexados em `index.json`.
- [ ] Notas do agente em cada estória estão preenchidas.

## 7. Veredito

- [ ] **APROVADO** — todos os itens acima `pass` ou `n/a` justificado.
- [ ] **REPROVADO** — pelo menos um `fail`. Liste no relatório quais e proponha estórias de correção.

Preencha o relatório final em `report.md` usando o que você observou aqui.
