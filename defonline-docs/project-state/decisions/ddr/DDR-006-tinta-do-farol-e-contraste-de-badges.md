---
id: DDR-006
title: "Tinta do farol — a cor que pinta o fundo não é a cor que escreve o texto"
status: accepted
created_at: 2026-08-06
decided_at: 2026-08-06
approved_by: Alexandro
supersedes: ~
superseded_by: ~
related_ddrs: [DDR-005]
related_adrs: []
related_pdrs: [PDR-006, PDR-008]
scope: transversal
affects_screens: [SCREEN-STORY-046-demonstracao-mes, SCREEN-STORY-046-serie-historica]
---

# DDR-006 — Tinta do farol e contraste de badges

## Contexto

O gate de acessibilidade da página do relatório de Diagnóstico reprova por contraste em dois elementos: os **pills de farol** (`Verde`, `Amarelo`, `Vermelho`, `Sem classificação`) e o **badge "Versão MVP"**. O `PDR-006` §1 trouxe a dívida para a `STORY-059` e disse com todas as letras que **a solução é do Designer**, porque pode exigir ajuste de token — e ajuste de token atravessa o produto inteiro.

O componente `<x-relatorio.farol>` pinta texto e ícone na cor do farol sobre um fundo de 10–12% da **mesma** cor. É um padrão comum e é justamente o que produz o problema: quanto mais a cor tinge o fundo, menos ela contrasta com o próprio texto.

Medições sobre os hex vigentes de `tokens.css`, texto sobre o fundo tingido:

| Pill | Cor | Fundo resultante | Sobre `surface` | Sobre `neutral` |
|---|---|---|---|---|
| Verde | `#0E9F6E` | `#E7F5F0` | **3,02:1** ❌ | **2,88:1** ❌ |
| Amarelo | `#D97706` | `#FAEFE1` | **2,81:1** ❌ | **2,66:1** ❌ |
| Vermelho | `#DC2626` | `#FCE9E9` | **4,13:1** ❌ | **3,92:1** ❌ |
| Sem classificação | `#94A3B8` | `#EFF1F4` | **2,27:1** ❌ | **2,16:1** ❌ |
| Badge "Versão MVP" | `#635BFF` | `#EFEFFF` | **4,13:1** ❌ | **3,90:1** ❌ |

O piso é 4,5:1 (texto normal, 12px) e **nenhum dos cinco passa** — nem o vermelho, que era o mais próximo. Os ícones do pill, que hoje herdam a mesma cor do texto, reprovam também o piso de 3:1 da WCAG 1.4.11 nos casos amarelo (2,81) e cinza (2,27).

A ironia foi registrada no `tokens.md` e vale repetir, porque é o argumento que dá urgência à correção: **o farol ganhou ícone e texto exatamente para não depender de cor, protegendo quem tem dificuldade de distinguir cores — e é essa pessoa que o contraste insuficiente ataca.**

Documentos lidos: `STORY-059` (CA-3 a CA-8), `PDR-006` §1, `PDR-008` §3, `design/system/tokens.md` §*Restrições de uso verificadas* e §*Dívidas de acessibilidade*, `IDR-008`, `especificacao/V2/design-system.md` §Tokens e §*Componentes da matriz de farol*, `components/relatorio/farol.blade.php`, `DDR-005`.

## Forças (drivers)

- **Princípio #5, acessibilidade como hábito** (altíssimo): o piso AA não é negociável, e aqui ele protege exatamente o usuário que o componente foi desenhado para proteger.
- **A identidade cromática do farol é informação** (alto): verde/amarelo/vermelho é vocabulário universal de semáforo. Uma paleta que deixe de ler como semáforo custa compreensão — não é preferência estética.
- **O que a `STORY-059` decidir vale no DRM sem renegociação** (alto): o `tokens.md` §*Dívidas* registra que o `EPIC-010` reserva slot de farol na Demonstração do Mês (`SCREEN-STORY-046-demonstracao-mes` §4.12) e que a `STORY-054` **não** deve inventar tratamento próprio. Logo a correção precisa viver em **token**, não em componente — token propaga sozinho; conserto no componente teria que ser reimplementado lá.
- **`IDR-008`: nenhum hex literal fora de `tokens.css`** (alto): o badge "Versão MVP" viola isso hoje, com `style` inline em rgba. A correção do contraste é a oportunidade certa de fechar a violação — não uma tarefa separada.
- **Telas em produção** (médio): a mudança atinge o relatório de Diagnóstico, já em homologação e visto pelo Key User. Mudança grande de aparência exigiria reapresentação; mudança pequena, não.
- **Princípio #4, padronização > criatividade** (médio): a solução não pode criar um segundo dialeto de "como se pinta um estado semântico".

## Opções consideradas

### Opção A — Escurecer os quatro tokens de farol até 4,5:1

Um valor por farol, usado tanto no texto quanto no fundo tingido e nas bordas que já os consomem.

```
+--------------------------------------------+
| ( ✓ ) Verde        <- #0B7752 sobre #E5F2EC |
| ( ! ) Amarelo      <- #9A5404 sobre #F6EDE0 |
| ( x ) Vermelho     <- #CA2121 sobre #FAE9E9 |
+--------------------------------------------+
```

- **Prós:** um token por farol, sem conceito novo; resolve texto e ícone de uma vez.
- **Contras:** o fundo tingido passa a derivar da cor escura e **o pill inteiro fica mais apagado**; o amarelo a 4,5:1 vira marrom (`#9A5404`) e leva junto o fundo, que perde a leitura de "amarelo". O card do Resumo Executivo e o alerta do Quiz, que usam os mesmos tokens em borda e fundo, escurecem sem precisar — eles não têm problema de contraste.

### Opção B — Separar a tinta do pigmento: token de fundo vivo, token de texto escuro

Cada farol passa a ter **dois** tokens: o vivo, que continua tingindo fundo e borda, e a *tinta* (`-texto`), escurecida até 4,5:1 **sobre aquele fundo vivo**, usada em texto e ícone.

```
+---------------------------------------------------+
| ( ✓ ) Verde      fundo #E7F5F0 (vivo) + tinta #0B7A55 |
| ( ! ) Amarelo    fundo #FAEFE1 (vivo) + tinta #9E5704 |
| ( x ) Vermelho   fundo #FCE9E9 (vivo) + tinta #CA2121 |
| ( i ) Sem class. fundo #EFF1F4 (vivo) + tinta #596B86 |
+---------------------------------------------------+
```

| Pill | Fundo (inalterado) | Tinta nova | Sobre `surface` | Sobre `neutral` |
|---|---|---|---|---|
| Verde | `#E7F5F0` | `#0B7A55` | **4,76:1** ✅ | **4,54:1** ✅ |
| Amarelo | `#FAEFE1` | `#9E5704` | **4,84:1** ✅ | **4,59:1** ✅ |
| Vermelho | `#FCE9E9` | `#CA2121` | **4,79:1** ✅ | **4,55:1** ✅ |
| Sem classificação | `#EFF1F4` | `#596B86` | **4,79:1** ✅ | **4,57:1** ✅ |

- **Prós:** o pill mantém **exatamente a aparência de hoje** de longe — o que muda é a densidade da tinta; a identidade cromática do fundo é preservada, inclusive a do amarelo; os usos decorativos dos tokens vivos (borda do card do Resumo Executivo, alerta do Quiz) ficam intactos; a mesma tinta serve ao ícone, que passa de 2,27–4,13 para ≥4,5 e resolve 1.4.11 com folga.
- **Contras:** dobra o número de tokens de farol (4 → 8) e cria um conceito novo no DS — "cor viva para pigmento, tinta para texto". Quem for usar precisa saber qual é qual, e um uso trocado (tinta no fundo) produziria um pill escuro e feio, sem aviso automático.

### Opção C — Rótulo em `primary`, cor só no ícone e no fundo

O texto do pill vira `--color-primary` (13,3–13,9:1 sobre os fundos tingidos) e a cor fica no ícone e no fundo.

- **Prós:** zero token novo; contraste altíssimo no texto.
- **Contras:** **não resolve o ícone**, que continua em 2,27–4,13 e precisaria de escurecimento assim mesmo — ou seja, não evita mexer nos tokens, só adia. E remove a cor do rótulo, que é hoje a leitura mais rápida do pill em varredura visual: três pills de rótulo navy pedem foco no ícone para se distinguirem.

### Status quo — manter

- **Prós:** nada muda.
- **Contras:** o gate segue reprovando, a dívida atravessa para o DRM e o usuário com baixa visão continua sem enxergar o rótulo do farol. Rejeitado sem discussão.

## Avaliação contra os princípios

| Princípio | A | B | C | Status quo |
|---|---|---|---|---|
| 1. Simplicidade radical | ✅ um token por farol | ⚠️ dois tokens por farol, com regra de uso | ✅ nenhum token novo | ✅ |
| 2. Mobile-first com paridade | ✅ igual nos dois | ✅ igual nos dois | ✅ igual nos dois | ✅ |
| 3. Tom profissional para MPE | ⚠️ paleta apagada; amarelo vira marrom | ✅ mantém a paleta de hoje | ⚠️ pills perdem leitura rápida por cor | ✅ |
| 4. Padronização > criatividade | ✅ | ✅ regra única, aplicada em token | ⚠️ trata o pill diferente do resto | ✅ |
| 5. Acessibilidade como hábito | ✅ texto e ícone ≥4,5 | ✅ texto e ícone ≥4,5 | ❌ ícone segue reprovando | ❌ |
| 6. Performance percebida | ✅ | ✅ | ✅ | ✅ |
| 7. Estados além do caminho feliz | ✅ inclui `cinza` | ✅ inclui `cinza` | ✅ | ⚠️ |

## Decisão

> **Adotada:** Opção B — tokens de tinta separados dos tokens vivos.

A força decisiva é a terceira: **a correção precisa morar em token**, porque o `EPIC-010` vai herdá-la sem renegociar, e porque os tokens vivos já são consumidos como pigmento em dois lugares que não têm problema nenhum de contraste. Escurecer o token único (A) conserta o texto estragando o pigmento de quem não pediu; a Opção C não conserta o ícone e ainda cobra o preço de tirar cor do rótulo.

O custo assumido é o conceito novo — "cor viva pinta, tinta escreve". Ele é mitigado por nome (`-texto` no fim do token diz para que serve) e por teste arquitetural, que reprova texto ou ícone pintado com token vivo dentro do componente de farol.

O badge "Versão MVP" entra na mesma decisão por ser o mesmo defeito com outra cor: passa a usar `--color-tertiary-hover` (`#4F46E5`, **5,53:1** sobre o próprio fundo tingido), que **já existe** no DS — sem token novo — e sai do `style` inline, fechando de quebra a violação do `IDR-008`.

### Adendo da implementação — o fundo do pill passou a ser opaco

Descoberto ao rodar o gate depois de aplicar a decisão acima, e incorporado aqui porque muda a regra, não só o código.

Com a tinta nova, o gate subiu de 85 para 96 e **um único nó continuou reprovando**: o pill dentro do cabeçalho do card do Resumo Executivo, medindo 4,2:1. O motivo não estava no token — estava na composição. O fundo do pill era translúcido (`/10` do Tailwind) e o cabeçalho daquele card **já é** `bg-farol-verde/8`. Os dois tingimentos somavam: o mesmo componente media 4,76:1 na tabela e 4,2:1 ali dentro.

A conta em papel não pegaria isso, porque ela assume um fundo por vez. O gate pegou.

**Correção:** o fundo do pill virou `color-mix(in srgb, var(--color-farol-<cor>) N%, var(--color-surface))` — **cor opaca**, idêntica em qualquer superfície. Com isso as classes migraram do utilitário arbitrário do Tailwind para `.farol--<cor>` no `app.css`, o que de quebra elimina a dependência do scanner do Tailwind enxergar a string literal inteira (fragilidade que o docblock antigo do componente documentava).

**A regra que fica, e vale além do farol:** componente com fundo tingido usa **cor opaca**, não alpha. Contraste de componente não pode depender do que estiver atrás dele. Gate final: **100**.

### Adendo 2 — a geometria do pill não muda, e isso agora tem teste

A migração das utilities do Tailwind para a classe de componente trouxe junto uma mudança que **este DDR não decidiu**: o `font-size` passou a herdar `--text-body-sm` e o pill cresceu de 12px para 14px de fonte, de 24px para 28px de altura. Foi pego na validação da sprint (achado F-1) e revertido — `0.75rem` / `1rem`, os valores exatos de `text-xs`, que era o que já estava em produção.

Fica explícito, porque a frase "o pill mantém exatamente a aparência de hoje" é o compromisso deste DDR e agora é verificável: **cor é o que este DDR muda; geometria, não.** `FarolGeometriaBrowserTest` trava fonte, altura, padding e gap. Alterar qualquer um deles exige aditivo aqui, não um refactor de classe.

## Consequências

### Positivas

- Os cinco elementos que o gate acusa passam a AA com margem ≥0,04 sobre o piso, medida no fundo pior (`neutral`).
- O ícone do farol sobe de 2,27–4,13 para ≥4,5:1 — acima até do piso de 3:1 que a WCAG 1.4.11 pede para gráfico significativo.
- O farol continua comunicando por **cor, ícone e texto** (`STORY-059` CA-5): nenhum canal foi removido, e o canal cor ficou mais legível, não menos.
- A `STORY-054` (leitura do DRM) herda o tratamento pronto, como o `tokens.md` §*Dívidas* exige.
- O badge do relatório deixa de carregar hex literal no Blade (`IDR-008`).

### Negativas / trade-offs assumidos

- **Oito tokens de farol onde havia quatro.** É mais superfície para errar. Aceito porque a alternativa era escurecer pigmento que não precisava escurecer.
- **A tinta do vermelho (`#CA2121`) é quase igual à cor viva (`#DC2626`).** Um leitor apressado pode concluir que o par é redundante e usar um pelo outro. O comentário em `tokens.css` diz por que os dois existem, e o teste arquitetural pega o uso trocado.
- **Nada foi feito pelos usos decorativos dos tokens vivos** (borda do card do Resumo Executivo, faixa do alerta do Quiz). Eles não estavam na lista do gate e a `STORY-059` fecha cinco achados nomeados, não abre frente de auditoria. Se um gate futuro os acusar, a tinta já existe.

### Impacto no Design System

- **Quatro tokens novos** em `tokens.css` (`@theme`) e na tabela §*Componentes da matriz de farol* do DS canônico, com bump de versão lá: `--color-farol-verde-texto`, `--color-farol-amarelo-texto`, `--color-farol-vermelho-texto`, `--color-farol-cinza-texto`.
- **Regra de uso nova**, a registrar em `design/system/tokens.md`: token vivo tinge fundo e borda; token `-texto` escreve texto e ícone. Nunca o contrário.
- A tabela de combinações proibidas do `tokens.md` perde três linhas (farol verde/amarelo/cinza como texto) e ganha as quatro combinações aprovadas de tinta sobre fundo tingido.

### Impacto em telas existentes

- Relatório de Diagnóstico (`/diagnosticos/{id}`) — pills e badge. É a tela do gate.
- `SCREEN-STORY-046-demonstracao-mes` §4.12 e `SCREEN-STORY-046-serie-historica` — herdam sem alteração de spec: o slot de farol continua descrito do mesmo jeito, com contraste agora garantido pelo token.

## Implementação sugerida (notas para o Programador)

- As classes do Tailwind v4 precisam de string literal completa para o scanner emitir a var (o docblock de `farol.blade.php` já explica) — a tinta entra como `text-[color:var(--color-farol-verde-texto)]` no mesmo mapa de estilos, sem interpolação.
- O ícone herda `currentColor`; trocar a cor do `<span>` externo já leva ícone e rótulo juntos. Nada precisa ser dividido em dois elementos.
- O badge "Versão MVP" pede uma classe `pill--versao` no `app.css` em vez do `style` inline — é o que tira o hex do Blade.
- Teste arquitetural sugerido: falhar se `farol.blade.php` usar `--color-farol-<cor>)` (sem sufixo) em propriedade de texto, e se qualquer Blade voltar a trazer hex literal.

## Critérios para revisitar

- Se o DS ganhar tema escuro: as tintas foram calculadas contra fundos claros e teriam que ser recalculadas — e aí talvez um par de tokens por modo seja melhor que oito tokens fixos.
- Se um terceiro elemento precisar da mesma separação pigmento/tinta fora do farol: o conceito passa a merecer nome próprio no DS, não sufixo por token.
- Se a WCAG 3 (APCA) entrar em vigor no gate: os valores mudam de escala e a tabela inteira é refeita.

## Aprovação humana

| Campo | Valor |
|---|---|
| Apresentado em | 2026-08-06 |
| Aprovado por | Alexandro (PO) |
| Data da aprovação | 2026-08-06 |
| Observações do aprovador | ~ |

> Sem este bloco preenchido, o DDR **não** pode ir para `accepted`.
