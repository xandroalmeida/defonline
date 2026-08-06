# Voz e tom — ponteiro e vocabulário fixado

> **A referência de tom NÃO está aqui.** Ela vive em [`skills/designer/references/tone-and-voice.md`](../../../skills/designer/references/tone-and-voice.md) — atributos de voz, padrões de microcopy, o que evitar, checklist de revisão.
> O vocabulário do domínio vive em [`skills/po/references/glossary.md`](../../../skills/po/references/glossary.md) e é do PO. **O Designer não rebatiza termo de negócio.**
>
> Este arquivo carrega o que não está lá: o vocabulário **fixado por DDR**, que passa a ser vinculante entre telas.

## Quem escreve o quê

| Superfície | Dono |
|---|---|
| Rótulos de campo, tooltips, textos de leitura do DRM | **`STORY-052`** — pacote editorial da EBC |
| Rótulos de linha da demonstração | `estrutura-drm.md` §1, resolvidos dentro do snapshot (`ADR-007`) |
| Termos de domínio | Glossário do PO |
| Microcopy de interface (CTAs, estados, erros, avisos) | **Designer** — os specs de tela |

Toda a microcopy dos specs da `STORY-046` está marcada como **provisória**: ela existe para que a tela seja implementável e testável, e será substituída pela `STORY-052`.

## Vocabulário fixado por DDR

Vinculante entre telas — mudança exige revisar o DDR de origem, não só o spec.

### Indisponibilidade — DDR-005

| Motivo | Frase |
|---|---|
| `indisponivel:sem_faturamento` | "Sem faturamento neste mês — os percentuais sobre a receita não se aplicam." (verbatim do Anexo K §K.7) |
| `indisponivel:grupo_agregado` | "Este grupo foi lançado pelo total, então não há detalhe por item neste mês." |
| `indisponivel:sem_lancamento` | "Sem lançamento para este mês." |
| comparativo sem base | "Disponível a partir do 2º mês lançado." / "Disponível a partir do 4º mês lançado." |

**Regra:** a explicação nunca culpa o usuário nem o sistema. É estado, não falha.

### Escolha de modo de entrada — DDR-002

- Rótulos **factuais**, nunca adjetivos de valor. `Informar o total` / `Detalhar por item` — **nunca** `Rápido`, `Completo`, `Simplificado`, `Avançado`.
  Enquadrar uma das opções como atalho é o erro que o Anexo K §K.5 proíbe: a escolha pelo total é legítima e produz demonstração correta.
- A frase explicativa dá **a garantia antes do ganho**: o que o total assegura, depois o que o detalhe acrescenta. Nessa ordem. Nunca advertência.

### Valores herdados — DDR-003

- A marca é factual e minúscula: `veio de julho`. Não `Repetido`, não `Igual ao mês anterior`, não badge.
- O botão de confirmação **diz o que faz**: `Conferi este bloco`, `Conferir tudo e ir ao fechamento` — nunca `OK`, nunca `Confirmar` sozinho.

### Resultado negativo — DDR-004

- `Prejuízo no mês` — a palavra, não a cor.
- O rótulo do destaque troca de verbo: `SOBROU` → `FALTOU`. É o sinal que se lê antes do número.
- **Nunca** dramatizar: sem "Atenção", sem exclamação, sem ícone de alerta. Prejuízo é informação, e o usuário já o aceitou como alerta no lançamento.

### Bloqueios e alertas — DDR-001

- **Bloqueio:** diz o que impede e o que corrigir. Nunca oferece caminho para frente. Título: `Corrija antes de …`.
- **Alerta:** diz o que chamou atenção, por que pode ser legítimo, e oferece **um** aceite explícito. Título: `Confira antes de …`. Aceite: `Entendi, pode fechar assim`.
- A diferença de tom acompanha a diferença de mecânica: bloqueio é imperativo, alerta é convite a conferir.

## Verificação antes de marcar um spec como `ready`

Além do checklist de `tone-and-voice.md`:

- [ ] Toda copy em tabela única, marcada como provisória quando for.
- [ ] Nenhum termo de domínio rebatizado.
- [ ] Nenhum adjetivo de valor numa escolha que o produto oferece como legítima.
- [ ] Toda indisponibilidade tem frase, e a frase não culpa ninguém.
- [ ] Todo estado comunicado por texto, não só por cor ou glifo.
