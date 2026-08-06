---
pdr_id: PDR-009
slug: defaults-do-conteudo-drm-e-nome-comercial
title: Defaults do pacote de conteúdo do DRM, isolamento do provisório em código e nome comercial do módulo
status: accepted
decided_at: 2026-08-06
decided_by: PO (Alexandro / Claude)
related_epics: [EPIC-010]
related_stories: [STORY-052, STORY-047, STORY-048, STORY-049, STORY-054]
related_adrs: [ADR-007]
related_pdrs: [PDR-003, PDR-004]
related_idrs: [IDR-010]
resolves: ["[DECIDIR-A]"]
---

# PDR-009 — Defaults do conteúdo do DRM e nome comercial

## Contexto

A `STORY-052` entrega o pacote editorial do DRM: rótulos por setor, tooltips, faixas de farol, textos de leitura e mensagens de alerta. Parte desse conteúdo depende de validação externa da EBC, que não tem data. O CA-9 exige que todo item sem resposta fique registrado **com o default aplicado**, para que a implementação nunca pare esperando texto.

Este PDR fixa quais são esses defaults, como o provisório fica isolado em código, e fecha o `[DECIDIR-A]` do `PDR-003`.

O princípio que atravessa as cinco decisões de conteúdo é um só: **texto não validado nunca pode se passar por texto validado.** O produto pode ir ao ar incompleto; não pode ir ao ar mentindo sobre a origem do que afirma.

---

## Decisão 1 — Os cinco defaults

| CA | Item | Default enquanto a EBC não responde |
|---|---|---|
| CA-3 | Rótulos por setor | **Já resolvido.** CPV (Indústria) / CMV (Comércio) / Custo dos Serviços Prestados (Serviços) vêm do Anexo K §K.4, que é fonte canônica. Nasce validado. |
| CA-4 | Tooltips dos campos | Redigidos pela equipe de produto, marcados como provisórios. **Campo sem tooltip não renderiza o `<x-help>`** — nem ícone, nem balão vazio. |
| CA-5 | Faixas de farol | **Todas `nao_calibrada`.** Faixa não calibrada **não produz farol**, e isso é comportamento correto, não lacuna. |
| CA-6 | Textos de leitura | **Ausentes por consequência.** Leitura é interpretação de farol; sem farol calibrado, não há o que ler. Nenhum texto provisório de leitura vai ao ar. |
| CA-7 | Mensagens de alerta e bloqueio | Redigidas pela equipe de produto, marcadas como provisórias. **Não podem faltar** — bloqueio sem texto é tela travada sem explicação. |

**Por que tooltip ausente em vez de tooltip genérico.** Um ícone de ajuda que abre "informe o valor do campo" é pior que nenhum ícone: gasta o toque do usuário, ensina que a ajuda do produto não ajuda, e some da atenção dele para sempre. Ausência é honesta e reversível.

**Por que os alertas são a exceção.** Os cinco itens acima são conteúdo explicativo, e explicação pode faltar. Bloqueio é **função**: o Anexo K §K.6 define regras que impedem o usuário de fechar o mês. Uma tela que recusa o avanço sem dizer por quê é defeito, não lacuna editorial. Então esses textos existem desde o dia um, com origem declarada, e a EBC refina depois.

**Por que a leitura cai junto com o farol.** Ela poderia ter texto provisório sem depender da EBC. Mas a leitura em linguagem simples é onde o produto diz *"sua margem está apertada"* — é a fala mais interpretativa que temos. Publicá-la sobre faixa não calibrada é o mesmo erro do farol vestido de prosa, e é exatamente o risco que o NRF §9.3 existe para evitar. Onde o farol cala, a leitura cala.

## Decisão 2 — O provisório fica isolado em diretório próprio

Este é o pedido explícito, e a resposta é literal: **um diretório contém tudo o que a EBC ainda não validou.**

```
config/drm.php                      versões (motor_drm_version, conteudo_version)
config/drm/
├── rotulos.php                     validado — Anexo K §K.4
├── faixas-industria.php            validado quando a EBC entregar
├── faixas-comercio.php
├── faixas-servicos.php
├── help.php                        validado quando a EBC entregar
├── leitura.php
├── alertas.php
└── provisorio/                     ← TUDO o que é default mora aqui
    ├── README.md                   o que é isto, e como promover uma entrada
    ├── help.php
    └── alertas.php
```

**Regras do mecanismo:**

1. **Resolução por sobreposição.** A chave é procurada primeiro no arquivo validado; só cai em `provisorio/` se não existir lá. Promover uma entrada é **mover a linha** de um arquivo para o outro — nunca editar código de aplicação.
2. **Chave nunca vive nos dois.** Teste arquitetural falha se a mesma chave existir no validado e no provisório. Sem esse teste, a duplicata silenciosa faz o produto exibir o texto antigo achando que exibe o novo.
3. **Estado explícito nas faixas.** Cada faixa carrega `estado` (`calibrada` | `nao_calibrada`) e, quando calibrada, `origem` e `validado_em`. `nao_calibrada` não produz farol — regra de motor, não de tela, para que nenhuma superfície futura consiga contorná-la por engano.
4. **Inventário legível pelo PO.** O resolvedor registra as chaves servidas a partir de `provisorio/` num arquivo de lacunas, no mesmo padrão que a `STORY-032` já usa em `MatrizRecomendacoes` (`motor.matriz_lacunas_path`). O PO abre um arquivo e vê o que falta, sem ler código.
5. **Versão de conteúdo própria.** `conteudo_version` em `config/drm.php`, separada de `motor_drm_version` (`ADR-007`) e do `version` de cada arquivo, no padrão de `config/quiz/versao.php`. Promover conteúdo sobe a versão de conteúdo — **nunca** recalcula lançamento já fechado (`IDR-010`, `ADR-007`).

**Por que diretório separado, e não um campo `origem` em cada entrada.** As duas soluções são verificáveis por máquina; a diferença é o humano. Com um campo por entrada, saber o que falta exige ler o arquivo inteiro e filtrar mentalmente — e o provisório se dissolve no meio do validado até ninguém mais enxergar. Com um diretório, a pergunta "o que ainda não passou pela EBC?" tem resposta em `ls`. **O provisório precisa ser desconfortável de ver**, senão vira permanente. É a mesma lição da entrada inerte do `ncg_absoluto`, que virou a `STORY-060` dois meses depois.

## Decisão 3 — `[DECIDIR-A]`: o módulo se chama "Raio X do Negócio"

Fecha o último item aberto do `PDR-003`. A pergunta original era de três vias — o nome batiza o módulo DRM, o relatório de Diagnóstico ou a plataforma? **Resposta: o módulo DRM.**

- **Módulo DRM → "Raio X do Negócio".**
- **Relatório de Diagnóstico → segue "Diagnóstico".** Não é renomeado.
- **Plataforma → segue "DEFOnline".** Não é renomeada.

**"Raio X do Negócio" é o nome de interface.** É o que o usuário lê no menu, no cabeçalho das telas, nos e-mails e em qualquer texto voltado a ele.

**"DRM" continua sendo o nome técnico**, sem exceção: tabelas `drm_*` (`ADR-007`), `config/drm*`, `motor_drm_version`, rotas, classes, nomes de estórias e toda a documentação de projeto. Não há renomeação de nada que já foi decidido.

**A separação é deliberada e vale como regra.** Nome comercial muda — por marketing, por pesquisa com usuário, por decisão de posicionamento. Nome técnico não deve mudar junto, senão cada revisão de marca vira migração de banco. Um nome vive em arquivo de conteúdo; o outro, no esquema.

Consequência para a `STORY-052`: o rótulo de interface entra no pacote de conteúdo como qualquer outro texto — **validado, não provisório**, porque é decisão de PO e não depende da EBC.

---

## Efeitos

- A `STORY-052` ganha o **CA-10** (isolamento e mecanismo) e o **CA-11** (nome de interface), e seus defaults ficam escritos — deixa de depender de qualquer resposta externa para ser executada.
- A `STORY-054` (faróis e leitura) nasce sabendo que pode entregar **zero farol e zero leitura** em homologação, e que isso é aprovação, não reprovação.
- O `[DECIDIR-A]` do `PDR-003` está resolvido. **Não resta nenhuma decisão de negócio aberta no EPIC-010.**

## Gatilho de revisão

Se a EBC entregar as faixas e o inventário de lacunas continuar apontando tooltips provisórios por mais de um ciclo de conteúdo, o problema deixou de ser espera e passou a ser prioridade — e a resposta é redigir, não esperar mais.
