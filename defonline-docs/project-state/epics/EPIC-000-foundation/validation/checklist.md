---
epic_id: EPIC-000
type: validation-checklist
created_at: 2026-05-20
---

# Checklist de validação — EPIC-000 Foundation

> **Para o validador:** execute cada item em ordem. Para cada um, registre status `pass | fail | n/a` e evidência (link, screenshot, log). Não invente resultados. Em caso de falha, **não tente consertar** — registre e devolva para o PO.

**Nota sobre o EPIC-000:** este é o épico de fundação técnica. Alguns itens do checklist padrão do PO (`templates/validation-checklist.md`) não se aplicam ou estão atenuados — por exemplo, "cobertura ≥ 98% em regras de negócio" não tem regra de negócio para validar nesta fase. Itens não aplicáveis estão marcados como `n/a justificado`.

## 1. Critérios de aceite das estórias

- [ ] Todas as 8 estórias do EPIC-000 estão com `status: done` no `index.json` (incluindo as 6 spikes com ADRs `accepted`).
- [ ] Cada critério de aceite (CA) listado em cada `story.md` foi exercido pelo entregável (ADR aceita, no caso de spike, ou teste automatizado no caso de STORY-007).

## 2. Cobertura de testes

- [ ] Cobertura unitária do código novo do EPIC-000 ≥ **80%** (evidência: relatório do CI da STORY-007).
- [ ] **`n/a justificado`** para cobertura ≥ 98% em regras de negócio — EPIC-000 não introduz regras de negócio (Foundation técnica). Registrar como `n/a` com este motivo.
- [ ] Há ao menos 1 teste E2E rodando contra homologação real (não mock), validando a página viva.
- [ ] Testes E2E rodam em browser real via automação (princípio da skill PO).

## 3. Automação

- [ ] Setup de ambiente local automatizado (um comando) — **testado em máquina limpa** pelo validador.
- [ ] Pipeline CI verde no branch principal após o EPIC-000.
- [ ] Deploy para homologação automatizado e disparado pelo pipeline a cada merge na main.
- [ ] Provisionamento dos ambientes de homologação (e plano de produção) é Infra-as-Code conforme a ADR de Infra.
- [ ] Migrations rodam automaticamente no deploy de homologação.

## 4. Funcionalidade observável

- [ ] Entregável do `epic.md` está acessível: página "hello DEFOnline" rodando em URL pública de homologação.
- [ ] A página exibe nome do produto, versão deployada e indicador de healthcheck `OK`.
- [ ] Validador consegue percorrer manualmente: clonar repositório → subir local em 1 comando → rodar testes → fazer mudança trivial → abrir PR → mergear → ver a mudança em homologação.
- [ ] Healthcheck (`/health` ou path equivalente) responde 200 OK + JSON com status + versão.
- [ ] Log estruturado é emitido na inicialização conforme ADR de Observabilidade.

## 5. Qualidade transversal

- [ ] Nenhum aviso crítico de segurança aberto introduzido pelo épico (scanner do CI, se houver na ADR).
- [ ] **`n/a justificado`** para "migrações reversíveis testadas" — migration inicial pode ser apenas a tabela `evento_produto` ou vazia. Validador anota e justifica se for `n/a`.
- [ ] **`n/a justificado`** para "tratamento de dados pessoais LGPD" — EPIC-000 não toca em dado pessoal de Usuário ou Empresa Analisada (entra no EPIC-001).
- [ ] Mascaramento de PII em log está implementado conforme ADR de Observabilidade (testar com um caso fictício se não houver PII real).

## 6. Documentação e índice

- [ ] README do repositório explica: como subir local, como rodar testes, como funcionam os 3 ambientes, onde estão as ADRs.
- [ ] As 6 ADRs do EPIC-000 estão em `decisions/adr/` com `status: accepted` e indexadas no `index.json`.
- [ ] Notas do agente em cada estória do EPIC-000 estão preenchidas (decisões locais, descobertas, IDRs criados, links de evidência).

## 7. Veredito

- [ ] **APROVADO** — todos os itens acima `pass` ou `n/a justificado`.
- [ ] **REPROVADO** — pelo menos um `fail`. Liste no relatório quais e proponha estórias de correção.

Preencha o relatório final em `report.md` usando o que você observou aqui.
