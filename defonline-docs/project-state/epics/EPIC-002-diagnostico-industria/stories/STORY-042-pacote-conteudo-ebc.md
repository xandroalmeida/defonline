---
story_id: STORY-042
slug: pacote-conteudo-ebc
title: Pacote de conteúdo EBC — títulos, rótulos, textos auxiliares, glossário, NCG positivo
epic_id: EPIC-002
sprint_id: SPRINT-2026-W26
type: implementation
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-06-02
updated_at: 2026-06-02
estimated_session_size: M
related_devolutiva: ".docx 'DEF on line - MVP 270526 - observações ebc' — blocos 2, 3, 4 + diagnóstico + glossário"
---

# STORY-042 — Pacote de conteúdo EBC

> Coleção coordenada de mudanças textuais sugeridas pelo Edvan (EB Parcerias) no `.docx` de devolutiva — títulos de blocos, rótulos de itens, textos auxiliares, recomendação de NCG positivo e ordem do glossário. 1 commit coeso, sem mudança de regra de negócio do motor.

## Contexto

A EBC (Edvan) revisou o MVP entregue na SPRINT-2026-W25 e devolveu uma lista de ajustes editoriais no `.docx` "DEF on line - MVP 270526 - observações ebc". Ajustes são pontuais (não reescrevem fluxo) e melhoram a clareza para o público-alvo. Sem impacto em regras do motor — apenas em conteúdo renderizado e em texto da matriz de recomendações.

- Épico: `epics/EPIC-002-diagnostico-industria/epic.md`
- Devolutiva: `epics/EPIC-002-diagnostico-industria/devolutivas/2026-05-27-EBC/`
- Spec V2: Anexo A (quiz), Anexo F (matriz dez/2025), glossário.

## O quê

Aplicar a lista abaixo de mudanças em uma única estória coesa (1 PR, 1 commit ou poucos commits temáticos).

### Lista de mudanças

**Bloco 2 (DRE):**
- **Remover** do front o texto auxiliar "o motor anualiza (×12) no cálculo" — a anualização continua silenciosa no motor; o usuário não vê.
- **Subtítulo do item 3 — Custos e Despesas Fixas:** acrescentar exemplos: *"Folha de Pagamento, Encargos sociais, contador, aluguel, condomínio, energia, água, telefone, internet, franquia, retirada de sócios, etc."*
- **Subtítulo do item 4 — Custos e Despesas Variáveis:** acrescentar exemplos: *"Fretes, comissões, tributos, despesas de vendas, outras despesas variáveis, etc."*
- **Título do Bloco 2:** [PENDENTE confirmar com Edvan — `.docx` não especificou; manter texto atual se Edvan não responder até 2026-06-04].

**Bloco 3 (Balanço):**
- **Título do Bloco 3:** trocar para *"Saldos Atuais – uma foto da situação econômica do negócio hoje"*.
- **Patrimônio (Imobilizado), texto auxiliar:** trocar para *"Imóveis, equipamentos e veículos livres de ônus – valor estimado para venda rápida"*.

**Bloco 4 (Contexto):**
- **Q17 (Necessita de recursos?):** **ocultar** o texto auxiliar "Base de cálculo da capacidade de garantia (3× esse valor)" — vira regra interna, não texto na UI.
- **Q17 (Necessita de recursos?):** **ocultar** o texto auxiliar "Deve ser inferior à venda mensal..." — a regra de consistência vira validação backend, tratada na **STORY-043** (esta estória só remove o texto).

**Matriz dez-2025 (Anexo F):**
- **Recomendação NCG positivo:** trocar texto atual para *"A elevação deve ser acompanhada, na mesma proporção, pelo aumento das fontes de financiamento de longo prazo, preferencialmente de fornecedores"*.
- Atenção: a entrada `ncg_vendas × farol_positivo` em `config/recomendacoes/dez-2025.php` (ou equivalente). Snapshot de diagnósticos legados não é alterado (IDR-010 §retro).

**Glossário (na página do diagnóstico):**
- Reordenar: **"Ebitda (Margem Operacional)" como 2º item**.
- **Remover** o item que está antes do "Farol/Semáforo" — [PENDENTE: qual item exatamente? `.docx` ambíguo. Confirmar com Edvan na pauta 2026-06-02; se não responder até 2026-06-04, deixar como está e remover a tarefa do escopo].

## Por quê

Devolutiva da EBC aumenta a clareza para o Roberto e absorve o feedback do analista financeiro de referência. Tornar o quiz/relatório mais legível elevará taxa de submissão completa (D2 — Ativação).

## Critérios de aceite

- [ ] **CA-1 (Bloco 2 — texto anualização):** Texto "o motor anualiza (×12)" não aparece em nenhum lugar da UI do quiz; teste E2E percorre Bloco 2 e busca a string — não acha.
- [ ] **CA-2 (Bloco 2 — item 3):** Subtítulo do item Custos e Despesas Fixas exibe a lista de exemplos exata acima.
- [ ] **CA-3 (Bloco 2 — item 4):** Subtítulo do item Custos e Despesas Variáveis exibe a lista de exemplos exata acima.
- [ ] **CA-4 (Bloco 3 — título):** Cabeçalho do Bloco 3 do quiz exibe "Saldos Atuais – uma foto da situação econômica do negócio hoje".
- [ ] **CA-5 (Bloco 3 — imobilizado):** Subtítulo de Patrimônio (Imobilizado) exibe o texto novo.
- [ ] **CA-6 (Bloco 4 — Q17 ocultar):** Os 2 textos auxiliares de Q17 (base de cálculo 3× / inferior à venda mensal) não aparecem.
- [ ] **CA-7 (Matriz NCG positivo):** Diagnóstico novo (após deploy) com NCG positivo exibe a recomendação revisada. Diagnóstico legado abre com texto antigo (snapshot preservado).
- [ ] **CA-8 (Glossário ordem):** "Ebitda (Margem Operacional)" aparece como 2º item da lista do glossário.
- [ ] **CA-9 (Pendências fechadas):** Título do Bloco 2 e item a remover do glossário — fechados com Edvan ou registrado no PR como aberto/postergado.
- [ ] **CA-10 (cobertura):** suíte Pest verde; 1 Dusk percorrendo o quiz inteiro e verificando os textos novos; testes de snapshot da matriz/glossário re-emitidos onde necessário.

## Fora de escopo

- Validação cruzada de Q17 ≥ Q09 — vai para **STORY-043** (separada para isolar mudança de regra).
- Mudança de algoritmo de seleção de destaques no Resumo Executivo — aguarda Edvan (§4.7.1).
- Tradução para outros idiomas.

## Padrões de qualidade exigidos

- **Cobertura ≥ 80% nos arquivos tocados** (alguns só têm strings; cobre via E2E).
- **Snapshot da matriz dez-2025 atualizado** se houver teste de hash do conteúdo da matriz.

## Dependências

- **Bloqueada por:** respostas do Edvan (2 itens — título Bloco 2 + item glossário) — não-críticas (default documentado).
- **Bloqueia:** STORY-043 (R4 depende do Q17 já com textos ajustados — embora seja paralelizável se Programador for cuidadoso).

## Decisões já tomadas

- **Anualização (×12) — texto auxiliar removido** da UI, lógica do motor inalterada.
- **Q17 — textos auxiliares ocultos** (a regra de "inferior à venda mensal" vira validação backend na STORY-043).
- **Matriz dez-2025** — diagnósticos legados preservados (IDR-010 §retro).
- **Glossário item a remover** — se Edvan não responder até 2026-06-04, **manter** e fechar pendência como "diferido".

## Liberdade técnica do agente

- Como organizar o commit (1 commit gigante OK; ou 5 commits temáticos com squash no merge — Programador decide).
- Como executar mudanças no glossário (componente único ou múltiplos templates).
- Onde mora o texto da matriz NCG positivo (config PHP versionado — já existe).

## Definição de Pronto

- [ ] CA-1..CA-10 passam.
- [ ] Snapshot tests onde aplicável.
- [ ] Dusk percorrendo quiz com textos novos.
- [ ] CI verde.
- [ ] Deploy em homol verificado (sweep visual + smoke).
- [ ] `index.json` atualizado.

## Notas do agente

### Decisões tomadas
- 

### Pendências fechadas com Edvan
- 

### Cobertura final
- 

### Links de evidência
- 
