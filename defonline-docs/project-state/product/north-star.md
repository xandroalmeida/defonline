# North Star e Árvore de Métricas — DEFOnline

**Versão:** 1.0 — 20/05/2026
**Mantido por:** Product Owner

## Métrica de norte

> **MPEs ativas com ao menos um diagnóstico concluído nos últimos 90 dias.**

Conta cada Empresa Analisada distinta que teve **ao menos um diagnóstico concluído** dentro da janela móvel de 90 dias terminando no momento da leitura da métrica. Empresas sem diagnóstico no período não contam, mesmo que existam no cadastro. Diagnósticos não concluídos (rascunho, abandonado, com erro) não contam.

**Por que essa janela e não 30 dias.** O gatilho de uso da persona alvo (Roberto) é a decisão financeira concreta — investir, captar, reajustar preço — que aparece a cada 2 a 4 meses. Uma janela de 30 dias subestimaria sistematicamente quem usa o produto exatamente como projetado. Uma janela de 12 meses esconderia churn ativo. Noventa dias captura a frequência natural sem mascarar deterioração.

**Por que essa métrica e não outras.** É outcome puro (comportamento real do usuário, não output do time), trivialmente instrumentável (consulta direta no banco, sem pesquisa), e cresce **apenas se o produto está entregando valor** ao usuário no horizonte em que ele opera. Não é vanity: usuário que cadastrou e nunca voltou some da contagem em 90 dias.

## Ambição inicial

| Marco | Alvo |
|---|---|
| Final do beta fechado (D + 60 dias) | 20 MPEs ativas |
| D + 6 meses pós go-live MVP | 200 MPEs ativas |
| D + 12 meses pós go-live MVP | **500 MPEs ativas** |

Esses números são **hipótese inicial** — não foram derivados de baseline (produto novo) nem de benchmark externo direto. Serão recalibrados em PDR após **3 meses pós go-live**, com dado real em mão. A postura é: a primeira onda valida a hipótese central da visão (existe demanda real e o produto entrega valor); escala vem depois.

Não tratamos o alvo como meta a ser perseguida a qualquer custo. Se atingirmos 500 com 200% de churn e NPS baixo, fracassamos — o número subiu, a hipótese central caiu. Por isso a árvore abaixo tem driver de qualidade obrigatório.

## Árvore de métricas (drivers)

A métrica de norte é alimentada por três drivers de comportamento e um driver transversal de qualidade. Toda feature/épico precisa explicar **qual desses drivers move** — se nenhum, repensar o épico.

```
                          NORTH STAR
              MPEs ativas com diagnóstico em 90d
                              |
        +---------------------+---------------------+
        |                     |                     |
    AQUISIÇÃO              ATIVAÇÃO              RECORRÊNCIA
  (entram no funil)    (extraem valor 1x)     (voltam por mais)
        |                     |                     |
        +---------------------+---------------------+
                              |
                     QUALIDADE PERCEBIDA
                  (driver transversal — NPS)
```

### D1 — Aquisição

> **Novos usuários cadastrados por mês** (contagem de cadastros completos, descontando duplicidades e fraudes).

Mede entrada de novos pretendentes no funil. Sem aquisição, o north star morre por estagnação. Driver subordinado: taxa de conversão `visita ao hotsite → cadastro completo`.

**Alvo orientativo no primeiro ano:** progressão de 30 cadastros/mês no D+3 para ~120/mês no D+12.

### D2 — Ativação

> **% de usuários cadastrados que concluem o 1º diagnóstico em até 7 dias após o cadastro.**

Cadastro sem diagnóstico é leadless — não entrega valor para o usuário nem para o produto. Esse driver mede a primeira experiência completa do produto. É a métrica mais diretamente afetada pela UX do quiz, qualidade da onboarding e clareza do relatório.

**Alvo orientativo no primeiro ano:** ≥ 60% (de cada 10 cadastros, pelo menos 6 saem com diagnóstico em 1 semana).

### D3 — Recorrência

> **% de usuários que retornam para o 2º+ diagnóstico em 180 dias após o 1º.**

Diagnóstico único pode ser curiosidade. Diagnóstico recorrente é hábito de uso. Esse driver valida que o produto entrega valor continuado — alinhado com o gatilho de Roberto (3 a 4 diagnósticos/ano).

**Alvo orientativo no primeiro ano:** ≥ 40% (de cada 10 usuários ativados, ao menos 4 voltam dentro de 6 meses).

### Driver transversal — Qualidade percebida

> **NPS médio do diagnóstico, coletado em pesquisa simples ao fim do relatório.**

NPS isoladamente é ruidoso em volume baixo, por isso **não é** o norte. Mas é a barreira contra "atingir o norte com produto ruim". Tratado como sinal de alerta: se cair persistentemente abaixo de patamar mínimo, força revisão antes de continuar empurrando aquisição.

**Patamar mínimo:** NPS médio ≥ 30 sustentado por 90 dias.
**Patamar de excelência:** NPS médio ≥ 50.
**Linha vermelha:** queda abaixo de 20 por 60 dias consecutivos dispara revisão de hipótese de produto.

## Janela de validação por tipo de feature

Cada épico declara a janela em que sua métrica primária será observada. Padrões herdados desta árvore:

- **Feature que afeta aquisição (D1):** observar em D+30 após deploy; fechar em D+60.
- **Feature que afeta ativação (D2):** observar em D+7; fechar em D+30.
- **Feature que afeta recorrência (D3):** observar em D+90; fechar em D+180.
- **Feature de qualidade (NPS):** observar continuamente; revisão formal trimestral.

Sem janela declarada, métrica é olhada cedo demais (vira ruído) ou esquecida.

## Anti-padrões a evitar

- **Vanity:** "total acumulado de cadastros desde o início" — sobe sempre, não informa nada. Não usar.
- **Output disfarçado de outcome:** "entregamos N features" — não é métrica de sucesso.
- **NPS sem contexto:** comemorar NPS alto com volume de 20 respondentes — viés de auto-seleção. Combinar sempre com volume.
- **Hindsight bias:** ajustar a régua depois do resultado. Decisão de calibração da ambição vira PDR com data anterior ao dado novo.
- **Foco em alvo sem hipótese:** correr atrás do número sem clareza de qual driver mover. Cada épico declara a hipótese explícita.

## Como o PO usa esta árvore

Toda vez que abre uma onda, decompõe um épico ou escreve PDR de escopo, o PO se pergunta: **qual driver desta árvore essa decisão move, e qual é a hipótese?** Se a resposta não está na ponta da língua, a decisão precisa amadurecer antes de virar plano.

Calibração formal dos números desta árvore vira PDR após **3 meses de produção real**. Até lá, são hipóteses de trabalho explícitas.
