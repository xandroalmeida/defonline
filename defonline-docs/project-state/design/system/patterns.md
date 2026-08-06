# Padrões compostos

> Padrão = combinação de componentes que resolve um problema recorrente. Entra por **DDR**, como componente.
> Cada padrão abaixo tem um DDR que o decidiu e ao menos um spec de tela que o consome.

---

## 1. `formulario-longo`

**DDR-001** · Consome: `progresso-blocos`, `aviso-inline`, `card`, `campo-monetario`, `button`
**Em produção:** Quiz do Diagnóstico (4 blocos, 23 campos) · **Especificado:** Lançamento mensal do DRM (6 blocos, até 38 campos)

Formulário que não cabe numa tela. **É o único dialeto de formulário longo do produto** — um segundo dialeto faria o usuário reaprender a preencher.

1. **Um bloco por tela.** Bloco é o que o usuário responde de uma sentada; até ~2 dobras em 360×800.
2. **Progresso** = barra segmentada **mais** linha textual `Bloco N de M · Nome`. A barra responde "quanto falta"; o nome responde "onde estou", que é o que permite retomar.
3. **Rascunho salvo ao concluir cada bloco**, nunca por *debounce* silencioso. O botão de avanço declara o salvamento (`Próximo` → `Salvando…`).
4. **Retomada nomeia o bloco, não o número:** *"você parou em Despesas administrativas"*.
5. **Rodapé permanente:** *"Você pode sair e voltar depois — o que já preencheu fica salvo."* É a frase que torna o abandono seguro — e abandono seguro é o que permite retomada.
6. **Três severidades, e só três:** `info`, `alerta`, `bloqueio` (`aviso-inline`). **A ausência de botão de seguir em frente é o que faz de um bloqueio um bloqueio.**
7. **Bloco final de conferência.** Mostra tudo o que será gravado, agrupado pelos blocos anteriores, com link para voltar a cada um. É onde vivem os alertas que só são computáveis no fim.
8. **Acessibilidade:** cada bloco é `<fieldset>` + `<legend>`; ao trocar de bloco o foco vai para o **cabeçalho** (`tabindex="-1"`), não para o primeiro campo — o leitor de tela anuncia onde chegou antes do que preencher. Resumo de erros no topo, com link por campo.

**Quando não usar.** Formulário de até ~8 campos. Fatiar o que já cabe numa tela é atrito gratuito.

---

## 2. `pre-preenchimento-periodo-anterior`

**DDR-003** · Consome: `campo-monetario` estado `herdado`, `aviso-inline` `info`, `button`
**Especificado:** Lançamento mensal do DRM

Formulário periódico em que a maior parte dos valores repete do período anterior.

**A inversão que sustenta o padrão:** o usuário não precisa saber quais campos vieram do período anterior — precisa saber **quais ainda não olhou**. São conjuntos diferentes, e é o segundo que fecha a tarefa.

1. **Marca discreta por campo:** legenda de uma linha, `--text-label` / `--color-secondary`. Sem cor, sem ícone, sem badge, sem mudança de fundo.
2. **A marca some ao editar ou ao confirmar.** Não volta. **A marcação decresce conforme o trabalho avança** — o progresso fica visível sem indicador adicional.
3. **Confirmação por bloco:** `aviso-inline` `info` no topo do bloco, com a contagem e um botão que limpa as marcas daquele bloco.
4. **Confirmação total na entrada**, oferecida quando o período anterior está completo: confirma tudo e leva **ao bloco de conferência** — **nunca ao fechamento**. Confirmar em massa não pode ser o último ato.
5. **Herança parcial é estado de primeira classe.** Quando não há de onde herdar (granularidade diferente entre os períodos), os campos vêm vazios, sem marca, com explicação do porquê. **Nunca inferir valor de item a partir de um total.**
6. **`aria-describedby`** liga a legenda ao campo enquanto ela existir.
7. **A marcação não é persistida** no registro final. É ajuda de preenchimento, não fato do dado.

**Quando não usar.** Sem período anterior (primeira vez): campos vazios, sem marca, e uma orientação de primeira vez que **promete** o pré-preenchimento seguinte — é no momento de maior esforço que a promessa importa.

---

## 3. `demonstracao-financeira`

**DDR-004** · Consome: `demonstracao-linha`, `card`, `pill`, `button` variante `link`
**Especificado:** Demonstração do Mês (DRM)

Apresentação de uma conta encadeada com múltiplos níveis, em que cada linha carrega valor nominal e percentual.

1. **Toda linha carrega o par.** Nunca só um dos dois. Ausência é indisponibilidade explícita (padrão 4), nunca célula vazia.
2. **Os números que respondem à pergunta vêm antes da conta**, como destaques. Quem só quer a resposta para na primeira dobra.
3. **Seleção dos destaques é declarativa, com fallback**: destaque não encontrado simplesmente não é exibido; a conta segue completa. Nunca erro, nunca placeholder.
4. **Mobile agrupa em cards por trecho da conta — não por linha.** Cada card fecha num totalizador. Um card por linha cortaria a subtração que o usuário precisa ver; é a diferença entre uma conta e uma lista de indicadores independentes.
5. **Desktop colapsa os cards numa tabela única.** Régua e recuo bastam para separar os trechos; a borda de card viraria ruído. Mesma informação com um separador a menos.
6. **Três níveis, quatro canais:** recuo **e** tamanho **e** cor **e** marcador. Recuo sozinho não é anunciado por leitor de tela.
7. **Valor negativo é neutro:** sinal `−`, `--color-primary`, `pill` textual. **Sem cor** — vermelho no produto já significa erro ou farol.
8. **`tabular-nums` e alinhamento à direita** em todos os números. É o que permite comparar ordens de grandeza sem ler dígito a dígito.
9. **Render genérico.** A tela desenha a lista de linhas como ela vem do dado, sem conhecer códigos de linha. Uma linha desconhecida não pode derrubar a tela.
10. **Camada de interpretação (farol, leitura) é ausente até existir — nunca vazia.** Coluna com cabeçalho e sem conteúdo é exatamente o que "parecer incompleto" significa.

---

## 4. `indisponibilidade`

**DDR-005** · Consome: `aviso-inline` `info`, `demonstracao-linha` estado `indisponivel`, `serie-linha-competencia` estado `nao-lancada`
**Especificado:** as três telas do DRM · **Compatível com:** relatório de Diagnóstico em produção

Como o produto distingue **"não dá para calcular"**, **"não existe"** e **"é zero"**.

1. **Marca única de célula:** `—` visual **mais** o motivo em texto acessível. Nunca célula em branco, nunca `0`, nunca `N/A`. Nunca só cor, nunca só glifo.
2. **O motivo em português é dito uma vez, no escopo em que vale:**
   - vale para a **coluna** → `aviso-inline` `info` acima da tabela;
   - vale para um **grupo** → nota no cabeçalho do grupo;
   - vale para um **registro** → o estado visual do próprio registro na lista.

   Repetir o motivo em 17 linhas é o mesmo erro de escala que o padrão 2 evita com a marca de herança.
3. **Ausência é estrutural, não numérica.** Um registro que não existe não é "uma linha com traço": é borda tracejada, ausência de área de valor, rótulo textual de estado e um CTA para criá-lo. Um registro com valor zero é uma linha normal exibindo `0`. **As duas nunca se parecem.**
4. **Indisponível não é erro.** Sem `--color-destructive`, sem ícone de alerta, sem opacidade que sugira falha.
5. **Indisponibilidade parcial é normal.** Uma linha pode ter nominal presente e percentual indisponível. Só a célula afetada muda — nunca a linha inteira.
6. **Vocabulário fixo por motivo**, em prosa, sem jargão de sistema.
7. **A explicação nunca culpa** o usuário nem o sistema. É estado, não falha — especialmente quando é consequência de uma escolha legítima que o próprio produto ofereceu.

**Por que este padrão existe.** O modelo de dados pode gastar índices, constraints e proibições de query para garantir que ausência nunca vire zero. Essa garantia **morre na última camada** se a tela apresentar as duas coisas com a mesma aparência. A tela é a única superfície onde o usuário encontra o dado.
