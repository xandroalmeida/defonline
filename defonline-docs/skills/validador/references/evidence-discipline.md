# Disciplina de evidência

A diferença entre **validação** e **opinião** é evidência. Toda afirmação no seu relatório (`pass`, `fail`, `n/a`) precisa estar ancorada em algo **verificável** — algo que outro Validador, ou o PO, ou o Arquiteto pode olhar e confirmar.

Esta reference cobre: o que conta como evidência, como capturar bem, e os anti-padrões mais comuns.

---

## O princípio

> **Sem evidência, sua afirmação não vale.**

Não importa se você "viu", "tem certeza", "é óbvio". Se outro Validador chegar amanhã e não conseguir confirmar a mesma coisa, sua validação tem buraco.

A pergunta única que cabe a cada item do checklist:

> **"Se eu sair desta sessão e outro Validador continuar amanhã, ele consegue reproduzir minha conclusão a partir do que eu registrei?"**

Se a resposta é **sim** → evidência suficiente.
Se **não** → falta evidência, registre melhor antes de seguir.

---

## Tipos de evidência aceitáveis

Por ordem de força (mais forte → mais fraca):

### 1. Hash / ID determinístico

Identifica algo de forma única e estável:

- **Commit hash** (`abc123def...`) — código no momento da validação.
- **PR number + URL** (`#42` + link).
- **Build ID / pipeline run ID** + URL para o run específico.
- **Tag de release**.
- **ID de migração de banco**.

**Por que é forte**: identifica algo único e imutável. Qualquer pessoa com o hash pode chegar ao mesmo artefato.

### 2. Link a um sistema persistente

URL para algo que vive em sistema com histórico:

- Link para teste no relatório de CI (com timestamp).
- Link para dashboard de cobertura.
- Link para dashboard de observabilidade com janela temporal.
- Link para documento (ADR, IDR, PR description).

**Por que é forte**: outro Validador clica e vê o mesmo. Cuidado: alguns dashboards mostram "agora" e mudam — fixe janela temporal quando aplicável.

### 3. Output de comando reproducível

Comando que outro Validador pode rodar para obter o mesmo resultado:

```
$ npm run test:coverage
> [...]
> Statements   : 84.3% (1247/1480)
> Branches     : 82.1%
> Functions    : 88.7%
> Lines        : 84.5%
```

Inclua: comando exato, versão do projeto (commit hash), output relevante. Outro Validador checa-out o commit, roda o comando, confere.

**Por que é forte (se acompanhado de commit hash)**: reproducibilidade verificável.

### 4. Screenshot com contexto

Screenshot de tela contém UI, configuração, valor de campo. Quando usar:

- Aspecto **visual** (layout, mensagem de erro mostrada, presença de elemento).
- Verificação manual em homologação.
- Dashboards que não geram link permanente.

**Para ser bom**:
- **Timestamp visível** na captura (relógio do sistema ou metadado).
- **URL visível** quando aplicável.
- **Contexto suficiente** — não corte parte importante.
- **Nome de arquivo** descritivo: `epic-007-homolog-cadastro-empresa-sucesso-2026-05-20.png`.
- **Anexado ao relatório** com link relativo (em `validation/evidence/<filename>`).

**Por que é menos forte**: pode ser editado, perdido, ou interpretado fora de contexto. Use quando os tipos 1-3 não cobrem.

### 5. Log de sistema

Trecho de log com timestamp e contexto:

```
2026-05-20T14:32:08.123Z INFO http_request request_id=abc-123 method=POST path=/api/v1/empresas status=201 duration_ms=142
```

**Para ser bom**:
- **Timestamp explícito**.
- **Contexto suficiente** — não só uma linha solta.
- **Linka para o sistema** se possível (não apenas cópia em texto).

### 6. Observação registrada (último recurso)

Quando nenhum dos anteriores se aplica — geralmente para **smoke manual** em situações sem outra evidência:

```
"Em 2026-05-20 14:35, percorri manualmente o fluxo de cadastro de empresa
em https://homolog.defonline.com.br. Login funcionou, cadastrei empresa
'Teste Validação' com CNPJ válido. Empresa apareceu na listagem em <2s.
Fluxo completo sem erro de UI ou backend visível."
```

**Para ser bom**:
- **Data + hora + URL**.
- **O que você fez** (passos concretos).
- **O que você observou** (resultado específico, não "tudo ok").

**Por que é mais fraco**: depende da sua palavra. Use quando não há alternativa, e **complemente com screenshot** quando possível.

---

## O que NÃO conta como evidência

### "Achismo"

- ❌ "O sistema parece estar funcionando bem."
- ❌ "Cobertura está OK."
- ❌ "Não vi nada estranho."

**Por quê não vale**: outro Validador não consegue confirmar nem refutar.

### Afirmação sem timestamp ou contexto

- ❌ "Pipeline está verde." (quando? qual run?)
- ❌ "Cadastro funciona." (qual ambiente? quais dados?)

**Por quê não vale**: pipeline pode ficar verde e vermelho ao longo do dia; cadastro pode funcionar em um caso e quebrar em outro.

### Cópia de output sem fonte

- ❌ Linhas de log no relatório sem dizer de onde vieram.

**Por quê não vale**: pode ser real ou inventado. Linke para o sistema de log de origem.

### Captura sem o contexto importante

- ❌ Screenshot só do botão sem mostrar URL nem estado da tela.

**Por quê não vale**: foto de um botão isolado não prova nada — pode ser de qualquer ambiente, qualquer momento.

### "Por dedução"

- ❌ "O teste de cobertura passou no CI, então o código novo deve estar coberto."
- ❌ "Se a estória está done, os testes devem cobrir tudo."

**Por quê não vale**: deduzir não é verificar. Cobertura agregada pode estar OK e a parte nova do épico mal coberta. Validador verifica diretamente.

---

## Como capturar bem — hábitos práticos

### No momento, não depois

Capture **enquanto** verifica. "Vou anotar depois" sempre quebra. Mesmo que pareça desorganizado, tenha:

- Um arquivo aberto onde você cola evidência conforme aparece.
- Pastas locais para screenshots, com nomes descritivos.
- Links abertos em abas com nome do bloco (Bloco 2 — Cobertura).

### Estrutura por item do checklist

Para cada item:

```markdown
**Bloco 2.1 — Cobertura geral ≥ 80%**: ✅ PASS

Evidência:
- Commit hash validado: `abc123def`
- Relatório de cobertura: [CI Run #1234](https://...)
- Output: 84.3% statements, 82.1% branches (acima do alvo de 80%).
- Linhas descobertas: arquivo `payment_processor.py` linhas 45-52 (tratamento de fornecedor X — justificativa em PR #42 comment).
```

Cada conclusão sustentada por **algo concreto**. Sem isso, retorne ao item antes de seguir.

### Reproducibilidade

Padrão: outro Validador, com acesso ao repo e ambientes, **conseguiria reproduzir cada `pass`/`fail` que você registrou**?

Se não: faltou detalhe.

### Anexar e versionar evidência pesada

Screenshots e logs grandes vão em:

```
defonline-docs/project-state/epics/EPIC-XXX-<slug>/validation/evidence/
├── bloco-1-cas-passing.png
├── bloco-2-coverage-report.txt
├── bloco-4-homolog-fluxo-cadastro.png
├── bloco-4-homolog-fluxo-diagnostico.png
└── bloco-5-security-scan.log
```

Linkados a partir do `report.md` por path relativo. Tudo versionado em git.

---

## Quando a evidência é fraca mas não há melhor

Acontece. Exemplo: integração com fornecedor externo cujo log não é exportável; comportamento de UI que depende de hover impossível de capturar em screenshot.

Nesses casos:

- **Registre o melhor que conseguir** (observação detalhada).
- **Explicite a limitação** no relatório: "Evidência observacional (não foi possível obter log do fornecedor X)."
- **Marque como ressalva** se a evidência fraca afeta confiança no `pass`.

Não force `pass` baseado em evidência ruim. Quando em dúvida real, `pass com ressalva` é honesto.

---

## Evidência de fail — específica é dobrar útil

Quando um item é `fail`, evidência precisa permitir o Programador **reproduzir e consertar** sem voltar a perguntar:

```markdown
**Bloco 1.3 — CA-3 da STORY-007 sem teste cobrindo**: ❌ FAIL

Evidência:
- CA-3 da estória: "Sistema rejeita CNPJ com dígitos verificadores incorretos e retorna mensagem 'CNPJ inválido — dígitos verificadores não conferem'."
- Busca por testes: nenhum teste encontrado para esta validação específica.
- Comando usado: `grep -r "digitos verificadores" tests/`. Resultado: zero matches.
- Testes existentes para CNPJ: `test_cnpj_formato_invalido` (CA-2), `test_cnpj_valido` (CA-1). Falta o cenário de dígito verificador errado.
- Reprodução: rodar `npm test -- --coverage tests/cadastro/` mostra função `validar_digitos_verificadores` em `validators.ts` linhas 23-45 com 0% de cobertura.

**Recomendação**: estória de correção que adicione testes para CA-3 com pelo menos:
- CNPJ com primeiro dígito verificador errado.
- CNPJ com segundo dígito verificador errado.
- Validação retorna mensagem específica conforme CA.
```

Specific. Acionável. Reproduzível.

---

## Evidência de n/a — justificativa específica

`n/a` é declaração de que **o item não se aplica neste épico**. Precisa de **prosa específica** explicando por quê:

❌ **Ruim**:
> n/a — não se aplica.

✅ **Bom**:
> n/a — Este épico (EPIC-005) não envolve frontend web. Validação foi 100% de backend e API. Cenário E2E em browser real (item 2.3) não se aplica; cobertura E2E via cliente HTTP automatizado (item 2.4) está presente e validada.

Sem justificativa, `n/a` vira atalho preguiçoso. Com justificativa, é decisão honesta.

---

## Resumo operacional

Para cada item do checklist:

- [ ] Verifiquei diretamente (não deduzi).
- [ ] Tenho **evidência verificável** — hash, link, output, screenshot com contexto, ou observação detalhada.
- [ ] Outra pessoa com acesso ao mesmo ambiente **consegue reproduzir** minha conclusão.
- [ ] Evidência forte foi preferida sobre fraca quando havia opção.
- [ ] Limitações de evidência foram explicitadas onde existem.
- [ ] Evidência pesada (screenshots, logs longos) está em `evidence/` versionada.

> **Evidência é o que separa validação de carimbação. Não vire carimbo.**
