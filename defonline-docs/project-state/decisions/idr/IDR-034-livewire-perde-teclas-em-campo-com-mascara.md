---
idr_id: IDR-034
slug: livewire-perde-teclas-em-campo-com-mascara
title: Campo ausente no array e payload por referência fazem o Livewire 4.4 apagar teclas digitadas
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-095)
related_story: STORY-095
related_adrs: ["ADR-007"]
related_idrs: []
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-034 — Livewire 4.4 apaga teclas digitadas: pré-popular o array e copiar o payload

## Contexto

A validação do EPIC-010 (D-1) mostrou que, em campo monetário ainda não preenchido, digitar `123456` com 350 ms entre teclas e 100 a 300 ms de latência gravava `134,56` em vez de `1.234,56`, sem aviso. O Dusk `type()` envia o valor de uma vez e nunca pegou isso.

Reproduzido tecla a tecla (CDP `Network.emulateNetworkConditions`) e rastreado dentro do `livewire.js` 4.4.8. Dois mecanismos, os dois com o mesmo efeito: uma resposta do servidor faz o efeito do `wire:model` regravar o `<input>` com o valor que a requisição levou, e as teclas digitadas durante o voo somem.

1. **Chave ausente no array (DRM).** `valores` começava `[]` e ganhava chaves ao digitar. Chave nova no cliente vira atualização do array inteiro (`{"valores": {...}}`), e a conciliação da resposta troca o objeto reativo. Com a chave já presente, a atualização é só a folha (`valores.D01`) e nada é regravado. Por isso "campo já preenchido" nunca falhava.
2. **Payload por referência (qualquer lista de objetos).** Quando todos os filhos de um objeto mudam na mesma requisição (nome e CPF do sócio), o Livewire consolida o diff no pai (`socios.0`) e põe no `updates` uma **referência** ao objeto reativo, não uma cópia. Quem digita depois do envio muda esse objeto; a resposta é conciliada contra o texto novo como se fosse o enviado, difere do que o servidor devolveu, e o Livewire reescreve o campo. No quiz, `52998224725` virava `599.822.472-5`.

## Decisão

> **Decidi pré-popular o array do DRM com todos os campos vazios e copiar o `updates` de toda mensagem do Livewire no momento do envio.**

- `App\Livewire\Drm\Lancamento::dehydrate()` completa `valores` com D01..D42 vazios em toda resposta, inclusive a primeira renderização. O rascunho continua gravando só o que tem texto.
- `resources/js/app.js` registra `Livewire.interceptMessage` que troca `message.updates` e `message.payload.updates` por uma cópia JSON.

## Por quê

Texto vazio e chave ausente significam o mesmo para o domínio (`Dinheiro::interpretar`), então pré-popular não muda nenhum valor calculado. A cópia do payload é o conserto da causa (o payload enviado precisa ser imutável) e vale para todo o produto, não só para um formulário.

## Alternativas consideradas

- **Escrever o estado do cliente a cada tecla (`$wire.$set(path, valor, false)`)**: testado e descartado. A conciliação da resposta reverte o estado ao valor do servidor nos dois mecanismos.
- **`wire:model.blur` no nome do sócio**: testado e descartado. Com latência, duas requisições seguidas carregam as mesmas mudanças não confirmadas e o diff é consolidado de novo.
- **Linha de sócio com um terceiro campo constante**: evita a consolidação, mas muda o formato de `socios` (rascunho, motor e payload do Diagnóstico).

## Consequências

### Para outros agentes
- Campo de formulário Livewire com `wire:model.live` sobre array associativo: declare as chaves na hidratação, não as deixe nascer na digitação.
- Todo teste de campo com máscara deve digitar tecla a tecla, com latência: `tests/Browser/DrmDigitacaoTeclaATeclaBrowserTest.php` e `DiagnosticoDigitacaoTeclaATeclaBrowserTest.php` são o modelo.

### Para o projeto
- `valores` do DRM tem sempre 42 chaves; testes que comparam o array inteiro devem filtrar o vazio (helper `preenchidos()` em `LancamentoTest`).
- Workaround de bug do Livewire 4.4.8: reabrir esta IDR a cada atualização do Livewire e remover o interceptor se o `updates` passar a ser copiado pelo framework.

### Trade-offs aceitos
- Uma cópia JSON do `updates` por mensagem (pequena, o mesmo dado que já é serializado).

## Como verificar

- Os dois Dusk acima falham sem as duas correções (medido: `134,56`, `17,02`, `599.822.472-5`).

## Tipo

- [x] **Workaround**: contornar bug/limitação documentado.
