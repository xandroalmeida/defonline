---
wave_id: WAVE-2026-02
slug: rascunho-comercializacao-e-cobertura
title: Comercialização + recorrência (rascunho — DRM como épico âncora)
status: planned
created_at: 2026-05-20
updated_at: 2026-08-04
related_pdr: PDR-003
note: Rascunho. Detalhamento real só após retro da WAVE-2026-01 e aprendizado do beta fechado. Em 2026-08-04 o EPIC-010 (DRM) foi inserido como épico âncora — ver PDR-003.
---

# WAVE-2026-02 — Rascunho

Este arquivo é **rascunho** da próxima onda. Só épicos e outcomes, sem estórias detalhadas. Será revisitado no fim da WAVE-2026-01 com base no aprendizado real do beta fechado — a hipótese pode confirmar parcialmente, falhar, ou apontar caminho diferente. **Não tratar este conteúdo como compromisso.**

## Direção tentativa

Se a hipótese central da onda 1 se confirmar com Roberto (NPS sustentado, recorrência inicial visível, feedback qualitativo convergente), a onda 2 desbloqueia comercialização e abre o produto para Joana e — em planejamento — para Marcos.

Se a hipótese **não** se confirmar, esta onda muda inteiramente. Cenários possíveis: revisitar o motor/relatório (insistir e iterar), reorientar persona alvo (pivot suave para Marcos como cliente B2B do início), ou — em caso extremo — repensar a tese central via PDR.

## Épicos esboçados (a confirmar)

> **Atualização de 2026-08-04.** O feedback do Key User José Marques (03/08/2026), com parecer favorável da EBC, gerou o **EPIC-010 — DRM (Demonstração de Resultado Mensal)**, que passa a ser o **épico âncora desta onda**, à frente do EPIC-006. Dois motivos, registrados no `PDR-003`: o DRM entrega Comércio e Serviços **sem depender** da validação externa de faixas que bloqueia o EPIC-006, e cria a recorrência mensal que o EPIC-005 pressupõe para fazer sentido comercial. Sequência revisada: **EPIC-010 → EPIC-005 → EPIC-007 → EPIC-006 → EPIC-008 → EPIC-009**.

- **EPIC-010 — DRM: Demonstração de Resultado Mensal.** O dono de um pequeno negócio — indústria, comércio ou serviços — lança o movimento do mês em poucos minutos e vê se deu lucro, quanto sobrou depois da retirada dele, e como o mês se compara aos anteriores. Sem cobrança; instrumentado para medir adesão antes de o EPIC-005 investir em gateway. Outcome: o produto passa a servir de fato MEI e Simples Nacional, e ganha um motivo mensal para o usuário voltar. Detalhado em `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`; decisão em `decisions/pdr/PDR-003-modulo-drm-raio-x-do-negocio.md`; parecer em `../../revisao/analise-po-feedback-jose-marques-2026ago03.md`.

> Nota: a numeração foi deslocada em 2026-05-24 — o `EPIC-004` deixou de ser placeholder de "Cobrança e Plano Básico" e foi atribuído ao **App shell + materialização do design system v1** dentro da WAVE-2026-01 (ver `roadmap/current-wave.md`). Todos os placeholders abaixo subiram um número.

- **EPIC-005 — Cobrança e planos.** Roberto ativa o trial de 7 dias com 1 análise, assina um plano e compra créditos avulsos, com Pix e cartão recorrente via Asaas (provedor substituível). A equipe gere planos, pacotes e cupons em tela no backoffice, sem nada fixo em código, e os usuários do beta recebem cupom. O DRM entra em todos os planos pagos e no trial. Decisões em `PDR-020`. Outcome: produto comercializável.

- **EPIC-006 — Cobertura de Comércio e Serviços.** Quiz, motor de cálculo e matriz de recomendações estendidos para os setores Comércio e Serviços. Joana e autônomos passam a ter relatório com semáforo e recomendações específicas. Outcome: produto serve a maioria das MPEs brasileiras, não só Indústria.

- **EPIC-007 — Exportação do relatório em PDF.** Gera versão A4 com semáforo colorido, glossário, recomendações por indicador. Outcome: Roberto leva o relatório para a reunião com o banco/sócio.

- **EPIC-008 — Solicitação de análise primária de captação.** Formulário interno conectado ao relatório do diagnóstico; equipe EBP responde em até 5 dias úteis. Sem cálculo automático no MVP (espec §1.4.1 + roadmap §2.2). Outcome: Roberto pede ajuda para dimensionar captação concreta.

- **EPIC-009 — Hotsite público + Central de Ajuda + suporte por e-mail.** Substitui o acesso por convite por aquisição orgânica. Landing page, FAQ, primeiros artigos de SEO (parecer CLAUDE §2.2 — meta de 15–20 artigos pré-go-live). Outcome: Roberto descobre o produto sem convite.

## Outcomes esperados ao fim desta onda

Se esta onda fechar com sucesso, o DEFOnline alcança "MVP comercializável" — produto pago, três setores, PDF, captação manual, descoberta orgânica. Norte star (MPEs ativas em 90d) começa a se mover além do beta.

## Riscos antecipados

- **LGPD e jurídico:** a revisão do Termo de Adesão por advogado (NRF §8) segue pendente, mas deixou de bloquear o EPIC-005 (PDR-020). Risco aceito pelo PO. A versão `v2` dos Termos já traz as cláusulas comerciais, ainda sem revisão jurídica.
- **Gateway de pagamento:** Asaas (PDR-020). Falta a ADR da abstração do provedor.
- **Matriz multi-setor:** validação externa das faixas para Comércio e Serviços (NRF §9.3) precisa estar pronta antes do EPIC-006. **O EPIC-010 não depende dela** — é justamente por isso que vem antes.
- **Atrito do lançamento mensal (EPIC-010):** até 36 campos monetários, doze vezes por ano. É o risco nº 1 da onda e é de retenção, não de construção. Mitigado por três mecanismos obrigatórios no MVP (PDR-003, decisão 6) e detectado pela métrica primária do épico — 2º lançamento em 45 dias.
- **Pendências editoriais da EBC (EPIC-010):** `[DECIDIR-B]` e `[DECIDIR-E]` bloqueiam o motor do DRM. Consulta única recomendada antes da abertura do sprint; defaults registrados em cada estória.

## O que NÃO entra nesta onda

- Plano Pro com fluxo de contador (Marcos) — onda posterior. Motivo: ainda exige refinamento sobre portfólio multi-cliente e workflow recorrente.
- Apps móveis, internacionalização, API pública — roadmap §5, §4.3 (v2.0).
- Backoffice EBP completo — roadmap §6.1 (v1.2). A fatia só-leitura (usuários/empresas) foi antecipada no EPIC-011 (PDR-011).
- Mecanismo de feedback 👍/👎 por recomendação — roadmap §1.4 (cabe aqui só se beta da onda 1 indicar necessidade urgente).

## Histórico

- 2026-05-20 — Rascunho inicial criado junto com a abertura da WAVE-2026-01.
- 2026-11-05 — Decisões de cobrança registradas no `PDR-020`: catálogo em tela, trial ativado, Asaas substituível, Termos `v2` sem revisão jurídica, medição do DRM sem bloqueio, DRM nos planos e cupons, inclusive para o beta.
- 2026-08-04 — **EPIC-010 (DRM) inserido como épico âncora**, à frente do EPIC-006, com 13 estórias detalhadas. Origem: feedback do Key User José Marques (03/08/2026) + parecer favorável da EBC. Decisão em PDR-003, parecer do PO em `../../revisao/analise-po-feedback-jose-marques-2026ago03.md`. Sequência da onda revisada e dois riscos novos registrados. Exceção deliberada à regra de "só épicos e outcomes" neste arquivo: o EPIC-010 foi detalhado antes da retro da onda 1 porque o feedback do Key User e o parecer da EBC já dão base suficiente, e porque as decisões pendentes da EBC têm prazo longo — adiar a especificação atrasaria a consulta, não o contrário.
- 2026-05-24 — Numeração dos placeholders deslocada (+1) porque o `EPIC-004` foi reatribuído ao "App shell + materialização do design system v1" dentro da WAVE-2026-01 (ver `roadmap/current-wave.md`). Cobrança virou EPIC-005, Cobertura virou EPIC-006, PDF virou EPIC-007, Captação virou EPIC-008, Hotsite virou EPIC-009. Riscos atualizados para refletir nova numeração.
