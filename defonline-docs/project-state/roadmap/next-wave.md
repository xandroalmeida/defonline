---
wave_id: WAVE-2026-02
slug: rascunho-comercializacao-e-cobertura
title: Comercialização + cobertura de setores (rascunho)
status: planned
created_at: 2026-05-20
note: Rascunho. Detalhamento real só após retro da WAVE-2026-01 e aprendizado do beta fechado.
---

# WAVE-2026-02 — Rascunho

Este arquivo é **rascunho** da próxima onda. Só épicos e outcomes, sem estórias detalhadas. Será revisitado no fim da WAVE-2026-01 com base no aprendizado real do beta fechado — a hipótese pode confirmar parcialmente, falhar, ou apontar caminho diferente. **Não tratar este conteúdo como compromisso.**

## Direção tentativa

Se a hipótese central da onda 1 se confirmar com Roberto (NPS sustentado, recorrência inicial visível, feedback qualitativo convergente), a onda 2 desbloqueia comercialização e abre o produto para Joana e — em planejamento — para Marcos.

Se a hipótese **não** se confirmar, esta onda muda inteiramente. Cenários possíveis: revisitar o motor/relatório (insistir e iterar), reorientar persona alvo (pivot suave para Marcos como cliente B2B do início), ou — em caso extremo — repensar a tese central via PDR.

## Épicos esboçados (a confirmar)

- **EPIC-004 — Cobrança e Plano Básico.** Roberto consegue assinar o Plano Básico (R$ 49,90/mês) e comprar créditos avulsos. Pix e cartão recorrente. Trial gratuito (`[DECIDIR]` 2.3 da spec) ainda pendente — decisão até abertura efetiva desta onda. Outcome: produto comercializável para o setor Indústria.

- **EPIC-005 — Cobertura de Comércio e Serviços.** Quiz, motor de cálculo e matriz de recomendações estendidos para os setores Comércio e Serviços. Joana e autônomos passam a ter relatório com semáforo e recomendações específicas. Outcome: produto serve a maioria das MPEs brasileiras, não só Indústria.

- **EPIC-006 — Exportação do relatório em PDF.** Gera versão A4 com semáforo colorido, glossário, recomendações por indicador. Outcome: Roberto leva o relatório para a reunião com o banco/sócio.

- **EPIC-007 — Solicitação de análise primária de captação.** Formulário interno conectado ao relatório do diagnóstico; equipe EBP responde em até 5 dias úteis. Sem cálculo automático no MVP (espec §1.4.1 + roadmap §2.2). Outcome: Roberto pede ajuda para dimensionar captação concreta.

- **EPIC-008 — Hotsite público + Central de Ajuda + suporte por e-mail.** Substitui o acesso por convite por aquisição orgânica. Landing page, FAQ, primeiros artigos de SEO (parecer CLAUDE §2.2 — meta de 15–20 artigos pré-go-live). Outcome: Roberto descobre o produto sem convite.

## Outcomes esperados ao fim desta onda

Se esta onda fechar com sucesso, o DEFOnline alcança "MVP comercializável" — produto pago, três setores, PDF, captação manual, descoberta orgânica. Norte star (MPEs ativas em 90d) começa a se mover além do beta.

## Riscos antecipados

- **LGPD e jurídico:** Termo de Adesão revisado por advogado (NRF §8) é gatilho que destrava EPIC-004; sem ele, não se pode cobrar.
- **Gateway de pagamento:** ADR específica de gateway sai como spike antecipado, talvez no fim da WAVE-2026-01.
- **Matriz multi-setor:** validação externa das faixas para Comércio e Serviços (NRF §9.3) precisa estar pronta antes do EPIC-005.

## O que NÃO entra nesta onda

- Plano Pro com fluxo de contador (Marcos) — onda posterior. Motivo: ainda exige refinamento sobre portfólio multi-cliente e workflow recorrente.
- Apps móveis, internacionalização, API pública — roadmap §5, §4.3 (v2.0).
- Backoffice EBP completo — roadmap §6.1 (v1.2).
- Mecanismo de feedback 👍/👎 por recomendação — roadmap §1.4 (cabe aqui só se beta da onda 1 indicar necessidade urgente).

## Histórico

- 2026-05-20 — Rascunho inicial criado junto com a abertura da WAVE-2026-01.
