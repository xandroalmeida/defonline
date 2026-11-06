---
epic_id: EPIC-010
type: validation-report
validated_at: 2026-11-05
validated_by: validador (STORY-057)
verdict: rejected
checklist_source: epics/EPIC-010-drm-demonstracao-resultado-mensal/validation/checklist.md
commit_validado: 7a3150b (lane/drm, igual à main no início da validação)
---

# Relatório de Validação — EPIC-010 (DRM / Raio X do Negócio)

## TL;DR

> **Veredito: REJECTED.** O motor está correto. O formulário não: em lançamento novo, ele perde dígitos enquanto a pessoa digita, e o valor errado é gravado (D-1).
> **Contagem (67 itens):** 38 pass, 13 pass com ressalva, 6 fail (1 bloqueante, 5 não-bloqueantes), 1 n/a, 9 pendentes.
> **Próximo passo recomendado:** corrigir D-1 e repetir o percurso em rede lenta. Depois, decidir as pendências do PO e demonstrar os sete itens num ambiente de homologação.

## Resumo executivo

O EPIC-010 entrega o lançamento mensal, a demonstração com valor e percentual, a retificação, a série, a leitura em português e a exportação XLSX, nos três setores.

A correção aritmética foi aprovada. Foram 3.007 casos contra um oráculo independente, 7 casos feitos à mão, 382 células lidas na tela em 360 px e 1280 px e 300 colunas da planilha recalculadas no LibreOffice, sem nenhuma divergência. Snapshot, retificação, comparativos, autorização entre contas, acesso por plano e eventos sem dado pessoal também passaram.

O problema está antes do motor. Em campo que nunca foi preenchido, com 350–500 ms entre teclas e 100–300 ms de latência, digitar `123456` resultou em `134,56` em vez de `1.234,56`. O campo fica assim na tela, sem aviso, e esse valor segue para o snapshot. O 1º lançamento abre em modo detalhado, com até 37 campos, que é o caso mais exposto. Os testes existentes não pegam o defeito porque o Dusk envia o valor inteiro de uma vez.

**Sem homologação:** só existe o ambiente `defonline-prod`, e esta validação não teve acesso a ele. Os itens que exigem homologação ficam `pendente`, com a evidência local ao lado.

## Defeitos

### Bloqueante

#### D-1 — Dígitos perdidos ao digitar em campo ainda não preenchido

- **Onde:** `app/resources/views/components/drm/campo-monetario.blade.php:39` (`wire:model.live.debounce.300ms="valores.{campo}"`), junto da máscara das linhas 15–24.
- **Item:** 10.1. O motor calcula certo sobre o valor recebido, mas esse valor não é o que a pessoa digitou.
- **Observado:** digitando `123456` (esperado `1.234,56`), em 360×800, 2 repetições em 2 campos (D01 e D02), com variação de ±15% no intervalo:

| Latência emulada | Intervalo entre teclas | Campo não preenchido: errados | Campo já preenchido: errados | Exemplo |
|---|---|---|---|---|
| 0 ms | 350 ms | 1/4 | 0/4 | `134,56` |
| 100 ms | 350 ms | 3/4 | 0/4 | `134,56` |
| 300 ms | 350 ms | 4/4 | 0/4 | `134,56` |
| 300 ms | 500 ms | 4/4 | 0/4 | `134,56` |
| 0, 100 e 300 ms | 200 ms | 0/4 | 0/4 | — |
| 0 e 100 ms | 500 ms | 0/4 | 0/4 | — |
| 0, 100 e 300 ms | 800 ms | 0/4 | 0/4 | — |

- **Trocando de campo, no bloco de 21 itens (todos sem valor):**

| Tecla / pausa entre campos / latência | Campos errados | Exemplo (valor digitado → gravado) |
|---|---|---|
| 120 ms / 150 ms / 0 ms | 14/21 | `117,01` → `17,01` |
| 120 ms / 150 ms / 200 ms | 19/21 | `117,01` → `7,01` |
| 200 ms / 400 ms / 0 ms | 0/21 | — |
| 200 ms / 400 ms / 200 ms | 0/21 | — |
| 200 ms / 400 ms / 600 ms | 20/21 | `117,01` → `7,01` |
| 300 ms / 1000 ms / 600 ms | 21/21 | D16 a D21 → `0,01` |
| 300 ms / 1500 ms / 200 ms | 21/21 | `116,00` → `16,00` |
| Sem pausa entre campos (Dusk `type`) | 5/21 | campo fica vazio e grava `0,00` |

- **Reprodução:** com o interruptor de cobrança desligado, abrir `/empresas/{id}/drm/lancar` de uma empresa sem lançamento, em 360×800. Emular 300 ms de latência (CDP `Network.emulateNetworkConditions`) e digitar `123456` em "Quanto vendeu no mês", tecla a tecla, a cada 350 ms. O campo termina em `134,56`.
- **Por que é bloqueante:** o valor gravado difere do digitado, e o erro entra no snapshot e na série. A persona usa celular, onde esse ritmo e essa latência são plausíveis. O erro aparece no campo e na conferência, mas nada avisa a pessoa.
- **Causa provável (hipótese, não confirmada):** a resposta do Livewire à primeira atualização de uma chave ainda ausente em `valores` reescreve o campo, apagando as teclas digitadas nesse intervalo. Isso é coerente com a ausência de erros em campo já preenchido.
- **Não verificado:** digitação rápida trocando entre campos já preenchidos, o que também afeta a retificação. `resources/views/livewire/diagnostico/partials/campo-brl.blade.php:15` usa o mesmo padrão e não foi testado, por estar fora do épico.
- **Falta de cobertura:** nenhum teste digita tecla a tecla com intervalo. Vale incluir um teste assim na correção.

### Não-bloqueantes

#### D-2 — Comparativos da linha L7b dão o motivo errado e negam o acumulado

- **Onde:** `app/app/Domain/Drm/Serie/SerieDrm.php:131`, `:151` e `:179`. Linha omitida por estar zerada é tratada como `GRUPO_AGREGADO`. **Item:** 3.5.
- **Reprodução:** lançar meses com `D41 = D42 = 0` e depois um mês com `D41 = 500,00`. Na linha "(±) Outras receitas e despesas" desse mês, os três comparativos ficam indisponíveis com `indisponivel:grupo_agregado`: "esta linha não foi detalhada em algum dos meses comparados".
- **Esperado:** `estrutura-drm.md` §5 diz que o acumulado fica "sempre disponível a partir do 1º lançamento", e a linha não tem detalhamento. Nenhum valor errado é exibido.

#### D-3 — Portão local falha à noite: `FinanceiroTest`

- **Onde:** `app/tests/Feature/Livewire/Backoffice/FinanceiroTest.php:100-101`. **Item:** 9.1. **Fora do EPIC-010.**
- **Observado:** a suíte deu 1 failed, 3254 passed às 21:08 BRT. A falha se repetiu em 3 de 3 execuções isoladas, no filtro `de`/`ate` de hoje para a assinatura. Com isso, `scripts/verificar.sh` sai com erro.
- **Causa provável (hipótese):** relógio, porque depois das 21h BRT o dia em UTC já é outro. Não testei fora dessa janela, nem se o defeito está no teste ou no filtro.

#### D-4 — Contraste abaixo de AA no botão de trocar o modo

- **Onde:** `app/resources/views/livewire/drm/demonstracao.blade.php:79`, classe `.btn--link`. **Item:** 12.4.
- **Observado:** axe-core 4.10.2 mediu 4,44:1 (`#635bff` sobre `#f6f9fc`, 15,68 px), abaixo de 4,5:1.

#### D-5 — Status das estórias não permite fechar o épico

- **Item:** 1.1. No `index.json`, STORY-052 e STORY-058 estão `in_review`.
- As STORY-047 a 051 e 054 a 056 estão `done` no índice, mas `in_review` no frontmatter, com os CAs desmarcados.

#### D-6 — Divergências de documentação não corrigidas

- **Item:** 13.3. No Anexo K:
  - §K.11 aponta células de rótulo (D01 → C3, D40 → B48), quando os valores estão na coluna D. Já registrado nas notas da STORY-048.
  - D16 diz "ver D39", mas a retirada é D40.
  - §K.3.6 e §K.12 ainda dão `[DECIDIR-B]` e `[DECIDIR-A]` como pendentes.
  - O checklist fala em 7 campos e o §K.3 em 9. Na tela são 7 obrigatórios e 2 opcionais.
- `estrutura-drm.md` §1.2 não lista L8a/L8b no modo "dentro", mas o motor as emite.

### Observações de baixa gravidade (itens com ressalva, não contam como fail)

- **O-1 — Aceite vale para o código do alerta, não para o valor** (`app/app/Livewire/Drm/Lancamento.php:392`). Aceitei A3 com prejuízo de R$ 2.123,45, voltei e agravei para R$ 11.999,00. O mês fechou sem novo aceite. O valor novo aparece na tela e é o gravado.
- **O-2 — Imutabilidade só no evento do model** (`app/app/Models/DrmLancamento.php:88`). `update()` no model é recusado. `DrmLancamento::query()->update()` e SQL direto alteram a linha (testados com rollback), e não há trigger. `diagnosticos` tampouco tem trigger.
- **O-3 — Botão "Fechar o mês" não fica `disabled`** com alerta pendente (`lancamento.blade.php:310`): tem só `aria-disabled="true"`. O clique não fecha nada (0 lançamentos), pois o servidor recusa, e o motivo aparece na tela.
- **O-4 — "Teve algo fora do movimento normal" recolheu sozinho.** O bloco aberto por script se fechou com a resposta do servidor no 1º lançamento, e o Dusk falhou com `element not interactable`. Não reproduzi no 2º mês. Causa provável: `lancamento.blade.php:152`.
- **O-5 — Plural:** "1 meses lançados" (`app/config/drm/serie.php:69-71`).
- **O-6 — Ponto flutuante nas fórmulas do XLSX:** `299,999999999996` em vez de 300 quando há centavos na receita. O formato de duas casas exibe `300,00`, e o erro é menor que R$ 0,005.
- **O-7 — Teclado:** um `help-trigger` entre cada campo dobra o número de Tabs no bloco longo (42 paradas para 21 campos). Não há armadilha de foco e a ordem está correta.

## Checklist preenchido

Percursos locais em Chrome headless real via Dusk, contra `web-test`.

### Bloco 1 — Critérios de aceite

| Item | Status | Evidência |
|---|---|---|
| 1.1 Estórias 045–056 `done` | fail NB | D-5 |
| 1.2 CA com teste | ressalva | Cruzei CAs com os testes de cada estória. Sem menção literal: cobertura (047/13, 048/12, 049/13, 050/10, 051/14, 054/11, 055/11, 056/11), PDF `n/a` (055/3) e decisões da 052. 048/1e, 049/3c, 051/12, 054/2, 054/10 e 056/10 têm comportamento coberto sem citar o número. Foi amostragem, não auditoria teste a teste |
| 1.3 STORY-045 e 053 `accepted` | pass | ADR-007 e ADR-008 `accepted` no arquivo e no índice |
| 1.4 Pacote da STORY-052 | ressalva | Defaults no PDR-009 e em `config/drm/provisorio/`. Validação da EBC pendente |

### Bloco 2 — Correção aritmética (bloqueante)

| Item | Status | Evidência |
|---|---|---|
| 2.1 Valor de cada linha na tela × cálculo à mão, por setor | pass | 382 células (nominal e %), 7 casos, fora e dentro, 360 e 1280 px: **0 divergências** (A.1). Com digitação controlada; ver D-1 |
| 2.2 Percentuais | pass | Oráculo em `Decimal`: truncado em 4 casas, 1 casa meio-para-cima. 91.206 linhas, 0 divergências |
| 2.3 Igual à planilha | ressalva | 300 colunas e 4.500 células recalculadas no LibreOffice: 0 divergências em nominal e %. **O `.xlsx` original não está nesta máquina**: usei as fórmulas de `tests/Fixtures/Drm/planilha-drm.json`, fonte secundária |
| 2.4 Agregado = detalhado | ressalva | I-D×I-A, C-D×C-A, S-D×S-A idênticos em todas as linhas comuns. L8a, L8b e L6c só existem no detalhado, conforme o contrato §1 |
| 2.5 Golden tests | pass | 10 cenários passam. Em cópia descartável, somar 1 centavo em L5 quebrou o golden, e somar 1 centavo na base do % também. Código do repositório intacto |

### Bloco 3 — Casos de borda

| Item | Status | Evidência |
|---|---|---|
| 3.1 Sem faturamento | pass | Caso S-0: nominais presentes, % `null` com motivo, aviso na tela, sem exceção |
| 3.2 Prejuízo | pass | `−R$ 7.300,50`, `−24,3%`, selo "Prejuízo no mês", rótulo "Faltou depois da sua retirada" |
| 3.3 Lacuna na série | pass | Maio: "não lançado". Coluna em branco no XLSX |
| 3.4 Granularidades mistas | pass | `SerieDrmTest` passa; código devolve `GRUPO_AGREGADO` sem inferir. A tela só compara nível 1 |
| 3.5 Comparativos indisponíveis | fail NB | Condições de §5 corretas (1º mês, mês anterior ausente, menos de 3 meses), mas ver D-2 |
| 3.6 Atípico sinalizado e incluído | pass | A4 aceito; "Mês atípico" na série; acumulado R$ 13.000,00 = 3×4.000 + 1.000; média −75,0% |

### Bloco 4 — Retificação e integridade

| Item | Status | Evidência |
|---|---|---|
| 4.1 Versão anterior recuperável | pass | V1 de abril com `md5` de snapshot e payload iguais e `substituido_por_id` preenchido |
| 4.2 Sinalização | pass | Selo "Retificado", "corrigido 1×", série e aba Mês do XLSX |
| 4.3 Ajusta meses seguintes | pass | Julho, L11: vs. junho +66,7% → +25,0%; acumulado R$ 14.000 → R$ 17.000. Confere à mão |
| 4.4 Não retificadas intactas | pass | Março e julho: `md5`, hash e `updated_at` idênticos |
| 4.5 Audit log | pass | 2 registros `drm.lancamento.retificado` com autor e `occurred_at`, sem payload financeiro |
| 4.6 Imutabilidade do snapshot (pedida) | ressalva | O-2. `update()` no model recusado; o snapshot não muda na leitura |

### Bloco 5 — Adaptação por setor

| Item | Status | Evidência |
|---|---|---|
| 5.1 Campos do §K.4 | pass | Bloco 2 na tela: Indústria D04–D07; Comércio D04, D06, D07; Serviços D06, D07 |
| 5.2 Rótulo do custo | pass | CPV, CMV, CSP |
| 5.3 Suprimido = nulo | pass | Serviços: `D04 = null`, `D05 = null`. Comércio: `D05 = null` |

### Bloco 6 — Atrito

| Item | Status | Evidência |
|---|---|---|
| 6.1 Modo por grupo, alternável depois de salvo | pass | Os 4 grupos no agregado e detalhado; retificação agregado → detalhado gerou v2 com demonstração idêntica |
| 6.2 Pré-preenchimento | pass | "veio de setembro", 37 valores repetidos, conferência em lote, não fecha sozinho |
| 6.3 Rascunho | pass | Sessão apagada, novo login: "Retomando seu rascunho", D06 preservado |
| 6.4 Agregado em 7 campos | pass | Caso C-A: 7 obrigatórios e 2 opcionais recolhidos |
| 6.5 Percurso do 2º mês, 360×800, ≤ 5 min | ressalva | Dois toques: **0,8 s** (automação). Bloco a bloco com 7 campos alterados: **100,5 s**, em ritmo modelado (8 s por bloco, 6 s por campo, 15 s na conferência). Não é tempo humano |
| 6.6 Percurso com usuário real | pendente | Exige pessoa real |

### Bloco 7 — Validações e alertas

| Item | Status | Evidência |
|---|---|---|
| 7.1 B1 a B4 | pass | Testados no browser, cada um com mensagem própria. B3 forçado no servidor, porque a máscara remove o sinal |
| 7.2 A1 a A6 | ressalva | Todos exibidos e gravados em `alertas_aceitos`. O-1 e O-3 |
| 7.3 Nota de contexto | pass | "Ressalvas registradas no lançamento (4)" |

### Bloco 8 — Cobertura

| Item | Status | Evidência |
|---|---|---|
| 8.1 Geral ≥ 80% | pass | Linhas 98,40% (11.010/11.189). O `--min` não chegou a avaliar por causa de D-3; o número vem do relatório de texto |
| 8.2 Núcleo ≥ 98% | pass | `App\Domain` 99,79%. `MotorDrm`, `SerieDrm`, `Faixas`, `Percentual`: 100%. `LeituraDrm`: 99,15% |
| 8.3 Pureza do motor | pass | `MotorDrmArchTest` passa; sem `now`, `Auth`, `DB` ou sessão no fonte |
| 8.4 E2E | ressalva | Dusk completo: 111 passed às 21:55 BRT. Os quatro fluxos existem, mas nenhum digita tecla a tecla (D-1) |

### Bloco 9 — Automação

| Item | Status | Evidência |
|---|---|---|
| 9.1 Pipeline verde | fail NB | Sem CI em push, por decisão do projeto. No portão local, Pint, PHPStan, Pennant, audit e Dusk passam; Pest falha por D-3 |
| 9.2 Deploy em homologação | pendente | Sem ambiente de homologação; deploy por tag fora do escopo |
| 9.3 Migrações reversíveis | ressalva | Em base descartável: as 3 migrations do épico voltaram e foram reaplicadas. Não testadas em homologação |
| 9.4 Sem passo manual novo | ressalva | Nenhum identificado. A STORY-055 registra que a imagem com `ext-gd` não foi reconstruída; o container desta pista tem `gd` |

### Bloco 10 — Funcionalidade em homologação

Local: verificado em `web-test`. Nenhum item demonstrado em homologação (CA-2).

| Item | Status | Evidência |
|---|---|---|
| 10.1 Escolhe empresa e lança | **fail bloqueante** | D-1 |
| 10.2 Valor e % em cada linha | pendente | Local OK (A.1) |
| 10.3 Corrige sem perder histórico | pendente | Local OK (Bloco 4) |
| 10.4 Variação no 2º, média no 4º | pendente | Local OK: "média disponível a partir do 4º mês"; +66,7% com 3 meses anteriores |
| 10.5 Farol e leitura | pendente | Local: 0 faróis, por faixas `nao_calibrada` (PDR-009), ausência deliberada. Leitura presente, com números conferidos |
| 10.6 Planilha e PDF | pendente | Local: XLSX OK (Bloco 14). PDF `n/a` pelo ADR-008, com impressão interina |
| 10.7 Um lançamento por setor | pendente | Local OK (A.1 e Bloco 5) |

### Bloco 11 — Instrumentação

| Item | Status | Evidência |
|---|---|---|
| 11.1 Métricas calculáveis | pass | Consultas em A.2, rodadas sobre eventos reais do fluxo |
| 11.2 Sem dado pessoal | pass | Busca nas propriedades por nome, e-mail, empresa, documento e valores: nada. 22 chaves, só identificadores opacos, contagens e setor |
| 11.3 Idempotência | pass | Reemitir `lancamentoConcluido`: 31 → 31 eventos |

### Bloco 12 — Qualidade transversal

| Item | Status | Evidência |
|---|---|---|
| 12.1 Segurança | pass | Trivy e `composer audit` sem achados; Gitleaks sem vazamentos (na árvore principal, mesmo commit) |
| 12.2 Cross-tenant (autorização) | pass | Outra conta recebe 404 nas 5 rotas, sem revelar dado. Anônimo é redirecionado. Competência inexistente ou inválida: 404 |
| 12.3 LGPD | ressalva | Eventos limpos; XLSX `no-store`, sem retenção; audit só com hash. A anonimização pós-exclusão não tem cascata implementada (`Usuario.php:26`); ADR-007 herda a política do Diagnóstico |
| 12.4 Acessibilidade AA | fail NB | D-4. axe: 0 violações no formulário e na série. Help com 18 px visíveis e área de 44 px no CSS (medida no CSS, não por toque). Sem scroll horizontal em 360 px. O-7 |
| 12.5 Hex literal | pass | Nenhum nas views e no suporte do DRM |
| 12.6 Diagnóstico intocado | pass | Nenhum commit das estórias 045–058 toca `Domain/Motor`, `Domain/Quiz` ou `config/motor.php`. Há commits de outras frentes nesses caminhos, como `ff3f54c` |

### Bloco 13 — Documentação

| Item | Status | Evidência |
|---|---|---|
| 13.1 Decisões indexadas | ressalva | Todas no índice, com status igual ao arquivo. ADR-011, DDR-009 e IDR-013 seguem `proposed` |
| 13.2 Notas do agente | ressalva | Preenchidas; na STORY-052 a seção se chama "Notas" |
| 13.3 Divergências registradas | fail NB | D-6 |

### Bloco 14 — Verificações adicionais pedidas

| Item | Status | Evidência |
|---|---|---|
| 14.1 XLSX relido | pass | Fórmulas vivas nas duas abas (`B9/B$9`, `B8-B9`), zip íntegro. 191 valores conferem à mão, tolerância R$ 0,005 (O-6) |
| 14.2 Aberto no LibreOffice | pass | 17 arquivos convertidos nas duas abas; fórmulas recalculadas pelo LibreOffice |
| 14.3 Cobrança desligada, sem plano | pass | Lançar e corrigir abrem o formulário |
| 14.4 Cobrança ligada | pass | Sem plano ou trial vencido: bloqueio em lançar e corrigir. Demonstração, série e exportação abrem. Trial vigente: formulário abre |
| 14.5 Acesso perdido com a tela aberta | pass | Fechar não grava: 0 lançamentos, rascunho preservado, 0 `drm_lancamento_concluido`, bloqueio na tela |
| 14.6 PDF | n/a | ADR-008 e PDR-007 devolveram o PDF gerado ao EPIC-007; a camada de impressão é a saída interina, coberta pelo `DrmExportacaoBrowserTest` |
| 14.7 Faróis | pendente | Grau zero verificado nos 3 setores. Com faixa calibrada, só testes existentes; calibração depende da EBC |

## Pendências para o PO

Registradas, sem decisão do Validador.

1. **ADR-011 (`proposed`)** — provisão de férias e 13º; a recomendação é manter caixa. A STORY-058 depende desse aceite.
2. **Setor deduzido por CNAE** (`app/app/Domain/Drm/Setor.php:28`). Hoje agro (01–03) e construção (41–43) caem em Serviços, e empresa sem CNAE vira Indústria. O setor define campos e rótulos do formulário.
3. **Frases próprias da leitura** (`config/drm/leitura_frases.php`) — não validadas pela EBC. Podem ir ao ar?
4. **Faixa de farol nos dois modos do pró-labore** (dúvida da STORY-054), para quando a EBC calibrar L6b ou L7.
5. **Conteúdo da EBC** — itens em `config/drm/provisorio/` e faixas `nao_calibrada`.
6. **Homologação** — só existe `defonline-prod`. Onde demonstrar os sete itens, com um lançamento real por setor (CA-2)?
7. **Percurso com usuário real** para o limite de 5 minutos (6.6).
8. **`.xlsx` original** — fornecer o arquivo para confirmar o fixture contra a fonte (2.3).
9. **DDR-009 e IDR-013**, ainda `proposed`.
10. **O-1 e O-2** — o aceite deve cair quando o valor muda? A imutabilidade deve ir ao banco?

## Recomendação ao PO

**Não fechar o épico até corrigir D-1.** D-2 a D-6 podem virar pendências, a seu critério.

Correções sugeridas (não vinculantes):

- **D-1 (S–M):** corrigir a perda de teclas e incluir Dusk tecla a tecla com latência emulada, em campo novo e já preenchido. Verificar também o Diagnóstico.
- **D-2 (S):** distinguir linha omitida de item agregado em `SerieDrm`.
- **D-3 (S):** investigar `FinanceiroTest`; estória da frente de cobrança.
- **D-4 e O-5 (XS):** contraste e plural.
- **D-5 e D-6 (XS):** alinhar status e Anexo K.

**Processo:** a suíte passou inteira com D-1 presente, porque `type()` envia o valor de uma vez. Testes de digitação simulam um usuário que não existe.

## Limitações da validação

- **Sem homologação nem produção:** itens marcados `pendente`.
- **Planilha original indisponível:** procurei em `Documents`, `Downloads`, `Desktop`, `Projetos` e no Spotlight, sem sucesso. A conferência usa o fixture.
- **Tempo do 2º mês** medido por automação e ritmo modelado, não por pessoa.
- **Latência emulada** pelo Chrome sobre `php artisan serve` (RTT local de 39 ms), não em rede móvel real, e com poucas repetições por célula.
- **Acessibilidade:** axe em 360 px, sem leitor de tela.
- **Testes em Chrome headless**, sem teclado virtual de celular.
- **Clock:** rodada entre 21:08 e 22:05 BRT de 05/11; não testei o portão em outro horário.

## Apêndice A — Evidências

### A.1 — Conferência à mão (valores em R$)

Cada caso foi digitado pelo formulário, em agregado e em detalhado, e lido na tela.

| Linha | Indústria, fora | Indústria, dentro | Comércio, fora | Comércio, dentro | Serviços, fora | Serviços, dentro |
|---|---|---|---|---|---|---|
| L1 Receita | 120.000,00 | 120.000,00 | 84.300,00 | 84.300,00 | 30.000,01 | 30.000,01 |
| L2 Deduções | 7.200,50 | 7.200,50 | 5.058,00 | 5.058,00 | 1.800,00 | 1.800,00 |
| L3 Líquida | 112.799,50 | 112.799,50 | 79.242,00 | 79.242,00 | 28.200,01 | 28.200,01 |
| L4 Custo | 39.501,00 | 39.501,00 | 41.200,50 | 41.200,50 | 600,00 | 600,00 |
| L5 Lucro Bruto | 73.298,50 | 73.298,50 | 38.041,50 | 38.041,50 | 27.600,01 | 27.600,01 |
| L6a Vendas | 5.201,00 | 5.201,00 | 8.000,00 | 8.000,00 | 1.299,99 | 1.299,99 |
| L6b Administrativas | 39.701,00 | 47.701,00 | 20.702,00 | 24.702,00 | 26.000,02 | 31.000,02 |
| L6 Operacionais | 44.902,00 | 52.902,00 | 28.702,00 | 32.702,00 | 27.300,01 | 32.300,01 |
| L7 Res. Operacional | 28.396,50 | 20.396,50 | 9.339,50 | 5.339,50 | 300,00 | −4.700,00 |
| L7b Outras | 300,00 | 300,00 | omitida | omitida | −500,50 | −500,50 |
| L8 Financeiras | 1.300,00 | 1.300,00 | 1.100,00 | 1.100,00 | 2.100,00 | 2.100,00 |
| L9 Antes do pró-labore | 27.396,50 | não existe | 8.239,50 | não existe | −2.300,50 | não existe |
| L10 Pró-labore | 8.000,00 | não existe | 4.000,00 | não existe | 5.000,00 | não existe |
| **L11 Lucro Líquido** | **19.396,50** | **19.396,50** | **4.239,50** | **4.239,50** | **−7.300,50** | **−7.300,50** |
| L11 % | 16,2% | 16,2% | 5,0% | 5,0% | −24,3% | −24,3% |

L11 idêntico nos dois modos em 3.007/3.007 casos.

### A.2 — Métricas do épico

Rodadas sobre 4 empresas com eventos reais do fluxo. Os números só provam que as consultas funcionam.

**Recorrência**, resultado de teste: 4 com o 1º, 1 com o 2º em 45 dias, 25,0%.

    WITH c AS (SELECT usuario_id, empresa_id, (propriedades->>'numero_lancamento')::int n, ocorrido_em
               FROM evento_produto WHERE nome_evento='drm_lancamento_concluido'),
         p AS (SELECT usuario_id, empresa_id, ocorrido_em FROM c WHERE n=1),
         s AS (SELECT usuario_id, empresa_id, ocorrido_em FROM c WHERE n=2)
    SELECT count(*) AS concluiram_1o,
           count(s.*) FILTER (WHERE s.ocorrido_em <= p.ocorrido_em + interval '45 days') AS concluiram_2o_em_45d
    FROM p LEFT JOIN s USING (usuario_id, empresa_id);

**Atrito, conclusão**, resultado de teste: 6 iniciados, 5 concluídos.

    SELECT (SELECT count(*) FROM evento_produto WHERE nome_evento='drm_lancamento_iniciado' AND propriedades->>'origem'='inicial') AS iniciados,
           (SELECT count(*) FROM evento_produto WHERE nome_evento='drm_lancamento_concluido') AS concluidos;

**Atrito, tempo:** mediana de `duracao_seg` com `numero_lancamento >= 2`.

    SELECT percentile_cont(0.5) WITHIN GROUP (ORDER BY (propriedades->>'duracao_seg')::int) AS mediana_seg
    FROM evento_produto
    WHERE nome_evento='drm_lancamento_concluido' AND (propriedades->>'numero_lancamento')::int >= 2;

**Alcance**, resultado de teste: Comércio 20%, Indústria 40%, Serviços 40%.

    SELECT propriedades->>'setor' AS setor, count(*) AS n, round(100.0*count(*)/sum(count(*)) OVER (),1) AS pct
    FROM evento_produto WHERE nome_evento='drm_lancamento_concluido' GROUP BY 1 ORDER BY 1;

Use `ocorrido_em` na recorrência: `dias_desde_anterior` segue a competência anterior e fica nulo quando alguém lança um mês mais antigo depois.

### A.3 — Suítes (commit 7a3150b)

| Portão | Resultado |
|---|---|
| Pest `--testsuite=All` | 1 failed, 3254 passed, 51.821 assertions, 220,68 s (D-3) |
| Pest `FinanceiroTest`, 3 repetições | 1 failed, 32 passed em cada |
| Dusk completo | 111 passed, 928 assertions, 234 s |
| Pint, PHPStan | sem erros |
| Pennant, `composer audit` | sem pendências ou avisos |
| Gitleaks, Trivy | sem achados |

## Apêndice B — Arquivos de evidência

Por instrução da tarefa, só este relatório entra no commit. Logs e roteiros ficam no scratchpad da sessão, que é temporário: `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v57/`.

- `V57ValidadorBrowserTest.php` — roteiro Dusk do Validador. Ficou em `app/tests/Browser/` só durante as rodadas e foi removido.
- `casos_mao.json`, `conferir.py`, `conferir_tela.py`, `conferir_replica.py`, `conferir_xlsx.py` — casos e oráculos.
- `saida/` — `tela`, `tempo`, `serie`, `validacoes`, `acesso`, `metricas`, `perda`, `teclas`, `virgem`, `k` (JSON) e `xlsx/`.
- `pest-all-coverage.log`, `cov.txt`, `dusk-full.log`, `migracoes.log`, `trivy.log`, `gitleaks.log`.

## Histórico

- 2026-11-05 — relatório inicial, veredito REJECTED por D-1.
