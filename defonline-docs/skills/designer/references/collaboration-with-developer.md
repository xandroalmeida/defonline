# Colaboração com o Programador (trabalho em paralelo)

No DEFOnline, Designer e Programador pegam a **mesma estória ao mesmo tempo**. Esse modelo é mais rápido que o sequencial ("design termina, código começa"), mas tem **risco real de retrabalho** se vocês não se alinharem cedo.

Este documento descreve as salvaguardas operacionais. Siga a ordem; ela existe por motivo.

## Por que paralelo (e não sequencial)

- Time pequeno (você + 1–3 devs) — esperar 2 dias por spec antes de começar a codar é caro.
- Ciclos de feedback curtos — Programador encontra limitação técnica enquanto Designer ainda pode ajustar.
- Spec evolui com aprendizado real do código (algumas decisões só ficam claras quando se tenta implementar).

Mas paralelo só funciona com **alinhamento cedo e explícito**. Sem isso, vira caos.

## O ritmo padrão

### Passo 0 — PO escreve a estória

A estória do PO traz:

- `target_role: programador` (estória principal)
- `requires_design: true` (sinaliza que Designer também atua)
- CAs funcionais (não dita UI)
- Referências a documentos (spec funcional, PDRs, ADRs, DDRs vigentes)

Quando PO marca `requires_design: true`, **você (Designer) e o Programador entram juntos**.

### Passo 1 — Rabisco inicial em ≤30 min (Designer)

Você produz um **rabisco do spec de tela** antes que qualquer código de UI comece. O rabisco contém:

- Fluxo grosseiro (entrada → ações → saída)
- Layout aproximado **mobile** (sketch ASCII/SVG, sem polimento)
- Lista de componentes do DS que pretende reusar
- Lista dos estados que vão precisar ser desenhados (vazio, loading, erro, etc — só a lista, não o desenho final)
- Dúvidas técnicas em aberto (ex: "esse padrão de wizard cabe com a stack que o Arquiteto escolheu?")

Salva em `project-state/design/screens/STORY-XXX-*.md` com `status: draft`.

**Por que isso importa:** rabisco serve para alinhamento, não para entrega. O Programador precisa ver **a direção** antes de começar a montar a estrutura — não os pixels finais.

### Passo 2 — Sync com Programador (≤15 min)

Conversa curta — chat ou call rápida. Pauta:

- Você apresenta o rabisco em 5 min.
- Programador aponta **limitações técnicas conhecidas**: componente que ainda não existe na lib, restrição da stack escolhida (ADR vigente), dependência de API que ainda não está pronta, dificuldade desproporcional em algum padrão.
- Vocês acordam ajustes no rabisco **antes** que vire código.
- Vocês acordam **a ordem natural de implementação** (que estado começar, que componente é prioritário) — isso vira a sequência de entrega do spec detalhado.

Documenta o sync em "Notas do agente" da estória — 3–5 bullets com decisões tomadas e ajustes acordados.

### Passo 3 — Spec detalhado em paralelo com o código

Você refina o spec — agora completo (estados, microcopy, identificadores, exceções, acessibilidade). Programador começa pela estrutura (componentes base, contratos com API). **Você entrega cada estado antes que o Programador chegue a ele.**

Heurística operacional: o Programador implementa caminho feliz primeiro? Você entrega caminho feliz completo primeiro, e os estados de erro/vazio/loading na ordem em que ele vai implementar.

**Anti-padrão:** Designer "termina tudo" e joga no Programador no fim. Anula o ganho do paralelo.

### Passo 4 — Mudança consciente quando o spec evolui

Mudança no spec **depois** que o código começou é normal — paralelo descobre coisas. Mas mudança em silêncio quebra confiança. Quando uma mudança aparece:

1. Avalie o **impacto**: quanto código já foi escrito que precisa mudar?
2. Discuta com o Programador: vale a pena agora, ou vira backlog/próxima estória?
3. Registre a decisão em "Notas do agente" da estória — o que mudou, por quê, custo aceito.
4. Atualize o spec com data e motivo em "Histórico de mudanças".

**Você não muda spec em silêncio.** Spec é contrato de paralelismo.

### Passo 5 — Revisão quando o PR sobe (estória já em `in_review`)

Quando o Programador abre o PR e move a estória para `status: in_review`, você **revisa o implementado** comparando com o spec:

- Em **mobile e desktop reais** (browser, não só monitor — DevTools com viewport mobile vale).
- Em **todos os estados** que o spec previu (vazio, loading, erro, sem permissão — não só caminho feliz).
- **Acessibilidade**: contraste, foco visível, tab order, ícones com label, alvos de toque (ver `accessibility-basics.md`).

Divergências em relação ao spec são **bug**, não preferência — abrem como comentário no PR.

- **Divergência não-bloqueante:** comentário no PR; Programador ajusta no mesmo PR antes do merge.
- **Divergência bloqueante** (estado faltando, acessibilidade abaixo do piso WCAG AA, fluxo divergente): comentário marcado como bloqueante; o Programador volta a estória para `status: in_progress` e resolve antes de retomar `in_review`.

**Você não emite veredito independente.** Você sinaliza divergências em relação ao spec. Veredito independente final continua sendo do Validador no fim do épico.

**Anti-padrão a evitar:** "tá quase, depois ajusta". A janela de ajuste fácil é antes do merge — depois disso, vira estória nova, que é caro.

### Passo 6 — Acessibilidade: revisão sua + CI + Validador

Três camadas, cada uma com papel próprio:

- **Você (Designer)** revisa acessibilidade no PR como parte do Passo 5. Sinaliza divergências como bloqueantes quando o piso WCAG AA é violado.
- **CI** roda checagens automáticas (axe/Lighthouse — conforme `quality-standards.md` do PO). Falha em check obrigatório bloqueia merge tecnicamente.
- **Validador** confere acessibilidade no fim do épico como parte do checklist independente — ele tem a palavra final.

Sua revisão **complementa**, não substitui, CI e Validador. Você não é o gate técnico de merge nem a checagem independente.

## Quando o Programador escala para você (`[ESCALONAMENTO-DESIGNER]`)

Programador escala quando:

- Componente do DS não cobre o caso e ele não sabe se cria variante ou usa exceção.
- Spec é ambíguo em algum estado.
- Limitação técnica descoberta no meio força reabrir uma decisão de UX.
- Microcopy faltando ou ambíguo para uma situação que o spec não previu.

Sua resposta:

- **Decisão local (cabe na estória atual)** → resolve e atualiza o spec em paralelo.
- **Decisão durável (afeta outras telas)** → abre DDR; estória do Programador entra em `blocked` se não dá pra contornar sem a decisão; segue se dá.

**Você não inventa decisão técnica.** Se a escalação é "qual lib de form usar?" → devolva ao Arquiteto.

## Quando você escala para o Programador

Você escala quando:

- Não tem certeza se uma escolha de design é viável tecnicamente sem custo desproporcional.
- Está propondo um padrão novo que pode ter implicação técnica que você não vê.
- Quer entender o "porquê" de uma escolha existente do código antes de redesenhar.

A escala é informal — chat/issue/comentário. Não vira `blocked` da sua parte; é busca de informação.

## Padrões anti-retrabalho

- **Rabisco antes de detalhar.** Detalhe perfeito antes de validar com Programador = retrabalho garantido.
- **Estado nunca esquecido.** Listar estados no rabisco já filtra surpresas.
- **DS antes de tela.** Reusar componente do DS evita 80% da divergência possível.
- **Microcopy no spec, não no chat.** Combinado no chat se perde; combinado no spec, fica.
- **Revisão em browser real, não monitor.** "Ficou bem no meu monitor de 27" não vale para mobile.
- **Acessibilidade conferida antes do merge.** Refatorar acessibilidade depois é o tipo de retrabalho mais caro.

## O que NUNCA fazer (especificamente neste workflow)

- Mudar spec em silêncio depois que o Programador começou.
- Aprovar implementação que diverge do spec só porque "ele já fez assim".
- Esperar o Programador "terminar" para começar a refinar o spec.
- Empurrar componente novo para o DS sem DDR só porque a estória está com pressa.
- Substituir spec por conversa no chat ("a gente combina depois") — se não está no spec, não existe.
