---
id: DDR-005
title: Vocabulário visual de indisponibilidade — "não dá para calcular", "não existe" e "é zero" são três coisas diferentes na tela
status: accepted
created_at: 2026-08-04
decided_at: 2026-08-06
approved_by: Alexandro
supersedes: ~
superseded_by: ~
related_ddrs: [DDR-002, DDR-004]
related_adrs: [ADR-007]
related_pdrs: [PDR-003, PDR-004]
scope: transversal
affects_screens: [SCREEN-STORY-046-demonstracao-mes, SCREEN-STORY-046-serie-historica, SCREEN-STORY-046-lancamento-mensal]
---

# DDR-005 — Vocabulário visual de indisponibilidade

## Contexto

O DEFOnline já trata indisponibilidade em produção: o relatório de Diagnóstico exibe *"Indisponível"* em itálico, com opacidade reduzida e sem farol, quando um indicador não pôde ser calculado. Um motivo, um tratamento.

O DRM traz **três motivos semanticamente distintos**, e um quarto valor que se parece com eles e não é:

| O que é | Motivo (`estrutura-drm` §4) | Origem |
|---|---|---|
| Não dá para calcular o percentual | `indisponivel:sem_faturamento` | `D01 = 0` (Anexo K §K.7) |
| Este item não foi coletado | `indisponivel:grupo_agregado` | grupo lançado em modo agregado (§K.5) |
| Este mês não foi lançado | `indisponivel:sem_lancamento` | ausência de linha (`ADR-007`) |
| **É zero mesmo** | — | mês sem movimento, com lançamento |

A quarta linha é o motivo deste DDR. A confusão entre as três primeiras e ela não é cosmética — é a falha que o `ADR-007` chama de **"quedas fantasma"** e proíbe por construção (*"`COALESCE(…, 0)` é proibido em qualquer query de série"*). A proibição existe no banco e na query. **Falta o correspondente na tela:** um mês não lançado que apareça na série como um traço solitário, ao lado de um mês zerado que apareça como `R$ 0,00`, ainda pode ser lido como "caiu para quase nada" por quem só olha a coluna.

E há a assimetria que o Anexo K §K.7 exige e que nenhum tratamento único resolve: com receita zero, **os percentuais somem e os valores nominais ficam** — na mesma linha, uma metade indisponível e a outra presente. *"É a competência mais importante de todas para o usuário ver: quanto custou manter o negócio parado."*

Documentos lidos: `design/estrutura-drm.md` §4 e §5, Anexo K §K.5 e §K.7, `ADR-007` (§*Ausência ≠ zero*), `STORY-049` CA-5, `STORY-051` CA-2 e CA-4, `IDR-013`, e o tratamento em produção em `components/relatorio/linha-indicador.blade.php`.

## Forças (drivers)

- **Ausência lida como zero corrompe a série** (altíssimo): é o risco nomeado pelo `ADR-007` e pelo `estrutura-drm` §4. A defesa no banco existe; a defesa na tela não.
- **A assimetria de `sem_faturamento`** (alto): metade da linha indisponível, metade presente, e a presente é a que mais importa.
- **"Não parecer erro do sistema"** (alto): `STORY-049` CA-5 — *"motivo explicado sem parecer erro do sistema"*. Mês sem faturamento é legítimo (obra parada, sazonalidade — Anexo K, alerta A1).
- **Precedente em produção** (médio): o Diagnóstico já tem um tratamento. Princípio #4 manda herdar a forma e estender o vocabulário, não criar outro.
- **Acessibilidade** (alto): um traço `—` não é lido por leitor de tela como "indisponível". Sem texto alternativo, a informação simplesmente não existe para quem não vê.
- **Custo de repetir o motivo** (médio): repetir "sem faturamento" em 17 linhas é o mesmo erro de escala do DDR-003.

## Opções consideradas

### Opção A — Tratamento único: tudo vira "Indisponível" (o que o Diagnóstico faz)

Todos os motivos renderizam igual; o motivo fica só no dado.

- **Prós:** herda literalmente o que está em produção; zero decisão nova.
- **Contras:** achata exatamente a distinção que o `ADR-007` construiu o modelo para preservar. E não resolve a assimetria de `sem_faturamento`, em que a linha é metade indisponível e metade presente. Herdar a forma sem herdar o problema seria copiar sem ler.

### Opção B — Um ícone por motivo, na célula

Cada motivo ganha um glifo próprio na célula, com tooltip.

- **Prós:** compacto; o motivo fica junto do dado.
- **Contras:** ensina três glifos novos a quem abriu o produto para saber se teve lucro. Tooltip não funciona em toque nem em teclado (`accessibility-basics` §6). Repetido em 17 linhas, é o problema de escala do DDR-003 com ícones em vez de selos.

### Opção C — Marca curta e uniforme na célula + **motivo explicado uma vez, no escopo em que ele vale**

A célula indisponível recebe sempre a mesma marca discreta: `—` visual com o motivo no texto acessível. O **motivo em português** aparece uma única vez, no nível em que ele é verdadeiro:

- vale para a **coluna inteira** (`sem_faturamento`) → aviso acima da demonstração;
- vale para um **grupo** (`grupo_agregado`) → nota no cabeçalho daquele grupo;
- vale para uma **competência** (`sem_lancamento`) → o próprio estado da linha na série, com CTA.

```
Sem faturamento — demonstração (mobile)
+------------------------------------------------+
| ┌────────────────────────────────────────────┐ |
| │ Sem faturamento neste mês — os percentuais │ |
| │ sobre a receita não se aplicam. Os valores │ |
| │ em reais continuam abaixo.                 │ |
| └────────────────────────────────────────────┘ |
+------------------------------------------------+
| Receita                                        |
|  Receita Bruta                                 |
|    R$ 0,00                                  —  |
|  (–) Deduções da Receita Bruta                 |
|    R$ 0,00                                  —  |
| ────────────────────────────────────────────── |
|  = Receita Operacional Líquida                 |
|    R$ 0,00                                  —  |
+------------------------------------------------+
| Despesas operacionais                          |
|  (–) Despesas Operacionais                     |
|    R$ 8.430,00                              —  |   ← o número que importa
+------------------------------------------------+

Série histórica — ausência vs. zero (mobile)
+------------------------------------------------+
| AGO/2026                            lançado    |
| Lucro do mês   R$ 0,00                  0,0%   |   ← mês sem movimento: é zero
+------------------------------------------------+
| ╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌ |
| ╎ JUL/2026                    não lançado    ╎ |   ← borda tracejada, sem número
| ╎ Sem lançamento para este mês.              ╎ |
| ╎ [ Lançar julho/2026 ]                      ╎ |
| ╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌╌ |
+------------------------------------------------+
| JUN/2026                            lançado    |
| Lucro do mês   R$ 4.120,00              8,2%   |
+------------------------------------------------+
```

- **Prós:** o motivo é dito uma vez, em prosa, no escopo certo — sem repetição de 17 linhas e sem vocabulário de glifos; a ausência de lançamento fica **estruturalmente** diferente de um número (borda tracejada, ausência de valor, presença de CTA), e não apenas numericamente diferente; a assimetria de `sem_faturamento` cai naturalmente, porque a marca é por célula e o aviso é por coluna.
- **Contras:** exige que a tela saiba em que escopo cada motivo vale — três regras em vez de uma.

### Status quo — herdar o Diagnóstico sem estender

É a Opção A.

## Avaliação contra os princípios

| Princípio | A | B | C |
|---|---|---|---|
| 1. Simplicidade radical | ✅ uma regra | ⚠️ três glifos a aprender | ✅ uma marca, motivo em prosa |
| 2. Mobile-first com paridade | ✅ | ⚠️ tooltip não existe no toque | ✅ aviso e nota são blocos de texto |
| 3. Tom profissional para MPE | ⚠️ "Indisponível" é seco para um mês legítimo | ⚠️ | ✅ explica sem culpar |
| 4. Padronização > criatividade | ✅ | ❌ vocabulário novo | ✅ estende o padrão existente |
| 5. Acessibilidade como hábito | ⚠️ | ❌ tooltip inacessível | ✅ motivo no texto acessível da célula |
| 6. Performance percebida | ✅ | ✅ | ✅ |
| 7. Estados além do caminho feliz | ❌ é o estado que ele achata | ⚠️ | ✅ é o assunto |

## Decisão

> **Adotada: Opção C.**

A força decisiva é que **o `ADR-007` gastou modelo de dados para tornar "ausência ≠ zero" impossível de violar, e essa garantia morre na última camada se a tela apresentar as duas coisas com a mesma aparência.** A tela é a única superfície onde o usuário encontra o dado; uma garantia que não chega até lá não é garantia.

A Opção A cai porque achata a distinção que o modelo protege. A Opção B cai por acessibilidade — tooltip não sobrevive a toque nem a teclado, e o motivo passaria a ser informação inacessível.

### O que o padrão fixa

1. **Marca única de célula indisponível:** `—` visual, com o motivo em texto acessível na própria célula (`sr-only` ou equivalente). Nunca célula em branco, nunca `0`, nunca `N/A`. Nunca só cor, nunca só glifo.
2. **O motivo em português aparece uma vez, no escopo em que vale:**
   - **coluna inteira** → `aviso-inline` variante `info` acima da tabela;
   - **grupo** → nota no cabeçalho do grupo;
   - **competência** → o estado visual da própria linha na série.
3. **Ausência é estrutural, não numérica.** Uma competência não lançada não é "uma linha com traço": é uma linha com **borda tracejada**, sem área de valor, com rótulo textual *"não lançado"* e um CTA para lançá-la. Um mês com movimento zero é uma linha normal exibindo `R$ 0,00`. As duas nunca se parecem.
4. **Indisponível não é erro.** Nada de `--color-destructive`, nada de ícone de alerta, nada de opacidade que sugira falha. Tokens: `--color-secondary` sobre `--color-surface` (7,8:1).
5. **Indisponibilidade parcial é normal.** Uma linha pode ter valor nominal presente e percentual indisponível ao mesmo tempo. Isso não degrada a linha inteira — nem opacidade, nem itálico na linha toda. Só a célula afetada.
6. **Vocabulário fixo** (texto provisório; redação final é da `STORY-052`):
   | Motivo | Frase |
   |---|---|
   | `indisponivel:sem_faturamento` | "Sem faturamento neste mês — os percentuais sobre a receita não se aplicam." (verbatim do Anexo K §K.7) |
   | `indisponivel:grupo_agregado` | "Este grupo foi lançado pelo total, então não há detalhe por item neste mês." |
   | `indisponivel:sem_lancamento` | "Sem lançamento para este mês." |
   | comparativo sem base | "Disponível a partir do 2º mês lançado." / "Disponível a partir do 4º mês lançado." |
7. **A explicação nunca culpa o usuário nem o sistema.** Estado, não falha. Especialmente em `grupo_agregado`, que é consequência de uma escolha legítima que o próprio produto ofereceu (DDR-002).

## Consequências

### Positivas

- A garantia "ausência ≠ zero" atravessa do banco até o pixel.
- O mês sem faturamento — que o Anexo K chama de o mais importante de todos — mostra o que tem a mostrar, sem parecer quebrado.
- O vocabulário serve o Diagnóstico também: `Indisponível` em produção passa a ser um caso desta regra, com motivo em prosa, se o PO quiser aproveitar.

### Negativas / trade-offs assumidos

- **Três escopos de explicação em vez de uma regra.** É complexidade real, e é o preço de não mentir.
- **A marca `—` é discreta.** Deliberado: quem quer o motivo tem a prosa; quem quer o número tem o número; e a alternativa (17 repetições do motivo) já foi descartada por escala.
- **Não estendemos isto ao Diagnóstico agora.** Fica como oportunidade para o PO, não como trabalho embutido no `EPIC-010`.

### Impacto no Design System

- Padrão `indisponibilidade` em `patterns.md` — os 7 itens acima, com a tabela de vocabulário.
- Estado `indisponivel` do componente `demonstracao-linha` (DDR-004) e estado `nao-lancado` da linha de série.
- **Nenhum token novo.** A borda tracejada é `--color-border` com `border-style: dashed`.

### Impacto em telas existentes

- As três telas da `STORY-046`.
- **Relatório de Diagnóstico (produção):** compatível, não alterado. Seu `Indisponível` já obedece aos itens 1, 4 e 7. Alinhá-lo aos itens 2 e 6 seria melhoria — proposta ao PO, fora do `EPIC-010`.

## Implementação sugerida (notas para o Programador)

- O motivo já vem no snapshot por linha (`ADR-007`). A tela mapeia motivo → frase por configuração, no mesmo arquivo em que a `STORY-052` vai mexer.
- Para o aviso de escopo de coluna, basta detectar que **todas** as células de percentual estão indisponíveis pelo mesmo motivo — não é preciso um sinalizador próprio.
- A espinha de meses da série é gerada na leitura (`ADR-007`); os meses sem correspondente são os que recebem o estado `nao-lancado`. `COALESCE(…, 0)` continua proibido.

## Critérios para revisitar

- Se aparecer um quarto motivo de indisponibilidade, verificar se ele cabe em um dos três escopos.
- Se testes de usabilidade mostrarem alguém lendo um mês não lançado como queda, o item 3 foi discreto demais e precisa de mais peso estrutural.
- Se o PO decidir alinhar o Diagnóstico a este vocabulário, revisar com dois consumidores.

## Aprovação humana

| Campo | Valor |
|---|---|
| Apresentado em | 2026-08-04 |
| Aprovado por | (pendente) |
| Data da aprovação | (pendente) |
| Observações do aprovador | |

> Sem este bloco preenchido, o DDR **não** pode ir para `accepted`.
