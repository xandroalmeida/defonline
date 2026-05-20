# Craft do relatório de validação

O `validation/report.md` é o **produto final** do Validador. PO lê, decide o que fazer com o épico, comunica eventualmente para outros stakeholders. Relatório bem escrito poupa idas e voltas; relatório vago gera reuniões para "entender o que você quis dizer".

Esta reference cobre **como escrever o relatório bem**.

---

## A mentalidade

- **Quem lê é o PO** (e possivelmente o Alexandro). Eles precisam **agir** em cima do que você escreveu. Otimize para acionabilidade.
- **Tom factual**, sem retórica. Sem "infelizmente", "lamentavelmente", "infelizmente identificamos". O fato, com evidência. Sem drama.
- **Estrutura previsível.** Quem leu 3 relatórios seus sabe onde achar cada parte do quarto. Mude a estrutura, perde leitura rápida.
- **Concisão sem perder detalhe.** TL;DR no topo para quem tem 30 segundos; apêndices para quem tem 30 minutos. Atenda os dois.

---

## Estrutura padrão (espelha `templates/validation-report.md`)

```markdown
# Relatório de Validação — EPIC-XXX

## TL;DR
## Resumo executivo
## Checklist preenchido (por bloco)
## Fails categorizados por gravidade
## Recomendação ao PO
## Apêndice — evidência detalhada
## Histórico
```

Cada seção tem propósito distinto. Não cole tudo em uma — quem lê precisa pular para a seção certa rapidamente.

---

## TL;DR — para quem tem 30 segundos

3 linhas, máximo 5. Inclui **sempre**:

- **Veredito**: APPROVED / REJECTED / APPROVED com pendências.
- **Contagem rápida**: X passes, Y fails (bloqueantes/não-bloqueantes), Z n/a.
- **Próximo passo recomendado** em uma frase.

**Exemplo bom:**

> **Veredito: REJECTED.** 18 passes, 2 fails (1 bloqueante, 1 não-bloqueante), 3 n/a justificados. **Recomendação**: corrigir o fail bloqueante (CA-3 sem teste) antes de fechar; não-bloqueante pode virar estória própria.

**Exemplo ruim:**

> Foi feita a validação completa do EPIC-007 que envolveu... [parágrafo de 200 palavras descrevendo o épico inteiro]

TL;DR não é resumo do épico. É veredito acionável.

---

## Resumo executivo — para quem tem 2 minutos

1-2 parágrafos. Inclui:

- Contexto curto do épico (para quem não lembra de cabeça).
- **O que foi entregue**, em uma frase.
- **Achados principais** — bem o suficiente para entender, sem entrar em cada item.
- **Conexão com o veredito** — por que aprovou ou reprovou em termos gerais.

**Exemplo:**

> O EPIC-007 entregou o fluxo de importação de planilha xlsx — usuário consegue subir planilha em homologação, ver indicadores extraídos e prosseguir para diagnóstico. Cobertura geral 84%, núcleo 98.5%. Pipeline verde nos últimos 8 commits da branch principal. **Achei um fail bloqueante**: CA-3 ("sistema rejeita arquivo > 5MB com mensagem específica") não tem teste automatizado cobrindo — a validação funciona em homologação mas não está sob teste, o que viola padrão do PO. **Outro fail não-bloqueante**: README do módulo de importação não foi atualizado; vira estória pequena de correção. Restante OK.

Concisão + específico + acionável.

---

## Checklist preenchido — para quem precisa de detalhe

Cada bloco do checklist do PO (7 blocos: CAs, cobertura, automação, funcionalidade observável, qualidade transversal, documentação, veredito) aparece com seus itens:

```markdown
### Bloco 1 — Critérios de aceite das estórias

| Item | Status | Evidência |
|---|---|---|
| 1.1 — CA-1 da STORY-001 (cadastro funciona com dados válidos) | ✅ PASS | Teste `test_cadastro_dados_validos` passa. [CI #1234]. Commit `abc123`. |
| 1.2 — CA-2 da STORY-001 (CNPJ inválido retorna mensagem específica) | ✅ PASS | Teste `test_cadastro_cnpj_invalido` passa. [CI #1234]. |
| 1.3 — CA-3 da STORY-007 (rejeita arquivo > 5MB) | ❌ FAIL — bloqueante | Não há teste cobrindo. Função `validar_tamanho_arquivo` com 0% de cobertura. Detalhes no apêndice A.3. |
| ... | ... | ... |
```

**Princípios:**

- **Todos os itens aparecem**, mesmo os `pass` simples. PO precisa saber que foi verificado.
- **Status visual**: `✅ PASS`, `⚠️ PASS com ressalva`, `❌ FAIL — bloqueante`, `❌ FAIL — não-bloqueante`, `🚫 n/a`.
- **Evidência inline**: link, hash, comando — algo verificável.
- **Detalhes longos no apêndice** com referência. Não inflam o checklist.

---

## Fails categorizados por gravidade

Lista dedicada para PO escanear rapidamente:

```markdown
## Fails identificados

### Bloqueantes (épico não deve fechar até resolver)

1. **CA-3 da STORY-007 sem teste cobrindo** (Bloco 1.3)
   - Função `validar_tamanho_arquivo` em `importacao/validators.py` com 0% de cobertura.
   - Validação funciona manualmente em homologação mas não está sob teste.
   - **Sugestão**: estória nova com testes para CA-3 cobrindo: arquivo no limite (5MB), arquivo acima (5.01MB, 100MB), arquivo abaixo (0 bytes, 1MB).
   - Evidência: apêndice A.3.

### Não-bloqueantes (podem virar pendência)

1. **README do módulo `importacao` desatualizado** (Bloco 6.1)
   - Mudanças do épico não refletidas no README.
   - **Sugestão**: estória pequena (S) para atualizar README.
   - Evidência: apêndice A.6.
```

**Para cada fail:**

- **Nome curto** no formato consistente.
- **Descrição factual** (não emocional).
- **Sugestão** ao PO (o que poderia virar estória — sem decidir por ele).
- **Link para evidência detalhada** no apêndice.

---

## Recomendação ao PO

Esta seção é onde você **traduz fails em ação possível** — sem decidir pelo PO. Você é conselheiro técnico do veredito; ele decide o que fazer.

```markdown
## Recomendação ao PO

**Sobre o épico**:
- Veredito REJECTED por causa de 1 fail bloqueante.
- Sugiro **não fechar o épico** até esse fail virar correção. Não-bloqueante pode entrar como estória da próxima sprint.

**Sobre estórias de correção sugeridas**:
- STORY-XXX-correcao: adicionar testes para CA-3 da STORY-007. Tamanho estimado S.
- STORY-YYY-correcao: atualizar README do módulo `importacao`. Tamanho estimado S.

**Observações que merecem atenção** (não fails, mas vale notar):
- Cobertura geral em 84.3% — atende mínimo de 80%, mas próxima do limite. Considere meta interna de 85% para manter folga.
- Estória STORY-009 tem "Notas do agente" muito breves comparado às outras. Pode indicar pressa no fim — vale conversa em retro.

**Sobre o processo** (input para retrospectiva):
- O fail bloqueante (CA sem teste) sugere que `done-checklist` do Programador não pegou. Vale lembrança no próximo sprint.
```

**Princípio**: você dá insumo, PO decide.

---

## Apêndice — evidência detalhada

Para cada fail e para `pass com ressalva` significativo, expanda no apêndice:

```markdown
## Apêndice A — Evidências detalhadas

### A.3 — CA-3 da STORY-007 sem teste (FAIL bloqueante)

**Critério esperado** (CA-3 da estória):
> "Quando o usuário tenta enviar planilha > 5MB, o sistema rejeita com mensagem 'Arquivo excede limite de 5MB — selecione arquivo menor'."

**O que verifiquei**:
1. Busca por teste relacionado:
   ```
   $ grep -r "5MB\|tamanho_arquivo\|limite_tamanho" tests/
   (nenhum resultado)
   ```
2. Cobertura da função `validar_tamanho_arquivo`:
   ```
   importacao/validators.py:
   - linha 45: 0% (função `validar_tamanho_arquivo` declarada)
   - linhas 46-52: 0% (lógica do limite)
   ```
3. Verificação manual em homologação: subi planilha de 6MB → vi mensagem correta. Funcionalidade existe mas não está coberta.

**Reprodução**:
- Commit hash em validação: `abc123def`
- Branch: `main`
- Comando: `npm run test:coverage -- importacao/`

**Impacto**: regressão pode acontecer sem detecção.

**Sugestão**: estória nova com testes cobrindo limite (5MB), acima (5.01MB, 100MB), abaixo (1MB).
```

Apêndice é o lugar para **detalhe extenso**. Quem quer entender em profundidade vem aqui.

---

## Tom: factual, sem retórica

| Evitar | Preferir |
|---|---|
| "Infelizmente identificamos um problema crítico..." | "Fail bloqueante: ..." |
| "O time fez um excelente trabalho em..." | "Pass com evidência forte em..." |
| "Acredito que devemos..." | "Recomendo..." (e só se vc é o validador, **se** for recomendação) |
| "Não foi possível verificar..." | "Não verifiquei porque [motivo específico]. Limitação registrada." |
| "Parece que..." | "Verifiquei e..." (com evidência) ou "Não pude verificar; observação parcial:..." |

Você é **régua**. Régua não tem emoção. Régua tem precisão.

---

## Anti-padrões em relatório

**1. Apenas "OK" em PR comment ou chat — sem relatório.**
Validação **sempre** vira `report.md` versionado. Sem arquivo, não é validação — é opinião.

**2. Relatório copia-cola do checklist.**
Você não está repetindo o checklist; está **respondendo** a cada item com evidência específica.

**3. Veredict sem contar fails.**
"Approved" sem TL;DR mostrando 0 fails e contagem confunde — PO precisa saber o que olhou.

**4. Linguagem ambígua.**
"Mais ou menos OK", "essencialmente passa", "provavelmente cumpre". Validação é binária por item; estado intermediário é `pass com ressalva` declarado.

**5. Evidência ausente em `pass`.**
"Pass" sem mostrar evidência é carimbação. Cada `pass` cita evidência inline.

**6. `n/a` sem justificativa.**
Anti-padrão clássico. Sempre prosa específica.

**7. Tom emocional.**
"Lamentavelmente" ou "felizmente" entra em opinião. Você é factual.

**8. Recomendação como decisão.**
"Decido reprovar e exigir correção em 3 dias." Você não decide isso. Você relata e recomenda. PO decide.

**9. Relatório muito longo.**
20+ páginas de prosa cansa quem lê. Use apêndice para detalhe — corpo principal compacto.

**10. Atualizar `report.md` sem registrar no histórico.**
Se você precisa corrigir/complementar o relatório depois do veredito inicial, adicione no "Histórico" do template — não reescreva silenciosamente.

---

## Releitura antes de submeter

Antes de marcar o relatório como completo:

- [ ] **TL;DR** está no topo, 3-5 linhas, com veredito + contagem + próximo passo.
- [ ] **Resumo executivo** dá contexto em 1-2 parágrafos.
- [ ] **Cada item do checklist** tem status + evidência inline.
- [ ] **Cada `pass` tem evidência verificável** (não só "ok").
- [ ] **Cada `n/a` tem prosa específica** justificando.
- [ ] **Cada `fail` tem gravidade** classificada (bloqueante / não-bloqueante).
- [ ] **Recomendação ao PO** é insumo, não decisão.
- [ ] **Apêndice** expande os fails e ressalvas relevantes com reprodução.
- [ ] **Tom factual** — releia procurando palavras emocionais e remova.
- [ ] **Cabe em pessoa lendo em ~5 min** o corpo principal (apêndice à parte).

Se uma das checagens acima falha → não submeta ainda. Ajuste.

---

## Resumo operacional

Relatório bem feito tem:

1. **Topo acionável** (TL;DR).
2. **Corpo navegável** (checklist + fails + recomendação).
3. **Apêndice profundo** (evidência detalhada).
4. **Tom factual** em todo o texto.
5. **Releitura final** antes de submeter.

> **Relatório bom não convence — informa. PO faz o resto.**
