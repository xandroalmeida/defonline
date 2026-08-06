---
pdr_id: PDR-006
slug: escalacoes-sprint-w32
title: Três escalações da SPRINT-2026-W32 — gate de acessibilidade, texto do NCG absoluto e dado histórico do BUG-001
status: accepted
decided_at: 2026-08-04
decided_by: PO (Alexandro / Claude)
related_epics: [EPIC-002, EPIC-004]
related_stories: [STORY-040, STORY-042, STORY-043, STORY-059, STORY-060]
related_pdrs: [PDR-005]
---

# PDR-006 — Escalações da SPRINT-2026-W32

## Contexto

A execução da `SPRINT-2026-W32` produziu três questões que os agentes corretamente **não** decidiram sozinhos. Duas travavam a promoção do EPIC-002 a `done`; a terceira apareceu como achado lateral e é a mais séria das três.

Registro as três juntas porque foram levantadas no mesmo ciclo e porque duas delas compartilham a mesma raiz: uma estória não deve ser mantida aberta por dívida que não é dela.

---

## Escalação 1 — Gate de acessibilidade da STORY-040

**A questão.** Os critérios de aceite da `STORY-040` passam 100%, mas o gate de acessibilidade da página inteira reprova por três achados: dois de app shell (`button-name` no toggle de conta, `aria-hidden-focus` no drawer fechado) e um de contraste nos pills de farol e no badge de versão. Nenhum foi introduzido pela estória — os dois primeiros são do EPIC-004, o terceiro é dos tokens do design system.

O agente ofereceu três caminhos e escolheu nenhum, corretamente: a definição de pronto é do PO.

**Decisão.** A `STORY-040` fecha como `done`. Seus critérios estão verdes e a dívida é de outro épico.

A alternativa — mantê-la aberta até que alguém conserte o app shell — é o mecanismo que produziu a `STORY-023` parada por dois meses e meio, exatamente o problema que o `PDR-005` §5 identificou. Estória que carrega dívida alheia não fecha nunca, e a dívida também não anda, porque fica escondida dentro de uma estória cujo título não fala dela.

**Mas a dívida não some.** Abro a `STORY-059`, focada, com os três achados e um dono claro. O contraste dos pills de farol é o mais grave dos três: o farol é o elemento visual mais importante do relatório, e a spec exige explicitamente que ele não dependa só de cor. Contraste insuficiente ataca justamente o usuário que já tem dificuldade de distinguir as cores.

**Não reinterpreto o gate como "escopo da estória"** (a terceira opção oferecida). O gate é da página porque a experiência do usuário é da página. Baixar a régua para caber no que foi entregue é o começo da erosão que o princípio 3 do método existe para impedir.

---

## Escalação 2 — CA-7 da STORY-042 (texto do NCG absoluto)

**A questão.** O CA-7 pedia que um diagnóstico novo exibisse a recomendação revisada da EBC para NCG positivo. O texto foi para a matriz, mas **nenhum diagnóstico renderiza aquela entrada**: o indicador `NcgAbsoluto` não consome a matriz.

**Isso não é um bug — é uma decisão anterior, deliberada e documentada.** A `STORY-032` migrou 13 indicadores para a matriz e deixou o NCG absoluto de fora com justificativa registrada no briefing: o Anexo F oferece 2 cenários (positiva/negativa), enquanto o motor entrega 3 faixas semânticas (folga / moderado / alto). Manteve-se a granularidade maior porque ela é mais útil ao usuário.

**Decisão.** O **CA-7 é marcado `n/a`** e a `STORY-042` fecha como `done` com 9 CAs verdes e 1 não aplicável.

O CA-7 foi escrito sobre uma premissa errada — a de que o NCG absoluto lê a matriz. Fazê-lo passar exigiria desfazer a `STORY-032` e trocar três mensagens por duas, **piorando o produto para satisfazer um critério de aceite mal formulado**. O erro é meu, na redação do CA; a resposta certa é corrigir o critério, não o produto.

**Fica um resíduo real, e ele vira estória.** A entrada `ncg_absoluto` na matriz é hoje configuração inerte: ninguém a lê, e o próximo agente que topar com ela vai perder tempo entendendo por quê. Pior: a intenção editorial da EBC — o texto revisado — de fato não chegou ao usuário, e essa parte é legítima. A `STORY-060` resolve os dois lados: leva a intenção editorial para as três mensagens de faixa que o produto realmente exibe, e trata a entrada inerte da matriz.

Como as mensagens de faixa fazem parte da saída do motor, isso implica bump de `motor_version` e diagnósticos antigos preservam o texto com que foram gerados — `IDR-010`, sem exceção.

---

## Escalação 3 — Dado histórico do BUG-001

**A questão.** Durante a `STORY-043` descobriu-se um bug de produção pré-existente: a máscara monetária perdia o último dígito ao sincronizar com o componente. A tela mostrava `R$ 150.000,00` e o valor que chegava ao motor era `R$ 15.000,00`. Foi corrigido no mesmo release, com teste de regressão em browser real.

O que ficou aberto: **diagnósticos gerados pela interface antes deste release podem ter valores até 10× menores que os digitados.**

**Avaliação do alcance.** Menor do que parece, por três razões, mas não nulo:

1. **Nunca houve beta.** O `PDR-005` §4 registra que a hipótese da onda não foi testada — o beta com Robertos reais ficou bloqueado pelo gate do NRF §9.3 durante toda a onda. Não há usuário real com diagnóstico afetado.
2. **Indicadores de razão não foram afetados.** Se todos os campos monetários encolhem na mesma proporção, margens e demais razões entre dois valores em reais permanecem corretas. É a própria observação do agente ao analisar a R4.
3. **O que foi afetado são os valores absolutos** — NCG absoluto, e agora os valores nominais que a `STORY-044` acabou de passar a exibir — e quaisquer indicadores que cruzem reais com dias.

**Decisão.** **Nenhuma remediação de dados.** Não migramos, não recalculamos e não expurgamos os diagnósticos existentes em homologação. São dados de teste e demonstração, sem usuário real por trás, e o custo de qualquer remediação supera em muito o valor de corrigir números que ninguém vai consultar.

**Duas ações que não são remediação, e que faço questão de registrar:**

- **Avisar a EBC.** O Edvan avaliou o MVP em 27/05 e produziu a devolutiva que originou as estórias 038 a 043. Ele fez isso com a interface afetada pelo bug. As leituras de margem que ele fez continuam válidas — são razões. As leituras de **valor absoluto**, não. Se algum ponto da devolutiva dele se apoiou em um número absoluto que pareceu estranho, agora há uma explicação melhor do que "o motor está errado". É informação que ele merece ter.
- **Nada de novo é gerado errado.** A correção vale daqui para a frente e tem regressão em browser cobrindo as três máscaras (moeda, dias e percentual), do rascunho até o payload que chega ao motor.

**Reflexão de processo, porque este é o achado mais incômodo do ciclo.** O bug sobreviveu a um épico inteiro, a uma validação com 51 itens e a uma devolutiva de especialista externo. Passou porque os testes de feature e de domínio não atravessam a máscara — eles injetam o valor já canônico — e porque o único teste que atravessaria era de browser, onde ninguém tinha conferido o número exato. **Foi um teste de browser que o encontrou, e por acidente.**

A lição não é "faltou teste"; a cobertura estava em 97%. É que **cobertura alta em camadas que não se tocam não prova que o sistema funciona ponta a ponta.** O caminho do dedo do usuário até o motor não tinha nenhuma asserção numérica exata. Agora tem.

---

## Consequências

### Positivas

- O EPIC-002 é promovido a `done`: 039 a 044 fechadas, validação interna e externa aprovadas.
- Três dívidas saem de dentro de estórias alheias e viram itens próprios, visíveis e com dono.
- Um bug sério de produção foi encontrado e corrigido antes de qualquer usuário real ter sido exposto — o único aspecto sortudo do beta ter atrasado.

### Negativas / trade-offs aceitos

- Diagnósticos existentes em homologação seguem com valores absolutos possivelmente errados, sem sinalização. Aceito porque não há usuário real e porque `migrate:fresh` resolve quando incomodar.
- A intenção editorial da EBC para NCG positivo continua sem chegar ao usuário até a `STORY-060`.
- O gate de acessibilidade da página do relatório segue reprovando até a `STORY-059`.

## Sinais de revisão

- **Se um usuário real acessar homologação antes da `STORY-059`**, o contraste dos pills de farol deixa de ser dívida e vira defeito ativo — priorizar imediatamente.
- **Se o beta começar antes de a base ser limpa**, os diagnósticos afetados pelo BUG-001 precisam ser expurgados antes, não depois.
- **Se outro bug atravessar máscara e motor sem ser pego**, a lição de processo não pegou: cabe exigir asserção numérica exata ponta a ponta como padrão de qualidade, não como exceção.
