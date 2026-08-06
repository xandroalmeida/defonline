---
epic_id: EPIC-010
slug: drm-demonstracao-resultado-mensal
title: "DRM — Demonstração de Resultado Mensal (o negócio deu lucro este mês?)"
wave: WAVE-2026-02
status: in_progress
owner_role: po
created_at: 2026-08-04
updated_at: 2026-08-04
target_completion: 2026-10-31
related_pdr: [PDR-003, PDR-004]
origem: "Feedback do Key User José Marques, 03/08/2026 — parecer favorável da EBC"
---

# EPIC-010 — DRM: Demonstração de Resultado Mensal

## Por que existimos (problema do usuário)

A Joana fecha o mês sem saber se ganhou ou perdeu dinheiro. Ela sabe quanto entrou na conta e sabe que pagou muita coisa, mas a diferença entre as duas não é lucro — tem estoque comprado, tem imposto, tem a taxa da maquininha, tem o dinheiro que ela mesma tirou para viver. No fim do mês, o saldo do banco conta uma história e o negócio conta outra. Ela decide preço, contratação e compra no escuro, e só descobre que o ano foi ruim quando o contador fecha o balanço, meses depois, quando não dá mais para corrigir.

O DEF Online hoje não resolve isso. O Diagnóstico entregue no EPIC-002 pede 23 campos, seis deles de Balanço Patrimonial — estoques, recebíveis, imobilizado, dívidas. Quem tem esses números tem escrita contábil, e quem tem escrita contábil não é MEI nem, na maioria das vezes, optante do Simples. O Key User disse isso diretamente: só 5 das 15 perguntas que ele viu são "palatáveis e assimiláveis" pelo público-alvo. A EBC concordou. Construímos um produto excelente para uma persona que não é a que dissemos servir.

Há um segundo problema, nosso e não do usuário: o Diagnóstico é episódico. O usuário volta quando enfrenta uma decisão grande — uma ou duas vezes por ano. Nosso north star mede atividade em janela de 90 dias, e o modelo de assinatura do EPIC-005 pressupõe um motivo para voltar todo mês. Esse motivo não existe hoje.

## Resultado esperado (outcome)

Ao fim deste épico, **o dono de um pequeno negócio — indústria, comércio ou serviços — lança o movimento do mês em poucos minutos e vê, na tela, se o negócio deu lucro, quanto sobrou depois da retirada dele, e como esse mês se compara aos anteriores.**

## Métrica de sucesso

- **Primária — recorrência:** ≥ 40% dos usuários que concluem o 1º lançamento concluem o **2º** em até 45 dias. É a métrica que define se o módulo é um produto ou uma curiosidade. Janela: D+90 após o épico ir para homologação.
- **Secundária — atrito:** ≥ 70% dos lançamentos iniciados são concluídos; tempo mediano do 2º lançamento em diante ≤ 5 minutos. Valida diretamente os mecanismos anti-atrito do PDR-003 (decisão 6).
- **Secundária — alcance:** ao menos 30% dos lançamentos vêm de empresas de Comércio ou Serviços. Valida a hipótese de que o DRM alcança o público que o Diagnóstico não alcança.
- **Qualidade:** zero divergência entre o valor exibido na tela e o recalculado a partir do snapshot; zero erro 5xx no fluxo de lançamento durante a primeira semana.

## Entregável visível no fim do épico

- [ ] Usuário logado escolhe uma Empresa Analisada e lança a competência do mês em homologação.
- [ ] A Demonstração de Resultado Mensal é exibida com **valor nominal e percentual sobre a receita em cada linha** — atendendo diretamente ao pedido do Key User.
- [ ] O usuário corrige um mês já lançado, e a tela sinaliza a retificação sem perder o histórico.
- [ ] A partir do 2º lançamento, a tela mostra a variação em relação ao mês anterior; a partir do 4º, a comparação com a média dos meses anteriores.
- [ ] Cada linha do resultado traz um farol e uma leitura em português simples.
- [ ] O usuário exporta a demonstração em planilha e em PDF.
- [ ] Um lançamento em cada setor — Indústria, Comércio e Serviços — produz demonstração correta, com os campos irrelevantes ausentes do formulário.

## Fora de escopo (explicitamente)

- **Cobrança, assinatura, cota ou paywall.** PDR-003, decisão 8. A assinatura sugerida pelo Key User (R$ 600/ano ou 12× R$ 50) é insumo do EPIC-005.
- **Vínculo entre DRM e Diagnóstico.** Nenhum pré-preenchimento do quiz a partir dos 12 meses de DRM, nenhuma leitura cruzada. Os módulos são independentes (PDR-003, decisão 1). O modelo de dados não deve *impedir* o vínculo futuro, mas não o implementa.
- **Balanço Patrimonial e fluxo de caixa projetado.** O DRM é resultado do mês, em regime de caixa. Outro módulo, outra hora.
- **Importação automática** de extrato bancário, nota fiscal ou ERP. É a evolução natural se o atrito manual se mostrar insuportável — e é justamente o que a métrica primária vai nos dizer.
- **Regime de competência.** Só caixa. Provisionar é sugestão de tooltip, não funcionalidade.
- **Recálculo retroativo** de lançamentos antigos quando o motor evoluir. Snapshot é snapshot.
- **Multiusuário na mesma empresa** e perfil de contador. Segue valendo tenant = Usuário.

## Referências

- `defonline-docs/project-state/decisions/pdr/PDR-003-modulo-drm-raio-x-do-negocio.md` — decisão de produto e escopo
- `defonline-docs/revisao/analise-po-feedback-jose-marques-2026ago03.md` — parecer do PO, achados F1–F12
- `defonline-docs/especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` — campos, validações, adaptação por setor
- `defonline-docs/project-state/epics/EPIC-010-drm-demonstracao-resultado-mensal/design/estrutura-drm.md` — estrutura aritmética canônica
- `defonline-docs/especificacao/V2/design-system.md` e `requisitos-nao-funcionais-e-juridicos.md`
- `defonline-docs/project-state/decisions/adr/ADR-003-persistencia.md`, `decisions/idr/IDR-009-*`, `decisions/idr/IDR-010-*`

## Dependências

- **Bloqueia:** EPIC-005 (cobrança) — o modelo de assinatura pressupõe a recorrência que este épico cria.
- **Bloqueado por:** EPIC-001 (Empresa Analisada) e EPIC-004 (app shell + design system) — ambos `done`. EPIC-002 não é pré-requisito.
- **Decisões arquiteturais necessárias:** versionamento e retificação de competência — **resolvida em 04/08/2026 pelo `ADR-007`** (`STORY-045` `done`; `STORY-047` a `STORY-051` destravadas); motor de exportação em planilha e PDF (`STORY-053`, compartilhado com EPIC-007), ainda aberta como spike **antes** da implementação correspondente.
- **Decisões de negócio:** `[DECIDIR-B]`, `[DECIDIR-C]`, `[DECIDIR-D]` e `[DECIDIR-E]` **resolvidas em 04/08/2026** pelo `PDR-004` — a `STORY-048` está desbloqueada. Permanecem: `[DECIDIR-A]` (nome "Raio X do Negócio", não bloqueia nada) e as **faixas de farol por setor**, que exigem validação externa da EBC e mantêm a `STORY-052` bloqueada.

## Estórias

**Checkpoint 1 — caminho feliz vivo** (lança um mês e vê a demonstração)

- [x] STORY-045 (spike, arquiteto) — Modelo de dados, versionamento e retificação de competência → **`ADR-007` accepted (04/08/2026)**
- [ ] STORY-046 (design) — Specs das telas do módulo DRM
- [ ] STORY-047 — Lançamento mensal: formulário adaptativo, modos agregado/detalhado, rascunho
- [ ] STORY-048 — Motor do DRM: estrutura canônica, três setores, nominal e percentual
- [ ] STORY-049 — Tela da Demonstração do Mês

**Checkpoint 2 — o módulo vira produto de recorrência**

- [ ] STORY-050 — Retificação de competência
- [ ] STORY-051 — Série histórica e comparativos
- [ ] STORY-052 (enablement) — Pacote de conteúdo EBC: rótulos, tooltips, faixas de farol, textos de leitura

**Checkpoint 3 — leitura e saída**

- [ ] STORY-053 (spike, arquiteto) — Motor de exportação: planilha e PDF
- [ ] STORY-058 (spike, arquiteto) — Provisão de férias e 13º: onde mora o saldo
- [ ] STORY-054 — Faróis e leitura em linguagem simples
- [ ] STORY-055 — Exportação em planilha e PDF
- [ ] STORY-056 — Eventos analíticos do módulo DRM

**Fechamento**

- [ ] STORY-057 (validação) — Validação final do épico

## Validação final

Critérios em `validation/checklist.md`. Relatório do validador em `validation/report.md`.

**Definição de épico concluído:** todas as estórias `done` + relatório de validação `approved` + os sete itens do entregável visível demonstrados em homologação, com pelo menos um lançamento real por setor.

## Riscos

| Risco | Impacto | Mitigação |
|---|---|---|
| **Atrito de 36 campos derruba a recorrência** | Alto — mata a métrica primária | Três mecanismos obrigatórios no MVP (PDR-003, decisão 6). É o risco nº 1 do épico e o motivo de a métrica primária ser o 2º lançamento, não o 1º |
| Faixas de farol sem validação externa | Médio — módulo entrega sem semáforo | Faixa não validada simplesmente não recebe farol. A demonstração e os comparativos entregam valor sem ele; o farol é camada adicional, não requisito |
| **Provisão de férias e 13º (`PDR-004`, decisão D) não caber no épico** | Médio — expectativa de negócio não atendida nesta onda | Spike próprio (`STORY-058`) com três saídas legítimas, incluindo virar EPIC-011. A `STORY-051` entrega o tratamento interino independentemente, e responde à dor concreta |
| Dois modos de pró-labore dobram a superfície de teste | Baixo | O modo é apresentação, não cálculo — o custo é de teste de tela e exportação, não de modelagem. Invariante testável: Lucro Líquido idêntico nos dois modos |
| ~~Retificação conflita com o IDR-010~~ | **Resolvido** | O `ADR-007` mostrou que a colisão era aparente — ela some ao separar *imutabilidade do registro* de *mutabilidade do conceito*. O IDR-010 é **estendido**, não revogado nem restringido, e nenhuma linha dele mudou |
| ~~Divergência de EBITDA entre DRM e Diagnóstico~~ | **Resolvido** | O `PDR-004` tornou a posição do pró-labore parametrizável: quem quiser reconciliar os dois módulos liga o modo "dentro" e os números passam a conversar |
| PDF abre frente técnica nova | Médio — é o EPIC-007 antecipado | Spike próprio (STORY-053), no Checkpoint 3; se a decisão for cara, a exportação em planilha entrega sozinha e o PDF volta ao EPIC-007 |

## Histórico

- 2026-08-04 — criado pelo PO a partir do feedback do Key User José Marques (03/08/2026) e do parecer favorável da EBC. Decisão de escopo em PDR-003.
- 2026-08-04 — **`ADR-007` aceito** (spike `STORY-045`). Modelo de dados do DRM fechado: três tabelas `drm_*`, competência versionada append-only com ponteiro de vigência, snapshot imutável por versão carregando os dois modos de pró-labore, comparativos e faróis derivados na leitura. Risco "retificação conflita com o IDR-010" **eliminado** — o IDR-010 é estendido, não revogado. `STORY-047` a `STORY-051` destravadas; o Checkpoint 1 pode começar. Duas consequências para estórias adiante: a `STORY-054` precisa deixar legível que o farol é leitura (pode mudar de cor retroativamente quando a EBC calibrar as faixas; valores nunca mudam), e a `STORY-049` precisa renderizar o snapshot genericamente, sem hardcode das linhas.
- 2026-08-04 — `PDR-004` resolveu as quatro decisões de negócio pendentes. `STORY-048` desbloqueada e promovida a `L` (dois modos de pró-labore + linhas não operacionais); `STORY-049` e `STORY-051` ajustadas; `STORY-058` (spike de provisão) criada; `STORY-052` segue bloqueada só pelas faixas de farol. Risco de divergência de EBITDA eliminado pela parametrização.
