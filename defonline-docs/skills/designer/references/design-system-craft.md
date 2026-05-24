# Ofício do Design System

Como construir e evoluir o Design System do DEFOnline. Use junto com `templates/design-system.md` para a estrutura inicial.

## O que o DS é (e não é)

**É:**

- Vocabulário visual e de interação **compartilhado** pelas telas.
- Fonte de verdade para tokens, componentes e padrões.
- Stack-agnóstico: descreve comportamento e visual em termos de tokens e estados, não em código de framework.
- Documento **vivo**, versionado em git, evoluído via DDR.

**Não é:**

- Catálogo de componentes "que talvez sejam úteis" — só vive no DS o que é usado.
- Implementação técnica em React/Vue/qualquer — isso é trabalho do Programador, restringido por ADR.
- Substituto do spec de tela — DS dá os blocos; spec monta o quebra-cabeças.
- Lugar para experimentos visuais — experimento vive em DDR `proposed`; só vai pro DS quando `accepted`.

## Estrutura

```
project-state/design/system/
├── README.md              ← visão, navegação, status, versão
├── tokens.md              ← cor, tipografia, espaçamento, raio, sombra, motion, breakpoints
├── components.md          ← biblioteca de componentes com variantes e estados
├── patterns.md            ← padrões compostos (form, listagem, wizard, vazio, erro)
└── voice-and-tone.md      ← tom, microcopy, vocabulário
```

A primeira vez que o DS for criado, use `templates/design-system.md` como esqueleto.

## Como tokens funcionam

Tokens são as **fundações**. Tudo no DS sai daqui:

- **Cor:** paleta sóbria; valores semânticos (`color.text.primary`, `color.bg.surface`), não valores brutos espalhados.
- **Tipografia:** família + escala de tamanhos (mobile/desktop) + pesos + line-height.
- **Espaçamento:** escala consistente (múltiplos de 4px é convenção sólida).
- **Raio (border-radius):** escala curta — `sm`, `md`, `lg`, `full`. Não invente tamanhos do meio.
- **Sombra:** escala curta — `sm`, `md`, `lg`. Sombra forte com parcimônia (tom profissional ≠ produto colorido).
- **Motion:** durações curtas (100–300ms) com easing. Acima de 300ms = erro de design.
- **Breakpoints:** mobile, tablet, desktop, wide.

### Regra de ouro dos tokens

> Em spec de tela, **nunca use valor cru** (`#1E40AF`, `16px`, `200ms`). Use token (`color.brand.primary`, `space.4`, `motion.base`).

Valor cru em spec = futuramente difícil mudar a paleta inteira, difícil garantir consistência. Token = mudança central propaga.

## Como componentes funcionam

Cada componente do DS tem **estrutura padronizada**:

```
### `<id>`

**Descrição:** propósito em uma frase.

**Anatomia:** estrutura visual (label, ícone, padding, raio, cor, peso).

**Variantes:** se há (`sm`/`md`/`lg`, `primary`/`secondary`/`danger`).

**Estados:** default / hover / focus / active / disabled / loading / error (quando aplicáveis).

**Usar quando:** caso de uso correto.

**Não usar quando:** caso de uso errado (restringe).

**Acessibilidade:** notas específicas (associação de label, foco, leitor de tela).

**Exemplo visual:** sketch ASCII/SVG inline.
```

### Estados são obrigatórios

Componente sem estados desenhados é componente fantasma. Mínimo:

- **Interativo:** default, hover, focus (visível!), active, disabled.
- **Com loading:** + loading state.
- **Com erro:** + error state.
- **Com seleção:** + selected/checked state.

### Variantes com parcimônia

3 variantes de botão? Razoável (primary/secondary/danger). 10 variantes? Você está reinventando design.

Variante existe para **propósito**, não para gosto. Se uma variante não tem caso de uso claro, ela não deveria estar no DS.

## Como padrões funcionam

Padrões são **combinações recorrentes** de componentes que resolvem problemas frequentes. Diferente de componente:

- Componente = elemento individual (botão, input, card).
- Padrão = composição (form completo, listagem com filtro, wizard).

Padrões evitam reinventar a roda em cada tela. Lista mínima esperada conforme o produto amadurece:

- `pattern.form` — campos + validação + submit
- `pattern.wizard` — fluxo em estágios
- `pattern.listing` — filtros + lista/tabela + paginação + estado vazio
- `pattern.empty` — empty-state padronizado
- `pattern.error` — erro recuperável (toast) vs erro de tela (página)
- `pattern.detail` — visualização detalhada de um item (header, seções, ações)

## Como o DS evolui

### Adicionar componente novo

1. Aparece necessidade numa tela (rabisco do spec).
2. **Confirme que nenhum componente existente serve** (revisar `components.md` inteiro). 90% das vezes, serve com leve adaptação.
3. Se realmente novo, abre **DDR proposto** descrevendo o componente, suas variantes/estados, e justificando por que não cabe estender existente.
4. Aprovação humana do DDR.
5. Atualiza `components.md` com o novo componente (anatomia, variantes, estados, regras de uso).
6. Tela pode usar o componente — `ds_components_used` no spec referencia.

**Atalho proibido:** "vou usar agora e adiciono ao DS depois". É como vira componente fantasma.

### Estender componente existente

Variante nova de componente que já existe (ex.: nova cor de badge, novo tamanho de input):

1. **Avalia se a variante é durável** (vai ser reutilizada) ou pontual (uma tela só).
2. **Pontual** → exceção declarada no spec, sem mexer no DS.
3. **Durável** → DDR (mesmo fluxo acima), só que atualizando o componente existente em `components.md`.

### Mudar token

Token é fundação — mudar afeta tudo que o referencia. Mudança de token:

1. **Sempre via DDR.** Mesmo "trocar cor primária de azul para verde" é DDR.
2. **Impacto declarado:** quais telas usam, quais componentes usam.
3. **Plano de migração:** Programador atualiza referências; verificação visual em todas as telas afetadas.

### Remover algo do DS

Remoção também via DDR. Componente que não é mais usado vira `deprecated` antes de remover (com data de remoção + alternativa indicada).

## Sincronia DS ↔ realidade

O DS é fonte de verdade **se for verdade**. Se diverge do que está em produção, vira documentação inútil.

Disciplinas para manter sincronia:

- **DS atualizado na mesma operação** em que tela usa novo padrão. Não "atualiza o DS depois".
- **Revisão periódica** (a cada fim de épico de UI): comparar DS com telas em produção, identificar divergências, registrar.
- **PR de tela aponta componentes do DS usados** — força o Programador a confirmar (e abre divergência se algo não bate).

## Quando o DS ainda não existe

Início do projeto: `project-state/design/system/` está vazio. Você não tem que entregar DS completo antes da primeira tela — você entrega o **mínimo necessário** para a primeira tela e cresce dali.

Sugestão de progressão:

- **Primeira tela:** tokens essenciais (cor, tipografia, espaçamento) + componentes da tela (provavelmente button.primary, input.text, card, empty-state).
- **Primeiras 3 telas:** já tem padrão de form, listagem básica, vazio.
- **Final do EPIC-001:** DS razoável para o domínio coberto (form, listagem, detail, wizard se houver).

Resista à tentação de "fazer o DS perfeito primeiro". Você acerta o DS **com uso real**, não em abstração.

## Sinais de DS saudável

- Spec novo refere componentes existentes em ≥80% dos casos.
- Tela em produção é visualmente coerente com outras (mesmo padrão de espaçamento, cor, hierarquia).
- Mudança de token propaga sem cirurgia em N telas.
- Novo Designer/Programador entende o vocabulário lendo o DS em <30 min.

## Sinais de DS apodrecendo

- Specs cheios de exceções ao DS sem justificativa robusta.
- Componente "quase igual" recriado em vez de variante.
- DS desatualizado em relação a telas em produção.
- Tokens com valores brutos espalhados pelos specs.
- Componente sem estados desenhados — só caminho feliz.
- Padrões duplicados (form de cadastro e form de edição com estruturas diferentes sem motivo).
