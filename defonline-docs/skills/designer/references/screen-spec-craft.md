# Ofício do screen spec

Como escrever um spec de tela que **evita retrabalho** e funciona com o Programador trabalhando em paralelo. Use junto com `templates/screen-spec.md`.

## O que torna um spec bom

Um spec bom tem três propriedades:

1. **Auto-suficiente.** O Programador implementa a tela inteira sem precisar perguntar nada de UX em nenhum estado.
2. **Honesto sobre estados.** Caminho feliz não é a tela — é só um dos estados. Spec sem vazio, loading e erro é meio spec.
3. **Vivo até o merge.** Spec acompanha a estória; mudança é registrada com data e motivo.

Spec ruim típico:

- Só caminho feliz desenhado, estados de erro como afterthought.
- Layout só desktop ("mobile a gente adapta").
- Microcopy genérico ("Erro!", "Sucesso!", "Carregando...").
- Identificadores de teste ausentes — E2E acopla a estrutura frágil do DOM.
- Sem referência ao DS — componentes "fantasma".
- Sem critério para o tablet — Programador chuta.

## Sequência de produção

A ordem importa. Pular passo gera o tipo errado de spec.

### 1. Leia a estória inteira

CAs, contexto, "fora de escopo", documentos referenciados. Você **não duplica** CAs no spec — você os atende.

### 2. Identifique os estados

Antes de qualquer sketch, **liste os estados** desta tela:

- Caminho feliz / preenchido
- Loading (primeiro fetch, refresh)
- Vazio (primeira-vez sem dados, ou filtro sem resultado)
- Erro (por tipo previsível: rede, permissão, dado inválido, inesperado)
- Sem permissão
- Parcial / degradado (quando parte dos dados falha)
- Primeira-vez vs recorrente (se aplicável)

Estados esquecidos viram bugs em produção. Liste antes de desenhar.

### 3. Rabisco mobile primeiro

Sketch ASCII/SVG grosseiro do **caminho feliz mobile**. Não polido. Resolve fluxo e hierarquia, não pixel.

### 4. Rabisco desktop

Sketch ASCII/SVG do mesmo estado em desktop. O espaço extra tem propósito — declare qual.

### 5. Sync com Programador (≤15 min)

Veja `collaboration-with-developer.md`. Ajuste o rabisco com base nas limitações técnicas conhecidas.

### 6. Detalhe os estados restantes

Em ordem: vazio → erro → loading → sem permissão → parcial. Cada um com sketch + microcopy.

### 7. Microcopy completo na tabela

Toda copy visível listada **em um só lugar** (Seção 5 do template). Revisar tom usando `tone-and-voice.md`.

### 8. Identificadores estáveis para E2E

Sugira `data-testid` para os elementos que o teste vai precisar ancorar. Convenção sugerida no template — Programador pode renomear se houver padrão do framework.

### 9. Notas de acessibilidade específicas

Além do piso (`accessibility-basics.md`), o que **desta tela** merece atenção? Foco inicial, ordem de tab não óbvia, live regions, ícones-ação.

### 10. Exceções ao DS, se houver

Toda divergência do DS é declarada com justificativa. Sem justificativa = desvio, não exceção.

### 11. Marque `status: ready` e avise no PR/estória

Spec sai de `draft` quando todos os estados estão cobertos e microcopy está completo.

## Como escrever cada seção

### Objetivo da tela

Uma frase. Se você precisa de duas, a tela está complexa demais (Princípio #1). Exemplo:

✅ "Permitir ao usuário iniciar um novo Diagnóstico para uma Empresa Analisada já cadastrada."

❌ "Tela de início de Diagnóstico, mostrando empresas cadastradas, com possibilidade de filtrar, ordenar, criar nova empresa, ver histórico de diagnósticos anteriores e iniciar um novo, além de exportar histórico."

(Esta última é 5 telas em uma.)

### Fluxo

Em três blocos:

- **Entrada:** de onde chega; o que precisa ser verdade antes (sessão, permissão, dado pré-carregado).
- **Ações possíveis:** primárias e secundárias, com destino de cada uma.
- **Saída:** o que acontece após sucesso/cancelamento/erro recuperável.

### Layout

Sketches simples. Não precisa ser bonito; precisa ser preciso quanto a:

- Hierarquia (o que vem primeiro visualmente, o que é secundário).
- Componentes do DS usados (referencie pelos ids: `button.primary`, `card`, etc).
- Espaçamento e alinhamento em prosa curta (não precisa medir em pixel — usa tokens).
- Como o espaço extra do desktop é usado (não é "mobile esticado").

Exemplo de sketch ASCII funcional:

```
Mobile (≥360px)
+----------------------------------+
| ← Voltar       Diagnóstico        |
+----------------------------------+
|                                  |
| Escolha a Empresa Analisada      |
|                                  |
| [ ▾ Buscar...                  ] |
|                                  |
| ┌────────────────────────────┐  |
| │ • Empresa Alpha LTDA       │  |
| │   CNPJ 00.000.000/0000-00  │  |
| └────────────────────────────┘  |
| ┌────────────────────────────┐  |
| │ • Empresa Beta ME          │  |
| │   CNPJ 11.111.111/1111-11  │  |
| └────────────────────────────┘  |
|                                  |
+----------------------------------+
| [ Iniciar Diagnóstico         ]  |
+----------------------------------+
```

```
Desktop (≥1024px)
+--------+-----------------------------------------------------+
|        | Diagnóstico — Nova análise                          |
| nav    +-----------------------------------------------------+
| lateral|                                                     |
|        |  Escolha a Empresa Analisada                        |
|        |                                                     |
|        |  [ ▾ Buscar...                                    ] |
|        |                                                     |
|        |  +------------------+ +------------------+         |
|        |  | Empresa Alpha    | | Empresa Beta ME  |         |
|        |  | LTDA             | | CNPJ 11.../11    |         |
|        |  | CNPJ 00.../00    | |                  |         |
|        |  +------------------+ +------------------+         |
|        |                                                     |
|        |                            [ Iniciar Diagnóstico ]  |
+--------+-----------------------------------------------------+
```

### Estados

Para cada estado:

- **Quando ocorre.** Condição precisa que dispara.
- **Sketch.** Como fica visualmente.
- **Microcopy.** Texto exato.
- **Caminho de saída.** O que o usuário pode fazer dali.

### Microcopy

Toda copy visível em **uma tabela única**. Facilita:

- Revisão de tom (lê de cima a baixo, pega inconsistência).
- Tradução futura (i18n).
- Cruzar com glossário do PO (vocabulário do domínio).

### Identificadores de teste

Convenção sugerida: `screen-<slug>-<elemento>` onde possível. Programador pode mudar se o framework tem padrão próprio (ele decide); o que importa é que existam **estáveis**, para o E2E não acoplar à estrutura do DOM.

### Exceções ao DS

Tabela: o que diverge | por quê | vai virar DDR?

Se a coluna "vai virar DDR" tem dois ou mais "sim" no mesmo spec, pare — você está propondo design novo demais para uma estória. Discuta com o PO.

## Sinais de spec pronto

- Você consegue dizer, sem olhar a estória, o objetivo da tela em uma frase.
- Cada estado tem sketch + microcopy + caminho de saída.
- Layout mobile e desktop ambos presentes; tablet só se mudar comportamento.
- Microcopy completo na tabela; tom revisado.
- Identificadores de teste sugeridos.
- Acessibilidade revisada (contraste, foco, teclado, alvos de toque).
- Exceções ao DS declaradas com justificativa.

## Sinais de spec mal feito

- "Tá quase, faltam só os erros."
- "Mobile a gente vê depois."
- Microcopy "lorem ipsum" ou genérico ("Erro!").
- Sem referência a componente do DS — tudo "inventado para esta tela".
- Spec atualizado pela última vez antes do código começar — não acompanhou a evolução.
