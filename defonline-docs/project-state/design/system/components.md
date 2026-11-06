# Componentes

> Stack-agnóstico. Descreve anatomia, variantes e estados em termos de tokens — o de-para para Blade/Livewire/Tailwind é do Programador (`IDR-008`).
> Componente novo entra **por DDR**. Cada entrada abaixo cita o seu.

**Legenda de status:** 🟢 em produção · 🟡 especificado, aguardando implementação

---

## 1. Em produção (desde a `STORY-019`, `EPIC-004`)

Registrados aqui para que o DS reflita o que existe. Definição visual de cada um: `especificacao/V2/design-system.md` §Componentes-base; implementação: `app/resources/views/components/` e `.btn`/`.input`/`.card`/… em `app/resources/css/app.css`.

| Componente | Variantes / estados | Nota |
|---|---|---|
| 🟢 `button` | `primary` · `secondary` · `ghost`; tamanhos `md` · `sm`; `disabled`, `loading` | `min-height: 44px` já garantido |
| 🟢 `input` | `default` · `focus` · `disabled` · `error` | **Borda mede 1,23:1** — dívida, ver `tokens.md` |
| 🟢 `campo-monetario` | prefixo `R$`, alinhamento à direita, máscara BRL | `.input-affix`. Ganha estado `herdado` — §2.4 |
| 🟢 `input-moeda` | prefixo `R$`, máscara a partir dos centavos | Obrigatório para todo dinheiro digitado (RNF-UI-01). `drm/campo-monetario` e `campo-brl` do quiz o usam por dentro |
| 🟢 `input-percentual` | sufixo `%`, `decimais` 0 a 2, digitação natural | Obrigatório para todo percentual digitado (RNF-UI-01) |
| 🟢 `label` | uppercase, `--text-label`, peso 600 | Nunca substituído por placeholder |
| 🟢 `card` | superfície branca, borda, raio `lg`, sombra `sm` | |
| 🟢 `link` | `default` · `hover` | Só sobre `--color-surface` (ver `tokens.md`) |
| 🟢 `pill` | neutra | Usada para ambiente, origem RFB e, agora, para estados de competência |
| 🟢 `help` | trigger `?` + bottom-sheet (<1024px) / popover (≥1024px) | **Alvo de 18×18px** — dívida, ver `tokens.md` |
| 🟢 `page-header` | título, subtítulo, slot de ações | |
| 🟢 `breadcrumb` | | |
| 🟢 `app-header` / `app-nav` / `app-footer` | drawer <1024px, sidebar fixa ≥1024px | |
| 🟢 `relatorio.farol` | `verde` · `amarelo` · `vermelho` · `nenhum`; cor + ícone + texto | **Reprova AA** — dívida 1, `STORY-059` |
| 🟢 `relatorio.linha-indicador` / `card-indicador` | tabela ≥md / card <md | Do Diagnóstico. **Não** serve à demonstração do DRM — ver §2.5 |

---

## 2. Especificados na `STORY-046` — aguardando implementação

### 2.1 `progresso-blocos` 🟡

**DDR-001.** Promoção do padrão que já existe inline no Quiz do Diagnóstico. Sem mudança visual.

**Descrição.** Indica em que etapa de um formulário longo o usuário está e quantas faltam.

**Anatomia.**
```
███ ███ ███ ▒▒▒ ▒▒▒ ▒▒▒     ← N segmentos, preenchidos até o atual
Bloco 3 de 6 · Custo das Mercadorias Vendidas
```

- Segmentos: altura 8px, raio `full`, `gap` 8px, largura distribuída igualmente.
- Preenchido: `--color-tertiary`. Vazio: `--color-neutral`.
- Linha textual: `--text-body-sm`, `--color-secondary`.

**Estados.** `em andamento` (atual < total) · `completo` (todos preenchidos, no último bloco).

**Regras de uso.**
- Sempre acompanhado da **linha textual com o nome do bloco**. A barra sozinha responde "quanto falta" mas não "onde estou" — e é a segunda pergunta que permite retomar.
- `role="progressbar"` com `aria-valuemin/max/now` e `aria-label`. Segmentos são `aria-hidden`.
- **Não usar** para progresso de carregamento (isso é skeleton) nem para porcentagem contínua.

---

### 2.2 `aviso-inline` 🟡

**DDR-001.** Consolida três construções quase idênticas hoje espalhadas em `quiz.blade.php`: banner de retomada, alertas cruzados e bloqueios.

**Descrição.** Bloco de mensagem no fluxo da página — não flutuante, não toast.

**Anatomia.**
```
┌──────────────────────────────────────────┐
│ ⓘ  Título opcional                       │
│    Texto da mensagem, uma ou mais linhas.│
│    [ Ação ]  [ Ação secundária ]         │
└──────────────────────────────────────────┘
```

**Variantes.**

| Variante | Borda / fundo | Semântica | `role` / `aria-live` | Tem saída para frente? |
|---|---|---|---|---|
| `info` | `--color-border` / `--color-neutral` | contexto, não pede ação | — / `polite` | n/a |
| `alerta` | `--color-farol-amarelo` a 40% / a 10% | pede confirmação | `alert` / `polite` | **Sim** — aceite explícito |
| `bloqueio` | `--color-destructive` a 40% / a 5% | impede o avanço | `alert` / `assertive` | **Não** |

**Texto:** `--color-primary` em todas as variantes (nunca a cor da borda como texto — ver `tokens.md`).

**Regras de uso.**
- **A ausência de botão de "seguir mesmo assim" é o que distingue `bloqueio` de `alerta`.** Nenhuma outra pista visual carrega essa distinção sozinha, e é por isso que ela é regra e não estilo.
- `bloqueio` com botões só quando os botões levam **para outro lugar** (ex.: "Abrir o lançamento existente"), nunca para frente no mesmo fluxo.
- Um `alerta` agrupa vários itens sob **um único** aceite — não um botão por item.
- **Não usar** para erro de campo (isso fica sob o campo) nem para sucesso (que é navegação, não banner).

---

### 2.3 `seletor-modo` 🟡

**DDR-002.** Controle de duas opções em que **cada opção declara o seu custo**.

**Descrição.** Escolha entre dois modos de entrada de um grupo de campos, com o custo em campos visível na própria opção.

**Anatomia.**
```
Como você quer informar?                    [?]
+---------------------+----------------------+
| (•) Informar        | ( ) Detalhar         |
|     o total         |     por item         |
|     1 campo         |     21 campos        |
+---------------------+----------------------+
Frase explicativa: o que a opção A garante, seguido
do que a opção B acrescenta. Nessa ordem.
```

- Trilho: `--color-neutral`, raio `md`. Opções lado a lado, largura igual, `min-height: 44px`.
- **Selecionada:** fundo `--color-surface`, borda `--color-tertiary`, rótulo `--color-primary`, custo `--color-secondary`.
- **Não selecionada:** fundo transparente, rótulo e custo `--color-secondary`.
- Rótulo: `--text-body` peso 500. Custo: `--text-label`.

**Estados.** `default` · `hover` · `focus` (anel `--color-ring`) · `selecionado` · `disabled`.

**Regras de uso.**
- **Semântica de `radiogroup` real:** `<fieldset>` + `<legend>` + dois `<input type="radio">`. A aparência segmentada é estilo sobre rádios; setas navegam.
- **`--color-tertiary` só em borda e marcador**, nunca no rótulo: sobre `--color-neutral` ele mede 4,45:1 e reprova AA (`tokens.md`).
- **O custo é obrigatório e factual.** É a informação inteira do componente. Contado depois de qualquer supressão condicional — o mesmo grupo pode dizer "4 campos" numa empresa e "2 campos" em outra.
- **A frase explicativa dá a garantia antes do ganho.** Nunca advertência, nunca adjetivo de valor ("rápido", "completo", "simplificado") — enquadrar uma das opções como atalho é o erro que o Anexo K §K.5 proíbe.
- **Só duas opções.** Com três, use rádios comuns.

---

### 2.4 `campo-monetario` — estado `herdado` 🟡

**DDR-003.** Estado novo de componente existente, **não componente novo**.

**Anatomia.**
```
SALÁRIOS                          [?]
[ R$                 12.400,00 ]
veio de julho                          ← legenda, --text-label / --color-secondary
```

- **Sem** mudança de fundo (`--color-neutral` em input já significa `disabled` — colisão registrada no DDR-003).
- **Sem** borda de acento, **sem** ícone, **sem** badge.
- A legenda ocupa o mesmo nó do texto de ajuda do campo. Havendo os dois, a ajuda vem primeiro.

**Transição.** A legenda **some** quando o campo é editado ou quando o bloco é confirmado. Não volta.

**Regras de uso.**
- Ligado ao campo por `aria-describedby` enquanto existir; some junto. **Não** `aria-label` — duplicaria o nome do campo.
- **A ausência da marca é o sinal de progresso.** O que resta marcado é o que o usuário ainda não olhou.
- Sempre acompanhado de uma ação de confirmação em lote (ver padrão `pre-preenchimento-periodo-anterior`).

---

### 2.5 `demonstracao-linha` 🟡

**DDR-004.** Uma linha de demonstração financeira, com o par nominal + percentual e três níveis de hierarquia.

**Por que não reusa `relatorio.linha-indicador`:** lá cada linha é um objeto independente (um indicador); aqui as linhas são elos de uma conta encadeada, em três níveis, e cada uma carrega dois números. São conteúdos de naturezas diferentes.

**Anatomia — desktop (linha de tabela):**
```
| Rótulo                          |     Valor  | % receita |
| (–) Custo das Mercadorias Vend. |  41.200,00 |    48,9%  |
|▓= Lucro Bruto (Margem Bruta)    |  38.042,00 |    45,1%  |
```
**Anatomia — mobile (linha de card):**
```
 (–) Custo das Mercadorias Vendidas
   R$ 41.200,00                    48,9%
 ──────────────────────────────────────
 = Lucro Bruto (Margem Bruta)
   R$ 38.042,00                    45,1%
```

**Variantes por nível/tipo:**

| Variante | Rótulo | Fundo | Recuo |
|---|---|---|---|
| `nivel-1-total` (`= …`) | `--color-primary`, peso 500 | `--color-neutral` + régua superior `--color-border` | — |
| `nivel-1-deducao` (`(–) …`) | `--color-primary`, peso 400 | — | — |
| `nivel-2-subtotal` | `--color-secondary`, `--text-body-sm` | — | `--spacing-md` |
| `nivel-3-item` | `--color-secondary`, `--text-body-sm`, marcador `·` | — | `--spacing-lg` |

**Estados.** `normal` · `indisponivel` (célula, não linha) · `negativo`.

**Regras de uso.**
- **Sempre os dois números.** Nunca uma linha com só o nominal ou só o percentual. Ausência é indisponibilidade explícita (padrão `indisponibilidade`), nunca célula em branco.
- `tabular-nums` e alinhamento à direita nos dois números.
- Percentual com **1 casa decimal**; arredondamento só na apresentação.
- **Negativo:** sinal `−` e `--color-primary`. **Nunca cor** — ver DDR-004, item 6.
- **A hierarquia usa quatro canais simultâneos** (recuo, tamanho, cor, marcador). Recuo sozinho não é anunciado por leitor de tela.
- Semântica: `<th scope="row">` no rótulo (tabela) ou `<dt>`/`<dd>` (card).

---

### 2.6 `serie-linha-competencia` 🟡

**DDR-005** (estados) + **DDR-004** (par nominal/percentual).

**Descrição.** Uma competência na série histórica, com o estado do mês e seus comparativos.

**Variantes de estado:**

| Estado | Borda | Área de valor | Rótulo | Ação |
|---|---|---|---|---|
| `lancada` | sólida `--color-border` | valor + % + 2 comparativos | `lançado` | `Corrigir` |
| `retificada` | sólida | idem + `pill` `Retificado` | `lançado` | `Corrigir` |
| `nao-lancada` | **tracejada** `--color-border` | **ausente** | `não lançado` | `Lançar` |
| `rascunho` | sólida + `pill` `Rascunho` | ausente + `você parou em {bloco}` | `lançado`¹ | `Continuar` |

¹ O rascunho é de uma competência que ainda não tem lançamento fechado; o rótulo de estado acompanha o que existir.

**Regra dura.** `nao-lancada` difere de um mês com movimento zero por **quatro canais simultâneos**: borda, presença de número, rótulo textual e presença de CTA. **Nenhum deles é cor.** Uma diferença só seria frágil demais para a garantia "ausência ≠ zero" que o `ADR-007` construiu o modelo inteiro para proteger.

**Regras de uso.**
- O rótulo do mês é o link; a ação é elemento **irmão**. Nunca botão dentro de link.
- Nome acessível completo na ação: `Corrigir agosto de 2026`, não `Corrigir`.
- Variações **sem cor** — a direção não tem semântica fixa (queda de custo é boa, queda de lucro é ruim).
- `pill`s neutros para `Retificado`, `Rascunho`, `Mês atípico`, `Prejuízo no mês`. Nunca tokens de farol: farol é interpretação e pertence à `STORY-054`.

---

### 2.7 `button` — variante `link` 🟡

**DDR-004** (§Impacto no Design System).

Botão com aparência de link, para ação que **muda estado sem navegar**. Caso motivador: a troca do modo de pró-labore na Demonstração do Mês.

- Aparência: `--color-tertiary`, sem fundo, sem borda; sublinhado no `hover`; `--text-body`.
- **É `<button>`**, não `<a>`. Usar `<a>` para trocar estado é semântica errada e quebra o comportamento padrão do teclado.
- **Só sobre `--color-surface`.** Sobre `--color-neutral` o `tertiary` mede 4,45:1 e reprova AA.
- Área de toque ≥44px, ainda que o texto seja menor.
- **Não usar** onde `ghost` serve — `ghost` tem peso de botão e é a escolha certa quando a ação está numa barra de ações, não no meio de texto corrido.

## Alinhamento (RNF-UI-02)

Cards lado a lado: `flex flex-col gap-4 lg:grid lg:grid-cols-2` na grade, `flex flex-col` na seção, `flex-1 flex flex-col` no `<x-card>` e `mt-auto` no bloco de ações.
