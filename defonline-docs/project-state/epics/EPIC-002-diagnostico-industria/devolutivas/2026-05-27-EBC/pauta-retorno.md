---
artifact: devolutiva-pauta-retorno
origem: "Devolutiva EBC sobre MVP Indústria — Edvan, 2026-05-27"
materiais: 
  - "DEF on line - MVP 270526 - observações EBC.docx"
  - "WhatsApp Audio 2026-05-27 at 10.37.33.opus"
  - "WhatsApp msg #1 (CPFs dos sócios)"
  - "WhatsApp msg #2 (Inadimplência 2 decimais)"
written_by: po (Alexandro)
written_at: 2026-06-02
status: ready-to-send
target: Edvan / EB Parcerias
---

# Pauta de retorno EBC — Devolutiva 2026-05-27

Edvan, obrigado pela devolutiva detalhada. Segue um resumo do que **já entrou em sprint** para implementação imediata e os **5 pontos abertos** em que preciso da sua confirmação para fechar o ciclo.

---

## ✅ O que já entrou em sprint (SPRINT-2026-W26, 03-09/06)

Sprint curta de hotfixes pós-devolutiva, sem mexer no algoritmo do Resumo Executivo (esse aguarda você voltar com a lista dos 3 indicadores — ver §1 abaixo).

1. **Sócios como lista dinâmica** (STORY-038) — substitui os 3 CPFs fixos por uma lista com 1 sócio obrigatório, adicionar/editar/remover livres, cap 10. Resolve o problema do teste em que você precisou repetir o CPF.
2. **Inadimplência aceita 2 casas decimais** (STORY-039) — `3,46%` passa a ser aceito; bug do limite "ZERO e CEM" corrigido.
3. **Card do Resumo Executivo clicável** (STORY-040) — clique no card de destaque rola até a linha completa na tabela detalhada e destaca por 1s. Elimina o "dead-end" que você relatou no áudio (clicar no NCG/Vendas e nada abrir).
4. **Plural "ações"** (STORY-041) — troca "demandam ação" → "demandam ações" no veredito de alerta.
5. **Pacote de conteúdo** (STORY-042) — todas as mudanças textuais do `.docx`:
   - Bloco 2: remove "o motor anualiza (×12)" da UI; subtítulos dos itens 3 e 4 com a lista de exemplos que você indicou.
   - Bloco 3: título "Saldos Atuais – uma foto da situação econômica do negócio hoje"; Patrimônio (Imobilizado) com texto novo.
   - Bloco 4 (Q17): oculta os 2 textos auxiliares.
   - Matriz dez-2025: nova recomendação para NCG positivo.
   - Glossário: EBITDA como 2º item.
6. **Validação cruzada nova** (STORY-043) — o campo de recebíveis (venda mensal com cartão/duplicatas) passou a ter **bloqueio** quando o valor informado é **maior ou igual** à venda mensal média. Vira regra de checagem em vez de texto auxiliar — ver §5 abaixo, onde peço sua confirmação sobre o sentido.

---

## 🔲 5 pontos em que preciso da sua confirmação

### 1. Algoritmo do Resumo Executivo / farol geral por 3 indicadores eleitos

No áudio você disse que vai eleger **3 indicadores "determinantes" por segmento** (3 para Indústria, 3 para Serviço) e o farol geral sai deles. **Não estamos mexendo no algoritmo atual até você voltar com essa lista** — me confirme:

- (a) Quais **3 indicadores** para Indústria você considera determinantes? Pode ser uma lista preliminar.
- (b) **Critério de combinação** dos 3 para o farol geral: o pior dos 3 prevalece? Média ponderada? Outro?
- (c) **Texto fixo** muda em função do veredito? (hoje temos 3 textos — saudável/atenção/alerta — fixos por veredito.)

Para contexto, hoje o critério interno de **escolher quais 3 indicadores aparecem como destaque** no Resumo Executivo (entre os 14 calculados) usa a fórmula:

```
severidade = |valor_calculado − fronteira_amarela| / amplitude_faixa_vermelha
```

Ou seja: quanto mais o indicador está fora da faixa segura, mais alta a severidade — top 3 viram destaque. Você acha esse critério razoável? Se sim, talvez bastasse explicarmos isso ao usuário (um "?" no resumo) em vez de mudar o algoritmo. Se não, sua proposta de "3 indicadores eleitos a priori" vira a nova regra — mas vai precisar passar pelo validador externo (item §6 abaixo).

### 2. Lista do que é anualizado (×12)

No `.docx` você pediu confirmação. Minha leitura é que **5 campos do quiz** são anualizados internamente:

- Compras de matéria-prima/mercadorias
- Vendas
- Custos e despesas fixas
- Custos e despesas variáveis
- Despesas financeiras

Pode confirmar? Ou há outros campos do quiz que entram no ×12 e eu não listei?

### 3. Glossário — qual item remover "antes do Farol/Semáforo"

No `.docx` você disse "excluir o item que está antes do Farol/Semáforo". Não ficou claro **qual** item exatamente. Você pode me dizer o nome do item para remoção?

**Política se você não responder até 04/06:** manter o glossário como está e fechar essa pendência como "diferida". Não bloqueia a sprint.

### 4. Título do Bloco 2

O `.docx` especificou novo título do Bloco 3 ("Saldos Atuais...") e ajustes em rótulos dos itens 3 e 4, mas **não trocou o título do Bloco 2**. Você quer trocar o título do Bloco 2 também? Se sim, qual?

**Política se você não responder até 04/06:** manter o título atual.

### 5. Recebíveis × venda mensal — confirmar o sentido da regra

No `.docx` você escreveu, sobre o campo de recebíveis do Bloco 4:

> "também deve ser ocultada a informação sobre ser inferior à venda mensal, pois também deve ser uma regra para checagem e não aceitar informação neste campo (recebíveis) de valor **inferior** ao contido no campo 2 do bloco 2 (vendas – média mensal)"

Implementamos o texto auxiliar como regra, conforme você pediu — mas **no sentido inverso do que a frase diz ao pé da letra**, e quero confirmar com você:

- O texto que estava na tela dizia *"deve ser inferior à venda mensal"*, e o Anexo A já registra, desde as suas correções de 22/04/2026, a validação **"recebíveis ≥ venda mensal: bloqueio rígido"**.
- Além disso, recebíveis (vendas por cartão/duplicata) são um **subconjunto** das vendas totais — não podem ser maiores que elas.
- Aplicar a frase ao pé da letra tornaria o bloco impossível de preencher: o campo teria que ser ao mesmo tempo menor e maior que a venda mensal.

**Entendemos que você quis dizer:** o sistema não deve aceitar recebíveis **iguais ou maiores** que a venda mensal média — e foi assim que ficou (inclusive a igualdade bloqueia, já que o texto dizia "inferior"). **Confirma?** Se a intenção era mesmo a literal, isso não é ajuste de regra: muda a definição do campo, e precisamos rever o Anexo A junto.

---

## 📌 Status do projeto e próximos marcos

- **EPIC-002 (Diagnóstico Indústria):** entregue tecnicamente em 26/05, status `done_under_review`. Aguarda 3 marcos para fechar de vez: (a) sua devolutiva absorvida — esta sprint, (b) validação externa formal do motor — item §6 abaixo, (c) validação interna final + handoff.

### 6. Validação externa formal do motor (STORY-036)

A spec V2 §9.3 exige um **parecer técnico** de especialista financeiro antes do uso real do motor por terceiros. Esse parecer formalizará a discussão dos 3 indicadores do farol geral e arbitrará qualquer divergência com o algoritmo §4.7.1 atual. Estou começando o processo de contratação — você (ou a EBC como organização) tem disponibilidade/interesse em fazer esse parecer técnico estruturado? Se sim, fechamos escopo + entregáveis em uma reunião curta. Se não, indique alguém de confiança da rede da EBC.

---

— Alexandro
PO DEFOnline
2026-06-02
