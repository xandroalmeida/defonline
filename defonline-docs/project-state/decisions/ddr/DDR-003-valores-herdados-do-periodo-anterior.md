---
id: DDR-003
title: Valores herdados do período anterior — a marca some quando o usuário confirma, e o silêncio é o progresso
status: proposed
created_at: 2026-08-04
decided_at: ~
approved_by: ~
supersedes: ~
superseded_by: ~
related_ddrs: [DDR-001, DDR-002]
related_adrs: [ADR-007]
related_pdrs: [PDR-003]
scope: formulários
affects_screens: [SCREEN-STORY-046-lancamento-mensal]
---

# DDR-003 — Valores herdados do período anterior

## Contexto

Segundo mecanismo anti-atrito obrigatório do `PDR-003`, decisão 6: ao abrir um lançamento novo, os campos vêm com os valores da competência anterior, *"marcados visualmente como repetidos até confirmação"* (Anexo K §K.9). A justificativa é empírica e correta — *"aluguel, contador, internet e seguros raramente mudam"*.

A `STORY-046` faz a pergunta difícil, que o Anexo K não responde: *"Como um campo 'repetido do mês passado' se distingue de um campo confirmado, sem poluir a tela com badges em 30 campos. Como o usuário confirma tudo de uma vez."*

O problema é real e é de escala. Um selo por campo funciona em 3 campos e falha em 21: vinte e uma etiquetas idênticas empilhadas deixam de ser sinal e viram textura — e uma textura que o usuário aprende a ignorar em dois segundos. Marcação que ninguém lê é ruído com custo de implementação.

Documentos lidos: Anexo K §K.9 e §K.3.5, `PDR-003` decisão 6, `ADR-007` (§*Pré-preenchimento é semente de leitura* — o payload do rascunho carrega a lista `repetidos: [campos]`), `especificacao/V2/design-system.md`, `app/resources/css/app.css` (estados de `.input` e `.input-affix`).

## Forças (drivers)

- **Escala** (altíssimo): 21 campos num único bloco. Qualquer marcação por campo é multiplicada por 21.
- **Meta de tempo** (alto): ≤5 minutos do 2º lançamento em diante (`EPIC-010`, métrica secundária). O 2º lançamento é justamente aquele em que quase tudo vem herdado.
- **Risco de confirmação cega** (alto): se confirmar tudo for barato demais e invisível demais, o usuário fecha meses inteiros sem olhar, e a série histórica registra uma repetição que nunca aconteceu.
- **O estado "confirmado" não é dado persistido** (médio): `ADR-007` guarda `repetidos[]` no payload do **rascunho**. Ao fechar o mês, o valor é valor — não existe "este número foi herdado" no lançamento. A marcação é, portanto, exclusivamente uma ajuda de preenchimento.
- **Acessibilidade** (alto): a marca não pode ser só visual. Precisa estar no nome acessível do campo, senão quem usa leitor de tela não distingue herdado de digitado.
- **Colisão de estilos** (alto): `--color-neutral` como fundo de input **já significa `disabled`** em `app.css`. Está queimado.

## Opções consideradas

### Opção A — Selo por campo (`badge` "repetido")

Cada campo herdado ganha uma etiqueta ao lado do rótulo.

```
| SALÁRIOS  (repetido)                 |
| [ R$              12.400,00 ]        |
| ALUGUEL  (repetido)                  |
| [ R$               3.200,00 ]        |
| ENERGIA  (repetido)                  |
| [ R$                 890,00 ]        |
|  ... × 21                            |
```

- **Prós:** explícito; trivial de implementar.
- **Contras:** 21 etiquetas idênticas na mesma dobra. Cada uma compete com o rótulo do campo ao lado. A informação que o usuário precisa não é "este veio de julho" — é "**ainda não olhei para este**", e o selo não distingue as duas.

### Opção B — Fundo/borda diferente no campo herdado

Campo herdado recebe tratamento visual distinto (fundo, borda lateral).

- **Prós:** silencioso; sem texto repetido.
- **Contras:** **`--color-neutral` como fundo de input já é o estado `disabled`** no `app.css`. Reusá-lo faria 21 campos editáveis parecerem bloqueados — o pior mal-entendido possível num formulário cujo objetivo é ser editado. Uma borda lateral própria exigiria token novo, e ainda seria informação só por cor, reprovando no Princípio #5.

### Opção C — A marca é uma legenda discreta que **some ao ser tocada**; o silêncio é o progresso

Campo herdado e ainda não conferido carrega uma legenda curta na posição da dica do campo — `--text-label`, `--color-secondary`, sem cor de acento, sem ícone, sem borda, sem fundo. **No instante em que o usuário edita o campo ou confirma o bloco, a legenda desaparece** e o campo passa a ser um campo comum.

```
Antes de conferir                        Depois de editar/conferir
+----------------------------------+     +----------------------------------+
| SALÁRIOS                     [?] |     | SALÁRIOS                     [?] |
| [ R$              12.400,00 ]    |     | [ R$              12.400,00 ]    |
| veio de julho                    |     |                                  |
|                                  |     |                                  |
| ALUGUEL                      [?] |     | ALUGUEL                      [?] |
| [ R$               3.200,00 ]    |     | [ R$               3.200,00 ]    |
| veio de julho                    |     |                                  |
+----------------------------------+     +----------------------------------+
```

- **Prós:** o que resta marcado é exatamente o que falta olhar — a marcação **decresce** conforme o trabalho avança, em vez de permanecer constante; nenhuma cor nova, nenhum token novo; a legenda ocupa a linha da dica, que já existe no `campo-brl` em produção, então não desloca layout; o texto entra no nome acessível do campo.
- **Contras:** a marca é discreta e pode passar despercebida por quem não a procura; e a ausência de marca é semanticamente sobrecarregada — campo nunca herdado e campo já conferido ficam idênticos. Mitigado: o bloco de conferência final (DDR-001) mostra tudo antes de fechar.

### Status quo — não marcar nada

- **Consequência:** o usuário abre 21 campos preenchidos e não sabe se aquilo é dele ou herdado. Descumpre o Anexo K §K.9 e converte pré-preenchimento — o mecanismo — em risco de erro silencioso.

## Avaliação contra os princípios

| Princípio | A | B | C | Status quo |
|---|---|---|---|---|
| 1. Simplicidade radical | ❌ 21 selos competindo | ✅ | ✅ marcação decresce com o uso | ✅ (mas não resolve) |
| 2. Mobile-first com paridade | ❌ consome largura em 360px | ✅ | ✅ 1 linha já prevista no campo | ✅ |
| 3. Tom profissional para MPE | ⚠️ ruidoso | ✅ | ✅ | ⚠️ |
| 4. Padronização > criatividade | ⚠️ badge novo | ❌ colide com `disabled` | ✅ estado de componente existente | ✅ |
| 5. Acessibilidade como hábito | ✅ texto | ❌ só cor | ✅ texto + nome acessível | ⚠️ |
| 6. Performance percebida | ✅ | ✅ | ✅ | ✅ |
| 7. Estados além do caminho feliz | ⚠️ | ⚠️ | ✅ cobre herança parcial (abaixo) | ❌ |

## Decisão

> **Adotada: Opção C.**

A força decisiva é a inversão: **o usuário não precisa saber quais campos vieram de julho — precisa saber quais ainda não olhou.** São conjuntos diferentes, e o segundo é o que fecha a tarefa. Marcar a herança e apagar a marca ao conferir faz a interface responder à pergunta certa e, de brinde, torna o progresso legível sem nenhum indicador adicional: a tela converge para o silêncio.

A Opção B cai por colisão dura — `--color-neutral` em input já significa desabilitado no `app.css`, e nenhum ganho justifica ensinar ao usuário que um campo editável parece bloqueado. A Opção A cai por escala.

### O que o padrão fixa

1. **Marca de campo:** legenda de uma linha na posição da dica do campo, `--text-label` / `--color-secondary`, sem cor de acento, sem ícone, sem borda, sem mudança de fundo. Texto provisório: *"veio de {mês anterior}"*.
2. **A marca some** quando o campo é editado **ou** quando o bloco é confirmado. Não volta.
3. **Nome acessível:** enquanto marcado, o campo é descrito por `aria-describedby` apontando para a legenda. Some junto com ela.
4. **Confirmação em bloco:** cada bloco com campos herdados abre com um `aviso-inline` variante `info` declarando **quantos** valores vieram e oferecendo um botão que limpa as marcas do bloco de uma vez. O botão diz o que faz, não "OK".
5. **Confirmação total, na entrada do formulário:** existindo competência anterior completa, o bloco 1 oferece conferir tudo de uma vez **e ir direto ao bloco de conferência final**. É o caminho de 2 toques do mês que não mudou — e ele **não fecha o lançamento**: desemboca na tela que mostra todos os valores, onde o usuário ainda precisa fechar deliberadamente. Confirmar em massa nunca é o último ato.
6. **Herança parcial é um estado de primeira classe.** Quando um grupo estava agregado no mês anterior e agora está detalhado, os itens **não têm de onde herdar** — vêm vazios, sem marca, e o bloco abre com um `aviso-inline` `info` explicando por quê. Nunca inferir item a partir do total (`ADR-007`); nunca deixar o vazio sem explicação.
7. **Nada disso é persistido no lançamento.** A marca vive no `repetidos[]` do payload do rascunho (`ADR-007`) e morre com ele. Não existe "este número foi herdado" num mês fechado.

## Consequências

### Positivas

- A marcação decresce à medida que o trabalho avança — o progresso fica visível sem indicador extra.
- Zero token novo, zero cor nova, zero colisão com estados existentes.
- O caminho de 2 toques do mês sem alterações ataca diretamente a meta de ≤5 minutos, **sem** permitir fechamento cego.
- Herança parcial deixa de ser um buraco no spec e vira estado desenhado.

### Negativas / trade-offs assumidos

- **A marca é discreta por escolha.** Quem não procurar pode não notar. Aceito: o aviso de bloco e o bloco de conferência final são as duas redes de segurança, e um selo forte × 21 já foi descartado por razão mais grave.
- **"Sem marca" é ambíguo** entre "conferido" e "nunca herdado". Aceito: a distinção não muda nenhuma ação do usuário.
- **A herança some no fechamento.** Não conseguimos responder depois "quais valores de agosto vieram repetidos de julho". Nenhuma estória pede; se virar requisito, é campo no snapshot e volta ao Arquiteto.

### Impacto no Design System

- Estado novo `herdado` do componente `campo-monetario` em `components.md` — descrito como estado, **não** como componente novo: é o `.input-affix` que já está em produção mais uma legenda.
- Padrão `pre-preenchimento-periodo-anterior` em `patterns.md`, com os 7 itens acima.
- **Nenhum token novo.** `--color-secondary` sobre `--color-surface` mede 7,8:1 — folgado para AA em texto pequeno.

### Impacto em telas existentes

- `SCREEN-STORY-046-lancamento-mensal` — consome o padrão.
- Nenhuma tela em produção é afetada. O Quiz do Diagnóstico não pré-preenche a partir de diagnóstico anterior; **se algum dia o fizer, herda este padrão** — que é a razão de isto ser DDR e não decisão local de tela.

## Implementação sugerida (notas para o Programador)

- `repetidos[]` já existe no payload do rascunho (`ADR-007`); remover o campo da lista é a operação que apaga a marca. Editar o campo remove; confirmar o bloco remove todos os daquele bloco; confirmar tudo esvazia a lista.
- A legenda ocupa o mesmo nó do texto de ajuda já presente em `campo-brl.blade.php` — não é elemento novo, é conteúdo condicional no elemento existente. Cuidado para que dica e legenda não disputem a mesma linha: quando houver ambas, a legenda vem depois.
- Confirmar tudo navega para o bloco de conferência; **não** submete.

## Critérios para revisitar

- **Se a taxa de retificação passar de 30%** (já é sinal de revisão do `PDR-003`) e a análise mostrar erros concentrados em campos herdados nunca editados, a marca é discreta demais e precisa de mais peso.
- Se a telemetria da `STORY-056` mostrar uso alto do "conferir tudo" com tempo de permanência na conferência final abaixo de poucos segundos, o caminho rápido virou fechamento cego.
- Se um segundo formulário do produto passar a pré-preencher a partir de período anterior, revalidar o padrão com dois consumidores em vez de um.

## Aprovação humana

| Campo | Valor |
|---|---|
| Apresentado em | 2026-08-04 |
| Aprovado por | (pendente) |
| Data da aprovação | (pendente) |
| Observações do aprovador | |

> Sem este bloco preenchido, o DDR **não** pode ir para `accepted`.
