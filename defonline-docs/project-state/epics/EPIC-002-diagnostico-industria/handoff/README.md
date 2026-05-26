---
artifact: epic-handoff
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
written_by: po (alexandro)
written_at: 2026-05-26
target_role: comercial / implantação
status: ready-for-pickup
related_validation: validation/report.md
---

# Handoff técnico — EPIC-002 (Diagnóstico Indústria) → comercial/implantação

> **PRÉ-REQUISITO PARA ABRIR O BETA.** A **STORY-036 (validação externa NRF §9.3)** ainda **não foi executada** — decisão do PO em 2026-05-26 de adiar a contratação do especialista externo. **O beta fechado não pode rodar com usuário real até a STORY-036 voltar com veredito `approved` ou `approved_with_pending`.** Esta condição precisa ser respeitada por comercial/implantação antes de qualquer convite ao Roberto.

## 1. Status do épico

EPIC-002 foi promovido a **`done_under_review`** (não `done` puro) no `index.json` após validação independente em 2026-05-25 — veredito `approved_with_pending` com 0 falhas bloqueantes (ver `validation/report.md`). Sob ótica técnica o produto está pronto; sob ótica regulatória (§9.3), falta a validação externa do motor.

**O que o produto faz hoje em homol:** Roberto autenticado seleciona Empresa Analisada Indústria, preenche quiz com 23 campos do Anexo A, recebe o relatório com 14 indicadores + faróis + Resumo Executivo (§4.7.1) + recomendações da matriz DEZ/2025 (Anexo F coluna Indústria) + tooltips por campo + validações cruzadas DRE × Balanço acionáveis + eventos analíticos `quiz_iniciado` / `diagnostico_concluido` capturados em `evento_produto`.

## 2. Ambiente

| Item | Valor |
|---|---|
| URL homologação | `https://defonline.xandrix.com.br` |
| Versão do motor | `motor_version = "1.3.0"` |
| Versão da matriz | `matrix_version = "dez-2025"` |
| Última tag rc | (a confirmar: `v1.0.0` ou equivalente — ver §10 ações pendentes) |
| Branch | `main` |

**Pré-validação:** confirmar com tech que o rc da SPRINT-2026-W25 está deployado em homol antes de qualquer abordagem ao Roberto. A validação independente do épico foi rodada em `localhost:8090` (dev), não em homol — registrado como F-NB-1 do relatório.

## 3. Criar conta de teste + Empresa Analisada (5 minutos)

Procedimento sugerido para comercial/implantação familiarizar-se com o fluxo antes do primeiro Roberto real.

1. Abrir homologação → "Criar conta grátis".
2. Cadastrar com e-mail válido (recebe link de confirmação por e-mail real).
3. Confirmar e-mail, fazer login.
4. "Adicionar Empresa" — usar CNPJ real de teste (consulta automática à RFB preenche razão social, UF, município).
5. Selecionar setor **Indústria** (Comércio e Serviços aparecem como "Em breve — Onda 2" — ver §7).
6. Pronto. Empresa cadastrada, hora de rodar um diagnóstico.

## 4. Smoke E2E reproduzível (15 minutos)

1. `/diagnosticos/novo` → selecionar a Empresa Analisada criada.
2. Bloco 1 (Identificação) → confirmar setor Indústria → Próximo.
3. Bloco 2 (DRE/Operação) → preencher os 9 campos com valores realistas (ou copiar fixture `quiz_industria_saudavel` para testar caminho feliz: Q08=50k, Q09=100k, Q14=20k, Q15=8k, Q16=2k, Q10=90, Q11=15, Q12=15, Q13=2%) → Próximo.
4. Bloco 3 (Balanço) → preencher 6 campos (saudável: Q02=50k, Q03=20k, Q04=10k, Q05=300k, Q06=40k, Q07=80k) → Próximo.
5. Bloco 4 (Captação) → "Não" para Q17 → "Calcular diagnóstico".
6. Em ≤ 3 segundos, relatório aparece com:
    - Bloco "Resumo Executivo" no topo (verde para o payload acima).
    - Tabela com 13 indicadores + farol + texto da matriz EBC.
    - Card "NCG — Necessidade de Capital de Giro" (informativo, sem farol).
    - Card "Ciclo Operacional" (informativo).
    - Glossário inline (Anexo I).
    - Rodapé: `motor v1.3.0 · matriz dez-2025 · gerado em …`.

**Para testar validação cruzada (R3 — passivo > ativo):** no Bloco 3, usar Q06=80k + Q07=20k (passivo 100k) e Q02..Q05 = 10k cada (ativo 40k). Ao clicar Próximo, banner amarelo aparece com mensagem acionável + botões "Revisar Balanço" e "Continuar mesmo assim". Aceitar e prosseguir confirma persistência do `alertas_aceitos` no payload.

## 5. Evidências de qualidade técnica (já arquivadas em `validation/evidence/`)

| Métrica | Resultado | Limite | Arquivo |
|---|---|---|---|
| p95 do relatório | **20 ms** em 50 navegações | ≤ 3000 ms | `A-3-p95.txt` |
| Cobertura motor | **99.6%** | ≥ 98% | `A-1-coverage.txt` |
| Cobertura geral | **97.3%** | ≥ 80% | `A-1b-coverage-geral.txt` |
| Casos de teste por fórmula | **204** (11–17 por indicador) | ≥ 140 (10/fórmula) | `A-2-cobertura-por-formula.txt` |
| Suíte Pest | **787 verdes** (2424 assertions) | 0 falhas | `A-1b-coverage-geral.txt` |
| Suíte Dusk | **17 verdes** (105 assertions) | 0 falhas | `A-5-dusk.txt` |
| Eventos analíticos | payload conforme ADR-004 §2.2, 0 PII, `request_id` v7 | — | `B-10`, `D-6`, `D-7` |
| Cross-tenant | HTTP **404** (IDR-009, sem leak) | 404 (não 403) | `D-8-cross-tenant.txt` |

> **Nota.** Todas as medições e SQLs foram coletados em `localhost:8090` (dev), não em homol. Re-rodar smoke read-only em homol após confirmar deploy é trabalho de tech ops antes do beta abrir (F-NB-1).

## 6. Parecer externo (NRF §9.3) — **PENDENTE**

| Item | Estado |
|---|---|
| Validador externo contratado | **Não — adiado por decisão PO 2026-05-26** |
| Parecer escrito em `validation/external-review.md` | Ausente |
| Veredito | N/A |

**Implicação para o beta:** veja boxe no topo deste documento. Beta fechado **não pode rodar** até este item virar `approved` ou `approved_with_pending`. Item registrado como débito no backlog (`backlog/POS-EPIC-002-validacao-externa.md`).

## 7. Escopo coberto vs fora

**Coberto na v1 (esta entrega):**

- Setor **Indústria** apenas.
- 14 indicadores do Anexo D (13 com farol + NCG abs informativo).
- Matriz DEZ/2025 (Anexo F), coluna Indústria.
- Resumo Executivo determinístico (§4.7.1).
- Validações cruzadas DRE × Balanço (§6.6) — R1/R2/R3 não-bloqueantes.
- Tooltips por campo no quiz (§6.8).
- Eventos `quiz_iniciado` e `diagnostico_concluido` (ADR-004 §2.2).
- Glossário inline (Anexo I).

**Fora de escopo (preservado):**

- Setores **Comércio** e **Serviços** — Onda 2 (EPIC-005).
- Exportação em **PDF** do relatório — Onda 2 (EPIC-006).
- **Solicitação de análise de captação** automática — Onda 2 (EPIC-007).
- **Compartilhamento** do relatório por link público ou e-mail — não previsto no MVP.
- **Feedback 👍/👎** por recomendação — roadmap §1.4.
- **Medianas setoriais** (benchmark) — roadmap §2.1.
- **IA** no Resumo Executivo ou recomendações — roadmap §2.3.
- **Edição do quiz após cálculo** — não permitido (relatório é read-only).

## 8. Feature flags ativas

**Nenhuma feature flag controla a entrega do EPIC-002.** Pennant está instalado no projeto (`config/pennant.php`) mas nenhuma feature foi definida nesta sprint — todo o comportamento descrito acima está **sempre ativo** em homol/prod uma vez deployado.

Quando feature flags entrarem em ondas futuras (ex.: ativar Comércio/Serviços), elas viverão em `App\Providers\AppServiceProvider::boot()` via `Feature::define(...)`.

## 9. Decisões abertas conhecidas (e como tratar se Roberto reclamar)

| Origem | Decisão pendente | Como tratar se aparecer no beta |
|---|---|---|
| Spec §6.4 | Expiração de rascunho (default 90 dias) | Default aceito; mensagem clara ao Roberto se o rascunho expirar. Reclamação → backlog do PO. |
| Spec §6.7 | Aviso de PL simplificado no relatório | Não exibido na v1. Se reclamarem, registrar e tratar como melhoria roadmap §1. |
| Spec §6.10 | Trial gratuito | Não ativo no MVP. Tratamento comercial: oferta de teste por convite. |
| F-NB-1 (validação) | Smoke em homol não confirmado | Tech ops valida deploy do rc + re-roda smoke read-only antes do primeiro convite. |
| F-NB-3 (validação) | Tag de release | Tech cria a tag quando handoff for assinado pelo comercial. |

## 10. Contato de suporte técnico durante o beta

| Papel | Contato | SLA |
|---|---|---|
| PO / dúvidas funcionais | Alexandro (xandroalmeida@gmail.com) | 1 dia útil |
| Suporte técnico / bugs em homol | (a definir antes do beta abrir) | 4 horas úteis |
| Validação externa do motor | (a contratar — STORY-036 / `backlog/POS-EPIC-002-validacao-externa.md`) | — |

## §Riscos

### R-1 — Validação externa adiada (NRF §9.3) — **CRÍTICO para liberar beta**

Decisão PO 2026-05-26 de adiar a contratação. Adia o gate regulatório que pré-condiciona o beta. **Mitigação:** comercial/implantação só envia convites depois que `validation/external-review.md` voltar com veredito não-`blocked`. Caso o adiamento se estenda além de 60 dias, PO reabre a decisão (custo de oportunidade do produto parado).

### R-2 — Sem dry-run interno do handoff (decisão PO 2026-05-26)

Este handoff vai direto para comercial/implantação como leitura, sem um representante real percorrer antes para reportar lacunas. **Risco aceito:** lacunas só aparecem no uso real. **Mitigação:** primeira sessão de uso (qualquer pessoa do time) reporta de volta ao PO para correção no handoff vivo; janela de feedback de 1 semana após a primeira leitura.

### R-3 — Snapshot retroativo não migrado

Diagnósticos persistidos em `motor_version` anteriores (`1.0.0` / `1.1.0` / `1.2.0`) continuam funcionando, mas exibem o texto da época (placeholder genérico "Faixa verde/amarela/vermelha"). Não há migração retroativa para o texto da matriz DEZ/2025. **Aceite:** snapshot é imutável por design (IDR-010); diagnósticos novos sempre saem com matriz vigente.

### R-4 — `matriz-lacunas.md` não materializa em runtime (F-NB-2 STORY-032)

Se a matriz futura tiver lacuna em algum par (indicador × farol), o auto-append do log fica silencioso no container de produção (path default fora do volume Docker). O `Log::warning` continua funcionando. **Mitigação:** quando a EBC entregar nova matriz, tech verifica os warnings nos logs e produz a lista de lacunas manualmente.

### R-5 — Pa11y formal não rodado (F-NB-1 STORY-033)

Auditoria automática de acessibilidade não foi executada nesta entrega. Cobertura funcional de teclado e ARIA está nos testes Dusk. **Mitigação:** rodar Pa11y na URL do quiz em homol assim que o rc subir; arquivar em `validation/evidence/`.

## Histórico

| Versão | Data | Autor | Mudança |
|---|---|---|---|
| 1.0 | 2026-05-26 | PO (Alexandro) | Primeira versão publicada para destravar F-NB-2 do relatório do Validador. |
