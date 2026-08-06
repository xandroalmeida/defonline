# `config/drm/provisorio/` — o que a EBC ainda não validou

Tudo o que está neste diretório é **texto provisório**, redigido pela equipe de produto para que a implementação não ficasse parada esperando a EBC. Nada mais vive aqui.

O diretório é separado de propósito, e o `PDR-009` §2 explica por quê: a alternativa era um campo `origem` em cada entrada, igualmente verificável por máquina, e pior para gente. Com um campo por entrada, responder *"o que ainda não passou pela EBC?"* exige ler o arquivo inteiro e filtrar mentalmente — e o provisório se dissolve no meio do validado até ninguém mais enxergar. Com um diretório, a resposta é um `ls`.

**O provisório precisa ser desconfortável de ver, senão vira permanente.** É a lição da entrada inerte do `ncg_absoluto`, que ficou dois meses parecendo configuração ativa até virar a `STORY-060`.

## Como promover uma entrada

Quando a EBC validar um texto:

1. **Mova a linha** do arquivo daqui para o arquivo homônimo em `config/drm/`.
2. Suba o `version` dos **dois** arquivos tocados.
3. Suba `conteudo_version` em `config/drm.php`.

Só isso. Nenhuma alteração de código de aplicação, nenhum deploy de classe, nenhuma migração. O resolvedor (`App\Domain\Drm\ConteudoDrm`) procura primeiro no validado e só cai aqui se não achar.

**Não copie — mova.** A mesma chave nos dois arquivos faz o produto exibir o texto antigo achando que exibe o novo. O teste arquitetural `ConteudoDrmArchTest` reprova a duplicata, mas o hábito certo é mover.

## Promover conteúdo nunca recalcula o passado

Lançamento fechado carrega no snapshot o texto com que nasceu (`IDR-010`, `ADR-007`). Texto novo vale para leitura nova. Não existe "atualizar os meses anteriores".

## O que **não** está aqui, e por quê

| Item | Onde está | Motivo |
|---|---|---|
| Rótulos de linha, de campo e de grupo | `../rotulos.php` | Nascem validados: vêm do Anexo K §K.4 e do `PDR-004`, não da EBC |
| Nome de interface ("Raio X do Negócio") | `../rotulos.php` | Decisão de PO (`PDR-009` §3), não depende da EBC |
| Faixas de farol | `../faixas-*.php` | Não são texto: carregam `estado` próprio. `nao_calibrada` **não produz farol**, e é o comportamento correto |
| Textos de leitura | `../leitura.php`, vazio | Sem farol calibrado não há o que ler. **Nenhum texto provisório de leitura vai ao ar** (`PDR-009` §1) |

## Inventário

As chaves efetivamente servidas a partir daqui em runtime são registradas em `defonline-docs/project-state/epics/EPIC-010-drm-demonstracao-resultado-mensal/design/conteudo-lacunas.md`, no mesmo padrão que `MatrizRecomendacoes` já usa no Diagnóstico. O PO abre um arquivo e vê o que falta, sem ler código.
