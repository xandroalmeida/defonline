# Disciplina de Pull Request

PR é o ponto onde seu código encontra outro par de olhos. Não é detalhe burocrático — é onde qualidade ganha ou perde. PR bem feito é fácil de revisar, fácil de aceitar, e te protege quando o sistema quebrar depois.

Esta reference cobre como o programador sênior trata PR: do self-review até a resposta a feedback.

## A mentalidade

- **PR é colaboração, não exposição.** Você está pedindo ajuda — outro par de olhos pode ver o que você não vê.
- **Revisor é seu aliado**, mesmo quando aponta problema. Feedback técnico não é ataque pessoal.
- **PR pequeno > PR grande.** Sempre. Um PR de 500 linhas com 8 arquivos diferentes é rejeitado de cansaço, não de mérito.
- **Você revisa antes de pedir revisão.** Auto-review é parte do trabalho.

## Self-review — antes de pedir revisão

Antes de marcar o PR como pronto para revisão, **você mesmo revisa**:

1. **Abra o diff completo** do PR como se fosse de outra pessoa.
2. **Leia cada arquivo modificado** — não confie em "eu sei o que mudei".
3. Procure por:
   - Código comentado que ficou esquecido.
   - `console.log` / `print` / `debugger` esquecidos.
   - TODOs sem owner ou contexto.
   - Variáveis renomeadas incompletamente (busca/substitui parcial).
   - Imports não usados.
   - Arquivos commitados por engano (`.env`, gerados, build artifacts).
   - Estruturas inconsistentes com o resto do projeto.
   - Trecho que **você mesmo** não consegue explicar em uma frase.

Se algo no diff te faz franzir a testa — **conserte**. Não submeta esperando "ninguém vai notar".

**Sinal de bom self-review:** você consegue **anotar inline no PR** com explicação curta nos pontos que justificam ("escolhi essa abordagem porque..."). Isso poupa revisor de perguntar.

## Tamanho do PR

PR pequeno é PR aceito. Heurísticas:

- **Idealmente**: até ~300 linhas mudadas (excluindo arquivos gerados e renomeações simples).
- **Aceitável**: até ~500 linhas com revisão cuidadosa.
- **Excessivo**: > 800 linhas — quebre em vários PRs.

**Quando o trabalho é grande, planeje fatiar em PRs:**

- PR 1: setup/refatoração não-funcional.
- PR 2: implementação core.
- PR 3: integração e UI.

Cada PR fica revizável; cada um pode entrar separadamente.

**Quando NÃO dá pra quebrar** (raramente — mas acontece): explique no início da descrição por que precisa ser monolítico. Revisor pode aceitar com olhar mais cuidadoso.

## Descrição do PR — o que vai

Boa descrição economiza tempo do revisor. Estrutura sugerida:

```markdown
## Estória
STORY-XXX — <link e título>

## O quê
<1–2 frases descrevendo o que esta PR entrega. Em linguagem de negócio, não código.>

## Por quê
<Conexão com valor para o usuário ou contexto técnico. Se o "por quê" já está claro pela estória, pode ser breve.>

## Critérios de aceite cobertos
- [x] CA-1 — <referência ao teste que cobre>
- [x] CA-2 — <idem>
- [x] CA-3 — <idem>

## Decisões locais relevantes
- <Decisão técnica que vale o revisor saber, ex: "usei pattern X aqui porque...">
- <Liste IDRs criados nesta PR, se houver>

## Como verificar manualmente
1. <Passos para o revisor reproduzir o comportamento principal>
2. <...>

## Pontos de atenção para revisão
- <Áreas onde você gostaria de olhar especial — "favor olhar com calma o handler de pagamento">
- <Trade-offs que você fez e quer validar>

## Screenshots / GIFs (se aplicável)
<Para mudanças visuais, inclua antes/depois>

## Checklist
- [x] Testes unitários + cobertura de exceções/bordas
- [x] E2E em browser real (se FE)
- [x] Suíte completa verde
- [x] Lint/formatter limpos
- [x] Deploy de homologação verificado
- [x] Notas do agente atualizadas
- [x] index.json atualizado
```

**Aplique bom senso** — PR muito pequeno não precisa de descrição enorme. Mas seções "O quê", "Por quê" e "CAs cobertos" devem estar sempre.

## Como responder a feedback

Feedback de revisor cai em três categorias. Trate cada uma adequadamente:

### 1. Apontamento de problema concreto ("isso está errado")

- **Trate como trabalho não terminado.** Volta a estória para `in_progress`, conserta, repete `done-checklist` inteiro, sobe novamente para `in_review`.
- Não confunda "concerto" com "ajuste pra atender" — você está consertando o código, não apaziguando o revisor.
- Confirme entendimento se o apontamento for ambíguo (peça exemplo, contraproposta).

### 2. Sugestão / discussão ("você considerou X?")

- **Tome a sério.** Avalie o trade-off antes de descartar.
- Se concordar: implemente.
- Se discordar: **conversar antes de ignorar.** Responda explicando seu raciocínio. Talvez o revisor saiba algo que você não sabe; talvez você saiba algo que ele não sabe.
- **Não trate sugestão como opcional** sem alinhar — fica de mau gosto, e às vezes o "opcional" era na verdade obrigatório no contexto.

### 3. Aprovação ("LGTM")

- Aceite com gratidão. Você passou.

## Como discordar bem

Você é sênior. Vai discordar de feedback às vezes — isso é saudável. Como fazer:

- **Sem ego.** "Não, eu fiz certo" não conversa. "Considerei essa abordagem porque X, Y; me ajude a ver o que estou perdendo?" conversa.
- **Com dado.** "Já testei essa alternativa em outro projeto e deu Z" > "acho que não vai funcionar".
- **Convidando a chegar junto.** Geralmente a verdade está no meio — o revisor viu algo, você viu outra. A solução final é melhor que cada um sozinho.
- **Se não chegar a acordo:** escalar (PO, Arquiteto, contexto do par). Não force-merge "porque eu acho que está certo".

## Iteração após mudança

Quando você ajusta o PR após feedback:

- **Re-rode** o `done-checklist` — sua mudança não introduziu nada que regrediu? Suíte continua verde?
- **Re-anuncie** no PR ("ajustes feitos: X, Y. Pronto para nova revisão").
- **Não force-push** em PR em revisão **sem aviso**. Push adicional é melhor — preserva o histórico do que mudou. Se precisar squash/rebase no fim, faça **depois de aprovado**, antes do merge.

## Commits no PR

- **Commits pequenos e nomeados**, mesmo dentro de um PR. Cada commit deve fazer sentido isolado.
- **Mensagem explica por quê**, não só o quê (veja `coding-principles.md` seção "Mensagens de commit").
- **Squash no merge** quando histórico individual é ruído (configuração do projeto decide isso).

## Quando o PR é seu e você revê o de outro

Você também vai revisar PR de outros agentes/devs ocasionalmente. Hábitos:

- **Leia a descrição** primeiro. Se está ruim, peça melhorias antes de mergulhar no código.
- **Rode local** antes de aprovar — não só leia. Veja com seus olhos que funciona.
- **Comente positivo também** — não só problemas. "Gostei dessa estrutura aqui" ajuda a calibrar.
- **Seja específico** em apontamentos. "Não gostei" sem alternativa não ajuda; "considere X porque Y" ajuda.
- **Não dragoneje em PR pequeno** — escolha suas batalhas. Code style é menos importante que segurança e correção.

## Sinais de PR ruim (a evitar)

- Sem descrição ou só "STORY-XXX feito".
- Mistura múltiplas mudanças não relacionadas ("limpei umas coisas + feature X + fix do bug Y").
- Sem teste — "depois eu adiciono".
- Arquivos commitados por engano.
- Refator gigantesco escondido junto com feature ("aproveitei e reescrevi módulo Z").
- Branch desatualizada (conflito de merge difícil; rebase antes).

## Resumo operacional

Antes de marcar PR pronto para revisão:

- [ ] Self-review do diff inteiro feito.
- [ ] Descrição com "o quê / por quê / CAs / como verificar".
- [ ] Tamanho razoável (< 500 linhas idealmente).
- [ ] Sem código comentado, console.log esquecido, TODOs órfãos.
- [ ] Done-checklist passado integralmente.
- [ ] Áreas que pedem atenção especial sinalizadas.

Após feedback:

- [ ] Cada apontamento foi tratado (implementado, discutido, ou registrado em conversa).
- [ ] Done-checklist re-rodado.
- [ ] PR re-anunciado quando significativo.

**O ciclo do PR não termina quando você abre. Termina quando ele entra com qualidade.**
